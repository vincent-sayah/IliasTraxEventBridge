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

    public function showDashboard(): void { $this->render($this->getRouterCommand('showDashboard')); }
    public function showAnalysis(): void { $this->render($this->getRouterCommand('showAnalysis')); }
    public function showExpert(): void { $this->render($this->getRouterCommand('showExpert')); }
    public function showConfig(): void { $this->render($this->getRouterCommand('showConfig')); }
    public function saveConfig(): void { $this->render('saveConfig'); }
    public function exportExpertCsv(): void { $this->render('exportExpertCsv'); }
    public function exportDashboardPdf(): void { $this->render('exportDashboardPdf'); }
    public function generateAiAnalysis(): void { $this->render('generateAiAnalysis'); }

    private function render(string $cmd): void
    {
        try {
            $cmd = $this->normalizeCommand($cmd);
            $this->debug('render.enter', ['cmd' => $cmd, 'legacy_cmd' => $this->legacyScreenCommand(), 'uri' => (string) ($_SERVER['REQUEST_URI'] ?? '')]);
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

            if (!$this->isExportCommand($cmd)) {
                $html = $this->renderCourseReturnBar($this->getCourseRefId()) . $html;
            }

            $this->sendContent($html);
        } catch (Throwable $e) {
            $this->debug('render.error', ['error' => $e->getMessage()]);
            $this->sendContent(
                '<div class="ilFailureMessage"><h2>Erreur Suivi xAPI</h2><pre>'
                . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</pre></div>'
            );
        }
    }

    private function sendContent(string $html): void
    {
        $tpl = $this->mainTemplate();
        if (is_object($tpl) && method_exists($tpl, 'setContent')) {
            $tpl->setContent($html);
        }

        if (is_object($tpl) && method_exists($tpl, 'printToStdout')) {
            $tpl->printToStdout();
            return;
        }
        if (is_object($tpl) && method_exists($tpl, 'show')) {
            $tpl->show();
            return;
        }
        echo $html;
    }

    private function getRouterCommand(string $fallback = 'showDashboard'): string
    {
        $legacy = $this->legacyScreenCommand();
        if ($legacy !== '') {
            return $this->routerCommandFromLegacyScreenCommand($legacy);
        }

        $cmd = '';
        try {
            if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'ctrl')) {
                $cmd = (string) $GLOBALS['DIC']->ctrl()->getCmd($fallback);
            }
        } catch (Throwable $ignored) {
            $cmd = '';
        }
        if ($cmd === '' && isset($_GET['cmd']) && is_scalar($_GET['cmd'])) {
            $cmd = (string) $_GET['cmd'];
        }
        return $this->normalizeCommand($cmd !== '' ? $cmd : $fallback);
    }

    private function normalizeCommand(string $cmd): string
    {
        return in_array($cmd, ['showDashboard', 'showAnalysis', 'showExpert', 'showConfig', 'saveConfig', 'exportExpertCsv', 'exportDashboardPdf', 'generateAiAnalysis'], true)
            ? $cmd
            : 'showDashboard';
    }

    private function legacyScreenCommand(): string
    {
        foreach ([$_GET, $_POST] as $source) {
            if (isset($source['itxeb_cui_cmd']) && is_scalar($source['itxeb_cui_cmd'])) {
                $cmd = (string) $source['itxeb_cui_cmd'];
                if ($cmd !== '') {
                    return $cmd;
                }
            }
        }
        return '';
    }

    private function routerCommandFromLegacyScreenCommand(string $legacy): string
    {
        $map = [
            'showCourseDashboard' => 'showDashboard',
            'showCourseAnalysis' => 'showAnalysis',
            'showCourseExpert' => 'showExpert',
            'showCourseTracking' => 'showConfig',
            'saveCourseTracking' => 'saveConfig',
            'exportCourseExpertCsv' => 'exportExpertCsv',
            'exportCourseDashboardPdf' => 'exportDashboardPdf',
            'generateCourseAiAnalysis' => 'generateAiAnalysis',
        ];
        return $map[$legacy] ?? 'showDashboard';
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

    private function isExportCommand(string $cmd): bool
    {
        return in_array($cmd, ['exportExpertCsv', 'exportDashboardPdf'], true);
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
        } catch (Throwable $e) {
            $this->debug('tabs.error', ['error' => $e->getMessage()]);
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
        } catch (Throwable $e) {
            $this->debug('link.error', ['error' => $e->getMessage()]);
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
            'exportExpertCsv' => 'exportCourseExpertCsv',
            'exportDashboardPdf' => 'exportCourseDashboardPdf',
        ];
        return $map[$cmd] ?? 'showCourseDashboard';
    }

    private function renderCourseReturnBar(int $courseRefId): string
    {
        if ($courseRefId <= 0) {
            return '';
        }
        $courseUrl = htmlspecialchars($this->courseUrl($courseRefId), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<div class="itxeb-course-return" style="margin:0 0 14px 0;padding:10px 12px;border:1px solid #d6d6d6;background:#f8f8f8;border-radius:4px;display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;">'
            . '<div><strong>Suivi xAPI du cours</strong><br><span style="color:#666;">Vous consultez le suivi xAPI hors de l’écran contenu du cours.</span></div>'
            . '<a class="btn btn-default" href="' . $courseUrl . '">← Retour au contenu du cours</a>'
            . '</div>';
    }

    private function courseUrl(int $courseRefId): string
    {
        if ($courseRefId <= 0) {
            return '/ilias.php';
        }

        // Ne jamais utiliser de cmdNode codé en dur pour revenir au cours.
        // ilCtrl peut le rejeter si la structure courante ne correspond plus.
        try {
            if (class_exists('ilLink') && method_exists('ilLink', '_getStaticLink')) {
                $url = ilLink::_getStaticLink($courseRefId, 'crs', true);
                if (is_string($url) && trim($url) !== '') {
                    return $url;
                }
            }
        } catch (Throwable $e) {
            $this->debug('course_url.static_error', ['error' => $e->getMessage()]);
        }

        try {
            if (class_exists('ilLink') && method_exists('ilLink', '_getLink')) {
                $url = ilLink::_getLink($courseRefId, 'crs');
                if (is_string($url) && trim($url) !== '') {
                    return $url;
                }
            }
        } catch (Throwable $e) {
            $this->debug('course_url.link_error', ['error' => $e->getMessage()]);
        }

        return '/goto.php?target=crs_' . $courseRefId;
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
        return $GLOBALS['tpl'] ?? null;
    }

    /** @param array<string,string> $data */
    private function debug(string $event, array $data = []): void
    {
        $line = json_encode(['ts' => date('c'), 'event' => $event] + $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        @file_put_contents('/var/www/logs/itxeb_router_debug.log', $line . PHP_EOL, FILE_APPEND);
    }
}