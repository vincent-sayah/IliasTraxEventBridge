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
$new = "        \$html .= \$this->renderLrsDirectSummary(\$course);\n        return \$html . '</section>';\n    }\n\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n    {\n        if (!\$this->lrsSummary) {\n            return '<section class=\"itxeb-cui-section\"><h3>TRAX / LRS direct</h3><p><em>Lecture LRS indisponible.</em></p></section>';\n        }\n        \$local = \$this->loadDashboard(\$course);\n        \$localSummary = is_array(\$local['summary'] ?? null) ? \$local['summary'] : [];\n        \$localTotal = (int) (\$localSummary['total'] ?? 0);\n        \$localSent = (int) (\$localSummary['sent'] ?? 0);\n        \$s = \$this->lrsSummary->build(\$course, \$this->getPeriodDays());\n        \$lrsReturned = (int) (\$s['returned'] ?? 0);\n        \$delta = \$localSent - \$lrsReturned;\n        \$coherence = !empty(\$s['available']) ? (\$delta === 0 ? 'cohérent' : (\$delta > 0 ? 'écart local > LRS' : 'écart LRS > local')) : 'LRS indisponible';\n        \$html = '<section class=\"itxeb-cui-section\"><h3>TRAX / LRS direct</h3><table class=\"itxeb-cui-table\"><tbody>'\n            . \$this->row('État', !empty(\$s['available']) ? 'disponible' : 'indisponible')\n            . \$this->row('HTTP', (string) (\$s['http_status'] ?? 0))\n            . \$this->row('Traces locales générées', (string) \$localTotal)\n            . \$this->row('Traces locales envoyées', (string) \$localSent)\n            . \$this->row('Statements retournés par TRAX', (string) \$lrsReturned)\n            . \$this->row('Écart envoyé local / LRS', (string) \$delta)\n            . \$this->row('Statut comparaison', \$coherence)\n            . \$this->row('More LRS', (string) (\$s['more'] ?? ''))\n            . \$this->row('Activité cours xAPI', (string) (\$s['activity_id'] ?? ''));\n        if ((string) (\$s['error'] ?? '') !== '') {\n            \$html .= \$this->row('Erreur', (string) \$s['error']);\n        }\n        \$html .= '</tbody></table>';\n        \$verbs = is_array(\$s['by_verb'] ?? null) ? \$s['by_verb'] : [];\n        if (count(\$verbs) > 0) {\n            \$html .= '<h4>Verbes retournés par TRAX</h4><table class=\"itxeb-cui-table\"><thead><tr><th>Verbe</th><th>Nombre</th></tr></thead><tbody>';\n            foreach (array_slice(\$verbs, 0, 10) as \$verb) {\n                \$html .= '<tr><td>' . \$this->esc((string) (\$verb['label'] ?? '')) . '<br><small>' . \$this->esc((string) (\$verb['verb_id'] ?? '')) . '</small></td><td>' . \$this->esc((string) (\$verb['count'] ?? 0)) . '</td></tr>';\n            }\n            \$html .= '</tbody></table>';\n        }\n        return \$html . '</section>';\n    }\n\n    /** @param array<string,mixed> \$course */\n    private function renderAnalysis";
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
