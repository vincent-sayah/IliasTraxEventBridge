<?php

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_lrs_direct_summary.php target.php\n");
    exit(1);
}
$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Cannot read {$file}\n");
    exit(1);
}
if (strpos($code, 'renderLrsDirectSummary') !== false) {
    echo "LRS direct summary patch already present in {$file}\n";
    exit(0);
}

$code = str_replace(
    "    /** @var ilIliasTraxEventBridgeCourseAnalyticsRepository|null */\n    private \$analytics;\n",
    "    /** @var ilIliasTraxEventBridgeCourseAnalyticsRepository|null */\n    private \$analytics;\n    /** @var ilIliasTraxEventBridgeLrsCourseSummary|null */\n    private \$lrsSummary;\n",
    $code
);

$code = str_replace(
    "            if (class_exists('ilIliasTraxEventBridgeCourseAnalyticsRepository')) {\n                \$this->analytics = new ilIliasTraxEventBridgeCourseAnalyticsRepository();\n            }\n",
    "            if (class_exists('ilIliasTraxEventBridgeCourseAnalyticsRepository')) {\n                \$this->analytics = new ilIliasTraxEventBridgeCourseAnalyticsRepository();\n            }\n            \$lrsPath = \$this->bridge->getMainPluginPath() . '/classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php';\n            if (is_file(\$lrsPath)) { require_once \$lrsPath; }\n            if (class_exists('ilIliasTraxEventBridgeLrsCourseSummary')) {\n                \$this->lrsSummary = new ilIliasTraxEventBridgeLrsCourseSummary();\n            }\n",
    $code
);

$old = "        return \$html . '</section>';\n    }\n\n    /** @param array<string,mixed> \$course */\n    private function renderAnalysis";
$new = <<<'PHP'
        $html .= $this->renderLrsDirectSummary($course);
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
        $verbs = is_array($s['by_verb'] ?? null) ? $s['by_verb'] : [];
        if (count($verbs) > 0) {
            $html .= '<h4>Verbes retournés par TRAX</h4><div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr><th>Verbe</th><th style="width:120px">Nombre</th></tr></thead><tbody>';
            foreach (array_slice($verbs, 0, 10) as $verb) {
                $html .= '<tr><td><strong>' . $this->esc((string) ($verb['label'] ?? '')) . '</strong><br><small style="overflow-wrap:anywhere">' . $this->esc((string) ($verb['verb_id'] ?? '')) . '</small></td><td>' . $this->esc((string) ($verb['count'] ?? 0)) . '</td></tr>';
            }
            $html .= '</tbody></table></div>';
        }
        $resources = is_array($s['by_resource'] ?? null) ? $s['by_resource'] : [];
        if (count($resources) > 0) {
            $html .= '<h4>Ressources retournées par TRAX</h4><div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr><th>Ressource</th><th style="width:90px">Type</th><th style="width:80px">ref_id</th><th style="width:120px">Statements</th></tr></thead><tbody>';
            foreach (array_slice($resources, 0, 20) as $resource) {
                $html .= '<tr><td><strong>' . $this->esc((string) ($resource['title'] ?? '')) . '</strong><br><small style="overflow-wrap:anywhere">' . $this->esc((string) ($resource['object_id'] ?? ($resource['key'] ?? ''))) . '</small></td><td>' . $this->esc((string) ($resource['obj_type'] ?? '')) . '</td><td>' . $this->esc((string) ($resource['ref_id'] ?? 0)) . '</td><td>' . $this->esc((string) ($resource['count'] ?? 0)) . '</td></tr>';
            }
            $html .= '</tbody></table></div>';
        }
        return $html . '</section>';
    }

    /** @param array<string,mixed> $course */
    private function renderAnalysis
PHP;
$updated = str_replace($old, $new, $code);
if ($updated === $code) {
    fwrite(STDERR, "Patch failed for {$file}\n");
    exit(1);
}
if (file_put_contents($file, $updated) === false) {
    fwrite(STDERR, "Cannot write {$file}\n");
    exit(1);
}
echo "LRS direct summary patch applied to {$file}\n";
