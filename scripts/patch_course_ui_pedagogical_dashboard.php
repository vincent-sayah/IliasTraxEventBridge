<?php

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_pedagogical_dashboard.php <CourseUIScreen.php>\n");
    exit(1);
}

$s = file_get_contents($file);
if ($s === false) {
    fwrite(STDERR, "Unable to read target file.\n");
    exit(1);
}

if (strpos($s, 'renderPedagogicalSynthesis') !== false) {
    echo "V0.12 pedagogical UI patch already applied.\n";
    exit(0);
}

$s = one($s,
    " . \$this->renderPeriodSelector('showCourseDashboard') . \$this->renderResourceFilter(\$course, 'showCourseDashboard') . \$this->renderAnalyticsWarning()\n            . '<div class=\"itxeb-kpi-grid\">",
    " . \$this->renderPeriodSelector('showCourseDashboard') . \$this->renderResourceFilter(\$course, 'showCourseDashboard') . \$this->renderAnalyticsWarning()\n            . \$this->renderPedagogicalSynthesis(\$dashboard)\n            . '<div class=\"itxeb-kpi-grid\">",
    'dashboard synthesis insertion'
);

$s = one($s,
    " . \$this->metricCard('Activées sans trace', (string) \$this->countEnabledWithoutTraceResources(\$dashboard), 'À surveiller')\n            . \$this->metricCard('Envoyées TRAX', (string) (\$summary['sent'] ?? 0), 'status sent')",
    " . \$this->metricCard('Critiques', (string) (\$dashboard['pedagogy']['critical_count'] ?? 0), 'Priorité')\n            . \$this->metricCard('À surveiller', (string) (\$dashboard['pedagogy']['watch_count'] ?? 0), 'Signal pédagogique')\n            . \$this->metricCard('Activées sans trace', (string) \$this->countEnabledWithoutTraceResources(\$dashboard), 'À surveiller')\n            . \$this->metricCard('Envoyées TRAX', (string) (\$summary['sent'] ?? 0), 'status sent')",
    'dashboard KPI insertion'
);

$start = strpos($s, "    /** @param array<string,mixed> \$course */\n    private function renderAnalysis(array \$course): string");
$end = strpos($s, "    /** @param array<string,mixed> \$course */\n    private function renderExpert(array \$course): string", $start);
if ($start === false || $end === false || $end <= $start) {
    fwrite(STDERR, "Unable to locate renderAnalysis block.\n");
    exit(1);
}

$helpers = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderPedagogicalSynthesis(array $dashboard): string
    {
        $pedagogy = is_array($dashboard['pedagogy'] ?? null) ? $dashboard['pedagogy'] : [];
        $lines = is_array($pedagogy['synthesis_lines'] ?? null) ? $pedagogy['synthesis_lines'] : [];
        $html = '<div class="itxeb-pedagogy-summary"><h3>Synthèse pédagogique</h3><div class="itxeb-pedagogy-kpis">'
            . $this->metricCard('OK', (string) ($pedagogy['ok_count'] ?? 0), 'Ressources sans signal')
            . $this->metricCard('À surveiller', (string) ($pedagogy['watch_count'] ?? 0), 'Signal faible')
            . $this->metricCard('Critiques', (string) ($pedagogy['critical_count'] ?? 0), 'Priorité')
            . $this->metricCard('Sans trace', (string) ($pedagogy['resources_without_trace'] ?? 0), 'Activées sans trace')
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

PHP;

$newAnalysis = <<<'PHP'
    /** @param array<string,mixed> $course */
    private function renderAnalysis(array $course): string
    {
        $dashboard = $this->loadDashboard($course);
        $resources = is_array($dashboard['by_resource'] ?? null) ? $dashboard['by_resource'] : [];
        $html = '<section class="itxeb-cui-section"><h2>Analyse des ressources</h2><p>Ressources utilisées, peu utilisées, activées sans trace ou associées à des signaux pédagogiques.</p>' . $this->renderPeriodSelector('showCourseAnalysis') . $this->renderResourceFilter($course, 'showCourseAnalysis') . $this->renderAnalyticsWarning() . $this->renderPedagogicalSynthesis($dashboard);
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
        return $html . '</tbody></table></div></section>';
    }

PHP;

$s = substr($s, 0, $start) . $helpers . $newAnalysis . substr($s, $end);

$s = one($s,
    "#itxeb-course-ui-screen .itxeb-cui-table th,#itxeb-course-ui-screen .itxeb-cui-table td{border:1px solid #ddd;padding:.5rem .6rem;vertical-align:top;line-height:1.35}",
    "#itxeb-course-ui-screen .itxeb-cui-table th,#itxeb-course-ui-screen .itxeb-cui-table td{border:1px solid #ddd;padding:.5rem .6rem;vertical-align:top;line-height:1.35}#itxeb-course-ui-screen .itxeb-pedagogy-summary{border:1px solid #ddd;background:#fff;margin:.7rem 0 1rem;padding:.75rem;border-radius:4px}#itxeb-course-ui-screen .itxeb-pedagogy-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.6rem;margin:.5rem 0}#itxeb-course-ui-screen .itxeb-pedagogy-lines{margin:.6rem 0 0 1.2rem}#itxeb-course-ui-screen .itxeb-pedagogy-badge{display:inline-block;padding:.25rem .45rem;border-radius:3px;font-weight:700;white-space:nowrap}#itxeb-course-ui-screen .itxeb-pedagogy-ok{background:#dff0d8;color:#3c763d}#itxeb-course-ui-screen .itxeb-pedagogy-watch{background:#fcf8e3;color:#8a6d3b}#itxeb-course-ui-screen .itxeb-pedagogy-critical{background:#f2dede;color:#a94442}#itxeb-course-ui-screen .itxeb-pedagogy-muted{background:#eee;color:#555}",
    'pedagogical styles'
);

file_put_contents($file, $s);
echo "V0.12 pedagogical UI patch applied.\n";

function one(string $s, string $old, string $new, string $label): string
{
    $count = 0;
    $s = str_replace($old, $new, $s, $count);
    if ($count !== 1) {
        fwrite(STDERR, "Patch failed for {$label}: {$count} replacement(s).\n");
        exit(1);
    }
    return $s;
}
