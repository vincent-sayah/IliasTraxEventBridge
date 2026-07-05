<?php
/**
 * V0.13 - Page Suivi xAPI propre via ilUIPluginRouterGUI.
 *
 * Objectif : abandonner l'injection d'un faux onglet principal de cours.
 * Le plugin UIHook ne sert plus qu'à poser un bouton/lien propre dans la page
 * Contenu du cours. Le lien ouvre une vraie page plugin routée par ILIAS :
 * ilUIPluginRouterGUI -> ilIliasTraxEventBridgeCourseUIRouterGUI.
 *
 * Cette page possède de vrais onglets ILIAS natifs :
 * - Tableau de bord
 * - Analyse
 * - Expert
 * - Configuration
 *
 * À lancer depuis la racine du plugin EventHook IliasTraxEventBridge :
 * php scripts/patch_v013_router_course_xapi_page.php
 */

function itxeb_router_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: {$message}\n");
    exit(1);
}

function itxeb_router_write(string $file, string $content): void
{
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        itxeb_router_fail("création répertoire impossible: {$dir}");
    }
    if (file_put_contents($file, $content) === false) {
        itxeb_router_fail("écriture impossible: {$file}");
    }
    echo "WRITE: {$file}\n";
}

function itxeb_router_patch_screen(string $file): void
{
    if (!is_file($file)) {
        echo "IGNORE: écran absent: {$file}\n";
        return;
    }
    $content = file_get_contents($file);
    if (!is_string($content)) {
        itxeb_router_fail("lecture impossible: {$file}");
    }
    $original = $content;

    if (strpos($content, 'private bool $renderInnerTabs') === false) {
        $needle = "    private string \$messageType = 'info';\n";
        $insert = "    private string \$messageType = 'info';\n    private bool \$renderInnerTabs = true;\n\n    public function setRenderInnerTabs(bool \$renderInnerTabs): void\n    {\n        \$this->renderInnerTabs = \$renderInnerTabs;\n    }\n";
        if (strpos($content, $needle) === false) {
            itxeb_router_fail("point insertion renderInnerTabs introuvable: {$file}");
        }
        $content = str_replace($needle, $insert, $content);
    }

    $old = "        \$html = \$this->renderMessage()\n            . \$this->renderInnerTabs(\$courseRefId, \$cmd)\n            . \$this->renderView(\$course, \$cmd);";
    $new = "        \$html = \$this->renderMessage()\n            . (\$this->renderInnerTabs ? \$this->renderInnerTabs(\$courseRefId, \$cmd) : '')\n            . \$this->renderView(\$course, \$cmd);";
    if (strpos($content, $old) !== false) {
        $content = str_replace($old, $new, $content);
    }

    if ($content !== $original) {
        itxeb_router_write($file, $content);
    } else {
        echo "OK: écran déjà corrigé: {$file}\n";
    }
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_router_fail('lance ce script depuis la racine du plugin EventHook IliasTraxEventBridge.');
}

$eventHookSuffix = '/Services/EventHandling/EventHook/IliasTraxEventBridge';
if (substr($root, -strlen($eventHookSuffix)) !== $eventHookSuffix) {
    itxeb_router_fail("chemin plugin principal inattendu: {$root}");
}

$customizingRoot = substr($root, 0, -strlen($eventHookSuffix));
$companionTemplate = $root . '/companion/IliasTraxEventBridgeCourseUI';
$companionInstalled = $customizingRoot . '/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$reportPlugin = $customizingRoot . '/Services/Repository/RepositoryObject/IliasTraxReport';

$uiHook = <<<'PHP'
<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIBridge.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIScreen.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php';

/**
 * UIHook léger : il ne crée plus de faux onglet principal de cours.
 * Il ajoute seulement un point d'entrée visible dans la page finale du cours.
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

        $context = $this->getCurrentCourseContext();
        $courseRefId = (int) ($context['course_ref_id'] ?? 0);
        if ($courseRefId <= 0 || empty($context['main_plugin_available']) || empty($context['course_tracking_classes_available']) || empty($context['can_manage'])) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $url = $this->buildRouterUrl($courseRefId, 'showDashboard');
        if ($url === '') {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $newHtml = $this->injectCourseEntryButton($html, $url, (string) ($context['course_title'] ?? ''));
        return $newHtml !== $html
            ? ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $newHtml]
            : ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
    }

    /** @param string $a_comp @param string $a_part @param array<string,mixed> $a_par */
    public function modifyGUI($a_comp, $a_part, $a_par = []): void
    {
        // Ne rien faire ici. Sur l'instance ILIAS 10 testée, modifyGUI(..., "tabs")
        // n'est pas appelé pour les onglets du cours. Les onglets natifs sont donc
        // posés dans la page routée ilUIPluginRouterGUI.
    }

    /** @return array<string,mixed> */
    public function getCurrentCourseContext(): array
    {
        return $this->bridge->getCourseContext();
    }

    private function buildRouterUrl(int $courseRefId, string $cmd): string
    {
        try {
            if (!isset($GLOBALS['DIC']) || !is_object($GLOBALS['DIC']) || !method_exists($GLOBALS['DIC'], 'ctrl')) {
                return $this->buildRouterUrlFallback($courseRefId, $cmd);
            }
            $ctrl = $GLOBALS['DIC']->ctrl();
            $ctrl->setParameterByClass(ilIliasTraxEventBridgeCourseUIRouterGUI::class, 'itxeb_course_ref_id', (string) $courseRefId);
            $url = (string) $ctrl->getLinkTargetByClass([
                ilUIPluginRouterGUI::class,
                ilIliasTraxEventBridgeCourseUIRouterGUI::class,
            ], $cmd);
            $ctrl->setParameterByClass(ilIliasTraxEventBridgeCourseUIRouterGUI::class, 'itxeb_course_ref_id', '');
            return $url !== '' ? $url : $this->buildRouterUrlFallback($courseRefId, $cmd);
        } catch (Throwable $ignored) {
            return $this->buildRouterUrlFallback($courseRefId, $cmd);
        }
    }

    private function buildRouterUrlFallback(int $courseRefId, string $cmd): string
    {
        $script = isset($_SERVER['SCRIPT_NAME']) && is_scalar($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/ilias.php';
        return $script . '?' . http_build_query([
            'baseClass' => 'ilUIPluginRouterGUI',
            'cmdClass' => strtolower(ilIliasTraxEventBridgeCourseUIRouterGUI::class),
            'cmd' => $cmd,
            'itxeb_course_ref_id' => (string) $courseRefId,
        ], '', '&');
    }

    private function injectCourseEntryButton(string $html, string $url, string $courseTitle): string
    {
        if (strpos($html, 'id="itxeb_course_xapi_entry"') !== false || strpos($html, "id='itxeb_course_xapi_entry'") !== false) {
            return $html;
        }

        if (!class_exists('DOMDocument')) {
            $entry = $this->entryHtml($url, $courseTitle);
            $newHtml = preg_replace('/(<[^>]+id=("|\')il_center_col\2[^>]*>)/isu', '$1' . $entry, $html, 1, $count);
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

        $entry = $dom->createElement('div');
        $entry->setAttribute('id', 'itxeb_course_xapi_entry');
        $entry->setAttribute('class', 'ilInfoScreenSec itxeb-course-xapi-entry');
        $entry->setAttribute('style', 'margin: 0 0 15px 0; padding: 12px; border: 1px solid #d0d0d0; background: #f8f8f8;');

        $title = $dom->createElement('h3');
        $title->appendChild($dom->createTextNode('Suivi xAPI'));
        $entry->appendChild($title);

        $p = $dom->createElement('p');
        $p->appendChild($dom->createTextNode('Consulter le tableau de bord, l’analyse pédagogique et la vue expert xAPI de ce cours.'));
        $entry->appendChild($p);

        $a = $dom->createElement('a');
        $a->setAttribute('class', 'btn btn-default');
        $a->setAttribute('href', $url);
        $a->appendChild($dom->createTextNode('Ouvrir le suivi xAPI'));
        $entry->appendChild($a);

        if ($center->firstChild instanceof DOMNode) {
            $center->insertBefore($entry, $center->firstChild);
        } else {
            $center->appendChild($entry);
        }

        $result = $dom->saveHTML();
        $result = preg_replace('/^<\?xml[^>]+>\s*/', '', (string) $result) ?? (string) $result;
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
        return $result;
    }

    private function entryHtml(string $url, string $courseTitle): string
    {
        return '<div id="itxeb_course_xapi_entry" class="ilInfoScreenSec itxeb-course-xapi-entry" style="margin:0 0 15px 0;padding:12px;border:1px solid #d0d0d0;background:#f8f8f8;">'
            . '<h3>Suivi xAPI</h3>'
            . '<p>Consulter le tableau de bord, l’analyse pédagogique et la vue expert xAPI de ce cours.</p>'
            . '<a class="btn btn-default" href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Ouvrir le suivi xAPI</a>'
            . '</div>';
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

        $html = $screen->handle();
        $this->mainTemplate()->setContent($html);
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
        if (isset($_POST['itxeb_cui_cmd']) && is_scalar($_POST['itxeb_cui_cmd'])) {
            $_POST['itxeb_cui_cmd'] = $map[$cmd] ?? (string) $_POST['itxeb_cui_cmd'];
        }
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
        $courseRefId = $this->getCourseRefId();
        try {
            $ctrl = $GLOBALS['DIC']->ctrl();
            $ctrl->setParameterByClass(self::class, 'itxeb_course_ref_id', (string) $courseRefId);
            $url = (string) $ctrl->getLinkTargetByClass([ilUIPluginRouterGUI::class, self::class], $cmd);
            $ctrl->setParameterByClass(self::class, 'itxeb_course_ref_id', '');
            if ($url !== '') {
                return $url;
            }
        } catch (Throwable $ignored) {
        }

        return '/ilias.php?' . http_build_query([
            'baseClass' => 'ilUIPluginRouterGUI',
            'cmdClass' => strtolower(self::class),
            'cmd' => $cmd,
            'itxeb_course_ref_id' => (string) $courseRefId,
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

itxeb_router_write($companionTemplate . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl', $uiHook);
itxeb_router_write($companionTemplate . '/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php.tpl', $router);
itxeb_router_patch_screen($companionTemplate . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl');

if (is_dir($companionInstalled)) {
    itxeb_router_write($companionInstalled . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php', $uiHook);
    itxeb_router_write($companionInstalled . '/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php', $router);
    itxeb_router_patch_screen($companionInstalled . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php');
}

if (is_dir($reportPlugin)) {
    echo "REMOVE: ancien RepositoryObject expérimental {$reportPlugin}\n";
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($reportPlugin, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $path) {
        $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
    }
    rmdir($reportPlugin);
}

$syntaxFiles = [
    $companionTemplate . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl',
    $companionTemplate . '/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php.tpl',
    $companionTemplate . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl',
];
if (is_dir($companionInstalled)) {
    $syntaxFiles[] = $companionInstalled . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php';
    $syntaxFiles[] = $companionInstalled . '/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php';
    $syntaxFiles[] = $companionInstalled . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
}

foreach ($syntaxFiles as $file) {
    if (!is_file($file)) {
        continue;
    }
    passthru('php -l ' . escapeshellarg($file), $code);
    if ($code !== 0) {
        itxeb_router_fail("syntaxe PHP invalide: {$file}");
    }
}

echo "\nCorrectif appliqué.\n";
echo "Modèle retenu : bouton Suivi xAPI dans le cours + page dédiée routée + onglets ILIAS natifs.\n";
echo "Redémarre php-fpm/httpd puis vide le cache ILIAS si nécessaire.\n";
