<?php
$root = getcwd();
$ai = $root . '/classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php';
$repo = $root . '/classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php';
$plugin = $root . '/plugin.php';

function rf(string $file): string
{
    $s = file_get_contents($file);
    if (!is_string($s)) {
        fwrite(STDERR, "Lecture impossible: $file\n");
        exit(1);
    }
    return $s;
}

function wf(string $file, string $old, string $new): void
{
    if ($old !== $new) {
        file_put_contents($file, $new);
        echo "WRITE: $file\n";
    } else {
        echo "OK: aucun changement $file\n";
    }
}

function patch_once(string &$s, string $old, string $new, string $label): void
{
    if (strpos($s, $new) !== false) {
        echo "OK: $label\n";
        return;
    }
    $pos = strpos($s, $old);
    if ($pos === false) {
        fwrite(STDERR, "BLOC INTROUVABLE: $label\n");
        exit(1);
    }
    $s = substr($s, 0, $pos) . $new . substr($s, $pos + strlen($old));
    echo "PATCH: $label\n";
}

function set_version(string $file, string $version): void
{
    $old = rf($file);
    $new = preg_replace('/\$version\s*=\s*\'[^\']*\';/', '$version = \'' . $version . '\';', $old, 1);
    if (!is_string($new)) {
        fwrite(STDERR, "Version impossible: $file\n");
        exit(1);
    }
    wf($file, $old, $new);
}

foreach ([$ai, $repo, $plugin] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Fichier absent: $file\n");
        exit(1);
    }
}

$old = rf($ai);
$s = $old;

patch_once(
    $s,
    "            'resource_analysis' => $filteredResources,\n            'learner_risk_aggregate' => $this->aggregateLearnerRisks($expertRows),",
    "            'resource_analysis' => $filteredResources,\n            'question_failure_analysis' => $this->buildQuestionFailureAnalysis($course, $dashboard),\n            'learner_risk_aggregate' => $this->aggregateLearnerRisks($expertRows),",
    'payload IA question_failure_analysis'
);

$method = <<<'PHP'
    /** @param array<string,mixed> $course @param array<string,mixed> $dashboard @return array<int,array<string,mixed>> */
    private function buildQuestionFailureAnalysis(array $course, array $dashboard): array
    {
        $risks = is_array($dashboard['question_risks'] ?? null) ? $dashboard['question_risks'] : [];
        if ($risks === []) {
            $path = __DIR__ . '/class.ilIliasTraxEventBridgeQuestionRiskRepository.php';
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
                    $risks = (new ilIliasTraxEventBridgeQuestionRiskRepository())->build((int) ($dashboard['period_days'] ?? 30), $allowedRefIds, 0);
                } catch (Throwable $ignored) {
                    $risks = [];
                }
            }
        }
        return $this->filterQuestionRisks($risks);
    }

    /** @param array<int|string,mixed> $risks @return array<int,array<string,mixed>> */
    private function filterQuestionRisks(array $risks): array
    {
        $out = [];
        foreach ($risks as $risk) {
            if (!is_array($risk)) { continue; }
            $out[] = $this->keepKeys($risk, [
                'question_id', 'question_title', 'test_title', 'ref_id', 'attempts',
                'failed', 'unanswered', 'failure_rate', 'avg_score', 'risk_label', 'risk_reason'
            ]);
            if (count($out) >= 10) { break; }
        }
        return $out;
    }

PHP;

if (strpos($s, 'private function buildQuestionFailureAnalysis(array $course, array $dashboard): array') === false) {
    $marker = "    /** @param array<string,mixed> $" . "summary @return array<string,mixed> */\n    private function filterSummary(array $" . "summary): array";
    $pos = strpos($s, $marker);
    if ($pos === false) {
        fwrite(STDERR, "Point insertion buildQuestionFailureAnalysis introuvable\n");
        exit(1);
    }
    $s = substr($s, 0, $pos) . $method . substr($s, $pos);
    echo "PATCH: méthodes IA question failures\n";
} else {
    echo "OK: méthodes IA question failures\n";
}

if (strpos($s, 'question_failure_analysis') === false || strpos($s, 'buildQuestionFailureAnalysis') === false) {
    fwrite(STDERR, "Patch IA incomplet.\n");
    exit(1);
}

wf($ai, $old, $s);
set_version($plugin, '0.21.2-dev');
echo "V0.21.2c appliquee : questions problematiques integrees dans le payload Analyse IA.\n";
