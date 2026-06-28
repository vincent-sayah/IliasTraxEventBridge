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
$new = "        \$html .= \$this->renderLrsDirectSummary(\$course);\n        return \$html . '</section>';\n    }\n\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n    {\n        if (!\$this->lrsSummary) {\n            return '<section class=\"itxeb-cui-section\"><h3>TRAX / LRS direct</h3><p><em>Lecture LRS indisponible.</em></p></section>';\n        }\n        \$local = \$this->loadDashboard(\$course);\n        \$localSummary = is_array(\$local['summary'] ?? null) ? \$local['summary'] : [];\n        \$localTotal = (int) (\$localSummary['total'] ?? 0);\n        \$localSent = (int) (\$localSummary['sent'] ?? 0);\n        \$s = \$this->lrsSummary->build(\$course, \$this->getPeriodDays());\n        \$lrsReturned = (int) (\$s['returned'] ?? 0);\n        \$delta = \$localSent - \$lrsReturned;\n        \$coherence = !empty(\$s['available']) ? (\$delta === 0 ? 'cohérent' : (\$delta > 0 ? 'écart local > LRS' : 'écart LRS > local')) : 'LRS indisponible';\n        \$activity = (string) (\$s['activity_id'] ?? '');\n        \$more = (string) (\$s['more'] ?? '');\n        \$pages = (int) (\$s['pages'] ?? 0);\n        \$complete = !empty(\$s['pagination_complete']);\n        \$limitReached = !empty(\$s['pagination_limit_reached']);\n        \$paginationStatus = \$complete ? 'complète' : (\$limitReached ? 'tronquée limite sécurité' : 'incomplète');\n        \$html = '<section class=\"itxeb-cui-section itxeb-lrs-direct\"><h3>TRAX / LRS direct</h3>'\n            . '<div class=\"itxeb-kpi-grid\">'\n            . \$this->metricCard('État LRS', !empty(\$s['available']) ? 'disponible' : 'indisponible', 'HTTP ' . (string) (\$s['http_status'] ?? 0))\n            . \$this->metricCard('Local générées', (string) \$localTotal, 'outbox locale')\n            . \$this->metricCard('Local envoyées', (string) \$localSent, 'status sent')\n            . \$this->metricCard('TRAX retournées', (string) \$lrsReturned, 'GET /statements')\n            . \$this->metricCard('Écart', (string) \$delta, \$coherence)\n            . \$this->metricCard('Pages LRS', (string) \$pages, \$paginationStatus)\n            . '</div>'\n            . '<div style=\"display:grid;grid-template-columns:minmax(210px,260px) minmax(0,1fr);gap:0;border:1px solid #ddd;margin-top:12px\">'\n            . '<div style=\"font-weight:600;padding:8px 10px;border-bottom:1px solid #ddd;background:#f8f8f8\">Statut comparaison</div><div style=\"padding:8px 10px;border-bottom:1px solid #ddd\">' . \$this->esc(\$coherence) . '</div>'\n            . '<div style=\"font-weight:600;padding:8px 10px;border-bottom:1px solid #ddd;background:#f8f8f8\">Pagination LRS</div><div style=\"padding:8px 10px;border-bottom:1px solid #ddd\">' . \$this->esc(\$paginationStatus . ' - ' . \$pages . ' page(s) lue(s)') . '</div>'\n            . '<div style=\"font-weight:600;padding:8px 10px;border-bottom:1px solid #ddd;background:#f8f8f8\">More LRS restant</div><div style=\"padding:8px 10px;border-bottom:1px solid #ddd;overflow-wrap:anywhere\">' . \$this->esc(\$more === '' ? '-' : \$this->shorten(\$more, 220)) . '</div>'\n            . '<div style=\"font-weight:600;padding:8px 10px;background:#f8f8f8\">Activité cours xAPI</div><div style=\"padding:8px 10px;overflow-wrap:anywhere;font-family:monospace\">' . \$this->esc(\$activity) . '</div>'\n            . '</div>';\n        if ((string) (\$s['pagination_error'] ?? '') !== '') {\n            \$html .= '<div class=\"itxeb-cui-alert itxeb-cui-error\" style=\"margin-top:12px\">Pagination LRS : ' . \$this->esc((string) \$s['pagination_error']) . '</div>';\n        }\n        if ((string) (\$s['error'] ?? '') !== '') {\n            \$html .= '<div class=\"itxeb-cui-alert itxeb-cui-error\" style=\"margin-top:12px\">' . \$this->esc((string) \$s['error']) . '</div>';\n        }\n        \$verbs = is_array(\$s['by_verb'] ?? null) ? \$s['by_verb'] : [];\n        if (count(\$verbs) > 0) {\n            \$html .= '<h4>Verbes retournés par TRAX</h4><div class=\"itxeb-cui-table-wrapper\"><table class=\"itxeb-cui-table\"><thead><tr><th>Verbe</th><th style=\"width:120px\">Nombre</th></tr></thead><tbody>';\n            foreach (array_slice(\$verbs, 0, 10) as \$verb) {\n                \$html .= '<tr><td><strong>' . \$this->esc((string) (\$verb['label'] ?? '')) . '</strong><br><small style=\"overflow-wrap:anywhere\">' . \$this->esc((string) (\$verb['verb_id'] ?? '')) . '</small></td><td>' . \$this->esc((string) (\$verb['count'] ?? 0)) . '</td></tr>';\n            }\n            \$html .= '</tbody></table></div>';\n        }\n        return \$html . '</section>';\n    }\n\n    /** @param array<string,mixed> \$course */\n    private function renderAnalysis";
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
