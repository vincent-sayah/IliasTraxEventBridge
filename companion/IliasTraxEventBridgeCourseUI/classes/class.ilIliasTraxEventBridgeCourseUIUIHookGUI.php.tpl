<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIBridge.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIScreen.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php';

/**
 * UIHook léger : point d'entrée Suivi xAPI dans l'onglet Contenu du cours.
 */
class ilIliasTraxEventBridgeCourseUIUIHookGUI extends ilUIHookPluginGUI
{
    /** @var ilIliasTraxEventBridgeCourseUIBridge */
    private $bridge;

    public function __construct()
    {
        $this->bridge = new ilIliasTraxEventBridgeCourseUIBridge();
    }

    /**
     * @param string $a_comp
     * @param string $a_part
     * @param array<string,mixed> $a_par
     * @return array<string,mixed>
     */
    public function getHTML($a_comp, $a_part, $a_par = []): array
    {
        if (!isset($a_par['html']) || !is_string($a_par['html'])) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $html = $a_par['html'];
        if (strpos($html, 'il_center_col') === false || strpos($html, 'mainspacekeeper') === false) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        // Ne jamais réintercepter la page routée ilUIPluginRouterGUI :
        // sinon le HTML du screen est réinséré comme texte échappé.
        if ($this->isRoutedPluginRequest()) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $context = $this->getCurrentCourseContext();
        $courseRefId = (int) ($context['course_ref_id'] ?? 0);
        if ($courseRefId <= 0 || empty($context['main_plugin_available']) || empty($context['course_tracking_classes_available']) || empty($context['can_manage'])) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        // Fallback technique : si la page est appelée avec itxeb_cui_cmd, on
        // remplace le contenu central. Le chemin principal reste la page routée
        // ilUIPluginRouterGUI avec vrais onglets ILIAS.
        if ($this->isCourseUiCommandRequest()) {
            $screen = new ilIliasTraxEventBridgeCourseUIScreen($this->bridge);
            $newHtml = $this->replaceCenterColumnContent($html, $screen->handle());
            return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $newHtml];
        }

        // Important : ne pas afficher l'encart sur Info/Membres/Paramètres.
        if (!$this->isCourseContentRequest()) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $url = $this->buildRouterUrl($courseRefId, 'showDashboard');
        $newHtml = $this->injectCourseEntryButton($html, $url);
        return $newHtml !== $html
            ? ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $newHtml]
            : ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
    }

    /** @param string $a_comp @param string $a_part @param array<string,mixed> $a_par */
    public function modifyGUI($a_comp, $a_part, $a_par = []): void
    {
    }

    /** @return array<string,mixed> */
    public function getCurrentCourseContext(): array
    {
        return $this->bridge->getCourseContext();
    }

    private function isRoutedPluginRequest(): bool
    {
        $query = [];
        $uri = isset($_SERVER['REQUEST_URI']) && is_scalar($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $parts = parse_url($uri);
        if (is_array($parts) && isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        } elseif (!empty($_GET)) {
            $query = $_GET;
        }

        $baseClass = strtolower((string) ($query['baseClass'] ?? $query['baseclass'] ?? ''));
        $cmdClass = strtolower((string) ($query['cmdClass'] ?? $query['cmdclass'] ?? ''));

        return $baseClass === 'iluipluginroutergui'
            || $cmdClass === strtolower(ilIliasTraxEventBridgeCourseUIRouterGUI::class);
    }
    private function isCourseUiCommandRequest(): bool
    {
        foreach ([$_GET, $_POST] as $source) {
            if (isset($source['itxeb_cui_cmd']) && is_scalar($source['itxeb_cui_cmd']) && (string) $source['itxeb_cui_cmd'] !== '') {
                return true;
            }
        }
        return false;
    }

    private function isCourseContentRequest(): bool
    {
        $uri = isset($_SERVER['REQUEST_URI']) && is_scalar($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $query = [];
        $parts = parse_url($uri);
        if (is_array($parts) && isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        } elseif (!empty($_GET)) {
            $query = $_GET;
        }

        $baseClass = strtolower((string) ($query['baseClass'] ?? $query['baseclass'] ?? ''));
        $cmdClass = strtolower((string) ($query['cmdClass'] ?? $query['cmdclass'] ?? ''));
        $cmd = strtolower((string) ($query['cmd'] ?? ''));

        if ($baseClass !== '' && $baseClass !== 'ilrepositorygui') {
            return false;
        }
        if ($cmdClass !== '' && $cmdClass !== 'ilobjcoursegui') {
            return false;
        }
        if ($cmd !== '' && !in_array($cmd, ['show', 'view', 'render'], true)) {
            return false;
        }

        return isset($query['ref_id']) && (int) $query['ref_id'] > 0;
    }

    private function buildRouterUrl(int $courseRefId, string $cmd): string
    {
        try {
            if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'ctrl')) {
                $ctrl = $GLOBALS['DIC']->ctrl();
                $ctrl->setParameterByClass(ilIliasTraxEventBridgeCourseUIRouterGUI::class, 'itxeb_course_ref_id', (string) $courseRefId);
                $url = (string) $ctrl->getLinkTargetByClass([
                    ilUIPluginRouterGUI::class,
                    ilIliasTraxEventBridgeCourseUIRouterGUI::class,
                ], $cmd);
                $ctrl->setParameterByClass(ilIliasTraxEventBridgeCourseUIRouterGUI::class, 'itxeb_course_ref_id', '');

                // Une URL ilCtrl correcte contient cmdNode. Si cmdNode est absent,
                // la structure de contrôle n'a pas encore été reconstruite.
                if ($url !== '' && strpos($url, 'cmdNode=') !== false) {
                    return $url;
                }
            }
        } catch (Throwable $ignored) {
        }

        return $this->buildCourseFallbackUrl($courseRefId);
    }

    private function buildCourseFallbackUrl(int $courseRefId): string
    {
        $script = isset($_SERVER['SCRIPT_NAME']) && is_scalar($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/ilias.php';
        if ($script === '') {
            $script = '/ilias.php';
        }
        return $script . '?' . http_build_query([
            'baseClass' => 'ilrepositorygui',
            'cmdClass' => 'ilobjcoursegui',
            'ref_id' => (string) $courseRefId,
            'itxeb_cui_cmd' => 'showCourseDashboard',
            'itxeb_course_ref_id' => (string) $courseRefId,
        ], '', '&');
    }

    private function injectCourseEntryButton(string $html, string $url): string
    {
        if (strpos($html, 'id="itxeb_course_xapi_entry"') !== false || strpos($html, "id='itxeb_course_xapi_entry'") !== false) {
            return $html;
        }

        $entryHtml = '<div id="itxeb_course_xapi_entry" class="ilInfoScreenSec itxeb-course-xapi-entry" style="margin:0 0 15px 0;padding:12px;border:1px solid #d0d0d0;background:#f8f8f8;">'
            . '<h3>Suivi xAPI</h3>'
            . '<p>Consulter le tableau de bord, l’analyse pédagogique et la vue expert xAPI de ce cours.</p>'
            . '<a class="btn btn-default" href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Ouvrir le suivi xAPI</a>'
            . '</div>';

        if (!class_exists('DOMDocument')) {
            $newHtml = preg_replace('/(<[^>]+id=("|\')il_center_col\2[^>]*>)/isu', '$1' . $entryHtml, $html, 1, $count);
            return is_string($newHtml) && $count > 0 ? $newHtml : $html;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        if (!$loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $html;
        }

        $center = $dom->getElementById('il_center_col');
        if (!$center instanceof DOMElement) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $html;
        }

        $fragment = $dom->createDocumentFragment();
        if (@$fragment->appendXML($entryHtml) === false) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $html;
        }
        if ($center->firstChild instanceof DOMNode) {
            $center->insertBefore($fragment, $center->firstChild);
        } else {
            $center->appendChild($fragment);
        }

        $result = $dom->saveHTML();
        $result = preg_replace('/^<\?xml[^>]+>\s*/', '', (string) $result) ?? (string) $result;
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
        return $result;
    }

    private function replaceCenterColumnContent(string $html, string $content): string
    {
        if ($html === '' || !class_exists('DOMDocument')) {
            return $content;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        if (!$loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $content;
        }

        $center = $dom->getElementById('il_center_col');
        if (!$center instanceof DOMElement) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $content;
        }

        while ($center->firstChild instanceof DOMNode) {
            $center->removeChild($center->firstChild);
        }

        $fragment = $dom->createDocumentFragment();
        if (@$fragment->appendXML('<div>' . $content . '</div>') !== false) {
            while ($fragment->firstChild instanceof DOMNode) {
                $center->appendChild($fragment->firstChild);
            }
        } else {
            $center->appendChild($dom->createTextNode($content));
        }

        $result = $dom->saveHTML();
        $result = preg_replace('/^<\?xml[^>]+>\s*/', '', (string) $result) ?? (string) $result;
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
        return $result;
    }
}