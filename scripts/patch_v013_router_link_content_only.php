<?php
/**
 * V0.13 - correction routage et affichage encart Suivi xAPI.
 *
 * Corrige deux points :
 * 1) l'encart Suivi xAPI ne doit apparaître que sur l'onglet Contenu du cours,
 *    pas sur Info/Membres/etc. ;
 * 2) le lien vers ilUIPluginRouterGUI doit contenir explicitement cmdClass,
 *    sinon ILIAS arrive dans ilUIPluginRouterGUI sans classe cible et journalise :
 *    "Class '' cannot be found in the control structure".
 *
 * À lancer depuis la racine du plugin EventHook IliasTraxEventBridge :
 * php scripts/patch_v013_router_link_content_only.php
 */

function itxeb_fix_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: {$message}\n");
    exit(1);
}

function itxeb_fix_write(string $file, string $content): void
{
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        itxeb_fix_fail("création répertoire impossible: {$dir}");
    }
    if (file_put_contents($file, $content) === false) {
        itxeb_fix_fail("écriture impossible: {$file}");
    }
    echo "WRITE: {$file}\n";
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_fix_fail('lance ce script depuis la racine du plugin EventHook IliasTraxEventBridge.');
}
$eventHookSuffix = '/Services/EventHandling/EventHook/IliasTraxEventBridge';
if (substr($root, -strlen($eventHookSuffix)) !== $eventHookSuffix) {
    itxeb_fix_fail("chemin plugin principal inattendu: {$root}");
}
$customizingRoot = substr($root, 0, -strlen($eventHookSuffix));
$companionTemplate = $root . '/companion/IliasTraxEventBridgeCourseUI';
$companionInstalled = $customizingRoot . '/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';

$uiHook = <<<'PHP'
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

        // Important : ne pas afficher l'encart sur Info/Membres/Paramètres.
        // La page Contenu du cours ILIAS 10 observée utilise cmdClass=ilobjcoursegui.
        if (!$this->isCourseContentRequest()) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $context = $this->getCurrentCourseContext();
        $courseRefId = (int) ($context['course_ref_id'] ?? 0);
        if ($courseRefId <= 0 || empty($context['main_plugin_available']) || empty($context['course_tracking_classes_available']) || empty($context['can_manage'])) {
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
        $script = isset($_SERVER['SCRIPT_NAME']) && is_scalar($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/ilias.php';
        if ($script === '') {
            $script = '/ilias.php';
        }

        // URL volontairement explicite : cela évite d'obtenir ilUIPluginRouterGUI
        // sans cmdClass, ce qui produit "Class '' cannot be found".
        return $script . '?' . http_build_query([
            'baseClass' => 'ilUIPluginRouterGUI',
            'cmdClass' => strtolower(ilIliasTraxEventBridgeCourseUIRouterGUI::class),
            'cmd' => $cmd,
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
}
PHP;

$router = <<<'PHP'
<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIBridge.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIScreen.php';

/**
 * Page routée propre pour le suivi xAPI cours.
 *
 * @ilCtrl_isCalledBy ilIliasTraxEventBridgeCourseUIRouterGUI: ilUIPluginRouterGUI
 */
class ilIliasTraxEventBridgeCourseUIRouterGUI
{
    /** @var ilIliasTraxEventBridgeCourseUIBridge */
    private $bridge;

    public function __construct()
    {
        $this->bridge = new ilIliasTraxEventBridgeCourseUIBridge();
    }

    public function executeCommand(): void
    {
        $cmd = $this->getRouterCommand();
        $this->prepareScreenCommand($cmd);
        $this->setTabs($cmd);

        $screen = new ilIliasTraxEventBridgeCourseUIScreen($this->bridge);
        if (method_exists($screen, 'setRenderInnerTabs')) {
            $screen->setRenderInnerTabs(false);
        }

        $this->mainTemplate()->setContent($screen->handle());
    }

    private function getRouterCommand(): string
    {
        $cmd = '';
        try {
            if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'ctrl')) {
                $cmd = (string) $GLOBALS['DIC']->ctrl()->getCmd('showDashboard');
            }
        } catch (Throwable $ignored) {
            $cmd = '';
        }
        if ($cmd === '' && isset($_GET['cmd']) && is_scalar($_GET['cmd'])) {
            $cmd = (string) $_GET['cmd'];
        }
        return in_array($cmd, ['showDashboard', 'showAnalysis', 'showExpert', 'showConfig', 'saveConfig', 'exportExpertCsv', 'exportDashboardPdf', 'generateAiAnalysis'], true)
            ? $cmd
            : 'showDashboard';
    }

    private function prepareScreenCommand(string $cmd): void
    {
        $map = [
            'showDashboard' => 'showCourseDashboard',
            'showAnalysis' => 'showCourseAnalysis',
            'showExpert' => 'showCourseExpert',
            'showConfig' => 'showCourseTracking',
            'saveConfig' => 'saveCourseTracking',
            'exportExpertCsv' => 'exportCourseExpertCsv',
            'exportDashboardPdf' => 'exportCourseDashboardPdf',
            'generateAiAnalysis' => 'generateCourseAiAnalysis',
        ];
        $_GET['itxeb_cui_cmd'] = $map[$cmd] ?? 'showCourseDashboard';
    }

    private function setTabs(string $activeCmd): void
    {
        try {
            if (!isset($GLOBALS['DIC']) || !is_object($GLOBALS['DIC'])) {
                return;
            }
            $tabs = $GLOBALS['DIC']->tabs();

            $tabs->addTab('itxeb_xapi_dashboard', 'Tableau de bord', $this->link('showDashboard'));
            $tabs->addTab('itxeb_xapi_analysis', 'Analyse', $this->link('showAnalysis'));
            $tabs->addTab('itxeb_xapi_expert', 'Expert', $this->link('showExpert'));
            $tabs->addTab('itxeb_xapi_config', 'Configuration', $this->link('showConfig'));

            $map = [
                'showDashboard' => 'itxeb_xapi_dashboard',
                'showAnalysis' => 'itxeb_xapi_analysis',
                'generateAiAnalysis' => 'itxeb_xapi_analysis',
                'showExpert' => 'itxeb_xapi_expert',
                'exportExpertCsv' => 'itxeb_xapi_expert',
                'showConfig' => 'itxeb_xapi_config',
                'saveConfig' => 'itxeb_xapi_config',
            ];
            $tabs->activateTab($map[$activeCmd] ?? 'itxeb_xapi_dashboard');

            $courseRefId = $this->getCourseRefId();
            if ($courseRefId > 0 && method_exists($tabs, 'addSubTab')) {
                $tabs->addSubTab('itxeb_back_to_course', 'Retour au cours', $this->courseUrl($courseRefId));
            }
        } catch (Throwable $ignored) {
        }
    }

    private function link(string $cmd): string
    {
        return '/ilias.php?' . http_build_query([
            'baseClass' => 'ilUIPluginRouterGUI',
            'cmdClass' => strtolower(self::class),
            'cmd' => $cmd,
            'itxeb_course_ref_id' => (string) $this->getCourseRefId(),
        ], '', '&');
    }

    private function courseUrl(int $courseRefId): string
    {
        return '/ilias.php?' . http_build_query([
            'baseClass' => 'ilrepositorygui',
            'cmdNode' => 'wt:lx',
            'cmdClass' => 'ilobjcoursegui',
            'ref_id' => (string) $courseRefId,
            'item_ref_id' => '0',
        ], '', '&');
    }

    private function getCourseRefId(): int
    {
        foreach ([$_GET, $_POST] as $source) {
            if (isset($source['itxeb_course_ref_id']) && is_scalar($source['itxeb_course_ref_id'])) {
                $value = (int) $source['itxeb_course_ref_id'];
                if ($value > 0) {
                    return $value;
                }
            }
        }
        return $this->bridge->detectCourseRefId();
    }

    private function mainTemplate()
    {
        if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'ui')) {
            return $GLOBALS['DIC']->ui()->mainTemplate();
        }
        return $GLOBALS['tpl'];
    }
}
PHP;

itxeb_fix_write($companionTemplate . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl', $uiHook);
itxeb_fix_write($companionTemplate . '/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php.tpl', $router);
if (is_dir($companionInstalled)) {
    itxeb_fix_write($companionInstalled . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php', $uiHook);
    itxeb_fix_write($companionInstalled . '/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php', $router);
}

$files = [
    $companionTemplate . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl',
    $companionTemplate . '/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php.tpl',
];
if (is_dir($companionInstalled)) {
    $files[] = $companionInstalled . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php';
    $files[] = $companionInstalled . '/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php';
}
foreach ($files as $file) {
    passthru('php -l ' . escapeshellarg($file), $code);
    if ($code !== 0) {
        itxeb_fix_fail("syntaxe PHP invalide: {$file}");
    }
}

echo "\nCorrectif appliqué : encart limité à Contenu + lien router avec cmdClass explicite.\n";
