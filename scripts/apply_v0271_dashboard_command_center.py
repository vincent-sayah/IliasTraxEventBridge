#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
V0.27.1 — Tableau de bord pédagogique amélioré.

Base attendue côté serveur : V0.26.2 déjà appliquée et fonctionnelle.
Compatible Python 3.6.
Préflight complet avant écriture.

Évolutions :
- carte "État global du cours" ;
- jauge "Réussite du cours" avec icône diplôme ;
- entonnoir pédagogique ;
- actions recommandées ;
- matrice ressources ;
- mode d'affichage Tableau de bord : compact / standard / complet.
"""

import os
import re
import shutil
import subprocess
import sys
import tempfile
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ILIAS_ROOT = Path(os.environ.get("ILIAS_ROOT", "/var/www/ilias")).resolve()
LIVE_COMPANION = ILIAS_ROOT / "public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI"
BACKUP_DIR = Path(tempfile.gettempdir()) / ("itxeb_v0271_backup_" + datetime.now().strftime("%Y%m%d%H%M%S"))

SCREEN_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl"
LIVE_SCREEN = LIVE_COMPANION / "classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
MAIN_PLUGIN = ROOT / "plugin.php"
COMPANION_PLUGIN_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl"
LIVE_COMPANION_PLUGIN = LIVE_COMPANION / "plugin.php"


def fail(message):
    print("ERREUR: " + message, file=sys.stderr)
    sys.exit(1)


def read_file(path):
    if not path.is_file():
        fail("fichier introuvable: " + str(path))
    return path.read_text(encoding="utf-8")


def backup(path):
    if not path.is_file():
        return
    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    target = BACKUP_DIR / str(path).lstrip("/").replace("/", "__")
    shutil.copy2(str(path), str(target))


def write_file(path, content):
    backup(path)
    path.write_text(content, encoding="utf-8")


def method_bounds(content, method_name):
    needle = "private function " + method_name + "("
    start = content.find(needle)
    if start < 0:
        needle = "public function " + method_name + "("
        start = content.find(needle)
    if start < 0:
        fail("méthode introuvable: " + method_name)
    if content.find(needle, start + 1) >= 0:
        fail("méthode en double: " + method_name)
    brace = content.find("{", start)
    if brace < 0:
        fail("accolade introuvable: " + method_name)
    depth = 0
    i = brace
    while i < len(content):
        ch = content[i]
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                return start, i + 1
        i += 1
    fail("fin de méthode introuvable: " + method_name)


def replace_method(content, method_name, new_method):
    start, end = method_bounds(content, method_name)
    return content[:start] + new_method.rstrip() + content[end:]


def replace_once(content, search, replace, label):
    count = content.count(search)
    if count != 1:
        fail("point de remplacement introuvable ou multiple: " + label + " count=" + str(count))
    return content.replace(search, replace, 1)


def patch_version(content, new_version, label):
    updated, count = re.subn(r"\$version\s*=\s*'[^']+';", "$version = '" + new_version + "';", content, count=1)
    if count != 1:
        fail("version introuvable: " + label)
    return updated


DASHBOARD_WIDGET_DEFINITIONS_METHOD = r'''    /** @return array<string,string> */
    private function dashboardWidgetDefinitions(): array
    {
        return [
            '__mode_compact' => 'Mode compact — décision rapide',
            '__mode_standard' => 'Mode standard — suivi formateur recommandé',
            '__mode_full' => 'Mode complet — tous les blocs disponibles',
            'command_center' => 'État global du cours',
            'success_gauge' => 'Jauge réussite du cours',
            'learner_funnel' => 'Entonnoir pédagogique',
            'recommended_actions' => 'Actions recommandées',
            'resource_matrix' => 'Matrice ressources',
            'comparison' => 'Comparaison entre périodes',
            'activity_by_day' => 'Activité par jour',
            'verb_distribution' => 'Actions xAPI',
            'top_resources' => 'Top ressources',
            'enabled_without_trace' => 'Ressources sans statement TRAX',
        ];
    }
'''


DASHBOARD_WIDGETS_METHOD = r'''    /** @return array<string,bool> */
    private function dashboardWidgets(int $courseRefId): array
    {
        $defaults = $this->dashboardDefaultWidgets('standard');
        if (!$this->repository) {
            return $defaults;
        }
        $stored = $this->repository->getDashboardWidgets($courseRefId);
        $mode = $this->dashboardDisplayModeFromStored($stored);
        return array_merge($this->dashboardDefaultWidgets($mode), $stored);
    }
'''


DASHBOARD_MODE_METHODS = r'''    /** @return array<string,bool> */
    private function dashboardDefaultWidgets(string $mode): array
    {
        $all = [
            '__mode_compact' => false,
            '__mode_standard' => false,
            '__mode_full' => false,
            'command_center' => true,
            'success_gauge' => true,
            'learner_funnel' => true,
            'recommended_actions' => true,
            'resource_matrix' => true,
            'comparison' => true,
            'activity_by_day' => true,
            'verb_distribution' => true,
            'top_resources' => true,
            'enabled_without_trace' => true,
        ];

        if ($mode === 'compact') {
            $all['__mode_compact'] = true;
            $all['command_center'] = true;
            $all['success_gauge'] = true;
            $all['learner_funnel'] = true;
            $all['recommended_actions'] = true;
            $all['resource_matrix'] = false;
            $all['comparison'] = false;
            $all['activity_by_day'] = true;
            $all['verb_distribution'] = false;
            $all['top_resources'] = false;
            $all['enabled_without_trace'] = false;
            return $all;
        }

        if ($mode === 'full') {
            $all['__mode_full'] = true;
            return $all;
        }

        $all['__mode_standard'] = true;
        $all['verb_distribution'] = false;
        return $all;
    }

    private function dashboardDisplayModeFromStored(array $stored): string
    {
        if (!empty($stored['__mode_compact'])) {
            return 'compact';
        }
        if (!empty($stored['__mode_full'])) {
            return 'full';
        }
        return 'standard';
    }

    private function dashboardDisplayMode(int $courseRefId): string
    {
        if (!$this->repository) {
            return 'standard';
        }
        return $this->dashboardDisplayModeFromStored($this->repository->getDashboardWidgets($courseRefId));
    }

    private function dashboardModeLabel(string $mode): string
    {
        if ($mode === 'compact') {
            return 'Compact';
        }
        if ($mode === 'full') {
            return 'Complet';
        }
        return 'Standard';
    }
'''


SAVE_DASHBOARD_METHOD = r'''    /** @param array<string,mixed> $course */
    private function saveDashboardPreferences(array $course): void
    {
        // ITXEB V0.27.1 dashboard command center preferences.
        $requestedMode = $this->postString('dashboard_display_mode');
        if (!in_array($requestedMode, ['compact', 'standard', 'full'], true)) {
            $requestedMode = 'standard';
        }

        $widgets = $this->dashboardDefaultWidgets($requestedMode);
        $enabled = array_fill_keys($this->postStringArray('dashboard_widgets'), true);
        foreach ($this->dashboardWidgetDefinitions() as $key => $label) {
            if (strpos($key, '__mode_') === 0) {
                $widgets[$key] = $key === ('__mode_' . $requestedMode);
                continue;
            }
            $widgets[$key] = isset($enabled[$key]);
        }
        $this->repository->setDashboardWidgets((int) ($course['course_ref_id'] ?? 0), (int) ($course['course_obj_id'] ?? 0), $widgets, $this->getCurrentUserId());
        $this->message = 'Préférences du tableau de bord enregistrées.';
        $this->messageType = 'success';
    }
'''


DASHBOARD_PREF_FORM_METHOD = r'''    /** @param array<string,mixed> $course */
    private function renderDashboardPreferencesForm(array $course): string
    {
        // ITXEB V0.27.1 dashboard command center form.
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $widgets = $this->dashboardWidgets($courseRefId);
        $mode = $this->dashboardDisplayMode($courseRefId);
        $html = '<section class="itxeb-cui-section"><h2>Personnalisation du tableau de bord</h2>'
            . '<p>Choisir le mode de lecture et les blocs visibles dans l’onglet Tableau de bord pour ce cours.</p>'
            . '<form method="post" action="' . $this->esc($this->currentUrlWith(['itxeb_cui_cmd' => 'showCourseTracking', 'itxeb_course_ref_id' => (string) $courseRefId])) . '">'
            . '<input type="hidden" name="itxeb_cui_cmd" value="showCourseTracking">'
            . '<input type="hidden" name="itxeb_dashboard_save" value="1">'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
            . '<div class="itxeb-dashboard-mode-grid">';
        foreach (['compact' => 'Compact — décision rapide', 'standard' => 'Standard — suivi formateur recommandé', 'full' => 'Complet — tous les blocs disponibles'] as $modeKey => $label) {
            $html .= '<label class="itxeb-widget-choice itxeb-dashboard-mode-choice"><input type="radio" name="dashboard_display_mode" value="' . $this->esc($modeKey) . '"' . ($mode === $modeKey ? ' checked="checked"' : '') . '> <strong>' . $this->esc($label) . '</strong></label>';
        }
        $html .= '</div><h3>Blocs du tableau de bord</h3><div class="itxeb-widget-grid">';
        foreach ($this->dashboardWidgetDefinitions() as $key => $label) {
            if (strpos($key, '__mode_') === 0) {
                continue;
            }
            $html .= '<label class="itxeb-widget-choice"><input type="checkbox" name="dashboard_widgets[]" value="' . $this->esc($key) . '"' . (!empty($widgets[$key]) ? ' checked="checked"' : '') . '> ' . $this->esc($label) . '</label>';
        }
        return $html . '</div><p><button class="btn btn-default" type="submit">Enregistrer l’affichage du tableau de bord</button></p></form></section>';
    }
'''


RENDER_DASHBOARD_METHOD = r'''    /** @param array<string,mixed> $course */
    private function renderDashboard(array $course): string
    {
        // ITXEB V0.27.1 dashboard command center.
        $dashboard = $this->loadDashboard($course);
        $widgets = $this->dashboardWidgets((int) ($course['course_ref_id'] ?? 0));
        $mode = $this->dashboardModeLabel($this->dashboardDisplayMode((int) ($course['course_ref_id'] ?? 0)));
        $html = '<section class="itxeb-cui-section itxeb-dashboard-v027"><h2>Tableau de bord du cours</h2><p>Vue de décision rapide pour le formateur. Mode actuel : <strong>' . $this->esc($mode) . '</strong>.</p>'
            . $this->renderPeriodSelector('showCourseDashboard') . $this->renderResourceFilter($course, 'showCourseDashboard') . $this->renderAnalyticsWarning();

        if (!empty($widgets['command_center'])) {
            $html .= $this->renderDashboardCommandCenter($dashboard);
        }
        if (!empty($widgets['success_gauge'])) {
            $html .= $this->renderCourseSuccessGauge($dashboard);
        }
        if (!empty($widgets['learner_funnel'])) {
            $html .= $this->renderLearnerFunnel($dashboard);
        }
        if (!empty($widgets['recommended_actions'])) {
            $html .= $this->renderRecommendedActions($dashboard, $course);
        }
        $html .= $this->renderPedagogicalSynthesis($dashboard, $course);
        if (!empty($widgets['resource_matrix'])) {
            $html .= $this->renderResourceSignalMatrix($dashboard);
        }
        if ($this->shouldRenderQuestionFailureHotspots($course)) {
            $html .= $this->renderQuestionFailureHotspots($dashboard, $course);
        }
        if (!empty($widgets['comparison'])) {
            $html .= $this->renderPeriodComparison($course);
        }
        if (!empty($widgets['activity_by_day']) || !empty($widgets['top_resources'])) {
            $html .= $this->renderDashboardActivityTopLayout($dashboard, !empty($widgets['activity_by_day']), !empty($widgets['top_resources']));
        }
        if (!empty($widgets['verb_distribution'])) {
            $html .= $this->renderVerbDistribution($dashboard);
        }
        if (!empty($widgets['enabled_without_trace'])) {
            $html .= $this->renderEnabledWithoutTraceResources($dashboard);
        }
        return $html . '</section>';
    }
'''


RENDER_ANALYSIS_METHOD = r'''    /** @param array<string,mixed> $course */
    private function renderAnalysis(array $course): string
    {
        // ITXEB V0.27.1 analysis action dashboard.
        $dashboard = $this->loadDashboard($course);
        $resources = is_array($dashboard['by_resource'] ?? null) ? $dashboard['by_resource'] : [];
        $html = '<section class="itxeb-cui-section itxeb-trainer-page"><h2>Analyse formateur</h2><div style="border:2px solid #c8d6e5;background:#f8fbff;border-radius:6px;padding:12px 14px;margin:10px 0 14px"><strong>Mode d’emploi rapide</strong><ul style="margin:8px 0 0 18px"><li>Choisir la période de suivi.</li><li>Lire les actions recommandées et les signaux critiques.</li><li>Utiliser l’onglet Analyse IA pour générer ou comparer les synthèses IA.</li></ul></div><p style="color:#555">Vue opérationnelle des ressources utilisées, peu utilisées, activées sans trace ou associées à des signaux pédagogiques.</p>' . $this->renderPeriodSelector('showCourseAnalysis') . $this->renderResourceFilter($course, 'showCourseAnalysis') . $this->renderAnalyticsWarning() . $this->renderTrainerActionSummary($dashboard) . $this->renderRecommendedActions($dashboard, $course) . $this->renderPedagogicalSynthesis($dashboard, $course) . $this->renderResourceSignalMatrix($dashboard) . ($this->shouldRenderQuestionFailureHotspots($course) ? $this->renderQuestionFailureHotspots($dashboard, $course) : '') . $this->renderMediaCastMediaDashboard($dashboard);
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
'''


COMMAND_CENTER_METHODS = r'''    /** @param array<string,mixed> $dashboard */
    private function renderDashboardCommandCenter(array $dashboard): string
    {
        $state = $this->courseGlobalState($dashboard);
        $class = $state['level'] === 'critical' ? 'itxeb-command-critical' : ($state['level'] === 'watch' ? 'itxeb-command-watch' : 'itxeb-command-ok');
        return '<section class="itxeb-cui-section itxeb-command-center ' . $class . '"><h3>État global du cours</h3>'
            . '<div class="itxeb-command-card"><div class="itxeb-command-status"><span class="itxeb-command-icon">' . $this->esc($state['icon']) . '</span><div><strong>' . $this->esc($state['label']) . '</strong><p>' . $this->esc($state['reason']) . '</p></div></div>'
            . '<div class="itxeb-command-mini-grid">'
            . $this->miniIndicator('Réussite', $this->courseSuccessRateText($dashboard), 'Progression ILIAS')
            . $this->miniIndicator('Activité', $this->activityTrendText($dashboard), 'Période sélectionnée')
            . $this->miniIndicator('Critiques', (string) ((int) ($dashboard['pedagogy']['critical_count'] ?? 0)), 'Ressources')
            . $this->miniIndicator('À accompagner', (string) $this->countStrugglingLearnersForDashboard($dashboard), 'Apprenants')
            . '</div></div></section>';
    }

    private function miniIndicator(string $label, string $value, string $hint): string
    {
        return '<div class="itxeb-mini-indicator"><span>' . $this->esc($label) . '</span><strong>' . $this->esc($value) . '</strong><small>' . $this->esc($hint) . '</small></div>';
    }

    /** @param array<string,mixed> $dashboard @return array<string,string> */
    private function courseGlobalState(array $dashboard): array
    {
        $pedagogy = is_array($dashboard['pedagogy'] ?? null) ? $dashboard['pedagogy'] : [];
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $critical = (int) ($pedagogy['critical_count'] ?? 0);
        $watch = (int) ($pedagogy['watch_count'] ?? 0);
        $withoutTrace = (int) ($pedagogy['resources_without_trace'] ?? 0);
        $failed = (int) ($summary['tests_failed'] ?? 0);
        $struggling = $this->countStrugglingLearnersForDashboard($dashboard);
        $successRate = $this->courseSuccessRateValue($dashboard);

        if ($critical > 0 || $failed >= 3 || $struggling >= 3 || ($successRate !== null && $successRate < 50.0)) {
            return ['level' => 'critical', 'icon' => '🔴', 'label' => 'Priorité formateur', 'reason' => 'Des ressources, tests ou apprenants demandent une action rapide.'];
        }
        if ($watch > 0 || $withoutTrace > 0 || $failed > 0 || ($successRate !== null && $successRate < 70.0)) {
            return ['level' => 'watch', 'icon' => '🟠', 'label' => 'À surveiller', 'reason' => 'Le cours fonctionne mais plusieurs signaux méritent une vérification.'];
        }
        return ['level' => 'ok', 'icon' => '🟢', 'label' => 'Situation stable', 'reason' => 'Aucun signal pédagogique défavorable majeur sur les données disponibles.'];
    }

    /** @param array<string,mixed> $dashboard */
    private function renderCourseSuccessGauge(array $dashboard): string
    {
        $progress = is_array($dashboard['course_progress'] ?? null) ? $dashboard['course_progress'] : [];
        if (empty($progress['configured'])) {
            return '';
        }
        $rate = $this->courseSuccessRateValue($dashboard);
        $rateText = $rate === null ? '-' : (string) $rate . ' %';
        $width = $rate === null ? 0 : max(0, min(100, (float) $rate));
        return '<section class="itxeb-cui-section itxeb-success-gauge"><h3>Réussite du cours</h3>'
            . '<div class="itxeb-gauge-card"><div class="itxeb-gauge-title"><span class="itxeb-gauge-icon">🎓</span><div><strong>' . $this->esc($rateText) . '</strong><small>' . $this->esc((string) ($progress['hint'] ?? 'Progression ILIAS')) . '</small></div></div>'
            . '<div class="itxeb-gauge-track"><div class="itxeb-gauge-fill" style="width:' . $this->esc((string) $width) . '%"></div></div>'
            . '<div class="itxeb-gauge-detail"><span>Réussis : ' . $this->esc((string) ($progress['completed'] ?? 0)) . '</span><span>En cours : ' . $this->esc((string) ($progress['in_progress'] ?? 0)) . '</span><span>Échecs : ' . $this->esc((string) ($progress['failed'] ?? 0)) . '</span><span>Non commencés : ' . $this->esc((string) ($progress['not_attempted'] ?? 0)) . '</span></div>'
            . '<p><small>Source : progression ILIAS du cours, pas TRAX/xAPI.</small></p></div></section>';
    }

    /** @param array<string,mixed> $dashboard */
    private function renderLearnerFunnel(array $dashboard): string
    {
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $progress = is_array($dashboard['course_progress'] ?? null) ? $dashboard['course_progress'] : [];
        $registered = (int) ($progress['total'] ?? 0);
        $active = (int) ($summary['active_learners'] ?? 0);
        $attempted = (int) ($summary['tests_attempted'] ?? 0);
        $completed = (int) ($progress['completed'] ?? ($summary['tests_passed'] ?? 0));
        $max = max(1, $registered, $active, $attempted, $completed);
        $steps = [
            ['label' => 'Inscrits', 'value' => $registered, 'hint' => 'Progression ILIAS'],
            ['label' => 'Actifs', 'value' => $active, 'hint' => 'Traces sur période'],
            ['label' => 'Tentatives', 'value' => $attempted, 'hint' => 'Tests tentés'],
            ['label' => 'Réussites', 'value' => $completed, 'hint' => 'Cours réussi'],
        ];
        $html = '<section class="itxeb-cui-section itxeb-funnel"><h3>Entonnoir pédagogique</h3><p>Lecture rapide du passage entre inscription, activité, tentative et réussite.</p><div class="itxeb-funnel-list">';
        foreach ($steps as $step) {
            $width = round(((int) $step['value'] / $max) * 100, 1);
            $html .= '<div class="itxeb-funnel-row"><div class="itxeb-funnel-label"><strong>' . $this->esc((string) $step['label']) . '</strong><small>' . $this->esc((string) $step['hint']) . '</small></div><div class="itxeb-funnel-bar"><span style="width:' . $this->esc((string) $width) . '%"></span></div><div class="itxeb-funnel-value">' . $this->esc((string) $step['value']) . '</div></div>';
        }
        return $html . '</div></section>';
    }

    /** @param array<string,mixed> $dashboard @param array<string,mixed> $course */
    private function renderRecommendedActions(array $dashboard, array $course): string
    {
        $actions = $this->recommendedActions($dashboard);
        $html = '<section class="itxeb-cui-section itxeb-actions"><h3>Actions recommandées</h3>';
        if (count($actions) === 0) {
            return $html . '<p><em>Aucune action prioritaire détectée sur la période sélectionnée.</em></p></section>';
        }
        $html .= '<div class="itxeb-action-list">';
        foreach (array_slice($actions, 0, 5) as $action) {
            $html .= '<div class="itxeb-action-card itxeb-action-' . $this->esc((string) $action['level']) . '"><strong>' . $this->esc((string) $action['title']) . '</strong><p>' . $this->esc((string) $action['text']) . '</p><small>' . $this->esc((string) $action['hint']) . '</small></div>';
        }
        return $html . '</div></section>';
    }

    /** @param array<string,mixed> $dashboard @return array<int,array<string,string>> */
    private function recommendedActions(array $dashboard): array
    {
        $actions = [];
        $resources = is_array($dashboard['by_resource'] ?? null) ? $dashboard['by_resource'] : [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $title = trim((string) ($resource['title'] ?? ''));
            if ($title === '') {
                $title = 'Ressource ref_id ' . (string) ($resource['ref_id'] ?? '');
            }
            $status = (string) ($resource['pedagogical_status'] ?? '');
            $failure = is_numeric($resource['failure_rate'] ?? null) ? (float) $resource['failure_rate'] : null;
            if ($status === 'critical') {
                $actions[] = ['level' => 'critical', 'title' => 'Reprendre une ressource critique', 'text' => $title, 'hint' => (string) ($resource['pedagogical_reason'] ?? 'Signal critique')];
                continue;
            }
            if ($failure !== null && $failure >= 30.0) {
                $actions[] = ['level' => 'watch', 'title' => 'Analyser les échecs', 'text' => $title . ' — ' . $failure . ' % d’échec', 'hint' => 'Vérifier les questions et consignes du test.'];
                continue;
            }
            if (!empty($resource['enabled']) && (int) ($resource['traces'] ?? 0) <= 0) {
                $actions[] = ['level' => 'watch', 'title' => 'Vérifier une ressource sans activité', 'text' => $title, 'hint' => 'Ressource activée dans le suivi mais sans activité sur la période.'];
            }
        }

        $struggling = $this->countStrugglingLearnersForDashboard($dashboard);
        if ($struggling > 0) {
            $actions[] = ['level' => $struggling >= 3 ? 'critical' : 'watch', 'title' => 'Accompagner les apprenants en difficulté', 'text' => (string) $struggling . ' apprenant(s) avec échecs ou scores faibles.', 'hint' => 'Consulter le bloc Apprenants en difficulté dans Analyse.'];
        }

        usort($actions, static function (array $a, array $b): int {
            $rank = ['critical' => 2, 'watch' => 1, 'ok' => 0];
            return ($rank[(string) ($b['level'] ?? '')] ?? 0) <=> ($rank[(string) ($a['level'] ?? '')] ?? 0);
        });
        return $actions;
    }

    /** @param array<string,mixed> $dashboard */
    private function renderResourceSignalMatrix(array $dashboard): string
    {
        $resources = is_array($dashboard['by_resource'] ?? null) ? $dashboard['by_resource'] : [];
        $html = '<section class="itxeb-cui-section itxeb-resource-matrix"><h3>Matrice ressources</h3><p>Vue compacte des ressources par activité, réussite et signal pédagogique.</p>';
        if (count($resources) === 0) {
            return $html . '<p><em>Aucune ressource à afficher.</em></p></section>';
        }
        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-matrix-table"><thead><tr><th>Ressource</th><th>Activité</th><th>Réussite</th><th>Signal</th></tr></thead><tbody>';
        foreach (array_slice($resources, 0, 12) as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $traces = (int) ($resource['traces'] ?? 0);
            $learners = (int) ($resource['learners_count'] ?? 0);
            $score = $resource['avg_score_raw'] === null ? '-' : (string) $resource['avg_score_raw'] . ' %';
            $failure = is_numeric($resource['failure_rate'] ?? null) ? ' / échec ' . (string) $resource['failure_rate'] . ' %' : '';
            $status = (string) ($resource['pedagogical_status'] ?? '');
            $html .= '<tr><td><strong>' . $this->esc((string) ($resource['title'] ?? '')) . '</strong><br><small>' . $this->esc((string) ($resource['obj_type'] ?? '')) . '</small></td>'
                . '<td>' . $this->signalPill($traces > 0 ? 'ok' : 'watch', $traces > 0 ? ($traces . ' trace(s) / ' . $learners . ' apprenant(s)') : 'aucune activité') . '</td>'
                . '<td>' . $this->esc($score . $failure) . '</td>'
                . '<td>' . $this->signalPill($status, (string) ($resource['pedagogical_label'] ?? $resource['signal'] ?? '')) . '</td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    private function signalPill(string $status, string $label): string
    {
        $class = $status === 'critical' ? 'itxeb-signal-danger' : ($status === 'watch' ? 'itxeb-signal-warning' : 'itxeb-pedagogy-ok');
        return '<span class="itxeb-signal ' . $class . '">' . $this->esc($label === '' ? '-' : $label) . '</span>';
    }

    /** @param array<string,mixed> $dashboard */
    private function courseSuccessRateValue(array $dashboard): ?float
    {
        $progress = is_array($dashboard['course_progress'] ?? null) ? $dashboard['course_progress'] : [];
        return is_numeric($progress['success_rate'] ?? null) ? (float) $progress['success_rate'] : null;
    }

    /** @param array<string,mixed> $dashboard */
    private function courseSuccessRateText(array $dashboard): string
    {
        $value = $this->courseSuccessRateValue($dashboard);
        return $value === null ? '-' : (string) $value . ' %';
    }

    /** @param array<string,mixed> $dashboard */
    private function activityTrendText(array $dashboard): string
    {
        $byDay = is_array($dashboard['by_day'] ?? null) ? $dashboard['by_day'] : [];
        if (count($byDay) < 4) {
            return 'Données faibles';
        }
        ksort($byDay);
        $values = array_values(array_map('intval', $byDay));
        $half = (int) floor(count($values) / 2);
        $previous = array_sum(array_slice($values, 0, $half));
        $current = array_sum(array_slice($values, $half));
        if ($previous <= 0) {
            return $current > 0 ? 'En hausse' : 'Stable';
        }
        $delta = (($current - $previous) / $previous) * 100;
        if ($delta > 10) {
            return 'En hausse +' . (string) round($delta, 1) . ' %';
        }
        if ($delta < -10) {
            return 'En baisse ' . (string) round($delta, 1) . ' %';
        }
        return 'Stable';
    }

    /** @param array<string,mixed> $dashboard */
    private function countStrugglingLearnersForDashboard(array $dashboard): int
    {
        $rows = is_array($dashboard['expert_rows'] ?? null) ? $dashboard['expert_rows'] : [];
        $learners = [];
        foreach ($rows as $row) {
            if (!is_array($row) || (string) ($row['obj_type'] ?? '') !== 'tst') {
                continue;
            }
            $identity = trim((string) ($row['learner_identity'] ?? ($row['user_id'] ?? '')));
            if ($identity === '') {
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
            if (!isset($learners[$identity])) {
                $learners[$identity] = 0;
            }
            $learners[$identity]++;
        }
        return count(array_filter($learners, static function (int $alerts): bool { return $alerts >= 2; }));
    }
'''


STYLES_EXTRA = """#itxeb-course-ui-screen .itxeb-dashboard-mode-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.5rem;margin:.7rem 0 1rem}#itxeb-course-ui-screen .itxeb-dashboard-mode-choice{border-width:2px}#itxeb-course-ui-screen .itxeb-command-center .itxeb-command-card{border:2px solid #c8d6e5;background:#f8fbff;border-radius:10px;padding:14px;box-shadow:0 1px 4px rgba(0,0,0,.08)}#itxeb-course-ui-screen .itxeb-command-status{display:flex;gap:14px;align-items:flex-start;margin-bottom:12px}#itxeb-course-ui-screen .itxeb-command-icon{font-size:34px;line-height:1}#itxeb-course-ui-screen .itxeb-command-status strong{font-size:22px}#itxeb-course-ui-screen .itxeb-command-status p{margin:4px 0 0;color:#444}#itxeb-course-ui-screen .itxeb-command-mini-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px}#itxeb-course-ui-screen .itxeb-mini-indicator{border:1px solid #d9e2ec;background:#fff;border-radius:8px;padding:10px}#itxeb-course-ui-screen .itxeb-mini-indicator span,#itxeb-course-ui-screen .itxeb-mini-indicator small{display:block;color:#666}#itxeb-course-ui-screen .itxeb-mini-indicator strong{display:block;font-size:20px;margin:3px 0}#itxeb-course-ui-screen .itxeb-command-critical .itxeb-command-card{border-color:#d9534f;background:#fff5f5}#itxeb-course-ui-screen .itxeb-command-watch .itxeb-command-card{border-color:#f0ad4e;background:#fffaf0}#itxeb-course-ui-screen .itxeb-command-ok .itxeb-command-card{border-color:#5cb85c;background:#f4fff4}#itxeb-course-ui-screen .itxeb-gauge-card{border:2px solid #c8d6e5;background:#fff;border-radius:10px;padding:14px;box-shadow:0 1px 4px rgba(0,0,0,.08)}#itxeb-course-ui-screen .itxeb-gauge-title{display:flex;align-items:center;gap:12px}#itxeb-course-ui-screen .itxeb-gauge-icon{font-size:34px;line-height:1}#itxeb-course-ui-screen .itxeb-gauge-title strong{font-size:28px;display:block}#itxeb-course-ui-screen .itxeb-gauge-title small{display:block;color:#666}#itxeb-course-ui-screen .itxeb-gauge-track{height:18px;background:#eee;border-radius:12px;overflow:hidden;margin:14px 0 10px}#itxeb-course-ui-screen .itxeb-gauge-fill{height:18px;background:#337ab7;border-radius:12px}#itxeb-course-ui-screen .itxeb-gauge-detail{display:flex;flex-wrap:wrap;gap:8px}#itxeb-course-ui-screen .itxeb-gauge-detail span{border:1px solid #d9e2ec;border-radius:20px;padding:4px 9px;background:#f8fbff}#itxeb-course-ui-screen .itxeb-funnel-list{border:1px solid #d9e2ec;background:#fff;border-radius:10px;padding:12px}#itxeb-course-ui-screen .itxeb-funnel-row{display:grid;grid-template-columns:150px minmax(0,1fr) 60px;gap:10px;align-items:center;margin:8px 0}#itxeb-course-ui-screen .itxeb-funnel-label small{display:block;color:#666}#itxeb-course-ui-screen .itxeb-funnel-bar{height:22px;background:#eee;border-radius:12px;overflow:hidden}#itxeb-course-ui-screen .itxeb-funnel-bar span{display:block;height:22px;background:#777;border-radius:12px}#itxeb-course-ui-screen .itxeb-funnel-value{text-align:right;font-weight:700}#itxeb-course-ui-screen .itxeb-action-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px}#itxeb-course-ui-screen .itxeb-action-card{border:2px solid #d9e2ec;border-radius:9px;background:#fff;padding:12px}#itxeb-course-ui-screen .itxeb-action-card p{margin:6px 0}#itxeb-course-ui-screen .itxeb-action-card small{color:#666}#itxeb-course-ui-screen .itxeb-action-critical{border-color:#d9534f;background:#fff5f5}#itxeb-course-ui-screen .itxeb-action-watch{border-color:#f0ad4e;background:#fffaf0}#itxeb-course-ui-screen .itxeb-matrix-table{min-width:980px}/* ITXEB V0.27.1 dashboard command center styles */"""


def patch_screen(content):
    if "ITXEB V0.27.1 dashboard command center" in content:
        fail("V0.27.1 semble déjà appliquée")
    if "ITXEB V0.26.2 configurable synthesis cards" not in content:
        fail("base V0.26.2 non détectée dans CourseUIScreen")

    content = replace_method(content, "saveDashboardPreferences", SAVE_DASHBOARD_METHOD)
    content = replace_method(content, "renderDashboardPreferencesForm", DASHBOARD_PREF_FORM_METHOD)
    content = replace_method(content, "renderDashboard", RENDER_DASHBOARD_METHOD)
    content = replace_method(content, "renderAnalysis", RENDER_ANALYSIS_METHOD)
    content = replace_method(content, "dashboardWidgetDefinitions", DASHBOARD_WIDGET_DEFINITIONS_METHOD)
    content = replace_method(content, "dashboardWidgets", DASHBOARD_WIDGETS_METHOD)

    marker = "    private function nullableBoolLabel($value): string\n"
    if marker not in content:
        fail("point insertion dashboard mode methods introuvable")
    content = content.replace(marker, DASHBOARD_MODE_METHODS.rstrip() + "\n\n" + marker, 1)

    marker = "    private function metricCardWithIcon(string $label, string $value, string $hint, string $icon): string\n"
    if marker not in content:
        fail("point insertion command center methods introuvable")
    content = content.replace(marker, COMMAND_CENTER_METHODS.rstrip() + "\n\n" + marker, 1)

    # Le style() retourne une longue chaîne. On injecte les styles juste avant le marqueur V0.22.4 déjà présent.
    style_marker = "/* V0.22.4 alignment and AI tab fixes */"
    if style_marker not in content:
        fail("marqueur CSS V0.22.4 introuvable")
    content = content.replace(style_marker, STYLES_EXTRA + style_marker, 1)

    # S'assurer que l'icône réussite du cours de la synthèse est bien le diplôme.
    content = content.replace("'🏁'", "'🎓'")
    return content


def lint_php_memory(name, content):
    temp_dir = Path(tempfile.mkdtemp(prefix="itxeb_v0271_lint_"))
    temp_file = temp_dir / (name + ".php")
    temp_file.write_text(content, encoding="utf-8")
    proc = subprocess.run(["php", "-l", str(temp_file)], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, universal_newlines=True)
    print(proc.stdout.strip())
    shutil.rmtree(str(temp_dir), ignore_errors=True)
    if proc.returncode != 0:
        fail("lint PHP mémoire KO: " + name)


def lint_php_file(path):
    if not path.is_file():
        return
    proc = subprocess.run(["php", "-l", str(path)], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, universal_newlines=True)
    print(proc.stdout.strip())
    if proc.returncode != 0:
        fail("lint PHP KO: " + str(path))


print("V0.27.1 préflight: lecture fichiers")
screen = read_file(SCREEN_TEMPLATE)
main_plugin = read_file(MAIN_PLUGIN)
companion_plugin = read_file(COMPANION_PLUGIN_TEMPLATE)
live_companion_plugin = read_file(LIVE_COMPANION_PLUGIN) if LIVE_COMPANION_PLUGIN.is_file() else ""

print("V0.27.1 préflight: vérification base V0.26.2")
if "0.26.2-dev" not in main_plugin:
    fail("plugin principal pas en base attendue 0.26.2-dev")
if "0.8.46" not in companion_plugin:
    fail("plugin compagnon template pas en base attendue 0.8.46")
for method in ["saveDashboardPreferences", "renderDashboardPreferencesForm", "renderDashboard", "renderAnalysis", "dashboardWidgetDefinitions", "dashboardWidgets", "metricCardWithIcon", "nullableBoolLabel"]:
    method_bounds(screen, method)
for needle in ["ITXEB V0.26.2 configurable synthesis cards", "renderSynthesisCardsPreferencesForm", "synthesisCardDefinitions"]:
    if needle not in screen:
        fail("base V0.26.2 incomplète, marqueur absent: " + needle)

print("V0.27.1 préflight: calcul patchs mémoire")
screen2 = patch_screen(screen)
main_plugin2 = patch_version(main_plugin, "0.27.1-dev", "plugin principal")
companion_plugin2 = patch_version(companion_plugin, "0.8.47", "plugin compagnon template")
live_companion_plugin2 = patch_version(live_companion_plugin, "0.8.47", "plugin compagnon live") if live_companion_plugin else ""

print("V0.27.1 préflight: contrôles mémoire")
checks = [
    ("command center", "renderDashboardCommandCenter", screen2),
    ("gauge", "renderCourseSuccessGauge", screen2),
    ("funnel", "renderLearnerFunnel", screen2),
    ("actions", "renderRecommendedActions", screen2),
    ("matrix", "renderResourceSignalMatrix", screen2),
    ("mode compact", "__mode_compact", screen2),
    ("mode full", "__mode_full", screen2),
    ("styles", "ITXEB V0.27.1 dashboard command center styles", screen2),
    ("marker", "ITXEB V0.27.1 dashboard command center", screen2),
    ("diplome", "🎓", screen2),
    ("version main", "0.27.1-dev", main_plugin2),
    ("version companion", "0.8.47", companion_plugin2),
]
for label, needle, content in checks:
    if needle not in content:
        fail("contrôle mémoire KO: " + label)

print("V0.27.1 préflight: lint PHP mémoire")
lint_php_memory("CourseUIScreen", screen2)
lint_php_memory("plugin", main_plugin2)
lint_php_memory("companion_plugin", companion_plugin2)

print("V0.27.1 préflight OK: écriture fichiers")
write_file(SCREEN_TEMPLATE, screen2)
write_file(MAIN_PLUGIN, main_plugin2)
write_file(COMPANION_PLUGIN_TEMPLATE, companion_plugin2)
if LIVE_SCREEN.is_file():
    write_file(LIVE_SCREEN, screen2)
if LIVE_COMPANION_PLUGIN.is_file() and live_companion_plugin2:
    write_file(LIVE_COMPANION_PLUGIN, live_companion_plugin2)

print("V0.27.1 contrôle PHP fichiers écrits")
for path in [SCREEN_TEMPLATE, MAIN_PLUGIN, COMPANION_PLUGIN_TEMPLATE, LIVE_SCREEN, LIVE_COMPANION_PLUGIN]:
    lint_php_file(path)

print("V0.27.1 appliquée : tableau de bord pédagogique complet, configurable et plus lisible.")
print("Versions : plugin principal 0.27.1-dev / compagnon 0.8.47")
print("Backups: " + str(BACKUP_DIR))
