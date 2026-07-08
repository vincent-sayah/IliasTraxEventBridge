<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIBridge.php';

class ilIliasTraxEventBridgeCourseUIScreen
{
    /** @var ilIliasTraxEventBridgeCourseUIBridge */
    private $bridge;
    /** @var ilIliasTraxEventBridgeCourseTrackingRepository|null */
    private $repository;
    /** @var ilIliasTraxEventBridgeCourseResourceResolver|null */
    private $resolver;
    /** @var ilIliasTraxEventBridgeCourseAnalyticsRepository|null */
    private $analytics;
    /** @var ilIliasTraxEventBridgeLrsCourseSummary|null */
    private $lrsSummary;
    /** @var ilIliasTraxEventBridgeAiAnalysisHistory|null */
    private $aiHistory;
    private string $message = '';
    private string $messageType = 'info';
    private bool $renderInnerTabs = true;

    public function setRenderInnerTabs(bool $renderInnerTabs): void
    {
        $this->renderInnerTabs = $renderInnerTabs;
    }
    /** @var array<string,mixed>|null */
    private $aiAnalysisResult = null;

    public function __construct(ilIliasTraxEventBridgeCourseUIBridge $bridge)
    {
        $this->bridge = $bridge;
        if ($this->bridge->loadCourseTrackingClasses()) {
            $this->repository = new ilIliasTraxEventBridgeCourseTrackingRepository();
            $this->resolver = new ilIliasTraxEventBridgeCourseResourceResolver($this->repository);
            $analyticsPath = $this->bridge->getMainPluginPath() . '/classes/class.ilIliasTraxEventBridgeCourseAnalyticsRepository.php';
            if (is_file($analyticsPath)) {
                require_once $analyticsPath;
            }
            if (class_exists('ilIliasTraxEventBridgeCourseAnalyticsRepository')) {
                $this->analytics = new ilIliasTraxEventBridgeCourseAnalyticsRepository();
            }
            $lrsPath = $this->bridge->getMainPluginPath() . '/classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php';
            if (is_file($lrsPath)) { require_once $lrsPath; }
            if (class_exists('ilIliasTraxEventBridgeLrsCourseSummary')) {
                $this->lrsSummary = new ilIliasTraxEventBridgeLrsCourseSummary();
            }
            $aiAnalyzerPath = $this->bridge->getMainPluginPath() . '/classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php';
            if (is_file($aiAnalyzerPath)) { require_once $aiAnalyzerPath; }
            $aiHistoryPath = $this->bridge->getMainPluginPath() . '/classes/class.ilIliasTraxEventBridgeAiAnalysisHistory.php';
            if (is_file($aiHistoryPath)) { require_once $aiHistoryPath; }
            if (class_exists('ilIliasTraxEventBridgeAiAnalysisHistory')) {
                $this->aiHistory = new ilIliasTraxEventBridgeAiAnalysisHistory($this->bridge->getMainPluginPath());
            }
        }
    }

    public function handle(): string
    {
        $courseRefId = $this->getCourseRefId();
        $cmd = $this->getCommand();

        if (!$this->repository || !$this->resolver) {
            return $this->renderShell('<div class="itxeb-cui-alert itxeb-cui-error">Plugin principal ou classes de configuration indisponibles.</div>', 0, '', $cmd);
        }
        if ($courseRefId <= 0) {
            return $this->renderShell('<div class="itxeb-cui-alert itxeb-cui-error">Cours introuvable : course_ref_id manquant.</div>', 0, '', $cmd);
        }
        if (!$this->repository->tablesExist()) {
            return $this->renderShell('<div class="itxeb-cui-alert itxeb-cui-error">Tables V0.7 absentes : evnt_evhk_itxeb_ccfg / evnt_evhk_itxeb_rcfg.</div>', $courseRefId, '', $cmd);
        }
        if (!$this->bridge->canManageCourse($courseRefId)) {
            $course = $this->resolver->resolveCourse($courseRefId);
            return $this->renderShell('<div class="itxeb-cui-alert itxeb-cui-error">Accès refusé : droits de gestion du cours insuffisants.</div>' . $this->renderCourseSummary($course), $courseRefId, (string) ($course['course_title'] ?? ''), $cmd);
        }

        if ($cmd === 'saveCourseTracking') {
            $this->save($courseRefId);
            $cmd = 'showCourseTracking';
        } elseif ($this->postString('itxeb_dashboard_save') === '1') {
            $course = $this->resolver->resolveCourse($courseRefId);
            $this->saveDashboardPreferences($course);
            $cmd = 'showCourseTracking';
        } elseif ($cmd === 'enableAllCourseTracking') {
            $this->setAll($courseRefId, true);
            $cmd = 'showCourseTracking';
        } elseif ($cmd === 'disableAllCourseTracking') {
            $this->setAll($courseRefId, false);
            $cmd = 'showCourseTracking';
        } elseif ($cmd === 'resetCourseTracking') {
            $this->resetCourse($courseRefId);
            $cmd = 'showCourseTracking';
        }

        $course = $this->resolver->resolveCourse($courseRefId);
        if ($cmd === 'generateCourseAiAnalysis') {
            $this->runCourseAiAnalysis($course);
            $cmd = 'showCourseAnalysis';
        }
        if ($cmd === 'exportCourseDashboardPdf') {
            $this->sendDashboardPdf($course);
        }
        if ($cmd === 'exportCourseExpertCsv') {
            $this->sendExpertCsv($course);
        }

        $html = $this->renderMessage()
            . ($this->renderInnerTabs ? $this->renderInnerTabs($courseRefId, $cmd) : '')
            . $this->renderView($course, $cmd);

        return $this->renderShell($html, $courseRefId, (string) ($course['course_title'] ?? ''), $cmd);
    }

    private function save(int $courseRefId): void
    {
        $course = $this->resolver->resolveCourse($courseRefId);
        $resources = is_array($course['resources'] ?? null) ? $course['resources'] : [];
        $enabledLookup = array_fill_keys($this->postIntArray('enabled_resources'), true);
        $updatedBy = $this->getCurrentUserId();
        $this->repository->setCourseEnabled($courseRefId, (int) ($course['course_obj_id'] ?? 0), $this->postString('course_enabled') === '1', $updatedBy);
        foreach ($resources as $resource) {
            $refId = (int) ($resource['ref_id'] ?? 0);
            if ($refId > 0) {
                $this->repository->setResourceEnabled($courseRefId, $refId, (int) ($resource['obj_id'] ?? 0), (string) ($resource['obj_type'] ?? ''), isset($enabledLookup[$refId]), $updatedBy);
            }
        }
        $this->message = 'Configuration xAPI du cours enregistrée.';
        $this->messageType = 'success';
    }

    /** @param array<string,mixed> $course */
    private function saveDashboardPreferences(array $course): void
    {
        $widgets = [];
        $enabled = array_fill_keys($this->postStringArray('dashboard_widgets'), true);
        foreach ($this->dashboardWidgetDefinitions() as $key => $label) {
            $widgets[$key] = isset($enabled[$key]);
        }
        $this->repository->setDashboardWidgets((int) ($course['course_ref_id'] ?? 0), (int) ($course['course_obj_id'] ?? 0), $widgets, $this->getCurrentUserId());
        $this->message = 'Préférences du tableau de bord enregistrées.';
        $this->messageType = 'success';
    }

    private function setAll(int $courseRefId, bool $enabled): void
    {
        $course = $this->resolver->resolveCourse($courseRefId);
        $updatedBy = $this->getCurrentUserId();
        $this->repository->setCourseEnabled($courseRefId, (int) ($course['course_obj_id'] ?? 0), $enabled, $updatedBy);
        foreach ((array) ($course['resources'] ?? []) as $resource) {
            $this->repository->setResourceEnabled($courseRefId, (int) ($resource['ref_id'] ?? 0), (int) ($resource['obj_id'] ?? 0), (string) ($resource['obj_type'] ?? ''), $enabled, $updatedBy);
        }
        $this->message = $enabled ? 'Toutes les ressources traçables du cours sont activées.' : 'Toutes les ressources traçables du cours sont désactivées.';
        $this->messageType = 'success';
    }

    private function resetCourse(int $courseRefId): void
    {
        $this->repository->deleteCourseConfig($courseRefId);
        $this->message = 'Configuration xAPI du cours réinitialisée.';
        $this->messageType = 'success';
    }

    /** @param array<string,mixed> $course */
    private function renderView(array $course, string $cmd): string
    {
        if ($cmd === 'showCourseDashboard') {
            return $this->renderDashboard($course);
        }
        if ($cmd === 'showCourseAnalysis') {
            return $this->renderAnalysis($course);
        }
        if ($cmd === 'showCourseExpert' || $cmd === 'exportCourseExpertCsv') {
            return $this->renderExpert($course);
        }
        return $this->renderCourseSummary($course) . $this->renderConfigForm($course) . $this->renderDashboardPreferencesForm($course) . $this->renderOutboxTechnicalSupervision($course) . $this->renderLrsDirectSummary($course) . $this->renderBulkActions((int) ($course['course_ref_id'] ?? 0));
    }

    /** @param array<string,mixed> $course */
    private function renderCourseSummary(array $course): string
    {
        $resources = is_array($course['resources'] ?? null) ? $course['resources'] : [];
        $configured = 0;
        $enabled = 0;
        foreach ($resources as $resource) {
            $configured += !empty($resource['configured']) ? 1 : 0;
            $enabled += !empty($resource['enabled']) ? 1 : 0;
        }
        return '<section class="itxeb-cui-section"><h2>Cours</h2><table class="itxeb-cui-table"><tbody>'
            . $this->row('Titre', (string) ($course['course_title'] ?? ''))
            . $this->row('course_ref_id', (string) ($course['course_ref_id'] ?? ''))
            . $this->row('course_obj_id', (string) ($course['course_obj_id'] ?? ''))
            . $this->row('Configuration cours', !empty($course['course_configured']) ? 'configuré' : 'non configuré')
            . $this->row('xAPI cours', !empty($course['course_enabled']) ? 'activé' : 'désactivé')
            . $this->row('Ressources traçables', (string) count($resources))
            . $this->row('Ressources configurées', (string) $configured)
            . $this->row('Ressources activées', (string) $enabled)
            . '</tbody></table></section>';
    }

    /** @param array<string,mixed> $course */
    private function renderConfigForm(array $course): string
    {
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        return '<section class="itxeb-cui-section"><h2>Activation xAPI</h2>'
            . '<form method="post" action="' . $this->esc($this->currentRequestUri()) . '">'
            . '<input type="hidden" name="itxeb_cui_cmd" value="saveCourseTracking">'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
            . '<p><label><input type="checkbox" name="course_enabled" value="1"' . (!empty($course['course_enabled']) ? ' checked="checked"' : '') . '> Activer les traces xAPI pour ce cours</label></p>'
            . $this->renderResourcesTable($course)
            . '<p><button class="btn btn-primary" type="submit">Enregistrer la configuration xAPI</button></p>'
            . '</form></section>';
    }

    /** @param array<string,mixed> $course */
    private function renderDashboardPreferencesForm(array $course): string
    {
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $widgets = $this->dashboardWidgets($courseRefId);
        $html = '<section class="itxeb-cui-section"><h2>Personnalisation du tableau de bord</h2>'
            . '<p>Choisir les blocs visibles dans l’onglet Tableau de bord pour ce cours. Les compteurs principaux restent toujours visibles.</p>'
            . '<form method="post" action="' . $this->esc($this->currentUrlWith(['itxeb_cui_cmd' => 'showCourseTracking', 'itxeb_course_ref_id' => (string) $courseRefId])) . '">'
            . '<input type="hidden" name="itxeb_cui_cmd" value="showCourseTracking">'
            . '<input type="hidden" name="itxeb_dashboard_save" value="1">'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
            . '<div class="itxeb-widget-grid">';
        foreach ($this->dashboardWidgetDefinitions() as $key => $label) {
            $html .= '<label class="itxeb-widget-choice"><input type="checkbox" name="dashboard_widgets[]" value="' . $this->esc($key) . '"' . (!empty($widgets[$key]) ? ' checked="checked"' : '') . '> ' . $this->esc($label) . '</label>';
        }
        return $html . '</div><p><button class="btn btn-default" type="submit">Enregistrer l’affichage du tableau de bord</button></p></form></section>';
    }

    /** @param array<string,mixed> $course */
    private function renderResourcesTable(array $course): string
    {
        $resources = is_array($course['resources'] ?? null) ? $course['resources'] : [];
        if (count($resources) === 0) {
            return '<p><em>Aucune ressource traçable détectée dans ce cours.</em></p>';
        }
        $html = '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-cui-resource-table"><thead><tr><th>xAPI</th><th>Type</th><th>Titre / chemin</th><th>ref_id</th><th>obj_id</th><th>Décision</th></tr></thead><tbody>';
        foreach ($resources as $resource) {
            $refId = (int) ($resource['ref_id'] ?? 0);
            $enabled = !empty($resource['enabled']);
            $configured = !empty($resource['configured']);
            $decision = $configured ? ($enabled ? 'activée' : 'désactivée') : 'non configurée';
            $html .= '<tr>'
                . '<td><label><input type="checkbox" name="enabled_resources[]" value="' . $this->esc((string) $refId) . '"' . ($enabled ? ' checked="checked"' : '') . '> activer</label></td>'
                . '<td>' . $this->esc((string) ($resource['obj_type'] ?? '')) . '<br><small>' . $this->esc((string) ($resource['resource_family'] ?? '')) . '</small></td>'
                . '<td><strong>' . $this->esc((string) ($resource['title'] ?? '')) . '</strong><br><small>' . $this->esc((string) ($resource['path'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc((string) $refId) . '</td><td>' . $this->esc((string) ($resource['obj_id'] ?? '')) . '</td><td>' . $this->esc($decision) . '</td></tr>';
        }
        return $html . '</tbody></table></div>';
    }

    /** @param array<string,mixed> $course */
    private function renderOutboxTechnicalSupervision(array $course): string
    {
        $html = '<section class="itxeb-cui-section"><h2>Supervision technique de l’envoi xAPI</h2>'
            . '<p>Cette section concerne uniquement la file locale d’envoi vers TRAX. Elle ne sert pas de source au suivi pédagogique xAPI, qui est lu directement dans TRAX/LRS.</p>';

        if (!$this->analytics || !method_exists($this->analytics, 'tableExists') || !$this->analytics->tableExists()) {
            return $html . '<div class="itxeb-cui-alert itxeb-cui-error">Outbox locale indisponible : table evnt_evhk_itxeb_out absente.</div></section>';
        }

        $dashboard = $this->analytics->buildForCourse($this->filterCourseResources($course), 365);
        $status = is_array($dashboard['by_status'] ?? null) ? $dashboard['by_status'] : [];
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $failed = (int) ($status['failed'] ?? 0);

        $html .= '<div class="itxeb-kpi-grid">'
            . $this->metricCard('À générer', (string) ($status['generated'] ?? 0), 'status generated')
            . $this->metricCard('En envoi', (string) ($status['sending'] ?? 0), 'status sending')
            . $this->metricCard('Envoyées', (string) ($status['sent'] ?? 0), 'GET /statements')
            . $this->metricCard('En erreur', (string) $failed, 'status failed')
            . $this->metricCard('Autres', (string) ($status['other'] ?? 0), 'autres statuts')
            . '</div>';

        if ($failed > 0) {
            $html .= '<div class="itxeb-cui-alert itxeb-cui-error"><strong>Attention :</strong> des envois xAPI sont en erreur dans l’outbox locale. Vérifier la configuration TRAX et les logs d’envoi.</div>';
        }

        $html .= '<table class="itxeb-cui-table"><tbody>'
            . $this->row('Périmètre', 'outbox locale technique sur 365 jours')
            . $this->row('Total outbox', (string) ($summary['total'] ?? 0))
            . $this->row('Rôle de cette section', 'supervision de l’envoi uniquement')
            . $this->row('Source du suivi xAPI', 'TRAX/LRS direct')
            . '</tbody></table></section>';
        return $html;
    }
    private function renderBulkActions(int $courseRefId): string
    {
        return '<section class="itxeb-cui-section"><h2>Actions rapides</h2><div class="itxeb-cui-actions">'
            . $this->actionForm('enableAllCourseTracking', $courseRefId, 'Tout activer')
            . $this->actionForm('disableAllCourseTracking', $courseRefId, 'Tout désactiver')
            . $this->actionForm('resetCourseTracking', $courseRefId, 'Réinitialiser ce cours')
            . '</div></section>';
    }

    private function actionForm(string $cmd, int $courseRefId, string $label): string
    {
        return '<form method="post" action="' . $this->esc($this->currentRequestUri()) . '" style="display:inline-block;margin-right:.5rem">'
            . '<input type="hidden" name="itxeb_cui_cmd" value="' . $this->esc($cmd) . '">'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
            . '<button class="btn btn-default" type="submit">' . $this->esc($label) . '</button></form>';
    }

    /** @param array<string,mixed> $course */
    private function renderDashboard(array $course): string
    {
        $dashboard = $this->loadDashboard($course);
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $widgets = $this->dashboardWidgets((int) ($course['course_ref_id'] ?? 0));
        $html = '<section class="itxeb-cui-section"><h2>Tableau de bord du cours</h2><p>Vue synthétique des statements xAPI présents dans TRAX pour ce cours.</p>'
            . $this->renderPeriodSelector('showCourseDashboard') . $this->renderResourceFilter($course, 'showCourseDashboard') . $this->renderAnalyticsWarning()
            . $this->renderPedagogicalSynthesis($dashboard)
            . '<div class="itxeb-kpi-grid">'
            . $this->metricCard('Statements TRAX', (string) ($summary['total'] ?? 0), 'Lecture LRS')
            . $this->metricCard('Apprenants actifs', (string) ($summary['active_learners'] ?? 0), 'Comptage anonyme')
            . $this->metricCard('Ressources utilisées', (string) ($summary['resources_with_traces'] ?? 0) . ' / ' . (string) ($summary['resources_total'] ?? 0), 'Au moins une trace')
            . $this->metricCard('Sans statement TRAX', (string) $this->countEnabledWithoutTraceResources($dashboard), 'À surveiller')
            . $this->metricCard('Pages LRS', (string) ($dashboard['pages'] ?? 0), 'pagination')
            . $this->metricCard('Critiques', (string) ($dashboard['pedagogy']['critical_count'] ?? 0), 'Priorité')
            . $this->metricCard('À surveiller', (string) ($dashboard['pedagogy']['watch_count'] ?? 0), 'Signal pédagogique')
            . $this->metricCard('Score moyen', $summary['avg_score_raw'] === null ? '-' : (string) $summary['avg_score_raw'] . ' %', 'Tests')
            . '</div>';
        if (!empty($widgets['comparison'])) {
            $html .= $this->renderPeriodComparison($course);
        }
        if (!empty($widgets['activity_by_day'])) {
            $html .= $this->renderActivityByDay($dashboard);
        }
        if (!empty($widgets['verb_distribution'])) {
            $html .= $this->renderVerbDistribution($dashboard);
        }
        if (!empty($widgets['top_resources'])) {
            $html .= $this->renderTopResources($dashboard);
        }
        if (!empty($widgets['enabled_without_trace'])) {
            $html .= $this->renderEnabledWithoutTraceResources($dashboard);
        }
        return $html . '</section>';
    }

    /** @param array<string,mixed> $course */
    private function renderLrsDirectSummary(array $course): string
    {
        if (!$this->lrsSummary) {
            return '<section class="itxeb-cui-section"><h3>TRAX / LRS direct</h3><p><em>Lecture LRS indisponible.</em></p></section>';
        }
        $s = $this->lrsSummary->build($course, $this->getPeriodDays());
        $lrsReturned = (int) ($s['returned'] ?? 0);
        $activity = (string) ($s['activity_id'] ?? '');
        $since = (string) ($s['since'] ?? '');
        $more = (string) ($s['more'] ?? '');
        $pages = (int) ($s['pages'] ?? 0);
        $complete = !empty($s['pagination_complete']);
        $limitReached = !empty($s['pagination_limit_reached']);
        $paginationStatus = $complete ? 'complète' : ($limitReached ? 'tronquée limite sécurité' : 'incomplète');
        $html = '<section class="itxeb-cui-section itxeb-lrs-direct"><h3>TRAX / LRS direct</h3>'
            . '<p>Lecture directe de TRAX/LRS. Ce bloc ne compare plus avec l\'outbox locale, car cette table peut être purgée en exploitation.</p>'
            . '<div class="itxeb-kpi-grid">'
            . $this->metricCard('État LRS', !empty($s['available']) ? 'disponible' : 'indisponible', 'HTTP ' . (string) ($s['http_status'] ?? 0))
            . $this->metricCard('Statements TRAX', (string) $lrsReturned, 'GET /statements')
            . $this->metricCard('Pages LRS', (string) $pages, $paginationStatus)
            . '</div>'
            . '<div style="display:grid;grid-template-columns:minmax(210px,260px) minmax(0,1fr);gap:0;border:1px solid #ddd;margin-top:12px">'
            . '<div style="font-weight:600;padding:8px 10px;border-bottom:1px solid #ddd;background:#f8f8f8">Pagination LRS</div><div style="padding:8px 10px;border-bottom:1px solid #ddd">' . $this->esc($paginationStatus . ' - ' . $pages . ' page(s) lue(s)') . '</div>'
            . '<div style="font-weight:600;padding:8px 10px;border-bottom:1px solid #ddd;background:#f8f8f8">Période depuis</div><div style="padding:8px 10px;border-bottom:1px solid #ddd;font-family:monospace">' . $this->esc($since) . '</div>'
            . '<div style="font-weight:600;padding:8px 10px;border-bottom:1px solid #ddd;background:#f8f8f8">More LRS restant</div><div style="padding:8px 10px;border-bottom:1px solid #ddd;overflow-wrap:anywhere">' . $this->esc($more === '' ? '-' : $this->shorten($more, 220)) . '</div>'
            . '<div style="font-weight:600;padding:8px 10px;background:#f8f8f8">Activité cours xAPI</div><div style="padding:8px 10px;overflow-wrap:anywhere;font-family:monospace">' . $this->esc($activity) . '</div>'
            . '</div>';
        if ((string) ($s['pagination_error'] ?? '') !== '') {
            $html .= '<div class="itxeb-cui-alert itxeb-cui-error" style="margin-top:12px">Pagination LRS : ' . $this->esc((string) $s['pagination_error']) . '</div>';
        }
        if ((string) ($s['error'] ?? '') !== '') {
            $html .= '<div class="itxeb-cui-alert itxeb-cui-error" style="margin-top:12px">' . $this->esc((string) $s['error']) . '</div>';
        }
        return $html . '</section>';
    }

    /** @param array<string,mixed> $dashboard */
    private function renderPedagogicalSynthesis(array $dashboard): string
    {
        $pedagogy = is_array($dashboard['pedagogy'] ?? null) ? $dashboard['pedagogy'] : [];
        $lines = is_array($pedagogy['synthesis_lines'] ?? null) ? $pedagogy['synthesis_lines'] : [];
        $html = '<div class="itxeb-pedagogy-summary"><h3>Synthèse pédagogique</h3><div class="itxeb-pedagogy-kpis">'
            . $this->metricCard('OK', (string) ($pedagogy['ok_count'] ?? 0), 'Ressources sans signal')
            . $this->metricCard('À surveiller', (string) ($pedagogy['watch_count'] ?? 0), 'Signal faible')
            . $this->metricCard('Critiques', (string) ($pedagogy['critical_count'] ?? 0), 'Priorité')
            . $this->metricCard('Sans trace', (string) ($pedagogy['resources_without_trace'] ?? 0), 'Sans statement TRAX')
            . '</div>';
        if (count($lines) > 0) {
            $html .= '<ul class="itxeb-pedagogy-lines">';
            foreach ($lines as $line) {
                if (is_scalar($line) && trim((string) $line) !== '') {
                    $html .= '<li>' . $this->esc((string) $line) . '</li>';
                }
            }
            $html .= '</ul>';
        }
        return $html . '</div>';
    }

    private function pedagogicalBadgeClass(string $status): string
    {
        return $status === 'critical' ? 'itxeb-pedagogy-critical' : ($status === 'watch' ? 'itxeb-pedagogy-watch' : ($status === 'ok' ? 'itxeb-pedagogy-ok' : 'itxeb-pedagogy-muted'));
    }
    /** @param array<string,mixed> $course */
    private function renderAnalysis(array $course): string
    {
        $dashboard = $this->loadDashboard($course);
        $resources = is_array($dashboard['by_resource'] ?? null) ? $dashboard['by_resource'] : [];
        $html = '<section class="itxeb-cui-section itxeb-trainer-page"><h2>Analyse formateur</h2><p>Vue opérationnelle des ressources utilisées, peu utilisées, activées sans trace ou associées à des signaux pédagogiques.</p>' . $this->renderPeriodSelector('showCourseAnalysis') . $this->renderResourceFilter($course, 'showCourseAnalysis') . $this->renderAnalyticsWarning() . $this->renderTrainerActionSummary($dashboard) . $this->renderAiAnalysisAction($course) . $this->renderAiAnalysisResult() . $this->renderAiHistoryPanel($course) . $this->renderPedagogicalSynthesis($dashboard);
        if (count($resources) === 0) {
            return $html . '<p><em>Aucune ressource traçable détectée.</em></p></section>';
        }
        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-cui-analysis-table"><thead><tr><th>Statut</th><th>Raison</th><th>Ressource</th><th>Type</th><th>xAPI</th><th>Traces</th><th>Apprenants</th><th>Dernière trace</th><th>Score moyen</th><th>Tests</th><th>Taux échec</th></tr></thead><tbody>';
        foreach ($resources as $stats) {
            $testText = (int) ($stats['test_attempts'] ?? 0) > 0 ? (string) ($stats['test_passed'] ?? 0) . ' réussis / ' . (string) ($stats['test_failed'] ?? 0) . ' échoués' : '-';
            $score = $stats['avg_score_raw'] === null ? '-' : (string) $stats['avg_score_raw'] . ' %';
            $failureRate = is_numeric($stats['failure_rate'] ?? null) ? (string) $stats['failure_rate'] . ' %' : '-';
            $status = (string) ($stats['pedagogical_status'] ?? '');
            $label = (string) ($stats['pedagogical_label'] ?? ($stats['signal'] ?? ''));
            $reason = (string) ($stats['pedagogical_reason'] ?? '');
            $html .= '<tr><td><span class="itxeb-pedagogy-badge ' . $this->pedagogicalBadgeClass($status) . '">' . $this->esc($label) . '</span></td>'
                . '<td><small>' . $this->esc($reason) . '</small></td>'
                . '<td><strong>' . $this->esc((string) ($stats['title'] ?? '')) . '</strong><br><small>' . $this->esc((string) ($stats['path'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc((string) ($stats['obj_type'] ?? '')) . '<br><small>' . $this->esc((string) ($stats['resource_family'] ?? '')) . '</small></td>'
                . '<td>' . (!empty($stats['enabled']) ? 'activé' : 'désactivé') . '</td><td>' . $this->esc((string) ($stats['traces'] ?? 0)) . '</td><td>' . $this->esc((string) ($stats['learners_count'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($stats['last_at'] ?? '')) . '</td><td>' . $this->esc($score) . '</td><td>' . $this->esc($testText) . '</td><td>' . $this->esc($failureRate) . '</td></tr>';
        }
        return $html . '</tbody></table></div>' . $this->renderStrugglingLearners($dashboard) . '</section>';
    }
    /** @param array<string,mixed> $course */
    private function runCourseAiAnalysis(array $course): void
    {
        if (!class_exists('ilIliasTraxEventBridgeCourseAiAnalyzer')) {
            $this->aiAnalysisResult = [
                'success' => false,
                'http_status' => 0,
                'message' => 'Service analyse IA indisponible.',
                'analysis' => '',
                'payload_summary' => '',
            ];
            $this->message = 'Analyse IA impossible : service indisponible.';
            $this->messageType = 'error';
            return;
        }

        $dashboard = $this->loadDashboard($course);
        $this->aiAnalysisResult = (new ilIliasTraxEventBridgeCourseAiAnalyzer())->analyze($course, $dashboard);
        if (!empty($this->aiAnalysisResult['success'])) {
            $this->saveAiAnalysisHistory($course, $this->aiAnalysisResult);
            $this->message = 'Analyse IA générée et historisée.';
            $this->messageType = 'success';
        } else {
            $this->message = 'Analyse IA échouée : ' . (string) ($this->aiAnalysisResult['message'] ?? 'erreur inconnue');
            $this->messageType = 'error';
        }
    }

    /** @param array<string,mixed> $course */
    private function renderAiAnalysisAction(array $course): string
    {
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $url = $this->currentUrlWith([
            'itxeb_cui_cmd' => 'generateCourseAiAnalysis',
            'itxeb_course_ref_id' => (string) $courseRefId,
            'itxeb_period_days' => (string) $this->getPeriodDays(),
            'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
            'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
        ]);

        return '<section class="itxeb-cui-section itxeb-ai-analysis-action itxeb-trainer-card"><h3>Analyse IA du cours</h3>'
            . '<p>Génère une synthèse pédagogique à partir des données xAPI agrégées de la période sélectionnée. En anonymisation stricte, aucun nom, courriel ou identité nominative apprenant n’est envoyé.</p>'
            . '<p><a class="btn btn-primary" href="' . $this->esc($url) . '">Générer une nouvelle analyse IA</a></p>'
            . '<p><small>La dernière analyse réussie est historisée localement et reprise dans l’export PDF.</small></p>'
            . '</section>';
    }

    private function renderAiAnalysisResult(): string
    {
        if ($this->aiAnalysisResult === null) {
            return '';
        }

        $success = !empty($this->aiAnalysisResult['success']);
        $http = (string) ($this->aiAnalysisResult['http_status'] ?? '0');
        $message = (string) ($this->aiAnalysisResult['message'] ?? '');
        $payloadSummary = (string) ($this->aiAnalysisResult['payload_summary'] ?? '');
        $analysis = trim((string) ($this->aiAnalysisResult['analysis'] ?? ''));
        $class = $success ? 'itxeb-cui-alert itxeb-cui-success' : 'itxeb-cui-alert itxeb-cui-error';

        $html = '<section class="itxeb-cui-section itxeb-ai-analysis-result"><h3>Résultat analyse IA</h3>'
            . '<div class="' . $class . '"><strong>HTTP ' . $this->esc($http) . '</strong> — ' . $this->esc($message) . '</div>';
        if ($payloadSummary !== '') {
            $html .= '<p><small>' . $this->esc($payloadSummary) . '</small></p>';
        }
        if ($analysis !== '') {
            $html .= $this->renderAiMarkdown($analysis);
        }
        return $html . '</section>';
    }
    /** @param array<string,mixed> $dashboard */
    private function renderTrainerActionSummary(array $dashboard): string
    {
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $pedagogy = is_array($dashboard['pedagogy'] ?? null) ? $dashboard['pedagogy'] : [];
        $critical = (int) ($pedagogy['critical_count'] ?? 0);
        $watch = (int) ($pedagogy['watch_count'] ?? 0);
        $withoutTrace = (int) ($pedagogy['resources_without_trace'] ?? 0);
        $failed = (int) ($summary['tests_failed'] ?? 0);
        $passed = (int) ($summary['tests_passed'] ?? 0);
        $priority = $critical > 0 ? 'Priorité haute : traiter les ressources critiques.' : ($watch > 0 || $withoutTrace > 0 ? 'Priorité moyenne : vérifier les ressources à surveiller.' : 'Situation stable sur les données disponibles.');
        return '<div class="itxeb-trainer-summary">'
            . $this->metricCard('Priorité formateur', $critical > 0 ? 'Haute' : (($watch + $withoutTrace) > 0 ? 'Moyenne' : 'Normale'), $priority)
            . $this->metricCard('Ressources critiques', (string) $critical, 'À reprendre en premier')
            . $this->metricCard('À surveiller', (string) $watch, 'Signaux faibles')
            . $this->metricCard('Tests', (string) $passed . ' / ' . (string) $failed, 'Réussis / échoués')
            . '</div>';
    }

    /** @param array<string,mixed> $course @param array<string,mixed> $result */
    private function saveAiAnalysisHistory(array $course, array $result): void
    {
        if (!$this->aiHistory) {
            return;
        }
        try {
            $this->aiHistory->save($course, $this->getPeriodDays(), $result, $this->getCurrentUserId());
        } catch (Throwable $e) {
            error_log('[IliasTraxEventBridge] Historisation IA impossible: ' . $e->getMessage());
        }
    }

    /** @param array<string,mixed> $course */
    private function renderAiHistoryPanel(array $course): string
    {
        if (!$this->aiHistory) {
            return '';
        }
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $records = $this->aiHistory->list($courseRefId, 20);
        if (count($records) === 0) {
            return '<section class="itxeb-cui-section itxeb-ai-history"><h3>Historique des analyses IA</h3><p><em>Aucune analyse IA historisée pour ce cours.</em></p></section>';
        }

        $selectedId = $this->getSelectedAiHistoryId();
        $selectedRecord = [];
        $html = '<section class="itxeb-cui-section itxeb-ai-history"><h3>Historique des analyses IA</h3><p>Dernières analyses générées pour ce cours. Cliquez sur <strong>Voir le détail</strong> pour relire une analyse historisée sans relancer l’IA.</p><div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr><th>Date UTC</th><th>Période</th><th>Statut</th><th>Résumé payload</th><th>Action</th></tr></thead><tbody>';
        foreach (array_slice($records, 0, 10) as $record) {
            $recordId = (string) ($record['id'] ?? '');
            $isSelected = $recordId !== '' && $recordId === $selectedId;
            if ($isSelected) {
                $selectedRecord = $record;
            }
            $url = $this->currentUrlWith([
                'itxeb_cui_cmd' => 'showCourseAnalysis',
                'itxeb_course_ref_id' => (string) $courseRefId,
                'itxeb_period_days' => (string) $this->getPeriodDays(),
                'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
                'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
                'itxeb_ai_history_id' => $recordId,
            ]);
            $action = $recordId !== ''
                ? ($isSelected ? '<strong>Affiché</strong>' : '<a class="btn btn-default btn-xs" href="' . $this->esc($url) . '">Voir le détail</a>')
                : '-';
            $html .= '<tr><td>' . $this->esc((string) ($record['created_at_utc'] ?? '')) . '</td>'
                . '<td>' . $this->esc((string) ($record['period_days'] ?? '')) . ' jour(s)</td>'
                . '<td>' . (!empty($record['success']) ? 'OK' : 'Erreur') . '</td>'
                . '<td><small>' . $this->esc((string) ($record['payload_summary'] ?? '')) . '</small></td>'
                . '<td>' . $action . '</td></tr>';
        }
        $html .= '</tbody></table></div>';

        if ($selectedId !== '') {
            if ($selectedRecord === []) {
                foreach ($records as $record) {
                    if ((string) ($record['id'] ?? '') === $selectedId) {
                        $selectedRecord = $record;
                        break;
                    }
                }
            }
            if ($selectedRecord === []) {
                $html .= '<div class="itxeb-cui-alert itxeb-cui-error">Analyse IA historisée introuvable pour cet identifiant.</div>';
            } else {
                $analysis = trim((string) ($selectedRecord['analysis'] ?? ''));
                $closeUrl = $this->currentUrlWith([
                    'itxeb_cui_cmd' => 'showCourseAnalysis',
                    'itxeb_course_ref_id' => (string) $courseRefId,
                    'itxeb_period_days' => (string) $this->getPeriodDays(),
                    'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
                    'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
                    'itxeb_ai_history_id' => '',
                ]);
                $html .= '<div class="itxeb-ai-history-detail"><h4>Détail de l’analyse IA historisée</h4>'
                    . '<p><small>ID : ' . $this->esc((string) ($selectedRecord['id'] ?? ''))
                    . ' — générée le ' . $this->esc((string) ($selectedRecord['created_at_utc'] ?? ''))
                    . ' — période ' . $this->esc((string) ($selectedRecord['period_days'] ?? '')) . ' jour(s)'
                    . ' — HTTP ' . $this->esc((string) ($selectedRecord['http_status'] ?? '')) . '</small></p>'
                    . '<p><small>' . $this->esc((string) ($selectedRecord['payload_summary'] ?? '')) . '</small></p>'
                    . ($analysis !== '' ? $this->renderAiMarkdown($analysis) : '<p><em>Analyse vide.</em></p>')
                    . '<p><a class="btn btn-default btn-xs" href="' . $this->esc($closeUrl) . '">Masquer le détail</a></p>'
                    . '</div>';
            }
        }

        return $html . '</section>';
    }

    private function renderAiMarkdown(string $markdown): string
    {
        $lines = preg_split('/\R/', trim($markdown));
        if (!is_array($lines)) {
            return '<div class="itxeb-ai-markdown"><p>' . $this->esc($markdown) . '</p></div>';
        }
        $html = '<div class="itxeb-ai-markdown">';
        $inList = false;
        $inTable = false;
        foreach ($lines as $line) {
            $trim = trim((string) $line);
            if ($trim === '' || $trim === '---') {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                if ($inTable) { $html .= '</tbody></table></div>'; $inTable = false; }
                continue;
            }
            if (preg_match('/^##\s*(.+)$/u', $trim, $m) === 1) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                if ($inTable) { $html .= '</tbody></table></div>'; $inTable = false; }
                $html .= '<h4>' . $this->renderInlineMarkdown((string) $m[1]) . '</h4>';
                continue;
            }
            if (preg_match('/^###\s*(.+)$/u', $trim, $m) === 1) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                if ($inTable) { $html .= '</tbody></table></div>'; $inTable = false; }
                $html .= '<h5>' . $this->renderInlineMarkdown((string) $m[1]) . '</h5>';
                continue;
            }
            if (strpos($trim, '|') === 0 && substr($trim, -1) === '|') {
                if (preg_match('/^\|\s*-+/', $trim) === 1) { continue; }
                if ($inList) { $html .= '</ul>'; $inList = false; }
                if (!$inTable) { $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-ai-table"><tbody>'; $inTable = true; }
                $html .= '<tr>';
                foreach (array_map('trim', explode('|', trim($trim, '|'))) as $cell) {
                    $html .= '<td>' . $this->renderInlineMarkdown($cell) . '</td>';
                }
                $html .= '</tr>';
                continue;
            }
            if (preg_match('/^-\s+(.+)$/u', $trim, $m) === 1) {
                if ($inTable) { $html .= '</tbody></table></div>'; $inTable = false; }
                if (!$inList) { $html .= '<ul>'; $inList = true; }
                $html .= '<li>' . $this->renderInlineMarkdown((string) $m[1]) . '</li>';
                continue;
            }
            if ($inList) { $html .= '</ul>'; $inList = false; }
            if ($inTable) { $html .= '</tbody></table></div>'; $inTable = false; }
            $html .= '<p>' . $this->renderInlineMarkdown($trim) . '</p>';
        }
        if ($inList) { $html .= '</ul>'; }
        if ($inTable) { $html .= '</tbody></table></div>'; }
        return $html . '</div>';
    }

    private function renderInlineMarkdown(string $text): string
    {
        $escaped = $this->esc($text);
        return preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $escaped) ?: $escaped;
    }

    /** @param array<string,mixed> $course */
    private function pdfAiAnalysisSection(array $course): string
    {
        if (!$this->aiHistory) {
            return '';
        }
        $record = $this->aiHistory->latest((int) ($course['course_ref_id'] ?? 0), $this->getPeriodDays());
        if ($record === []) {
            $record = $this->aiHistory->latest((int) ($course['course_ref_id'] ?? 0), 0);
        }
        $analysis = trim((string) ($record['analysis'] ?? ''));
        if ($analysis === '') {
            return '<h2>Analyse IA</h2><p><em>Aucune analyse IA historisée à inclure dans le PDF.</em></p>';
        }
        return '<h2>Analyse IA historisée</h2><p class="small">Générée le ' . $this->esc((string) ($record['created_at_utc'] ?? '')) . ' — ' . $this->esc((string) ($record['payload_summary'] ?? '')) . '</p>' . $this->renderAiMarkdown($analysis);
    }

    /** @param array<string,mixed> $course */
    /** @param array<string,mixed> $dashboard */
    private function renderStrugglingLearners(array $dashboard): string
    {
        $rows = is_array($dashboard['expert_rows'] ?? null) ? $dashboard['expert_rows'] : [];
        $learners = [];

        foreach ($rows as $row) {
            if (!is_array($row) || (string) ($row['obj_type'] ?? '') !== 'tst') {
                continue;
            }
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $score = is_numeric($row['score_raw'] ?? null) ? (float) $row['score_raw'] : null;
            $success = $row['success'] ?? null;
            $verbId = (string) ($row['verb_id'] ?? '');
            $failed = ($success === false) || stripos($verbId, 'failed') !== false;
            $lowScore = $score !== null && $score < 50.0;

            if (!$failed && !$lowScore) {
                continue;
            }

            if (!isset($learners[$userId])) {
                $learners[$userId] = [
                    'anonymous_id' => 'Apprenant ' . substr(sha1('itxeb:' . (string) $userId), 0, 8),
                    'alerts' => 0,
                    'failed' => 0,
                    'low_scores' => 0,
                    'scores_total' => 0.0,
                    'scores_count' => 0,
                    'last_at' => '',
                    'resources' => [],
                ];
            }

            $learners[$userId]['alerts']++;
            if ($failed) {
                $learners[$userId]['failed']++;
            }
            if ($lowScore) {
                $learners[$userId]['low_scores']++;
            }
            if ($score !== null) {
                $learners[$userId]['scores_total'] += $score;
                $learners[$userId]['scores_count']++;
            }
            $createdAt = (string) ($row['created_at'] ?? '');
            if ($createdAt !== '' && $createdAt > (string) $learners[$userId]['last_at']) {
                $learners[$userId]['last_at'] = $createdAt;
            }
            $title = trim((string) ($row['object_title'] ?? ''));
            if ($title !== '') {
                $learners[$userId]['resources'][$title] = true;
            }
        }

        $visible = [];
        foreach ($learners as $learner) {
            if ((int) $learner['failed'] >= 2 || (int) $learner['low_scores'] >= 2 || (int) $learner['alerts'] >= 3) {
                $learner['avg_score'] = (int) $learner['scores_count'] > 0
                    ? round(((float) $learner['scores_total']) / max(1, (int) $learner['scores_count']), 1)
                    : null;
                $visible[] = $learner;
            }
        }

        usort($visible, static function (array $a, array $b): int {
            return ((int) $b['alerts'] <=> (int) $a['alerts']) ?: ((int) $b['failed'] <=> (int) $a['failed']);
        });
        $visible = array_slice($visible, 0, 10);

        $html = '<section class="itxeb-cui-section"><h3>Apprenants en difficulté</h3>'
            . '<p>Vue anonymisée : aucun nom ni courriel n’est affiché. Les identifiants sont des pseudonymes techniques.</p>';
        if (count($visible) === 0) {
            return $html . '<p><em>Aucun apprenant en difficulté détecté sur la période et le filtre sélectionnés.</em></p></section>';
        }

        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-struggling-table"><thead><tr><th>Apprenant</th><th>Alertes</th><th>Échecs</th><th>Scores faibles</th><th>Score moyen</th><th>Dernière alerte</th><th>Ressources concernées</th></tr></thead><tbody>';
        foreach ($visible as $learner) {
            $resources = array_keys((array) ($learner['resources'] ?? []));
            sort($resources);
            $resourceText = count($resources) === 0 ? '-' : implode(', ', array_slice($resources, 0, 3));
            if (count($resources) > 3) {
                $resourceText .= ' +' . (count($resources) - 3);
            }
            $scoreText = $learner['avg_score'] === null ? '-' : (string) $learner['avg_score'] . ' %';
            $html .= '<tr><td><span class="itxeb-signal itxeb-signal-warning">' . $this->esc((string) $learner['anonymous_id']) . '</span></td>'
                . '<td>' . $this->esc((string) ($learner['alerts'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($learner['failed'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($learner['low_scores'] ?? 0)) . '</td>'
                . '<td>' . $this->esc($scoreText) . '</td>'
                . '<td>' . $this->esc((string) ($learner['last_at'] ?? '')) . '</td>'
                . '<td>' . $this->esc($resourceText) . '</td></tr>';
        }

        return $html . '</tbody></table></div></section>';
    }

    /** @param array<string,mixed> $course */
    private function renderExpert(array $course): string
    {
        $dashboard = $this->loadDashboard($course);
        $rows = is_array($dashboard['expert_rows'] ?? null) ? $dashboard['expert_rows'] : [];
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $exportUrl = $this->currentUrlWith([
            'itxeb_cui_cmd' => 'exportCourseExpertCsv',
            'itxeb_course_ref_id' => (string) $courseRefId,
            'itxeb_period_days' => (string) $this->getPeriodDays(),
            'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
            'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
        ]);
        $html = '<section class="itxeb-cui-section"><h2>Traces détaillées</h2><p>Vue support des 200 derniers statements retournés par TRAX pour ce cours.</p>'
            . $this->renderPeriodSelector('showCourseExpert') . $this->renderResourceFilter($course, 'showCourseExpert') . $this->renderAnalyticsWarning()
            . '<p><a class="btn btn-default itxeb-export-button" href="' . $this->esc($exportUrl) . '">Exporter CSV</a></p>';
        if (count($rows) === 0) {
            return $html . '<p><em>Aucun statement xAPI TRAX pour cette période ou cette ressource.</em></p></section>';
        }
        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-cui-expert-table"><thead><tr><th>Date</th><th>User ID</th><th>Verbe</th><th>Ressource</th><th>Type</th><th>Score</th><th>Completion</th><th>Success</th><th>Source</th><th>Statement ID</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr><td>' . $this->esc((string) ($row['created_at'] ?? '')) . '</td><td>' . $this->esc((string) ($row['user_id'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($row['verb_label'] ?? '')) . '<br><small>' . $this->esc((string) ($row['verb_id'] ?? '')) . '</small></td>'
                . '<td><strong>' . $this->esc((string) ($row['object_title'] ?? '')) . '</strong><br><small>ref_id ' . $this->esc((string) ($row['ref_id'] ?? 0)) . '</small></td>'
                . '<td>' . $this->esc((string) ($row['obj_type'] ?? '')) . '</td><td>' . $this->esc($row['score_raw'] === null ? '-' : (string) $row['score_raw'] . ' %') . '</td>'
                . '<td>' . $this->esc($this->nullableBoolLabel($row['completion'] ?? null)) . '</td><td>' . $this->esc($this->nullableBoolLabel($row['success'] ?? null)) . '</td><td>' . $this->esc((string) ($row['status'] ?? 'TRAX')) . '</td>'
                . '<td><small>' . $this->esc((string) ($row['statement_uuid'] ?? '')) . '</small></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    /** @param array<string,mixed> $course */
    private function renderDashboardPdfButton(array $course): string
    {
        $url = $this->currentUrlWith([
            'itxeb_cui_cmd' => 'exportCourseDashboardPdf',
            'itxeb_course_ref_id' => (string) ($course['course_ref_id'] ?? 0),
            'itxeb_period_days' => (string) $this->getPeriodDays(),
            'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
            'itxeb_filter_type' => $this->getSelectedObjectType(),
        ]);
        return '<p class="itxeb-export-button"><a class="btn btn-default" href="' . $this->esc($url) . '">Export PDF</a></p>';
    }

    /** @param array<string,mixed> $course */
    private function sendDashboardPdf(array $course): void
    {
        $dashboard = $this->loadDashboard($course);
        $html = $this->buildDashboardPdfHtml($course, $dashboard);
        $filename = 'suivi-xapi-cours-' . (string) ((int) ($course['course_ref_id'] ?? 0)) . '-' . gmdate('Ymd-His') . '.pdf';

        if (class_exists('Dompdf\\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $this->sendPdfBytes((string) $dompdf->output(), $filename);
        }

        $wkhtmltopdf = $this->findWkhtmltopdfBinary();
        if ($wkhtmltopdf !== '') {
            $pdf = $this->renderPdfWithWkhtmltopdf($wkhtmltopdf, $html);
            if ($pdf !== '') {
                $this->sendPdfBytes($pdf, $filename);
            }
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Export PDF indisponible</title></head><body>'
            . '<h1>Export PDF indisponible</h1>'
            . '<p>Aucun moteur PDF serveur disponible : Dompdf absent et wkhtmltopdf introuvable ou en erreur.</p>'
            . '<p>Le rapport HTML ci-dessous est généré depuis TRAX/LRS et peut être imprimé en PDF depuis le navigateur.</p>'
            . $html
            . '</body></html>';
        exit;
    }

    private function sendPdfBytes(string $pdf, string $filename): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private function findWkhtmltopdfBinary(): string
    {
        foreach (['/usr/local/bin/wkhtmltopdf', '/usr/bin/wkhtmltopdf', '/bin/wkhtmltopdf', '/opt/wkhtmltopdf/bin/wkhtmltopdf'] as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }
        if (function_exists('shell_exec')) {
            $found = trim((string) @shell_exec('command -v wkhtmltopdf 2>/dev/null'));
            if ($found !== '' && is_file($found) && is_executable($found)) {
                return $found;
            }
        }
        return '';
    }

    private function renderPdfWithWkhtmltopdf(string $binary, string $html): string
    {
        $input = tempnam(sys_get_temp_dir(), 'itxeb_pdf_');
        $output = tempnam(sys_get_temp_dir(), 'itxeb_pdf_');
        if (!is_string($input) || !is_string($output)) {
            return '';
        }
        $htmlFile = $input . '.html';
        $pdfFile = $output . '.pdf';
        @unlink($input);
        @unlink($output);
        if (file_put_contents($htmlFile, $html) === false) {
            return '';
        }
        $cmd = escapeshellarg($binary)
            . ' --encoding utf-8 --quiet --disable-local-file-access '
            . escapeshellarg($htmlFile) . ' ' . escapeshellarg($pdfFile) . ' 2>&1';
        $result = 1;
        $lines = [];
        @exec($cmd, $lines, $result);
        $pdf = '';
        if ($result === 0 && is_file($pdfFile)) {
            $bytes = file_get_contents($pdfFile);
            if (is_string($bytes)) {
                $pdf = $bytes;
            }
        }
        @unlink($htmlFile);
        @unlink($pdfFile);
        return $pdf;
    }

    /** @param array<string,mixed> $course @param array<string,mixed> $dashboard */
    private function buildDashboardPdfHtml(array $course, array $dashboard): string
    {
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $title = (string) ($course['course_title'] ?? 'Cours');
        $resourceFilter = $this->getSelectedResourceRefId() > 0 ? (string) $this->getSelectedResourceRefId() : 'toutes';
        $typeFilter = $this->getSelectedObjectType() !== '' ? $this->getSelectedObjectType() : 'tous';
        $html = '<html><head><meta charset="utf-8"><style>'
            . 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#222}h1{font-size:20px;margin:0 0 6px}h2{font-size:15px;margin:18px 0 6px;border-bottom:1px solid #999;padding-bottom:3px}table{width:100%;border-collapse:collapse;margin:6px 0 12px}th,td{border:1px solid #bbb;padding:4px 5px;vertical-align:top}th{background:#eee}.small{font-size:9px;color:#555}.kpi td{width:25%}'
            . '#itxeb-course-ui-screen .itxeb-v012-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;border:2px solid #c8d6e5;background:#f8fbff;padding:12px 14px;margin:0 0 16px;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,.08)}#itxeb-course-ui-screen .itxeb-v012-header h1{font-size:28px;font-weight:700;margin:0 0 4px;line-height:1.2}#itxeb-course-ui-screen .itxeb-v012-header p{margin:0;color:#444}#itxeb-course-ui-screen .itxeb-v012-header-actions{white-space:nowrap;padding-top:3px}#itxeb-course-ui-screen .itxeb-v012-pdf{font-weight:700}#itxeb-course-ui-screen .itxeb-cui-section h2{font-size:24px;font-weight:700;border-bottom:2px solid #c8d6e5;padding-bottom:.4rem;margin-top:1.1rem}#itxeb-course-ui-screen .itxeb-cui-section h3{font-weight:700}#itxeb-course-ui-screen .itxeb-kpi-card,#itxeb-course-ui-screen .itxeb-pedagogy-summary{border:2px solid #c8d6e5;box-shadow:0 1px 4px rgba(0,0,0,.08)}#itxeb-course-ui-screen .itxeb-kpi-label{font-weight:700}#itxeb-course-ui-screen .itxeb-cui-table{border:2px solid #c8d6e5}#itxeb-course-ui-screen .itxeb-cui-table th{font-weight:700;border-bottom:2px solid #c8d6e5}#itxeb-course-ui-screen .itxeb-cui-analysis-table td:nth-child(2) small{font-size:13px;line-height:1.45;color:#333}#itxeb-course-ui-screen .itxeb-pedagogy-kpis .itxeb-kpi-card:nth-child(2){border-color:#f0ad4e;background:#fff4df}#itxeb-course-ui-screen .itxeb-pedagogy-kpis .itxeb-kpi-card:nth-child(2) .itxeb-kpi-label,#itxeb-course-ui-screen .itxeb-pedagogy-kpis .itxeb-kpi-card:nth-child(2) .itxeb-kpi-value{color:#8a5a00}#itxeb-course-ui-screen .itxeb-pedagogy-kpis .itxeb-kpi-card:nth-child(3){border-color:#d9534f;background:#fdeaea}#itxeb-course-ui-screen .itxeb-pedagogy-kpis .itxeb-kpi-card:nth-child(3) .itxeb-kpi-label,#itxeb-course-ui-screen .itxeb-pedagogy-kpis .itxeb-kpi-card:nth-child(3) .itxeb-kpi-value{color:#a94442}#itxeb-course-ui-screen .itxeb-pedagogy-critical{border:2px solid #a94442;background:#f2dede;color:#8a1f11}#itxeb-course-ui-screen .itxeb-pedagogy-watch{border:2px solid #8a6d3b;background:#fcf8e3;color:#684f1d}.itxeb-trainer-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin:12px 0}.itxeb-trainer-card{border-left:4px solid #337ab7}.itxeb-ai-markdown{border:1px solid #c8d6e5;background:#fff;padding:14px;border-radius:6px;line-height:1.55}.itxeb-ai-markdown h4{font-size:18px;margin:16px 0 8px;border-bottom:1px solid #d9e2ec;padding-bottom:4px}.itxeb-ai-markdown h5{font-size:15px;margin:12px 0 6px}.itxeb-ai-markdown ul{margin:6px 0 12px 22px}.itxeb-ai-markdown li{margin:4px 0}.itxeb-ai-table td:first-child{font-weight:700}.itxeb-ai-history table small{line-height:1.35}.itxeb-ai-history-detail{border:2px solid #c8d6e5;background:#f8fbff;padding:14px;margin-top:14px;border-radius:6px}.itxeb-ai-history-detail h4{margin-top:0}.itxeb-ai-history .btn-xs{padding:2px 7px;font-size:12px;line-height:1.4}</style></head><body>';
        $html .= '<h1>Rapport Suivi xAPI — ' . $this->esc($title) . '</h1>';
        $html .= '<p class="small">Source fonctionnelle : TRAX/LRS direct. Généré le ' . $this->esc(gmdate('Y-m-d H:i:s') . ' UTC') . '.</p>';
        $html .= '<h2>Contexte</h2><table><tbody>'
            . $this->row('course_ref_id', (string) ($course['course_ref_id'] ?? 0))
            . $this->row('Période', (string) $this->getPeriodDays() . ' jours')
            . $this->row('Filtre ressource', $resourceFilter)
            . $this->row('Filtre type', $typeFilter)
            . '</tbody></table>';
        $html .= '<h2>Synthèse</h2><table class="kpi"><tbody><tr>'
            . '<td><strong>Statements TRAX</strong><br>' . $this->esc((string) ($summary['total'] ?? 0)) . '</td>'
            . '<td><strong>Apprenants actifs</strong><br>' . $this->esc((string) ($summary['active_learners'] ?? 0)) . '</td>'
            . '<td><strong>Ressources utilisées</strong><br>' . $this->esc((string) ($summary['resources_with_traces'] ?? 0)) . ' / ' . $this->esc((string) ($summary['resources_total'] ?? 0)) . '</td>'
            . '<td><strong>Score moyen</strong><br>' . $this->esc(($summary['avg_score_raw'] ?? null) === null ? '-' : (string) $summary['avg_score_raw'] . ' %') . '</td>'
            . '</tr><tr>'
            . '<td><strong>Sans statement TRAX</strong><br>' . $this->esc((string) $this->countEnabledWithoutTraceResources($dashboard)) . '</td>'
            . '<td><strong>Pages LRS</strong><br>' . $this->esc((string) ($dashboard['pages'] ?? 0)) . '</td>'
            . '<td><strong>Tests réussis</strong><br>' . $this->esc((string) ($summary['tests_passed'] ?? 0)) . '</td>'
            . '<td><strong>Tests échoués</strong><br>' . $this->esc((string) ($summary['tests_failed'] ?? 0)) . '</td>'
            . '</tr></tbody></table>';
        $html .= $this->pdfSimpleCountTable('Activité par jour', is_array($dashboard['by_day'] ?? null) ? $dashboard['by_day'] : []);
        $verbItems = [];
        foreach ((array) ($dashboard['by_verb'] ?? []) as $verb) {
            $verbItems[(string) ($verb['label'] ?? '')] = (int) ($verb['count'] ?? 0);
        }
        $html .= $this->pdfSimpleCountTable('Actions xAPI', array_slice($verbItems, 0, 12, true));
        $html .= $this->pdfResourcesTable($dashboard);
        $html .= $this->pdfAiAnalysisSection($course);
        return $html . '</body></html>';
    }

    /** @param array<string,int> $items */
    private function pdfSimpleCountTable(string $title, array $items): string
    {
        $html = '<h2>' . $this->esc($title) . '</h2>';
        if (count($items) === 0) {
            return $html . '<p><em>Aucune donnée.</em></p>';
        }
        $html .= '<table><thead><tr><th>Libellé</th><th style="width:90px">Nombre</th></tr></thead><tbody>';
        foreach ($items as $label => $count) {
            $html .= '<tr><td>' . $this->esc((string) $label) . '</td><td>' . $this->esc((string) $count) . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }

    /** @param array<string,mixed> $dashboard */
    private function pdfResourcesTable(array $dashboard): string
    {
        $resources = is_array($dashboard['by_resource'] ?? null) ? $dashboard['by_resource'] : [];
        $html = '<h2>Ressources</h2>';
        if (count($resources) === 0) {
            return $html . '<p><em>Aucune ressource.</em></p>';
        }
        $html .= '<table><thead><tr><th>Ressource</th><th>Type</th><th>ref_id</th><th>Statements</th><th>Apprenants</th><th>Score moyen</th><th>Signal</th></tr></thead><tbody>';
        foreach (array_slice($resources, 0, 50) as $resource) {
            $score = ($resource['avg_score_raw'] ?? null) === null ? '-' : (string) $resource['avg_score_raw'] . ' %';
            $html .= '<tr><td>' . $this->esc((string) ($resource['title'] ?? '')) . '</td><td>' . $this->esc((string) ($resource['obj_type'] ?? '')) . '</td><td>' . $this->esc((string) ($resource['ref_id'] ?? 0)) . '</td><td>' . $this->esc((string) ($resource['traces'] ?? 0)) . '</td><td>' . $this->esc((string) ($resource['learners_count'] ?? 0)) . '</td><td>' . $this->esc($score) . '</td><td>' . $this->esc((string) ($resource['signal'] ?? '')) . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }
    /** @param array<string,mixed> $course */
    private function sendExpertCsv(array $course): void
    {
        $dashboard = $this->loadDashboard($course);
        $rows = is_array($dashboard['expert_rows'] ?? null) ? $dashboard['expert_rows'] : [];
        $resources = is_array($dashboard['by_resource'] ?? null) ? $dashboard['by_resource'] : [];
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $filterRefId = $this->getSelectedResourceRefId();
        $filename = 'itxeb_course_' . $courseRefId . ($filterRefId > 0 ? '_ref_' . $filterRefId : '') . '_expert_' . date('Ymd_His') . '.csv';

        $resourceByRefId = [];
        $resourceByTitle = [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $refId = (int) ($resource['ref_id'] ?? 0);
            if ($refId > 0) {
                $resourceByRefId[$refId] = $resource;
            }
            $title = trim((string) ($resource['title'] ?? ''));
            if ($title !== '') {
                $resourceByTitle[$title] = $resource;
            }
        }

        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        if ($out !== false) {
            fputcsv($out, [
                'date', 'course_ref_id', 'filter_ref_id', 'user_id',
                'verb_label', 'verb_id', 'resource_title', 'ref_id', 'obj_id', 'obj_type',
                'score_raw', 'completion', 'success', 'status', 'outbox_id', 'statement_uuid', 'last_error',
                'pedagogical_status', 'pedagogical_label', 'pedagogical_reason',
                'resource_failure_rate', 'resource_avg_score_raw', 'resource_traces', 'resource_learners_count',
                'resource_is_critical', 'resource_is_watch'
            ], ';');
            foreach ($rows as $row) {
                $refId = (int) ($row['ref_id'] ?? 0);
                $resourceTitle = (string) ($row['object_title'] ?? '');
                $resource = $refId > 0 && isset($resourceByRefId[$refId]) ? $resourceByRefId[$refId] : ($resourceByTitle[$resourceTitle] ?? []);
                $pedagogicalStatus = (string) ($resource['pedagogical_status'] ?? '');
                fputcsv($out, [
                    (string) ($row['created_at'] ?? ''),
                    (string) $courseRefId,
                    $filterRefId > 0 ? (string) $filterRefId : '',
                    (string) ($row['user_id'] ?? 0),
                    (string) ($row['verb_label'] ?? ''),
                    (string) ($row['verb_id'] ?? ''),
                    $resourceTitle,
                    (string) ($row['ref_id'] ?? 0),
                    (string) ($row['obj_id'] ?? 0),
                    (string) ($row['obj_type'] ?? ''),
                    $row['score_raw'] === null ? '' : (string) $row['score_raw'],
                    $this->nullableBoolLabel($row['completion'] ?? null),
                    $this->nullableBoolLabel($row['success'] ?? null),
                    (string) ($row['status'] ?? ''),
                    (string) ($row['outbox_id'] ?? 0),
                    (string) ($row['statement_uuid'] ?? ''),
                    (string) ($row['last_error'] ?? ''),
                    $pedagogicalStatus,
                    (string) ($resource['pedagogical_label'] ?? ''),
                    (string) ($resource['pedagogical_reason'] ?? ''),
                    is_numeric($resource['failure_rate'] ?? null) ? (string) $resource['failure_rate'] : '',
                    is_numeric($resource['avg_score_raw'] ?? null) ? (string) $resource['avg_score_raw'] : '',
                    (string) ($resource['traces'] ?? ''),
                    (string) ($resource['learners_count'] ?? ''),
                    $pedagogicalStatus === 'critical' ? 'oui' : 'non',
                    $pedagogicalStatus === 'watch' ? 'oui' : 'non',
                ], ';');
            }
            fclose($out);
        }
        exit;
    }
    /** @param array<string,mixed> $course */
    private function loadDashboard(array $course): array
    {
        // LRS primary source for xAPI tracking.
        // The local outbox is only the technical sending queue.
        if (!$this->lrsSummary) {
            return ['summary' => ['total' => 0, 'sent' => 0, 'failed' => 0, 'active_learners' => 0, 'resources_total' => 0, 'resources_with_traces' => 0, 'avg_score_raw' => null, 'tests_attempted' => 0, 'tests_passed' => 0, 'tests_failed' => 0], 'by_day' => [], 'by_verb' => [], 'by_status' => [], 'by_resource' => [], 'expert_rows' => [], 'available' => false, 'error' => 'Lecture TRAX/LRS indisponible.'];
        }
        return $this->lrsSummary->build($this->filterCourseResources($course), $this->getPeriodDays());
    }

    /** @param array<string,mixed> $course */
    private function filterCourseResources(array $course): array
    {
        $selectedRefId = $this->getSelectedResourceRefId();
        $selectedObjectType = $selectedRefId > 0 ? '' : $this->getSelectedObjectType();
        $resources = is_array($course['resources'] ?? null) ? $course['resources'] : [];

        if ($selectedRefId <= 0 && $selectedObjectType === '') {
            return $course;
        }

        $filtered = [];
        foreach ($resources as $resource) {
            if ($selectedRefId > 0 && (int) ($resource['ref_id'] ?? 0) !== $selectedRefId) {
                continue;
            }
            if ($selectedObjectType !== '' && (string) ($resource['obj_type'] ?? '') !== $selectedObjectType) {
                continue;
            }
            $filtered[] = $resource;
        }
        $course['resources'] = $filtered;
        return $course;
    }

    private function renderAnalyticsWarning(): string
    {
        if (!$this->analytics) {
            return '<div class="itxeb-cui-alert itxeb-cui-error">Lecture TRAX/LRS indisponible.</div>';
        }
        if (!$this->analytics->tableExists()) {
            return '<div class="itxeb-cui-alert itxeb-cui-error">Lecture TRAX/LRS indisponible.</div>';
        }
        return '';
    }

    /** @param array<string,mixed> $course */
    private function renderResourceFilter(array $course, string $cmd): string
    {
        $resources = is_array($course['resources'] ?? null) ? $course['resources'] : [];
        if (count($resources) === 0) {
            return '';
        }
        $selected = $this->getSelectedResourceRefId();
        $selectedObjectType = $selected > 0 ? '' : $this->getSelectedObjectType();
        $objectTypes = $this->availableObjectTypes($course);
        $typeDisabled = $selected > 0;
        $typeDisabledAttr = $typeDisabled ? ' disabled="disabled"' : '';
        $html = '<form class="itxeb-resource-filter" method="get" action="' . $this->esc($this->currentPath()) . '">'
            . $this->hiddenCurrentQuery(['itxeb_cui_cmd', 'itxeb_course_ref_id', 'itxeb_period_days', 'itxeb_filter_ref_id', 'itxeb_filter_obj_type'])
            . '<input type="hidden" name="itxeb_cui_cmd" value="' . $this->esc($cmd) . '">'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) ($course['course_ref_id'] ?? 0)) . '">'
            . '<input type="hidden" name="itxeb_period_days" value="' . $this->esc((string) $this->getPeriodDays()) . '">'
            . '<label><strong>Ressource :</strong> <select name="itxeb_filter_ref_id">'
            . '<option value="0"' . ($selected <= 0 ? ' selected="selected"' : '') . '>Toutes les ressources</option>';
        foreach ($resources as $resource) {
            $refId = (int) ($resource['ref_id'] ?? 0);
            if ($refId <= 0) {
                continue;
            }
            $label = trim((string) ($resource['title'] ?? ''));
            if ($label === '') {
                $label = 'ref_id ' . $refId;
            }
            $label .= ' — ' . (string) ($resource['obj_type'] ?? '') . ' — ref_id ' . $refId;
            $html .= '<option value="' . $this->esc((string) $refId) . '"' . ($selected === $refId ? ' selected="selected"' : '') . '>' . $this->esc($label) . '</option>';
        }
        $html .= '</select></label> <label class="itxeb-type-filter"><strong>Type :</strong> <select name="itxeb_filter_obj_type"' . $typeDisabledAttr . '>'
            . '<option value=""' . ($selectedObjectType === '' ? ' selected="selected"' : '') . '>Tous les types</option>';
        foreach ($objectTypes as $type => $label) {
            $html .= '<option value="' . $this->esc($type) . '"' . ($selectedObjectType === $type ? ' selected="selected"' : '') . '>' . $this->esc($label) . '</option>';
        }
        $html .= '</select></label>';
        if ($typeDisabled) {
            $html .= ' <small class="itxeb-filter-help">Type ignoré : une ressource précise est sélectionnée.</small>';
        }
        return $html . ' <button class="btn btn-default" type="submit">Filtrer</button></form>';
    }

    /** @param array<int,string> $excludedKeys */
    private function hiddenCurrentQuery(array $excludedKeys): string
    {
        $params = $this->currentQueryArray();
        $html = '';
        foreach ($params as $key => $value) {
            if (!is_string($key) || in_array($key, $excludedKeys, true) || !is_scalar($value)) {
                continue;
            }
            $html .= '<input type="hidden" name="' . $this->esc($key) . '" value="' . $this->esc((string) $value) . '">';
        }
        return $html;
    }

    /** @param array<string,mixed> $course */
    private function renderPeriodComparison(array $course): string
    {
        // LRS primary period comparison.
        if (!$this->lrsSummary) {
            return '';
        }
        $days = $this->getPeriodDays();
        if ($days > 180) {
            return '<section class="itxeb-cui-section"><h3>Comparaison entre périodes</h3><p><em>Comparaison non affichée pour 365 jours : la lecture LRS est limitée à 365 jours.</em></p></section>';
        }

        $extended = $this->lrsSummary->build($this->filterCourseResources($course), $days * 2);
        $byDay = is_array($extended['by_day'] ?? null) ? $extended['by_day'] : [];

        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        if ($todayStart === false) {
            return '';
        }
        $currentStart = (int) $todayStart - (($days - 1) * 86400);
        $previousStart = $currentStart - ($days * 86400);

        $currentTotal = 0;
        $previousTotal = 0;
        foreach ($byDay as $day => $count) {
            $dayTs = strtotime((string) $day . ' 00:00:00');
            if ($dayTs === false) {
                continue;
            }
            if ($dayTs >= $currentStart) {
                $currentTotal += (int) $count;
            } elseif ($dayTs >= $previousStart && $dayTs < $currentStart) {
                $previousTotal += (int) $count;
            }
        }

        $delta = $currentTotal - $previousTotal;
        $currentAverage = round($currentTotal / max(1, $days), 2);
        $previousAverage = round($previousTotal / max(1, $days), 2);
        $trend = $this->formatTrend($delta, $previousTotal);

        return '<section class="itxeb-cui-section"><h3>Comparaison entre périodes</h3>'
            . '<p>Comparaison du volume de statements TRAX de la période sélectionnée avec la période précédente de même durée.</p>'
            . '<table class="itxeb-cui-table itxeb-comparison-table"><thead><tr><th>Indicateur</th><th>Période actuelle</th><th>Période précédente</th><th>Évolution</th></tr></thead><tbody>'
            . '<tr><td>Statements xAPI</td><td>' . $this->esc((string) $currentTotal) . '</td><td>' . $this->esc((string) $previousTotal) . '</td><td>' . $this->esc($trend) . '</td></tr>'
            . '<tr><td>Moyenne/jour</td><td>' . $this->esc((string) $currentAverage) . '</td><td>' . $this->esc((string) $previousAverage) . '</td><td>' . $this->esc($this->formatSignedNumber(round($currentAverage - $previousAverage, 2))) . '</td></tr>'
            . '</tbody></table></section>';
    }

    private function formatTrend(int $delta, int $previousTotal): string
    {
        $absolute = $this->formatSignedNumber($delta);
        if ($previousTotal <= 0) {
            return $delta === 0 ? 'stable' : $absolute . ' trace(s)';
        }
        $percent = round(($delta / $previousTotal) * 100, 1);
        return $absolute . ' trace(s) (' . $this->formatSignedNumber($percent) . ' %)';
    }

    private function formatSignedNumber($value): string
    {
        if (!is_numeric($value)) {
            return '0';
        }
        $number = (float) $value;
        if (abs($number) < 0.0001) {
            return '0';
        }
        $formatted = (floor($number) == $number) ? (string) (int) $number : (string) $number;
        return $number > 0 ? '+' . $formatted : $formatted;
    }

    /** @param array<string,mixed> $dashboard */
    private function renderActivityByDay(array $dashboard): string
    {
        return $this->renderBarSection('Activité par jour', is_array($dashboard['by_day'] ?? null) ? $dashboard['by_day'] : []);
    }

    /** @param array<string,mixed> $dashboard */
    private function renderVerbDistribution(array $dashboard): string
    {
        $items = [];
        foreach ((array) ($dashboard['by_verb'] ?? []) as $verb) {
            $items[(string) ($verb['label'] ?? '')] = (int) ($verb['count'] ?? 0);
        }
        return $this->renderBarSection('Actions xAPI', array_slice($items, 0, 8, true));
    }

    /** @param array<string,mixed> $dashboard */
    private function renderTopResources(array $dashboard): string
    {
        $items = [];
        foreach ((array) ($dashboard['by_resource'] ?? []) as $stats) {
            if ((int) ($stats['traces'] ?? 0) > 0) {
                $items[(string) ($stats['title'] ?? ('ref_id ' . ($stats['ref_id'] ?? '')))] = (int) ($stats['traces'] ?? 0);
            }
        }
        return $this->renderBarSection('Top ressources', array_slice($items, 0, 10, true));
    }

    /** @param array<string,mixed> $dashboard */
    private function countEnabledWithoutTraceResources(array $dashboard): int
    {
        $count = 0;
        foreach ((array) ($dashboard['by_resource'] ?? []) as $stats) {
            if (!empty($stats['enabled']) && (int) ($stats['traces'] ?? 0) === 0) {
                $count++;
            }
        }
        return $count;
    }

    /** @param array<string,mixed> $dashboard */
    private function renderEnabledWithoutTraceResources(array $dashboard): string
    {
        $rows = [];
        foreach ((array) ($dashboard['by_resource'] ?? []) as $stats) {
            if (!empty($stats['enabled']) && (int) ($stats['traces'] ?? 0) === 0) {
                $rows[] = $stats;
            }
        }

        $html = '<section class="itxeb-cui-section"><h3>Ressources sans statement TRAX</h3>';
        if (count($rows) === 0) {
            return $html . '<p><em>Aucune ressource aucun statement TRAX pour la période et le filtre sélectionnés.</em></p></section>';
        }

        $html .= '<p>Ces ressources sont activées xAPI dans la configuration du cours, mais aucun statement TRAX n’a été générée sur la période sélectionnée.</p>'
            . '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-cui-watch-table"><thead><tr><th>Ressource</th><th>Type</th><th>ref_id</th><th>obj_id</th><th>Signal</th></tr></thead><tbody>';
        foreach ($rows as $stats) {
            $html .= '<tr><td><strong>' . $this->esc((string) ($stats['title'] ?? '')) . '</strong><br><small>' . $this->esc((string) ($stats['path'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc((string) ($stats['obj_type'] ?? '')) . '<br><small>' . $this->esc((string) ($stats['resource_family'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc((string) ($stats['ref_id'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($stats['obj_id'] ?? 0)) . '</td>'
                . '<td><span class="itxeb-signal">aucun statement TRAX</span></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    /** @param array<string,int> $items */
    private function renderBarSection(string $title, array $items): string
    {
        if (count($items) === 0) {
            return '<section class="itxeb-cui-section"><h3>' . $this->esc($title) . '</h3><p><em>Aucune donnée.</em></p></section>';
        }
        $max = max(array_map('intval', array_values($items)));
        $html = '<section class="itxeb-cui-section"><h3>' . $this->esc($title) . '</h3><div class="itxeb-bar-list">';
        foreach ($items as $label => $count) {
            $html .= $this->barRow((string) $label, (int) $count, $max);
        }
        return $html . '</div></section>';
    }

    private function renderInnerTabs(int $courseRefId, string $activeCmd): string
    {
        $tabs = ['showCourseDashboard' => 'Tableau de bord', 'showCourseAnalysis' => 'Analyse', 'showCourseExpert' => 'Expert', 'showCourseTracking' => 'Configuration'];
        $html = '<nav class="itxeb-inner-tabs">';
        foreach ($tabs as $cmd => $label) {
            $html .= '<a class="itxeb-inner-tab' . ($this->normalizeCommand($activeCmd) === $cmd ? ' itxeb-active' : '') . '" href="' . $this->esc($this->currentUrlWith(['itxeb_cui_cmd' => $cmd, 'itxeb_course_ref_id' => (string) $courseRefId])) . '">' . $this->esc($label) . '</a>';
        }
        return $html . '</nav>';
    }

    private function renderPeriodSelector(string $cmd): string
    {
        $html = '<div class="itxeb-period-selector"><strong>Période :</strong> ';
        foreach ([7, 30, 90, 365] as $days) {
            $html .= '<a class="itxeb-period-link' . ($this->getPeriodDays() === $days ? ' itxeb-active' : '') . '" href="' . $this->esc($this->currentUrlWith(['itxeb_cui_cmd' => $cmd, 'itxeb_period_days' => (string) $days])) . '">' . $days . ' jours</a> ';
        }
        return $html . '</div>';
    }

    private function metricCard(string $label, string $value, string $hint): string
    {
        return '<div class="itxeb-kpi-card"><div class="itxeb-kpi-label">' . $this->esc($label) . '</div><div class="itxeb-kpi-value">' . $this->esc($value) . '</div><div class="itxeb-kpi-hint">' . $this->esc($hint) . '</div></div>';
    }

    private function barRow(string $label, int $count, int $max): string
    {
        $width = $max > 0 ? max(2, min(100, (int) round(($count / $max) * 100))) : 2;
        return '<div class="itxeb-bar-row"><div class="itxeb-bar-label">' . $this->esc($label) . '</div><div class="itxeb-bar-track"><div class="itxeb-bar-fill" style="width:' . $width . '%"></div></div><div class="itxeb-bar-value">' . $this->esc((string) $count) . '</div></div>';
    }

    private function renderShell(string $content, int $courseRefId, string $courseTitle, string $cmd): string
    {
        $title = trim($courseTitle) !== '' ? 'Suivi xAPI — ' . $courseTitle : 'Suivi xAPI — configuration du cours';
        $normalizedCmd = $this->normalizeCommand($cmd);
        $subtitle = $normalizedCmd === 'showCourseTracking' ? 'Configuration xAPI depuis l’objet cours' : 'Feedback et analyse des traces xAPI du cours';
        $header = '<div class="itxeb-v012-header itxeb-v012-layout"><div class="itxeb-v012-header-title"><h1>' . $this->esc($title) . '</h1><p>' . $this->esc($subtitle) . ($courseRefId > 0 ? ' — course_ref_id ' . $this->esc((string) $courseRefId) : '') . '</p></div>';
        if ($normalizedCmd === 'showCourseDashboard' && $courseRefId > 0) {
            $pdfUrl = $this->currentUrlWith([
                'itxeb_cui_cmd' => 'exportCourseDashboardPdf',
                'itxeb_course_ref_id' => (string) $courseRefId,
                'itxeb_period_days' => (string) $this->getPeriodDays(),
                'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
            ]);
            $header .= '<div class="itxeb-v012-header-actions"><a class="btn btn-default itxeb-v012-pdf" href="' . $this->esc($pdfUrl) . '">Export PDF</a></div>';
        }
        $header .= '</div>';
        return $this->styles() . '<div id="itxeb-course-ui-screen">' . $header . $content . '</div>';
    }
    private function renderMessage(): string
    {
        if ($this->message === '') {
            return '';
        }
        $class = $this->messageType === 'success' ? 'itxeb-cui-success' : 'itxeb-cui-alert';
        return '<div class="itxeb-cui-alert ' . $class . '">' . $this->esc($this->message) . '</div>';
    }

    private function row(string $label, string $value): string
    {
        return '<tr><td><strong>' . $this->esc($label) . '</strong></td><td>' . $this->esc($value) . '</td></tr>';
    }

    private function getCommand(): string
    {
        $cmd = $this->requestValue($_POST, 'itxeb_cui_cmd');
        if ($cmd === '') {
            $cmd = $this->requestValue($_GET, 'itxeb_cui_cmd');
        }
        return $cmd !== '' ? $cmd : 'showCourseDashboard';
    }

    private function getCourseRefId(): int
    {
        foreach (['itxeb_course_ref_id', 'course_ref_id', 'ref_id'] as $key) {
            $value = $this->requestValue($_POST, $key);
            if ((int) $value > 0) {
                return (int) $value;
            }
            $value = $this->requestValue($_GET, $key);
            if ((int) $value > 0) {
                return (int) $value;
            }
        }
        return $this->bridge->detectCourseRefId();
    }

    /** @return array<int,int> */
    private function postIntArray(string $key): array
    {
        $raw = $this->requestRawValue($_POST, $key);
        if (!is_array($raw)) {
            return [];
        }
        $values = [];
        foreach ($raw as $value) {
            if (is_scalar($value) && (int) $value > 0) {
                $values[] = (int) $value;
            }
        }
        return array_values(array_unique($values));
    }

    /** @return array<int,string> */
    private function postStringArray(string $key): array
    {
        $raw = $this->requestRawValue($_POST, $key);
        if (!is_array($raw)) {
            return [];
        }
        $values = [];
        foreach ($raw as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $values[] = trim((string) $value);
            }
        }
        return array_values(array_unique($values));
    }

    private function postString(string $key): string
    {
        return trim($this->requestValue($_POST, $key));
    }

    private function requestValue($source, string $key): string
    {
        $value = $this->requestRawValue($source, $key);
        return is_scalar($value) ? (string) $value : '';
    }

    private function requestRawValue($source, string $key)
    {
        try {
            if (is_array($source)) {
                return $source[$key] ?? null;
            }
            if ($source instanceof ArrayAccess) {
                return isset($source[$key]) ? $source[$key] : null;
            }
            if (is_object($source) && method_exists($source, 'offsetExists') && method_exists($source, 'offsetGet')) {
                return $source->offsetExists($key) ? $source->offsetGet($key) : null;
            }
        } catch (Throwable $ignored) {
            return null;
        }
        return null;
    }

    private function getCurrentUserId(): int
    {
        try {
            if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'user')) {
                $user = $GLOBALS['DIC']->user();
                if (is_object($user) && method_exists($user, 'getId')) {
                    return (int) $user->getId();
                }
            }
        } catch (Throwable $ignored) {
        }
        return isset($GLOBALS['ilUser']) && is_object($GLOBALS['ilUser']) && method_exists($GLOBALS['ilUser'], 'getId') ? (int) $GLOBALS['ilUser']->getId() : 0;
    }

    private function currentRequestUri(): string
    {
        return isset($_SERVER['REQUEST_URI']) && is_scalar($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    }

    private function currentPath(): string
    {
        return (string) (parse_url($this->currentRequestUri(), PHP_URL_PATH) ?: '');
    }

    /** @return array<string,mixed> */
    private function currentQueryArray(): array
    {
        $query = (string) (parse_url($this->currentRequestUri(), PHP_URL_QUERY) ?: '');
        $current = [];
        if ($query !== '') {
            parse_str($query, $current);
        }
        return is_array($current) ? $current : [];
    }

    /** @param array<string,string> $params */
    private function currentUrlWith(array $params): string
    {
        // Stable xAPI support route: keep the ILIAS route that rendered the
        // current xAPI screen. With Delos this must remain the Info/showSummary
        // support route, otherwise inner tabs fall back to the course content.
        $current = $this->currentQueryArray();
        $current['baseClass'] = $current['baseClass'] ?? 'ilrepositorygui';

        $courseRefId = (int) ($params['itxeb_course_ref_id'] ?? 0);
        if ($courseRefId <= 0) {
            $courseRefId = $this->getCourseRefId();
        }
        if ($courseRefId > 0) {
            $current['ref_id'] = (string) $courseRefId;
            $current['itxeb_course_ref_id'] = (string) $courseRefId;
        }

        foreach ($params as $key => $value) {
            $current[$key] = $value;
        }

        return $this->currentPath() . '?' . http_build_query($current, '', '&');
    }

    private function getPeriodDays(): int
    {
        $value = (int) $this->requestValue($_GET, 'itxeb_period_days');
        if ($value <= 0) {
            $value = (int) $this->requestValue($_POST, 'itxeb_period_days');
        }
        return in_array($value, [7, 30, 90, 365], true) ? $value : 30;
    }

    private function getSelectedResourceRefId(): int
    {
        $value = (int) $this->requestValue($_GET, 'itxeb_filter_ref_id');
        if ($value <= 0) {
            $value = (int) $this->requestValue($_POST, 'itxeb_filter_ref_id');
        }
        return max(0, $value);
    }

    private function getSelectedObjectType(): string
    {
        if ($this->getSelectedResourceRefId() > 0) {
            return '';
        }
        $value = trim($this->requestValue($_GET, 'itxeb_filter_obj_type'));
        if ($value === '') {
            $value = trim($this->requestValue($_POST, 'itxeb_filter_obj_type'));
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $value)) {
            return '';
        }
        return $value;
    }

    private function getSelectedAiHistoryId(): string
    {
        $value = trim($this->requestValue($_GET, 'itxeb_ai_history_id'));
        if ($value === '') {
            $value = trim($this->requestValue($_POST, 'itxeb_ai_history_id'));
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $value)) {
            return '';
        }
        return $value;
    }

    /** @param array<string,mixed> $course @return array<string,string> */
    private function availableObjectTypes(array $course): array
    {
        $types = [];
        foreach ((array) ($course['resources'] ?? []) as $resource) {
            $type = (string) ($resource['obj_type'] ?? '');
            if ($type === '') {
                continue;
            }
            $label = $this->objectTypeLabel($type);
            $types[$type] = $label;
        }
        ksort($types);
        return $types;
    }

    private function objectTypeLabel(string $type): string
    {
        $labels = [
            'blog' => 'Blog',
            'file' => 'Fichier',
            'frm' => 'Forum',
            'htlm' => 'Page HTML',
            'lm' => 'Module d’apprentissage',
            'mcst' => 'MediaCast',
            'sahs' => 'SCORM / module',
            'tst' => 'Test',
            'webr' => 'Lien web',
            'wiki' => 'Wiki',
        ];
        return ($labels[$type] ?? strtoupper($type)) . ' (' . $type . ')';
    }

    private function normalizeCommand(string $cmd): string
    {
        return in_array($cmd, ['showCourseDashboard', 'showCourseAnalysis', 'showCourseExpert', 'exportCourseExpertCsv', 'exportCourseDashboardPdf'], true)
            ? ($cmd === 'exportCourseExpertCsv' ? 'showCourseExpert' : ($cmd === 'exportCourseDashboardPdf' ? 'showCourseDashboard' : $cmd))
            : 'showCourseTracking';
    }

    /** @return array<string,string> */
    private function dashboardWidgetDefinitions(): array
    {
        return [
            'comparison' => 'Comparaison entre périodes',
            'activity_by_day' => 'Activité par jour',
            'verb_distribution' => 'Actions xAPI',
            'top_resources' => 'Top ressources',
            'enabled_without_trace' => 'Ressources sans statement TRAX',
        ];
    }

    /** @return array<string,bool> */
    private function dashboardWidgets(int $courseRefId): array
    {
        $defaults = [];
        foreach ($this->dashboardWidgetDefinitions() as $key => $label) {
            $defaults[$key] = true;
        }
        if (!$this->repository) {
            return $defaults;
        }
        return array_merge($defaults, $this->repository->getDashboardWidgets($courseRefId));
    }

    private function nullableBoolLabel($value): string
    {
        if ($value === true) {
            return 'oui';
        }
        if ($value === false) {
            return 'non';
        }
        return '-';
    }

    private function formatSeconds($seconds): string
    {
        if (!is_numeric($seconds) || (int) $seconds <= 0) {
            return '-';
        }
        $seconds = (int) $seconds;
        if ($seconds < 60) {
            return $seconds . ' s';
        }
        if ($seconds < 3600) {
            return (int) round($seconds / 60) . ' min';
        }
        return intdiv($seconds, 3600) . ' h ' . intdiv($seconds % 3600, 60) . ' min';
    }

    private function shorten(string $value, int $max): string
    {
        $value = trim($value);
        return strlen($value) <= $max ? $value : substr($value, 0, max(0, $max - 3)) . '...';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function styles(): string
    {
        return '<style>#itxeb-course-ui-screen{margin:0;padding:0}#itxeb-course-ui-screen h1{font-size:24px;margin:.2rem 0 .3rem}#itxeb-course-ui-screen h2{font-size:18px;margin:1rem 0 .5rem}#itxeb-course-ui-screen h3{font-size:15px;margin:1rem 0 .5rem}#itxeb-course-ui-screen .itxeb-cui-section{margin-bottom:18px}#itxeb-course-ui-screen .itxeb-cui-alert{padding:.65rem .8rem;margin:.4rem 0 .9rem;border:1px solid #bce8f1;background:#eef8fc;border-radius:4px}#itxeb-course-ui-screen .itxeb-cui-error{border-color:#ebccd1;background:#f2dede;color:#a94442}#itxeb-course-ui-screen .itxeb-cui-success{border-color:#d6e9c6;background:#dff0d8;color:#3c763d}#itxeb-course-ui-screen .itxeb-cui-table{width:100%;border-collapse:collapse;background:#fff}#itxeb-course-ui-screen .itxeb-cui-table th,#itxeb-course-ui-screen .itxeb-cui-table td{border:1px solid #ddd;padding:.5rem .6rem;vertical-align:top;line-height:1.35}#itxeb-course-ui-screen .itxeb-pedagogy-summary{border:1px solid #ddd;background:#fff;margin:.7rem 0 1rem;padding:.75rem;border-radius:4px}#itxeb-course-ui-screen .itxeb-pedagogy-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.6rem;margin:.5rem 0}#itxeb-course-ui-screen .itxeb-pedagogy-lines{margin:.6rem 0 0 1.2rem}#itxeb-course-ui-screen .itxeb-pedagogy-badge{display:inline-block;padding:.25rem .45rem;border-radius:3px;font-weight:700;white-space:nowrap}#itxeb-course-ui-screen .itxeb-pedagogy-ok{background:#dff0d8;color:#3c763d}#itxeb-course-ui-screen .itxeb-pedagogy-watch{background:#fcf8e3;color:#8a6d3b}#itxeb-course-ui-screen .itxeb-pedagogy-critical{background:#f2dede;color:#a94442}#itxeb-course-ui-screen .itxeb-pedagogy-muted{background:#eee;color:#555}#itxeb-course-ui-screen .itxeb-cui-table th{background:#f7f7f7}#itxeb-course-ui-screen .itxeb-cui-table-wrapper{overflow-x:auto;background:#fff;border:1px solid #ddd;border-radius:4px}#itxeb-course-ui-screen .itxeb-cui-resource-table{min-width:1050px}#itxeb-course-ui-screen .itxeb-cui-analysis-table{min-width:1250px}#itxeb-course-ui-screen .itxeb-cui-expert-table{min-width:1450px}#itxeb-course-ui-screen .itxeb-cui-watch-table{min-width:900px}#itxeb-course-ui-screen .itxeb-struggling-table{min-width:1050px}#itxeb-course-ui-screen .itxeb-struggling-table{min-width:1050px}#itxeb-course-ui-screen .itxeb-comparison-table{max-width:900px}#itxeb-course-ui-screen .itxeb-inner-tabs{display:flex;gap:.35rem;flex-wrap:wrap;margin:1rem 0;border-bottom:1px solid #ddd}#itxeb-course-ui-screen .itxeb-inner-tab{display:inline-block;padding:.55rem .8rem;border:1px solid #ddd;border-bottom:0;background:#f7f7f7;text-decoration:none;border-radius:4px 4px 0 0}#itxeb-course-ui-screen .itxeb-inner-tab.itxeb-active{background:#fff;font-weight:bold;position:relative;top:1px}#itxeb-course-ui-screen .itxeb-period-selector{margin:.6rem 0 .5rem}#itxeb-course-ui-screen .itxeb-period-link{display:inline-block;margin-left:.35rem;padding:.25rem .45rem;border:1px solid #ddd;border-radius:4px;text-decoration:none;background:#f7f7f7}#itxeb-course-ui-screen .itxeb-period-link.itxeb-active{font-weight:bold;background:#fff}#itxeb-course-ui-screen .itxeb-resource-filter{margin:.5rem 0 1rem;padding:.55rem;border:1px solid #ddd;background:#fff;border-radius:4px}#itxeb-course-ui-screen .itxeb-resource-filter select{max-width:560px}#itxeb-course-ui-screen .itxeb-type-filter{display:inline-block;margin-left:.75rem}#itxeb-course-ui-screen .itxeb-filter-help{color:#666;margin-left:.35rem}#itxeb-course-ui-screen .itxeb-widget-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:.4rem;margin:.7rem 0}#itxeb-course-ui-screen .itxeb-widget-choice{display:block;border:1px solid #ddd;background:#fff;border-radius:4px;padding:.45rem .55rem}#itxeb-course-ui-screen .itxeb-export-button{margin:.2rem 0 .7rem}#itxeb-course-ui-screen .itxeb-kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;margin:1rem 0}#itxeb-course-ui-screen .itxeb-kpi-card{border:1px solid #ddd;border-radius:6px;background:#fff;padding:.8rem}#itxeb-course-ui-screen .itxeb-kpi-label{font-size:12px;text-transform:uppercase;color:#666}#itxeb-course-ui-screen .itxeb-kpi-value{font-size:24px;font-weight:bold;margin:.25rem 0}#itxeb-course-ui-screen .itxeb-kpi-hint{font-size:12px;color:#777}#itxeb-course-ui-screen .itxeb-bar-list{border:1px solid #ddd;border-radius:4px;background:#fff;padding:.6rem}#itxeb-course-ui-screen .itxeb-bar-row{display:grid;grid-template-columns:minmax(140px,260px) 1fr 50px;gap:.6rem;align-items:center;margin:.35rem 0}#itxeb-course-ui-screen .itxeb-bar-label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}#itxeb-course-ui-screen .itxeb-bar-track{height:14px;background:#eee;border-radius:10px;overflow:hidden}#itxeb-course-ui-screen .itxeb-bar-fill{height:14px;background:#777;border-radius:10px}#itxeb-course-ui-screen .itxeb-bar-value{text-align:right;font-weight:bold}#itxeb-course-ui-screen .itxeb-signal{display:inline-block;padding:.15rem .35rem;border:1px solid #ddd;border-radius:4px;background:#f7f7f7}#itxeb-course-ui-screen .itxeb-signal-warning{border-color:#f0ad4e;background:#fcf8e3;color:#8a6d3b;font-weight:bold}#itxeb-course-ui-screen .itxeb-signal-danger{border-color:#d9534f;background:#f2dede;color:#a94442;font-weight:bold}#itxeb-course-ui-screen .itxeb-v012-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;border:2px solid #c8d6e5;background:#f8fbff;padding:12px 14px;margin:0 0 16px;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,.08)}#itxeb-course-ui-screen .itxeb-v012-header h1{font-size:28px;font-weight:700;margin:0 0 4px;line-height:1.2}#itxeb-course-ui-screen .itxeb-v012-header p{margin:0;color:#444}#itxeb-course-ui-screen .itxeb-v012-header-actions{white-space:nowrap;padding-top:3px}#itxeb-course-ui-screen .itxeb-v012-pdf{font-weight:700}#itxeb-course-ui-screen .itxeb-cui-section h2{font-size:24px;font-weight:700;border-bottom:2px solid #c8d6e5;padding-bottom:.4rem;margin-top:1.1rem}#itxeb-course-ui-screen .itxeb-cui-section h3{font-weight:700}#itxeb-course-ui-screen .itxeb-kpi-card,#itxeb-course-ui-screen .itxeb-pedagogy-summary{border:2px solid #c8d6e5;box-shadow:0 1px 4px rgba(0,0,0,.08)}#itxeb-course-ui-screen .itxeb-kpi-label{font-weight:700}#itxeb-course-ui-screen .itxeb-cui-table{border:2px solid #c8d6e5}#itxeb-course-ui-screen .itxeb-cui-table th{font-weight:700;border-bottom:2px solid #c8d6e5}#itxeb-course-ui-screen .itxeb-cui-analysis-table td:nth-child(2) small{font-size:13px;line-height:1.45;color:#333}#itxeb-course-ui-screen .itxeb-pedagogy-kpis .itxeb-kpi-card:nth-child(2){border-color:#f0ad4e;background:#fff4df}#itxeb-course-ui-screen .itxeb-pedagogy-kpis .itxeb-kpi-card:nth-child(2) .itxeb-kpi-label,#itxeb-course-ui-screen .itxeb-pedagogy-kpis .itxeb-kpi-card:nth-child(2) .itxeb-kpi-value{color:#8a5a00}#itxeb-course-ui-screen .itxeb-pedagogy-kpis .itxeb-kpi-card:nth-child(3){border-color:#d9534f;background:#fdeaea}#itxeb-course-ui-screen .itxeb-pedagogy-kpis .itxeb-kpi-card:nth-child(3) .itxeb-kpi-label,#itxeb-course-ui-screen .itxeb-pedagogy-kpis .itxeb-kpi-card:nth-child(3) .itxeb-kpi-value{color:#a94442}#itxeb-course-ui-screen .itxeb-pedagogy-critical{border:2px solid #a94442;background:#f2dede;color:#8a1f11}#itxeb-course-ui-screen .itxeb-pedagogy-watch{border:2px solid #8a6d3b;background:#fcf8e3;color:#684f1d}.itxeb-trainer-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin:12px 0}.itxeb-trainer-card{border-left:4px solid #337ab7}.itxeb-ai-markdown{border:1px solid #c8d6e5;background:#fff;padding:14px;border-radius:6px;line-height:1.55}.itxeb-ai-markdown h4{font-size:18px;margin:16px 0 8px;border-bottom:1px solid #d9e2ec;padding-bottom:4px}.itxeb-ai-markdown h5{font-size:15px;margin:12px 0 6px}.itxeb-ai-markdown ul{margin:6px 0 12px 22px}.itxeb-ai-markdown li{margin:4px 0}.itxeb-ai-table td:first-child{font-weight:700}.itxeb-ai-history table small{line-height:1.35}.itxeb-ai-history-detail{border:2px solid #c8d6e5;background:#f8fbff;padding:14px;margin-top:14px;border-radius:6px}.itxeb-ai-history-detail h4{margin-top:0}.itxeb-ai-history .btn-xs{padding:2px 7px;font-size:12px;line-height:1.4}</style>';
    }
}
