<?php
/**
 * V0.13 - handlers publics pour la classe routée Suivi xAPI.
 *
 * Le lien ilCtrl est maintenant correct et contient cmdNode. La page blanche
 * indique que le routage arrive probablement sur la classe GUI mais que la
 * commande showDashboard/showAnalysis/... n'est pas rendue comme attendu.
 *
 * Ce patch ajoute des méthodes publiques explicites pour chaque commande et un
 * garde-fou try/catch qui affiche l'erreur dans le template ILIAS au lieu de
 * laisser une page blanche.
 *
 * À lancer depuis la racine du plugin EventHook IliasTraxEventBridge :
 * php scripts/patch_v013_router_public_commands.php
 */

function itxeb_cmd_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: {$message}\n");
    exit(1);
}

function itxeb_cmd_write(string $file, string $content): void
{
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        itxeb_cmd_fail("création répertoire impossible: {$dir}");
    }
    if (file_put_contents($file, $content) === false) {
        itxeb_cmd_fail("écriture impossible: {$file}");
    }
    echo "WRITE: {$file}\n";
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_cmd_fail('lance ce script depuis la racine du plugin EventHook IliasTraxEventBridge.');
}
$eventHookSuffix = '/Services/EventHandling/EventHook/IliasTraxEventBridge';
if (substr($root, -strlen($eventHookSuffix)) !== $eventHookSuffix) {
    itxeb_cmd_fail("chemin plugin principal inattendu: {$root}");
}
$customizingRoot = substr($root, 0, -strlen($eventHookSuffix));
$companionTemplate = $root . '/companion/IliasTraxEventBridgeCourseUI';
$companionInstalled = $customizingRoot . '/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';

$pluginPhp = <<<'PHP'
<?php

$id = 'itxebcui';
$version = '0.3.2';
$ilias_min_version = '10.0.0';
$ilias_max_version = '10.999.999';
$responsible = 'TRAX / ILIAS integration';
$responsible_mail = 'noreply@localhost';
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
        $this->render($this->getRouterCommand());
    }

    public function showDashboard(): void
    {
        $this->render('showDashboard');
    }

    public function showAnalysis(): void
    {
        $this->render('showAnalysis');
    }

    public function showExpert(): void
    {
        $this->render('showExpert');
    }

    public function showConfig(): void
    {
        $this->render('showConfig');
    }

    public function saveConfig(): void
    {
        $this->render('saveConfig');
    }

    public function exportExpertCsv(): void
    {
        $this->render('exportExpertCsv');
    }

    public function exportDashboardPdf(): void
    {
        $this->render('exportDashboardPdf');
    }

    public function generateAiAnalysis(): void
    {
        $this->render('generateAiAnalysis');
    }

    private function render(string $cmd): void
    {
        try {
            $cmd = $this->normalizeCommand($cmd);
            $this->prepareScreenCommand($cmd);
            $this->setTabs($cmd);

            $screen = new ilIliasTraxEventBridgeCourseUIScreen($this->bridge);
            if (method_exists($screen, 'setRenderInnerTabs')) {
                $screen->setRenderInnerTabs(false);
            }

            $html = $screen->handle();
            if (!is_string($html) || trim($html) === '') {
                $html = '<div class="ilFailureMessage">Suivi xAPI : aucun contenu généré.</div>';
            }
            $this->mainTemplate()->setContent($html);
        } catch (Throwable $e) {
            $this->mainTemplate()->setContent(
                '<div class="ilFailureMessage"><h2>Erreur Suivi xAPI</h2><pre>'
                . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</pre></div>'
            );
        }
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
        return $this->normalizeCommand($cmd);
    }

    private function normalizeCommand(string $cmd): string
    {
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
        $courseRefId = $this->getCourseRefId();
        try {
            if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'ctrl')) {
                $ctrl = $GLOBALS['DIC']->ctrl();
                $ctrl->setParameterByClass(self::class, 'itxeb_course_ref_id', (string) $courseRefId);
                $url = (string) $ctrl->getLinkTargetByClass([ilUIPluginRouterGUI::class, self::class], $cmd);
                $ctrl->setParameterByClass(self::class, 'itxeb_course_ref_id', '');
                if ($url !== '') {
                    return $url;
                }
            }
        } catch (Throwable $ignored) {
        }

        return '/ilias.php?' . http_build_query([
            'baseClass' => 'ilrepositorygui',
            'cmdClass' => 'ilobjcoursegui',
            'ref_id' => (string) $courseRefId,
            'itxeb_cui_cmd' => $this->screenCommandFor($cmd),
            'itxeb_course_ref_id' => (string) $courseRefId,
        ], '', '&');
    }

    private function screenCommandFor(string $cmd): string
    {
        $map = [
            'showDashboard' => 'showCourseDashboard',
            'showAnalysis' => 'showCourseAnalysis',
            'showExpert' => 'showCourseExpert',
            'showConfig' => 'showCourseTracking',
        ];
        return $map[$cmd] ?? 'showCourseDashboard';
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

itxeb_cmd_write($companionTemplate . '/plugin.php.tpl', $pluginPhp);
itxeb_cmd_write($companionTemplate . '/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php.tpl', $router);
if (is_dir($companionInstalled)) {
    itxeb_cmd_write($companionInstalled . '/plugin.php', $pluginPhp);
    itxeb_cmd_write($companionInstalled . '/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php', $router);
}

$files = [
    $companionTemplate . '/plugin.php.tpl',
    $companionTemplate . '/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php.tpl',
];
if (is_dir($companionInstalled)) {
    $files[] = $companionInstalled . '/plugin.php';
    $files[] = $companionInstalled . '/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php';
}
foreach ($files as $file) {
    passthru('php -l ' . escapeshellarg($file), $code);
    if ($code !== 0) {
        itxeb_cmd_fail("syntaxe PHP invalide: {$file}");
    }
}

echo "\nCorrectif appliqué : version itxebcui 0.3.2 + méthodes publiques router.\n";
