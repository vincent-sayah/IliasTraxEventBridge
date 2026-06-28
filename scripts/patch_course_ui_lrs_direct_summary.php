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
        $local = $this->loadDashboard($course);
        $localSummary = is_array($local['summary'] ?? null) ? $local['summary'] : [];
        $localTotal = (int) ($localSummary['total'] ?? 0);
        $localSent = (int) ($localSummary['sent'] ?? 0);
        $localComparable = $localTotal > 0 || $localSent > 0;
        $s = $this->lrsSummary->build($course, $this->getPeriodDays());
        $lrsReturned = (int) ($s['returned'] ?? 0);
        $delta = $localSent - $lrsReturned;
        $deltaLabel = (string) $delta;
        if (!$localComparable && $lrsReturned > 0) {
            $coherence = 'non comparable - outbox locale vide';
            $deltaLabel = '-';
        } else {
            $coherence = !empty($s['available']) ? ($delta === 0 ? 'cohérent' : ($delta > 0 ? 'écart local > LRS' : 'écart LRS > local')) : 'LRS indisponible';
        }
        $activity = (string) ($s['activity_id'] ?? '');
        $more = (string) ($s['more'] ?? '');
        $pages = (int) ($s['pages'] ?? 0);
        $complete = !empty($s['pagination_complete']);
        $limitReached = !empty($s['pagination_limit_reached']);
        $paginationStatus = $complete ? 'complète' : ($limitReached ? 'tronquée limite sécurité' : 'incomplète');
        $html = '<section class="itxeb-cui-section itxeb-lrs-direct"><h3>TRAX / LRS direct</h3>'
            . '<div class="itxeb-kpi-grid">'
            . $this->metricCard('État LRS', !empty($s['available']) ? 'disponible' : 'indisponible', 'HTTP ' . (string) ($s['http_status'] ?? 0))
            . $this->metricCard('Local générées', (string) $localTotal, 'outbox locale')
            . $this->metricCard('Local envoyées', (string) $localSent, 'status sent')
            . $this->metricCard('TRAX retournées', (string) $lrsReturned, 'GET /statements')
            . $this->metricCard('Écart', $deltaLabel, $coherence)
            . $this->metricCard('Pages LRS', (string) $pages, $paginationStatus)
            . '</div>';
        if (!$localComparable && $lrsReturned > 0) {
            $html .= '<div class="itxeb-cui-alert" style="margin-top:12px">L\'outbox locale est vide. TRAX contient encore des statements historiques : la comparaison local / TRAX est donc informative, pas une incohérence.</div>';
        }
        $html .= '<div style="display:grid;grid-template-columns:minmax(210px,260px) minmax(0,1fr);gap:0;border:1px solid #ddd;margin-top:12px">'
            . '<div style="font-weight:600;padding:8px 10px;border-bottom:1px solid #ddd;background:#f8f8f8">Statut comparaison</div><div style="padding:8px 10px;border-bottom:1px solid #ddd">' . $this->esc($coherence) . '</div>'
            . '<div style="font-weight:600;padding:8px 10px;border-bottom:1px solid #ddd;background:#f8f8f8">Pagination LRS</div><div style="padding:8px 10px;border-bottom:1px solid #ddd">' . $this->esc($paginationStatus . ' - ' . $pages . ' page(s) lue(s)') . '</div>'
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
        $lrsResources = is_array($s['by_resource'] ?? null) ? $s['by_resource'] : [];
        $localResources = is_array($local['by_resource'] ?? null) ? $local['by_resource'] : [];
        $localByRef = [];
        foreach ($localResources as $localResource) {
            $ref = (int) ($localResource['ref_id'] ?? 0);
            if ($ref > 0) { $localByRef[$ref] = $localResource; }
        }
        $lrsByRef = []; $lrsNoRef = [];
        foreach ($lrsResources as $resource) {
            $ref = (int) ($resource['ref_id'] ?? 0);
            if ($ref > 0) { $lrsByRef[$ref] = $resource; } else { $lrsNoRef[] = $resource; }
        }
        $refs = array_values(array_unique(array_merge(array_keys($localByRef), array_keys($lrsByRef))));
        sort($refs);
        if (count($refs) > 0 || count($lrsNoRef) > 0) {
            $html .= '<h4>Comparaison local / TRAX par ressource</h4><div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr><th>Ressource</th><th style="width:90px">Type</th><th style="width:80px">ref_id</th><th style="width:95px">Local</th><th style="width:95px">Envoyées</th><th style="width:95px">TRAX</th><th style="width:85px">Écart</th><th style="width:150px">Statut</th></tr></thead><tbody>';
            foreach ($refs as $ref) {
                $localResource = $localByRef[$ref] ?? []; $lrsResource = $lrsByRef[$ref] ?? [];
                $localCount = (int) ($localResource['traces'] ?? 0); $localSentCount = (int) ($localResource['sent'] ?? 0); $lrsCount = (int) ($lrsResource['count'] ?? 0);
                $resourceDelta = $localSentCount - $lrsCount;
                $resourceDeltaLabel = (string) $resourceDelta;
                if (!$localComparable && $lrsCount > 0) {
                    $status = 'historique TRAX';
                    $resourceDeltaLabel = '-';
                } else {
                    $status = $resourceDelta === 0 ? 'cohérent' : ($lrsCount === 0 && $localSentCount > 0 ? 'absent TRAX' : ($resourceDelta > 0 ? 'local > TRAX' : 'TRAX > local'));
                }
                $title = (string) ($localResource['title'] ?? ($lrsResource['title'] ?? ('ref_id ' . $ref)));
                $type = (string) ($localResource['obj_type'] ?? ($lrsResource['obj_type'] ?? ''));
                $objectId = (string) ($lrsResource['object_id'] ?? '');
                $html .= '<tr><td><strong>' . $this->esc($title) . '</strong>' . ($objectId !== '' ? '<br><small style="overflow-wrap:anywhere">' . $this->esc($objectId) . '</small>' : '') . '</td><td>' . $this->esc($type) . '</td><td>' . $this->esc((string) $ref) . '</td><td>' . $this->esc((string) $localCount) . '</td><td>' . $this->esc((string) $localSentCount) . '</td><td>' . $this->esc((string) $lrsCount) . '</td><td>' . $this->esc($resourceDeltaLabel) . '</td><td>' . $this->esc($status) . '</td></tr>';
            }
            foreach (array_slice($lrsNoRef, 0, 10) as $resource) {
                $status = !$localComparable ? 'historique TRAX sans ref_id' : 'TRAX sans ref_id';
                $html .= '<tr><td><strong>' . $this->esc((string) ($resource['title'] ?? 'Ressource TRAX sans ref_id')) . '</strong><br><small style="overflow-wrap:anywhere">' . $this->esc((string) ($resource['object_id'] ?? ($resource['key'] ?? ''))) . '</small></td><td>' . $this->esc((string) ($resource['obj_type'] ?? '')) . '</td><td>0</td><td>0</td><td>0</td><td>' . $this->esc((string) ($resource['count'] ?? 0)) . '</td><td>-</td><td>' . $this->esc($status) . '</td></tr>';
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
