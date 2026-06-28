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
if (strpos($code, 'LRS primary source for xAPI tracking') !== false) {
    echo "LRS primary views patch already present in {$file}\n";
    exit(0);
}

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
$updated = str_replace($old, $new, $code);
if ($updated === $code) {
    fwrite(STDERR, "loadDashboard block not found in {$file}\n");
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

if (file_put_contents($file, $updated) === false) {
    fwrite(STDERR, "Cannot write {$file}\n");
    exit(1);
}
echo "LRS primary views patch applied to {$file}\n";
