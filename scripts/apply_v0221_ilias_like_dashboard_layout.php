<?php
$root = getcwd();
$screen = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$plugin = $root . '/plugin.php';
$companionPlugin = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$servicesRoot = dirname(dirname(dirname($root)));
$liveRoot = $servicesRoot . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$liveScreen = $liveRoot . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = $liveRoot . '/plugin.php';

function v0221_read(string $file): string
{
    $s = file_get_contents($file);
    if (!is_string($s)) {
        fwrite(STDERR, "Lecture impossible: $file\n");
        exit(1);
    }
    return $s;
}

function v0221_write(string $file, string $old, string $new): void
{
    if ($old !== $new) {
        file_put_contents($file, $new);
        echo "WRITE: $file\n";
    } else {
        echo "OK: aucun changement $file\n";
    }
}

function v0221_set_version(string $file, string $version): void
{
    if (!is_file($file)) {
        return;
    }
    $old = v0221_read($file);
    $replacement = '$version = ' . "'" . $version . "'" . ';';
    $new = preg_replace('/\$version = \'[^\']*\';/', $replacement, $old, 1);
    if (!is_string($new)) {
        fwrite(STDERR, "Version impossible: $file\n");
        exit(1);
    }
    v0221_write($file, $old, $new);
}

function v0221_replace_method(string &$s, string $methodName, string $newMethod): void
{
    if (strpos($s, $newMethod) !== false) {
        echo "OK: méthode $methodName\n";
        return;
    }
    $needle = 'private function ' . $methodName . '(';
    $pos = strpos($s, $needle);
    if ($pos === false) {
        echo "SKIP: méthode introuvable $methodName\n";
        return;
    }
    $lineStart = strrpos(substr($s, 0, $pos), "\n");
    $start = $lineStart === false ? 0 : $lineStart + 1;
    $brace = strpos($s, '{', $pos);
    if ($brace === false) {
        fwrite(STDERR, "Accolade introuvable pour $methodName\n");
        exit(1);
    }
    $depth = 0;
    $len = strlen($s);
    $end = null;
    for ($i = $brace; $i < $len; $i++) {
        $ch = $s[$i];
        if ($ch === '{') {
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                $end = $i + 1;
                if ($end < $len && $s[$end] === "\r") { $end++; }
                if ($end < $len && $s[$end] === "\n") { $end++; }
                break;
            }
        }
    }
    if ($end === null) {
        fwrite(STDERR, "Fin méthode introuvable pour $methodName\n");
        exit(1);
    }
    $s = substr($s, 0, $start) . $newMethod . "\n" . substr($s, $end);
    echo "PATCH: méthode $methodName\n";
}

function v0221_insert_helper(string &$s): void
{
    if (strpos($s, 'private function renderIliasLikeRow(') !== false) {
        echo "OK: helper renderIliasLikeRow\n";
        return;
    }
    $needle = '    private function row(string $label, string $value): string';
    $pos = strpos($s, $needle);
    if ($pos === false) {
        fwrite(STDERR, "Point insertion renderIliasLikeRow introuvable\n");
        exit(1);
    }
    $helper = <<<'PHP'
    private function renderIliasLikeRow(string $label, string $content, string $hint = ''): string
    {
        $labelHtml = $this->esc($label);
        if ($hint !== '') {
            $labelHtml .= '<span class="itxeb-ilias-hint">' . $this->esc($hint) . '</span>';
        }
        return '<div class="itxeb-ilias-row"><div class="itxeb-ilias-label">' . $labelHtml . '</div><div class="itxeb-ilias-value">' . $content . '</div></div>';
    }

PHP;
    $s = substr($s, 0, $pos) . $helper . substr($s, $pos);
    echo "PATCH: helper renderIliasLikeRow\n";
}

function v0221_insert_css(string &$s): void
{
    if (strpos($s, 'itxeb-ilias-row') !== false) {
        echo "OK: CSS ILIAS-like\n";
        return;
    }
    $css = '#itxeb-course-ui-screen .itxeb-ilias-like-section{border-top:1px solid #ddd;padding-top:8px}'
        . '#itxeb-course-ui-screen .itxeb-ilias-row{display:grid;grid-template-columns:260px minmax(0,1fr);gap:18px;border-top:1px solid #e5e5e5;padding:12px 0;align-items:start}'
        . '#itxeb-course-ui-screen .itxeb-ilias-row:first-of-type{border-top:0}'
        . '#itxeb-course-ui-screen .itxeb-ilias-label{font-weight:600;color:#333;line-height:1.35;padding-top:6px}'
        . '#itxeb-course-ui-screen .itxeb-ilias-value{min-width:0}'
        . '#itxeb-course-ui-screen .itxeb-ilias-value>p:first-child{margin-top:0}'
        . '#itxeb-course-ui-screen .itxeb-ilias-value .itxeb-kpi-grid{margin-top:0}'
        . '#itxeb-course-ui-screen .itxeb-ilias-value .itxeb-bar-list{margin-top:0}'
        . '#itxeb-course-ui-screen .itxeb-ilias-value .itxeb-cui-table-wrapper{margin-top:0}'
        . '#itxeb-course-ui-screen .itxeb-ilias-hint{display:block;font-weight:400;color:#777;font-size:12px;margin-top:3px}'
        . '@media(max-width:900px){#itxeb-course-ui-screen .itxeb-ilias-row{grid-template-columns:1fr;gap:6px}#itxeb-course-ui-screen .itxeb-ilias-label{padding-top:0}}';
    $needle = "</style>';";
    $pos = strrpos($s, $needle);
    if ($pos === false) {
        fwrite(STDERR, "Point insertion CSS introuvable\n");
        exit(1);
    }
    $s = substr($s, 0, $pos) . $css . substr($s, $pos);
    echo "PATCH: CSS ILIAS-like\n";
}

foreach ([$screen, $plugin, $companionPlugin] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Fichier absent: $file\n");
        exit(1);
    }
}

$old = v0221_read($screen);
$s = $old;

v0221_insert_css($s);
v0221_insert_helper($s);

$renderDashboard = <<<'PHP'
    /** @param array<string,mixed> $course */
    private function renderDashboard(array $course): string
    {
        $dashboard = $this->loadDashboard($course);
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $widgets = $this->dashboardWidgets((int) ($course['course_ref_id'] ?? 0));
        $filters = $this->renderPeriodSelector('showCourseDashboard') . $this->renderResourceFilter($course, 'showCourseDashboard') . $this->renderAnalyticsWarning();
        $kpis = '<div class="itxeb-kpi-grid">'
            . $this->metricCard('Statements TRAX', (string) ($summary['total'] ?? 0), 'Lecture LRS')
            . $this->metricCard('Apprenants actifs', (string) ($summary['active_learners'] ?? 0), 'Comptage anonyme')
            . $this->metricCard('Ressources utilisées', (string) ($summary['resources_with_traces'] ?? 0) . ' / ' . (string) ($summary['resources_total'] ?? 0), 'Au moins une trace')
            . $this->metricCard('Sans statement TRAX', (string) $this->countEnabledWithoutTraceResources($dashboard), 'À surveiller')
            . $this->metricCard('Pages LRS', (string) ($dashboard['pages'] ?? 0), 'pagination')
            . $this->metricCard('Critiques', (string) ($dashboard['pedagogy']['critical_count'] ?? 0), 'Priorité')
            . $this->metricCard('À surveiller', (string) ($dashboard['pedagogy']['watch_count'] ?? 0), 'Signal pédagogique')
            . $this->metricCard('Score moyen', $summary['avg_score_raw'] === null ? '-' : (string) $summary['avg_score_raw'] . ' %', 'Tests')
            . '</div>';

        $html = '<section class="itxeb-cui-section itxeb-ilias-like-section"><h2>Tableau de bord du cours</h2><p>Vue synthétique des statements xAPI présents dans TRAX pour ce cours.</p>'
            . $this->renderIliasLikeRow('Filtres', $filters, 'Période et ressource suivie')
            . $this->renderIliasLikeRow('Indicateurs clés', $kpis, 'Résumé de la période sélectionnée')
            . '</section>'
            . $this->renderPedagogicalSynthesis($dashboard)
            . $this->renderQuestionFailureHotspots($dashboard, $course);
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
        return $html;
    }
PHP;

$renderPedagogicalSynthesis = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderPedagogicalSynthesis(array $dashboard): string
    {
        $pedagogy = is_array($dashboard['pedagogy'] ?? null) ? $dashboard['pedagogy'] : [];
        $lines = is_array($pedagogy['synthesis_lines'] ?? null) ? $pedagogy['synthesis_lines'] : [];
        $content = '<div class="itxeb-pedagogy-summary"><div class="itxeb-pedagogy-kpis">'
            . $this->metricCard('OK', (string) ($pedagogy['ok_count'] ?? 0), 'Ressources sans signal')
            . $this->metricCard('À surveiller', (string) ($pedagogy['watch_count'] ?? 0), 'Signal faible')
            . $this->metricCard('Critiques', (string) ($pedagogy['critical_count'] ?? 0), 'Priorité')
            . $this->metricCard('Sans trace', (string) ($pedagogy['resources_without_trace'] ?? 0), 'Sans statement TRAX')
            . '</div>';
        if (count($lines) > 0) {
            $content .= '<ul class="itxeb-pedagogy-lines">';
            foreach ($lines as $line) {
                if (is_scalar($line) && trim((string) $line) !== '') {
                    $content .= '<li>' . $this->esc((string) $line) . '</li>';
                }
            }
            $content .= '</ul>';
        }
        $content .= '</div>';
        return '<section class="itxeb-cui-section itxeb-ilias-like-section"><h3>Synthèse pédagogique</h3>'
            . $this->renderIliasLikeRow('Résumé', $content, 'Indicateurs et signaux de la période')
            . '</section>';
    }
PHP;

$renderBarSection = <<<'PHP'
    /** @param array<string,int> $items */
    private function renderBarSection(string $title, array $items): string
    {
        $section = '<section class="itxeb-cui-section itxeb-ilias-like-section"><h3>' . $this->esc($title) . '</h3>';
        if (count($items) === 0) {
            return $section . $this->renderIliasLikeRow('Données', '<p><em>Aucune donnée.</em></p>') . '</section>';
        }
        $max = max(array_map('intval', array_values($items)));
        $content = '<div class="itxeb-bar-list">';
        foreach ($items as $label => $count) {
            $content .= $this->barRow((string) $label, (int) $count, $max);
        }
        return $section . $this->renderIliasLikeRow('Données', $content . '</div>') . '</section>';
    }
PHP;

$renderQuestionFailureHotspots = <<<'PHP'
    /** @param array<string,mixed> $dashboard @param array<string,mixed> $course */
    private function renderQuestionFailureHotspots(array $dashboard, array $course): string
    {
        $risks = is_array($dashboard['question_risks'] ?? null) ? $dashboard['question_risks'] : [];
        if ($risks === []) {
            $path = $this->bridge->getMainPluginPath() . '/classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php';
            if (is_file($path)) {
                require_once $path;
            }
            if (class_exists('ilIliasTraxEventBridgeQuestionRiskRepository')) {
                $allowedRefIds = [];
                foreach ((array) ($course['resources'] ?? []) as $resource) {
                    if (is_array($resource) && (string) ($resource['obj_type'] ?? '') === 'tst') {
                        $rid = (int) ($resource['ref_id'] ?? 0);
                        if ($rid > 0) { $allowedRefIds[] = $rid; }
                    }
                }
                try {
                    $risks = (new ilIliasTraxEventBridgeQuestionRiskRepository())->build($this->getPeriodDays(), $allowedRefIds, $this->getSelectedResourceRefId());
                } catch (Throwable $ignored) {
                    $risks = [];
                }
            }
        }

        $html = '<section class="itxeb-cui-section itxeb-question-risks itxeb-ilias-like-section"><h3>Questions à fort taux d’échec</h3>';
        if (count($risks) === 0) {
            return $html . $this->renderIliasLikeRow('Questions', '<p><em>Aucune question à fort taux d’échec détectée sur la période sélectionnée.</em></p>') . '</section>';
        }
        $content = '<p>Seules les questions problématiques sont remontées ici. Toutes les questions restent tracées dans TRAX et visibles côté Expert.</p>';
        $content .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr>'
            . '<th>Priorité</th><th>Question</th><th>Test</th><th>Réponses</th><th>Échecs / non-réponses</th><th>Taux d’échec</th><th>Score moyen</th><th>Dernière trace</th>'
            . '</tr></thead><tbody>';
        foreach (array_slice($risks, 0, 10) as $risk) {
            if (!is_array($risk)) { continue; }
            $avg = ($risk['avg_score'] ?? null) === null ? '-' : (string) $risk['avg_score'] . ' %';
            $failure = is_numeric($risk['failure_rate'] ?? null) ? (string) $risk['failure_rate'] . ' %' : '-';
            $label = (string) ($risk['risk_label'] ?? 'À surveiller');
            $class = $label === 'Critique' ? 'itxeb-pedagogy-critical' : 'itxeb-pedagogy-watch';
            $content .= '<tr>'
                . '<td><span class="itxeb-pedagogy-badge ' . $class . '">' . $this->esc($label) . '</span></td>'
                . '<td><strong>' . $this->esc((string) ($risk['question_title'] ?? '')) . '</strong><br><small>Question ' . $this->esc((string) ($risk['question_id'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc((string) ($risk['test_title'] ?? '')) . '<br><small>ref_id ' . $this->esc((string) ($risk['ref_id'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc((string) ($risk['attempts'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) (((int) ($risk['failed'] ?? 0)) + ((int) ($risk['unanswered'] ?? 0)))) . '</td>'
                . '<td>' . $this->esc($failure) . '</td>'
                . '<td>' . $this->esc($avg) . '</td>'
                . '<td>' . $this->esc((string) ($risk['last_at'] ?? '')) . '</td>'
                . '</tr>';
        }
        $content .= '</tbody></table></div>';
        return $html . $this->renderIliasLikeRow('Questions', $content, 'Questions problématiques détectées') . '</section>';
    }
PHP;

$renderActivityTimeline = <<<'PHP'
    /** @param array<string,int|float|string> $byDay */
    private function renderActivityTimeline(array $byDay): string
    {
        $periodDays = max(1, min(365, $this->getPeriodDays()));
        $mode = $this->getActivityTimelineMode($periodDays);
        $daily = $this->normalizeActivityDays($byDay, $periodDays);
        $total = array_sum(array_map('intval', array_values($daily)));

        $html = '<section class="itxeb-cui-section itxeb-activity-timeline itxeb-ilias-like-section"><h3>Activité dans le temps</h3>';
        $controls = '<p>Vue compacte de l’activité du cours. Le détail complet reste disponible sans occuper toute la page.</p>' . $this->renderActivityTimelineSelector($mode);
        $html .= $this->renderIliasLikeRow('Affichage', $controls, 'Choix de la granularité');

        if ($total <= 0) {
            return $html . $this->renderIliasLikeRow('Vue', '<p><em>Aucune activité enregistrée sur la période sélectionnée.</em></p>') . '</section>';
        }

        if ($mode === 'week') {
            $items = $this->aggregateActivityByWeek($daily);
            return $html . $this->renderIliasLikeRow('Vue hebdomadaire', $this->renderActivityTimelineSummary($items, 'semaine(s)') . $this->renderActivityTimelineBars($items)) . '</section>';
        }

        if ($mode === 'all') {
            $items = $daily;
            $summaryItems = $periodDays > 30 ? $this->aggregateActivityByWeek($daily) : $daily;
            $content = $this->renderActivityTimelineSummary($summaryItems, $periodDays > 30 ? 'semaine(s)' : 'jour(s)')
                . '<details class="itxeb-activity-details"><summary>Afficher le détail complet par jour (' . $this->esc((string) count($items)) . ' jour(s))</summary>'
                . $this->renderActivityTimelineBars($items)
                . '</details>';
            return $html . $this->renderIliasLikeRow('Détail complet', $content, 'Vue repliable par jour') . '</section>';
        }

        $limit = (int) $mode;
        if ($limit <= 0) { $limit = min(14, $periodDays); }
        $limit = min($limit, $periodDays);
        $items = array_slice($daily, -$limit, null, true);
        return $html . $this->renderIliasLikeRow('Vue quotidienne', $this->renderActivityTimelineSummary($items, 'jour(s)') . $this->renderActivityTimelineBars($items), (string) $limit . ' dernier(s) jour(s)') . '</section>';
    }
PHP;

v0221_replace_method($s, 'renderDashboard', $renderDashboard);
v0221_replace_method($s, 'renderPedagogicalSynthesis', $renderPedagogicalSynthesis);
v0221_replace_method($s, 'renderBarSection', $renderBarSection);
v0221_replace_method($s, 'renderQuestionFailureHotspots', $renderQuestionFailureHotspots);
if (strpos($s, 'private function renderActivityTimeline(') !== false) {
    v0221_replace_method($s, 'renderActivityTimeline', $renderActivityTimeline);
} else {
    echo "SKIP: méthode renderActivityTimeline absente, lancer apply_v022_activity_timeline.php avant pour l'activité compacte\n";
}

v0221_write($screen, $old, $s);
v0221_set_version($plugin, '0.22.1-dev');
v0221_set_version($companionPlugin, '0.8.7');

if (is_file($liveScreen)) {
    copy($screen, $liveScreen);
    echo "COPY: $screen -> $liveScreen\n";
}
if (is_file($livePlugin)) {
    copy($companionPlugin, $livePlugin);
    echo "COPY: $companionPlugin -> $livePlugin\n";
}

$lintFiles = [$screen, $plugin, $companionPlugin];
if (is_file($liveScreen)) { $lintFiles[] = $liveScreen; }
if (is_file($livePlugin)) { $lintFiles[] = $livePlugin; }
foreach ($lintFiles as $file) {
    $cmd = 'php -l ' . escapeshellarg($file);
    passthru($cmd, $code);
    if ($code !== 0) {
        fwrite(STDERR, "PHP lint KO: $file\n");
        exit(1);
    }
}

echo "V0.22.1 ILIAS-like dashboard layout applied.\n";
