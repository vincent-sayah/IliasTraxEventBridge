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
    private string $message = '';
    private string $messageType = 'info';

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
        $html = $this->renderMessage()
            . $this->renderInnerTabs($courseRefId, $cmd)
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
        if ($cmd === 'showCourseExpert') {
            return $this->renderExpert($course);
        }
        return $this->renderCourseSummary($course) . $this->renderConfigForm($course) . $this->renderBulkActions((int) ($course['course_ref_id'] ?? 0));
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
        return '<section class="itxeb-cui-section"><h2>Tableau de bord du cours</h2><p>Vue synthétique des traces xAPI générées par les ressources du cours.</p>'
            . $this->renderPeriodSelector('showCourseDashboard') . $this->renderAnalyticsWarning()
            . '<div class="itxeb-kpi-grid">'
            . $this->metricCard('Traces générées', (string) ($summary['total'] ?? 0), 'Volume xAPI')
            . $this->metricCard('Apprenants actifs', (string) ($summary['active_learners'] ?? 0), 'Comptage anonyme')
            . $this->metricCard('Ressources utilisées', (string) ($summary['resources_with_traces'] ?? 0) . ' / ' . (string) ($summary['resources_total'] ?? 0), 'Au moins une trace')
            . $this->metricCard('Envoyées TRAX', (string) ($summary['sent'] ?? 0), 'status sent')
            . $this->metricCard('En erreur', (string) ($summary['failed'] ?? 0), 'À vérifier')
            . $this->metricCard('Score moyen', $summary['avg_score_raw'] === null ? '-' : (string) $summary['avg_score_raw'] . ' %', 'Tests')
            . '</div>'
            . $this->renderActivityByDay($dashboard)
            . $this->renderVerbDistribution($dashboard)
            . $this->renderTopResources($dashboard)
            . $this->renderTechnicalStatus($dashboard)
            . '</section>';
    }

    /** @param array<string,mixed> $course */
    private function renderAnalysis(array $course): string
    {
        $dashboard = $this->loadDashboard($course);
        $resources = is_array($dashboard['by_resource'] ?? null) ? $dashboard['by_resource'] : [];
        $html = '<section class="itxeb-cui-section"><h2>Analyse des ressources</h2><p>Ressources utilisées, peu utilisées, activées sans trace ou associées à des erreurs.</p>' . $this->renderPeriodSelector('showCourseAnalysis') . $this->renderAnalyticsWarning();
        if (count($resources) === 0) {
            return $html . '<p><em>Aucune ressource traçable détectée.</em></p></section>';
        }
        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-cui-analysis-table"><thead><tr><th>Signal</th><th>Ressource</th><th>Type</th><th>xAPI</th><th>Traces</th><th>Apprenants</th><th>Dernière trace</th><th>Temps moyen</th><th>Score moyen</th><th>Tests</th></tr></thead><tbody>';
        foreach ($resources as $stats) {
            $testText = (int) ($stats['test_attempts'] ?? 0) > 0 ? (string) ($stats['test_passed'] ?? 0) . ' réussis / ' . (string) ($stats['test_failed'] ?? 0) . ' échoués' : '-';
            $score = $stats['avg_score_raw'] === null ? '-' : (string) $stats['avg_score_raw'] . ' %';
            $html .= '<tr><td><span class="itxeb-signal">' . $this->esc((string) ($stats['signal'] ?? '')) . '</span></td>'
                . '<td><strong>' . $this->esc((string) ($stats['title'] ?? '')) . '</strong><br><small>' . $this->esc((string) ($stats['path'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc((string) ($stats['obj_type'] ?? '')) . '<br><small>' . $this->esc((string) ($stats['resource_family'] ?? '')) . '</small></td>'
                . '<td>' . (!empty($stats['enabled']) ? 'activé' : 'désactivé') . '</td><td>' . $this->esc((string) ($stats['traces'] ?? 0)) . '</td><td>' . $this->esc((string) ($stats['learners_count'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($stats['last_at'] ?? '')) . '</td><td>' . $this->esc($this->formatSeconds($stats['avg_spent_seconds'] ?? null)) . '</td><td>' . $this->esc($score) . '</td><td>' . $this->esc($testText) . '</td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    /** @param array<string,mixed> $course */
    private function renderExpert(array $course): string
    {
        $dashboard = $this->loadDashboard($course);
        $rows = is_array($dashboard['expert_rows'] ?? null) ? $dashboard['expert_rows'] : [];
        $html = '<section class="itxeb-cui-section"><h2>Traces détaillées</h2><p>Vue support des 200 dernières traces locales du cours. Les identités sont limitées au user_id ILIAS.</p>' . $this->renderPeriodSelector('showCourseExpert') . $this->renderAnalyticsWarning();
        if (count($rows) === 0) {
            return $html . '<p><em>Aucune trace xAPI locale pour cette période.</em></p></section>';
        }
        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-cui-expert-table"><thead><tr><th>Date</th><th>User ID</th><th>Verbe</th><th>Ressource</th><th>Type</th><th>Score</th><th>Completion</th><th>Success</th><th>Status</th><th>Outbox</th><th>Erreur</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr><td>' . $this->esc((string) ($row['created_at'] ?? '')) . '</td><td>' . $this->esc((string) ($row['user_id'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($row['verb_label'] ?? '')) . '<br><small>' . $this->esc((string) ($row['verb_id'] ?? '')) . '</small></td>'
                . '<td><strong>' . $this->esc((string) ($row['object_title'] ?? '')) . '</strong><br><small>ref_id ' . $this->esc((string) ($row['ref_id'] ?? 0)) . '</small></td>'
                . '<td>' . $this->esc((string) ($row['obj_type'] ?? '')) . '</td><td>' . $this->esc($row['score_raw'] === null ? '-' : (string) $row['score_raw'] . ' %') . '</td>'
                . '<td>' . $this->esc($this->nullableBoolLabel($row['completion'] ?? null)) . '</td><td>' . $this->esc($this->nullableBoolLabel($row['success'] ?? null)) . '</td><td>' . $this->esc((string) ($row['status'] ?? '')) . '</td>'
                . '<td>#' . $this->esc((string) ($row['outbox_id'] ?? 0)) . '<br><small>' . $this->esc((string) ($row['statement_uuid'] ?? '')) . '</small></td><td><small>' . $this->esc($this->shorten((string) ($row['last_error'] ?? ''), 180)) . '</small></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    /** @param array<string,mixed> $course */
    private function loadDashboard(array $course): array
    {
        if (!$this->analytics) {
            return ['summary' => ['total' => 0, 'sent' => 0, 'failed' => 0, 'active_learners' => 0, 'resources_total' => 0, 'resources_with_traces' => 0, 'avg_score_raw' => null], 'by_day' => [], 'by_verb' => [], 'by_status' => [], 'by_resource' => [], 'expert_rows' => []];
        }
        return $this->analytics->buildForCourse($course, $this->getPeriodDays());
    }

    private function renderAnalyticsWarning(): string
    {
        if (!$this->analytics) {
            return '<div class="itxeb-cui-alert itxeb-cui-error">Classe analytics V0.9 indisponible.</div>';
        }
        if (!$this->analytics->tableExists()) {
            return '<div class="itxeb-cui-alert itxeb-cui-error">Table outbox absente : evnt_evhk_itxeb_out.</div>';
        }
        return '';
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

    /** @param array<string,mixed> $dashboard */
    private function renderTechnicalStatus(array $dashboard): string
    {
        $status = is_array($dashboard['by_status'] ?? null) ? $dashboard['by_status'] : [];
        return '<section class="itxeb-cui-section"><h3>État technique local</h3><table class="itxeb-cui-table"><tbody>'
            . $this->row('generated', (string) ($status['generated'] ?? 0))
            . $this->row('sending', (string) ($status['sending'] ?? 0))
            . $this->row('sent', (string) ($status['sent'] ?? 0))
            . $this->row('failed', (string) ($status['failed'] ?? 0))
            . $this->row('other', (string) ($status['other'] ?? 0))
            . '</tbody></table></section>';
    }

    private function renderInnerTabs(int $courseRefId, string $activeCmd): string
    {
        $tabs = ['showCourseTracking' => 'Configuration', 'showCourseDashboard' => 'Tableau de bord', 'showCourseAnalysis' => 'Analyse', 'showCourseExpert' => 'Expert'];
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
        $subtitle = $this->normalizeCommand($cmd) === 'showCourseTracking' ? 'Configuration xAPI depuis l’objet cours' : 'Feedback et analyse des traces xAPI du cours';
        return $this->styles() . '<div id="itxeb-course-ui-screen"><h1>' . $this->esc($title) . '</h1><p>' . $this->esc($subtitle) . ($courseRefId > 0 ? ' — course_ref_id ' . $this->esc((string) $courseRefId) : '') . '</p>' . $content . '</div>';
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
        return $cmd !== '' ? $cmd : 'showCourseTracking';
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

    /** @param array<string,string> $params */
    private function currentUrlWith(array $params): string
    {
        $uri = $this->currentRequestUri();
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '');
        $query = (string) (parse_url($uri, PHP_URL_QUERY) ?: '');
        $current = [];
        if ($query !== '') {
            parse_str($query, $current);
        }
        foreach ($params as $key => $value) {
            $current[$key] = $value;
        }
        return $path . '?' . http_build_query($current, '', '&');
    }

    private function getPeriodDays(): int
    {
        $value = (int) $this->requestValue($_GET, 'itxeb_period_days');
        if ($value <= 0) {
            $value = (int) $this->requestValue($_POST, 'itxeb_period_days');
        }
        return in_array($value, [7, 30, 90, 365], true) ? $value : 30;
    }

    private function normalizeCommand(string $cmd): string
    {
        return in_array($cmd, ['showCourseDashboard', 'showCourseAnalysis', 'showCourseExpert'], true) ? $cmd : 'showCourseTracking';
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
        return '<style>#itxeb-course-ui-screen{margin:0;padding:0}#itxeb-course-ui-screen h1{font-size:24px;margin:.2rem 0 .3rem}#itxeb-course-ui-screen h2{font-size:18px;margin:1rem 0 .5rem}#itxeb-course-ui-screen h3{font-size:15px;margin:1rem 0 .5rem}#itxeb-course-ui-screen .itxeb-cui-section{margin-bottom:18px}#itxeb-course-ui-screen .itxeb-cui-alert{padding:.65rem .8rem;margin:.4rem 0 .9rem;border:1px solid #bce8f1;background:#eef8fc;border-radius:4px}#itxeb-course-ui-screen .itxeb-cui-error{border-color:#ebccd1;background:#f2dede;color:#a94442}#itxeb-course-ui-screen .itxeb-cui-success{border-color:#d6e9c6;background:#dff0d8;color:#3c763d}#itxeb-course-ui-screen .itxeb-cui-table{width:100%;border-collapse:collapse;background:#fff}#itxeb-course-ui-screen .itxeb-cui-table th,#itxeb-course-ui-screen .itxeb-cui-table td{border:1px solid #ddd;padding:.5rem .6rem;vertical-align:top;line-height:1.35}#itxeb-course-ui-screen .itxeb-cui-table th{background:#f7f7f7}#itxeb-course-ui-screen .itxeb-cui-table-wrapper{overflow-x:auto;background:#fff;border:1px solid #ddd;border-radius:4px}#itxeb-course-ui-screen .itxeb-cui-resource-table{min-width:1050px}#itxeb-course-ui-screen .itxeb-cui-analysis-table{min-width:1250px}#itxeb-course-ui-screen .itxeb-cui-expert-table{min-width:1450px}#itxeb-course-ui-screen .itxeb-inner-tabs{display:flex;gap:.35rem;flex-wrap:wrap;margin:1rem 0;border-bottom:1px solid #ddd}#itxeb-course-ui-screen .itxeb-inner-tab{display:inline-block;padding:.55rem .8rem;border:1px solid #ddd;border-bottom:0;background:#f7f7f7;text-decoration:none;border-radius:4px 4px 0 0}#itxeb-course-ui-screen .itxeb-inner-tab.itxeb-active{background:#fff;font-weight:bold;position:relative;top:1px}#itxeb-course-ui-screen .itxeb-period-selector{margin:.6rem 0 1rem}#itxeb-course-ui-screen .itxeb-period-link{display:inline-block;margin-left:.35rem;padding:.25rem .45rem;border:1px solid #ddd;border-radius:4px;text-decoration:none;background:#f7f7f7}#itxeb-course-ui-screen .itxeb-period-link.itxeb-active{font-weight:bold;background:#fff}#itxeb-course-ui-screen .itxeb-kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;margin:1rem 0}#itxeb-course-ui-screen .itxeb-kpi-card{border:1px solid #ddd;border-radius:6px;background:#fff;padding:.8rem}#itxeb-course-ui-screen .itxeb-kpi-label{font-size:12px;text-transform:uppercase;color:#666}#itxeb-course-ui-screen .itxeb-kpi-value{font-size:24px;font-weight:bold;margin:.25rem 0}#itxeb-course-ui-screen .itxeb-kpi-hint{font-size:12px;color:#777}#itxeb-course-ui-screen .itxeb-bar-list{border:1px solid #ddd;border-radius:4px;background:#fff;padding:.6rem}#itxeb-course-ui-screen .itxeb-bar-row{display:grid;grid-template-columns:minmax(140px,260px) 1fr 50px;gap:.6rem;align-items:center;margin:.35rem 0}#itxeb-course-ui-screen .itxeb-bar-label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}#itxeb-course-ui-screen .itxeb-bar-track{height:14px;background:#eee;border-radius:10px;overflow:hidden}#itxeb-course-ui-screen .itxeb-bar-fill{height:14px;background:#777;border-radius:10px}#itxeb-course-ui-screen .itxeb-bar-value{text-align:right;font-weight:bold}#itxeb-course-ui-screen .itxeb-signal{display:inline-block;padding:.15rem .35rem;border:1px solid #ddd;border-radius:4px;background:#f7f7f7}</style>';
    }
}
