<?php
$root = getcwd();
$analytics = $root . '/classes/class.ilIliasTraxEventBridgeCourseAnalyticsRepository.php';
$screen = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$ai = $root . '/classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php';
$plugin = $root . '/plugin.php';
$companionPlugin = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$servicesRoot = dirname(dirname(dirname($root)));
$liveRoot = $servicesRoot . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$liveScreen = $liveRoot . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = $liveRoot . '/plugin.php';

function rf(string $file): string { $s = file_get_contents($file); if (!is_string($s)) { fwrite(STDERR, "Lecture impossible: $file\n"); exit(1); } return $s; }
function wf(string $file, string $old, string $new): void { if ($old !== $new) { file_put_contents($file, $new); echo "WRITE: $file\n"; } else { echo "OK: aucun changement $file\n"; } }
function set_version(string $file, string $version): void { $old = rf($file); $new = preg_replace('/\$version = \'[^\']*\';/', '$version = \'' . $version . '\';', $old, 1); if (!is_string($new)) { fwrite(STDERR, "Version impossible: $file\n"); exit(1); } wf($file, $old, $new); }
function patch_once(string &$s, string $old, string $new, string $label): void { if (strpos($s, $new) !== false) { echo "OK: $label\n"; return; } $pos = strpos($s, $old); if ($pos === false) { fwrite(STDERR, "BLOC INTROUVABLE: $label\n"); exit(1); } $s = substr($s, 0, $pos) . $new . substr($s, $pos + strlen($old)); echo "PATCH: $label\n"; }

foreach ([$analytics, $screen, $ai, $plugin, $companionPlugin] as $file) { if (!is_file($file)) { fwrite(STDERR, "Fichier absent: $file\n"); exit(1); } }

// 1) Agrégation des questions dans le repository analytics.
$old = rf($analytics);
$s = $old;
patch_once($s,
"            'by_resource' => $byResource,\n            'expert_rows' => [],\n        ];",
"            'by_resource' => $byResource,\n            'question_stats' => [],\n            'question_risks' => [],\n            'expert_rows' => [],\n        ];",
'analytics empty dashboard question buckets');

patch_once($s,
"            'spent_seconds' => $spentSeconds,\n            'read_count' => $this->numericOrNull($this->extensionValue($resultExtensions, '/read_count')),\n        ];",
"            'spent_seconds' => $spentSeconds,\n            'read_count' => $this->numericOrNull($this->extensionValue($resultExtensions, '/read_count')),\n            'question_id' => $this->numericOrNull($this->extensionValue($resultExtensions, '/question_id')),\n            'question_title' => (string) $this->extensionValue($resultExtensions, '/question_title'),\n            'question_score_percent' => $this->numericOrNull($this->extensionValue($resultExtensions, '/question_score_percent')),\n            'question_answered' => $this->boolOrNull($this->extensionValue($resultExtensions, '/question_answered')),\n            'question_points' => $this->numericOrNull($this->extensionValue($resultExtensions, '/question_points')),\n            'question_max_points' => $this->numericOrNull($this->extensionValue($resultExtensions, '/question_max_points')),\n        ];",
'analytics normalize question extensions');

patch_once($s,
"        if ($objType === 'tst') {\n            $dashboard['summary']['tests_attempted']++;\n            if (($item['success'] ?? null) === true) {\n                $dashboard['summary']['tests_passed']++;\n            } elseif (($item['success'] ?? null) === false) {\n                $dashboard['summary']['tests_failed']++;\n            }\n        }\n\n        $refId = (int) ($item['ref_id'] ?? 0);",
"        if ($objType === 'tst') {\n            $dashboard['summary']['tests_attempted']++;\n            if (($item['success'] ?? null) === true) {\n                $dashboard['summary']['tests_passed']++;\n            } elseif (($item['success'] ?? null) === false) {\n                $dashboard['summary']['tests_failed']++;\n            }\n        }\n\n        $this->addQuestionItemToDashboard($dashboard, $item);\n\n        $refId = (int) ($item['ref_id'] ?? 0);",
'analytics add question item call');

$questionMethods = <<<'PHP'
    /** @param array<string,mixed> $dashboard @param array<string,mixed> $item */
    private function addQuestionItemToDashboard(array &$dashboard, array $item): void
    {
        $questionIdRaw = $item['question_id'] ?? null;
        if (!is_numeric($questionIdRaw) || (int) $questionIdRaw <= 0) {
            return;
        }
        $questionId = (int) $questionIdRaw;
        $refId = (int) ($item['ref_id'] ?? 0);
        $key = $refId . ':' . $questionId;
        if (!isset($dashboard['question_stats'][$key])) {
            $dashboard['question_stats'][$key] = [
                'question_key' => $key,
                'question_id' => $questionId,
                'question_title' => (string) ($item['question_title'] ?? ('Question ' . $questionId)),
                'ref_id' => $refId,
                'obj_id' => (int) ($item['obj_id'] ?? 0),
                'test_title' => (string) ($item['object_title'] ?? ''),
                'attempts' => 0,
                'failed' => 0,
                'passed' => 0,
                'unanswered' => 0,
                'score_sum' => 0.0,
                'score_count' => 0,
                'learners' => [],
                'last_ts' => 0,
                'last_at' => '',
            ];
        }

        $stats =& $dashboard['question_stats'][$key];
        $stats['attempts']++;
        $learnerKey = (string) ($item['learner_key'] ?? '');
        if ($learnerKey !== '') { $stats['learners'][$learnerKey] = true; }
        $createdTs = (int) ($item['created_ts'] ?? 0);
        if ($createdTs > (int) ($stats['last_ts'] ?? 0)) {
            $stats['last_ts'] = $createdTs;
            $stats['last_at'] = (string) ($item['created_at'] ?? '');
        }

        $answered = $item['question_answered'] ?? null;
        if ($answered === false) {
            $stats['unanswered']++;
        }

        $score = $item['question_score_percent'];
        if ($score === null) { $score = $item['score_raw']; }
        if (is_numeric($score)) {
            $score = (float) $score;
            $stats['score_sum'] += $score;
            $stats['score_count']++;
            if ($score < 50.0) { $stats['failed']++; } else { $stats['passed']++; }
        } elseif (($item['success'] ?? null) === false || $answered === false) {
            $stats['failed']++;
        } elseif (($item['success'] ?? null) === true) {
            $stats['passed']++;
        }
        unset($stats);
    }

    /** @param array<string,array<string,mixed>> $questionStats @return array<int,array<string,mixed>> */
    private function finalizeQuestionRisks(array $questionStats): array
    {
        $risks = [];
        foreach ($questionStats as $stats) {
            if (!is_array($stats)) { continue; }
            $attempts = (int) ($stats['attempts'] ?? 0);
            if ($attempts <= 0) { continue; }
            $failed = (int) ($stats['failed'] ?? 0);
            $unanswered = (int) ($stats['unanswered'] ?? 0);
            $scoreCount = (int) ($stats['score_count'] ?? 0);
            $avgScore = $scoreCount > 0 ? round((float) ($stats['score_sum'] ?? 0.0) / $scoreCount, 2) : null;
            $failureRate = round((($failed + $unanswered) / max(1, $attempts)) * 100, 2);
            if ($failureRate < 50.0 && !($avgScore !== null && $avgScore < 50.0)) { continue; }
            $stats['learners_count'] = count(is_array($stats['learners'] ?? null) ? $stats['learners'] : []);
            unset($stats['learners'], $stats['score_sum'], $stats['score_count'], $stats['last_ts']);
            $stats['avg_score'] = $avgScore;
            $stats['failure_rate'] = $failureRate;
            $stats['risk_label'] = $failureRate >= 70.0 ? 'Critique' : 'À surveiller';
            $stats['risk_reason'] = $failureRate . ' % d’échec/non-réponse sur ' . $attempts . ' réponse(s).';
            $risks[] = $stats;
        }
        usort($risks, static function (array $a, array $b): int {
            $cmp = (float) ($b['failure_rate'] ?? 0) <=> (float) ($a['failure_rate'] ?? 0);
            if ($cmp !== 0) { return $cmp; }
            return (int) ($b['attempts'] ?? 0) <=> (int) ($a['attempts'] ?? 0);
        });
        return array_slice($risks, 0, 20);
    }

PHP;
if (strpos($s, 'private function addQuestionItemToDashboard(array &$dashboard, array $item): void') === false) {
    $marker = "    /** @param array<string,mixed> $" . "dashboard */\n    private function finalizeDashboard(array $" . "dashboard): array";
    $pos = strpos($s, $marker);
    if ($pos === false) { fwrite(STDERR, "Point insertion méthodes questions analytics introuvable\n"); exit(1); }
    $s = substr($s, 0, $pos) . $questionMethods . substr($s, $pos);
    echo "PATCH: analytics question methods\n";
} else { echo "OK: analytics question methods\n"; }

patch_once($s,
"        $dashboard['summary']['resources_with_traces'] = $resourcesWithTraces;\n\n        uasort($dashboard['by_resource'], static function (array $left, array $right): int {",
"        $dashboard['summary']['resources_with_traces'] = $resourcesWithTraces;\n        $dashboard['question_risks'] = $this->finalizeQuestionRisks(is_array($dashboard['question_stats'] ?? null) ? $dashboard['question_stats'] : []);\n        $dashboard['summary']['question_risk_count'] = count($dashboard['question_risks']);\n        $dashboard['summary']['questions_analyzed'] = count(is_array($dashboard['question_stats'] ?? null) ? $dashboard['question_stats'] : []);\n\n        uasort($dashboard['by_resource'], static function (array $left, array $right): int {",
'analytics finalize question risks');

patch_once($s,
"        unset($dashboard['learners'], $dashboard['score_sum'], $dashboard['score_count']);",
"        unset($dashboard['learners'], $dashboard['score_sum'], $dashboard['score_count'], $dashboard['question_stats']);",
'analytics unset question_stats');

$boolMethod = <<<'PHP'
    private function boolOrNull($value): ?bool
    {
        if (is_bool($value)) { return $value; }
        if (is_numeric($value)) { return ((int) $value) === 1; }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['1', 'true', 'yes', 'oui'], true)) { return true; }
            if (in_array($v, ['0', 'false', 'no', 'non'], true)) { return false; }
        }
        return null;
    }

PHP;
if (strpos($s, 'private function boolOrNull($value): ?bool') === false) {
    $marker = "    private function durationToSeconds(string $" . "duration): ?int";
    $pos = strpos($s, $marker);
    if ($pos === false) { fwrite(STDERR, "Point insertion boolOrNull introuvable\n"); exit(1); }
    $s = substr($s, 0, $pos) . $boolMethod . substr($s, $pos);
    echo "PATCH: analytics boolOrNull\n";
} else { echo "OK: analytics boolOrNull\n"; }
wf($analytics, $old, $s);

// 2) Affichage dans Tableau de bord et Analyse.
$old = rf($screen);
$s = $old;
patch_once($s,
"            . $this->renderPedagogicalSynthesis($dashboard)\n            . '<div class=\"itxeb-kpi-grid\">'",
"            . $this->renderPedagogicalSynthesis($dashboard)\n            . $this->renderQuestionFailureHotspots($dashboard)\n            . '<div class=\"itxeb-kpi-grid\">'",
'screen dashboard question hotspots');

patch_once($s,
"<li>Utiliser l’onglet Analyse IA pour générer ou comparer les synthèses IA.</li></ul></div><p style=\"color:#555\">Vue opérationnelle des ressources utilisées, peu utilisées, activées sans trace ou associées à des signaux pédagogiques.</p>' . $this->renderPeriodSelector('showCourseAnalysis') . $this->renderResourceFilter($course, 'showCourseAnalysis') . $this->renderAnalyticsWarning() . $this->renderTrainerActionSummary($dashboard) . $this->renderPedagogicalSynthesis($dashboard);",
"<li>Utiliser l’onglet Analyse IA pour générer ou comparer les synthèses IA.</li></ul></div><p style=\"color:#555\">Vue opérationnelle des ressources utilisées, peu utilisées, activées sans trace ou associées à des signaux pédagogiques.</p>' . $this->renderPeriodSelector('showCourseAnalysis') . $this->renderResourceFilter($course, 'showCourseAnalysis') . $this->renderAnalyticsWarning() . $this->renderTrainerActionSummary($dashboard) . $this->renderPedagogicalSynthesis($dashboard) . $this->renderQuestionFailureHotspots($dashboard);",
'screen analysis question hotspots');

$screenMethod = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderQuestionFailureHotspots(array $dashboard): string
    {
        $risks = is_array($dashboard['question_risks'] ?? null) ? $dashboard['question_risks'] : [];
        $html = '<section class="itxeb-cui-section itxeb-question-risks"><h3>Questions à fort taux d’échec</h3>';
        if (count($risks) === 0) {
            return $html . '<p><em>Aucune question à fort taux d’échec détectée sur la période sélectionnée.</em></p></section>';
        }
        $html .= '<p>Seules les questions problématiques sont remontées ici. Toutes les questions restent tracées dans TRAX et visibles côté Expert.</p>';
        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr>'
            . '<th>Priorité</th><th>Question</th><th>Test</th><th>Réponses</th><th>Échecs / non-réponses</th><th>Taux d’échec</th><th>Score moyen</th><th>Dernière trace</th>'
            . '</tr></thead><tbody>';
        foreach (array_slice($risks, 0, 10) as $risk) {
            if (!is_array($risk)) { continue; }
            $avg = ($risk['avg_score'] ?? null) === null ? '-' : (string) $risk['avg_score'] . ' %';
            $failure = is_numeric($risk['failure_rate'] ?? null) ? (string) $risk['failure_rate'] . ' %' : '-';
            $label = (string) ($risk['risk_label'] ?? 'À surveiller');
            $class = $label === 'Critique' ? 'itxeb-pedagogy-critical' : 'itxeb-pedagogy-watch';
            $html .= '<tr>'
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
        return $html . '</tbody></table></div></section>';
    }

PHP;
if (strpos($s, 'private function renderQuestionFailureHotspots(array $dashboard): string') === false) {
    $marker = "    private function pedagogicalBadgeClass(string $" . "status): string";
    $pos = strpos($s, $marker);
    if ($pos === false) { fwrite(STDERR, "Point insertion renderQuestionFailureHotspots introuvable\n"); exit(1); }
    $s = substr($s, 0, $pos) . $screenMethod . substr($s, $pos);
    echo "PATCH: screen renderQuestionFailureHotspots\n";
} else { echo "OK: screen renderQuestionFailureHotspots\n"; }
wf($screen, $old, $s);

// 3) Payload IA : inclure uniquement la liste filtrée des questions problématiques.
$old = rf($ai);
$s = $old;
patch_once($s,
"        $expertRows = is_array($dashboard['expert_rows'] ?? null) ? $dashboard['expert_rows'] : [];\n\n        $filteredResources = $this->filterResources($resources);",
"        $expertRows = is_array($dashboard['expert_rows'] ?? null) ? $dashboard['expert_rows'] : [];\n        $questionRisks = is_array($dashboard['question_risks'] ?? null) ? $dashboard['question_risks'] : [];\n\n        $filteredResources = $this->filterResources($resources);",
'ai question risks variable');
patch_once($s,
"            'resource_analysis' => $filteredResources,\n            'learner_risk_aggregate' => $this->aggregateLearnerRisks($expertRows),",
"            'resource_analysis' => $filteredResources,\n            'question_failure_analysis' => $this->filterQuestionRisks($questionRisks),\n            'learner_risk_aggregate' => $this->aggregateLearnerRisks($expertRows),",
'ai payload question risks');
$aiMethod = <<<'PHP'
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
if (strpos($s, 'private function filterQuestionRisks(array $risks): array') === false) {
    $marker = "    /**\n     * @param array<int|string,mixed> $" . "resources\n     * @return array<int,array<string,mixed>>\n     */\n    private function filterResources(array $" . "resources): array";
    $pos = strpos($s, $marker);
    if ($pos === false) { fwrite(STDERR, "Point insertion filterQuestionRisks introuvable\n"); exit(1); }
    $s = substr($s, 0, $pos) . $aiMethod . substr($s, $pos);
    echo "PATCH: ai filterQuestionRisks\n";
} else { echo "OK: ai filterQuestionRisks\n"; }
wf($ai, $old, $s);

set_version($plugin, '0.21.2-dev');
set_version($companionPlugin, '0.8.5');
if (!copy($screen, $liveScreen)) { fwrite(STDERR, "Copie live impossible: $liveScreen\n"); exit(1); }
if (!copy($companionPlugin, $livePlugin)) { fwrite(STDERR, "Copie live impossible: $livePlugin\n"); exit(1); }
echo "COPY: companion screen + plugin live\n";
echo "V0.21.2 appliquee : questions a fort taux d'echec visibles dans Tableau de bord, Analyse et payload IA.\n";
