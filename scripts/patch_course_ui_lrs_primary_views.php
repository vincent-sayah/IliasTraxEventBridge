<?php

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_lrs_primary_views.php target.php\n");
    exit(1);
}
$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Cannot read {$file}\n");
    exit(1);
}
$updated = $code;

if (strpos($updated, 'LRS primary source for xAPI tracking') === false) {
    $old = <<<'PHP'
    /** @param array<string,mixed> $course */
    private function loadDashboard(array $course): array
    {
        if (!$this->analytics) {
            return ['summary' => ['total' => 0, 'sent' => 0, 'failed' => 0, 'active_learners' => 0, 'resources_total' => 0, 'resources_with_traces' => 0, 'avg_score_raw' => null], 'by_day' => [], 'by_verb' => [], 'by_status' => [], 'by_resource' => [], 'expert_rows' => []];
        }
        return $this->analytics->buildForCourse($this->filterCourseResources($course), $this->getPeriodDays());
    }
PHP;
    $new = <<<'PHP'
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
PHP;
    $updated2 = str_replace($old, $new, $updated);
    if ($updated2 === $updated) {
        fwrite(STDERR, "loadDashboard block not found in {$file}\n");
        exit(1);
    }
    $updated = $updated2;
}

$oldComparison = <<<'PHP'
    /** @param array<string,mixed> $course */
    private function renderPeriodComparison(array $course): string
    {
        if (!$this->analytics) {
            return '';
        }
        $days = $this->getPeriodDays();
        if ($days > 180) {
            return '<section class="itxeb-cui-section"><h3>Comparaison entre périodes</h3><p><em>Comparaison non affichée pour 365 jours : la fenêtre locale maximale du MVP est 365 jours.</em></p></section>';
        }

        $extended = $this->analytics->buildForCourse($this->filterCourseResources($course), $days * 2);
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
            . '<p>Comparaison du volume de traces de la période sélectionnée avec la période précédente de même durée.</p>'
            . '<table class="itxeb-cui-table itxeb-comparison-table"><thead><tr><th>Indicateur</th><th>Période actuelle</th><th>Période précédente</th><th>Évolution</th></tr></thead><tbody>'
            . '<tr><td>Traces xAPI</td><td>' . $this->esc((string) $currentTotal) . '</td><td>' . $this->esc((string) $previousTotal) . '</td><td>' . $this->esc($trend) . '</td></tr>'
            . '<tr><td>Moyenne/jour</td><td>' . $this->esc((string) $currentAverage) . '</td><td>' . $this->esc((string) $previousAverage) . '</td><td>' . $this->esc($this->formatSignedNumber(round($currentAverage - $previousAverage, 2))) . '</td></tr>'
            . '</tbody></table></section>';
    }
PHP;
$newComparison = <<<'PHP'
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
PHP;
if (strpos($updated, 'LRS primary period comparison') === false) {
    $updated2 = str_replace($oldComparison, $newComparison, $updated);
    if ($updated2 === $updated) {
        fwrite(STDERR, "renderPeriodComparison block not found in {$file}\n");
        exit(1);
    }
    $updated = $updated2;
}

$updated = str_replace("        if (!empty(\$widgets['technical_status'])) {\n            \$html .= \$this->renderTechnicalStatus(\$dashboard);\n        }\n", "", $updated);
$updated = str_replace("            'technical_status' => 'État technique local',\n", "", $updated);

$updated = preg_replace(
    '/\n    \/\*\* @param array<string,mixed> \$dashboard \*\/\n    private function renderTechnicalStatus\(array \$dashboard\): string\n    \{.*?\n    \}\n\n/s',
    "\n",
    $updated,
    1
);
if (!is_string($updated)) {
    fwrite(STDERR, "Unable to remove renderTechnicalStatus from {$file}\n");
    exit(1);
}

$updated = str_replace('Classe analytics V0.9 indisponible.', 'Lecture TRAX/LRS indisponible.', $updated);
$updated = str_replace('Table outbox absente : evnt_evhk_itxeb_out.', 'Lecture TRAX/LRS indisponible.', $updated);
$updated = str_replace('Vue synthétique des traces xAPI générées par les ressources du cours.', 'Vue synthétique des statements xAPI présents dans TRAX pour ce cours.', $updated);
$updated = str_replace('Ressources utilisées, peu utilisées, activées sans trace ou associées à des erreurs.', 'Ressources utilisées dans TRAX ou sans statement TRAX sur la période.', $updated);
$updated = str_replace('Vue support des 200 dernières traces locales du cours. Les identités sont limitées au user_id ILIAS.', 'Vue support des 200 derniers statements retournés par TRAX pour ce cours.', $updated);
$updated = str_replace('Aucune trace xAPI locale pour cette période ou cette ressource.', 'Aucun statement xAPI TRAX pour cette période ou cette ressource.', $updated);
$updated = str_replace('Traces générées', 'Statements TRAX', $updated);
$updated = str_replace('Volume xAPI', 'Lecture LRS', $updated);
$updated = str_replace('Envoyées TRAX', 'Retournées TRAX', $updated);
$updated = str_replace('status sent', 'GET /statements', $updated);
$updated = str_replace('Ressources activées sans trace', 'Ressources sans statement TRAX', $updated);
$updated = str_replace('Activées sans trace', 'Sans statement TRAX', $updated);
$updated = str_replace('activée sans trace', 'aucun statement TRAX', $updated);
$updated = str_replace('aucune trace locale', 'aucun statement TRAX', $updated);

$updated = str_replace(
    "            . \$this->metricCard('Retournées TRAX', (string) (\$summary['sent'] ?? 0), 'GET /statements')\n            . \$this->metricCard('En erreur', (string) (\$summary['failed'] ?? 0), 'À vérifier')\n",
    "            . \$this->metricCard('Pages LRS', (string) (\$dashboard['pages'] ?? 0), 'pagination')\n",
    $updated
);
$updated = str_replace('<th>Status</th><th>Outbox</th><th>Erreur</th>', '<th>Source</th><th>Statement ID</th>', $updated);
$updated = str_replace(
    "</td><td>' . \$this->esc((string) (\$row['status'] ?? '')) . '</td>'\n                . '<td>#' . \$this->esc((string) (\$row['outbox_id'] ?? 0)) . '<br><small>' . \$this->esc((string) (\$row['statement_uuid'] ?? '')) . '</small></td><td><small>' . \$this->esc(\$this->shorten((string) (\$row['last_error'] ?? ''), 180)) . '</small></td></tr>';",
    "</td><td>' . \$this->esc((string) (\$row['status'] ?? 'TRAX')) . '</td>'\n                . '<td><small>' . \$this->esc((string) (\$row['statement_uuid'] ?? '')) . '</small></td></tr>';",
    $updated
);
$updated = str_replace('outbox_id', 'statement_source_outbox_removed', $updated);
$updated = str_replace('last_error', 'statement_error_removed', $updated);

if (strpos($updated, 'État technique local') !== false) {
    fwrite(STDERR, "Technical local status text still present in {$file}\n");
    exit(1);
}
if (strpos($updated, '<th>Outbox</th>') !== false || strpos($updated, '<th>Erreur</th>') !== false) {
    fwrite(STDERR, "Expert table still contains local outbox columns in {$file}\n");
    exit(1);
}

if (file_put_contents($file, $updated) === false) {
    fwrite(STDERR, "Cannot write {$file}\n");
    exit(1);
}
echo "LRS primary views patch applied to {$file}\n";
