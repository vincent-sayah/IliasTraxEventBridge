<?php
$root = getcwd();
$plugin = $root . '/plugin.php';
$factory = $root . '/classes/class.ilIliasTraxEventBridgeStatementFactory.php';
$router = $root . '/classes/class.ilIliasTraxEventBridgeEventRouter.php';
$extractor = $root . '/classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php';

function rf(string $file): string { $s = file_get_contents($file); if (!is_string($s)) { fwrite(STDERR, "Lecture impossible: $file\n"); exit(1); } return $s; }
function wf(string $file, string $old, string $new): void { if ($old !== $new) { file_put_contents($file, $new); echo "WRITE: $file\n"; } else { echo "OK: aucun changement $file\n"; } }
function rep(string &$s, string $old, string $new, string $label): void { if (strpos($s, $new) !== false) { echo "OK: $label\n"; return; } $pos = strpos($s, $old); if ($pos === false) { fwrite(STDERR, "BLOC INTROUVABLE: $label\n"); exit(1); } $s = substr($s, 0, $pos) . $new . substr($s, $pos + strlen($old)); echo "PATCH: $label\n"; }
function set_version(string $file, string $version): void { $old = rf($file); $new = preg_replace('/\$version = \'[^\']*\';/', '$version = \'' . $version . '\';', $old, 1); if (!is_string($new)) { fwrite(STDERR, "Version impossible: $file\n"); exit(1); } wf($file, $old, $new); }

if (!is_file($extractor)) { fwrite(STDERR, "Classe extracteur absente: $extractor\n"); exit(1); }

$old = rf($factory);
$s = $old;

$createMany = <<<'PHP'
    /**
     * @param array<string,mixed> $record
     * @return array<int,array<string,mixed>>
     */
    public function createStatementsFromEventRecord(array $record): array
    {
        $statements = [];
        $main = $this->createFromEventRecord($record);
        if ($main !== null) { $statements[] = $main; }
        foreach ($this->createQuestionResultStatements($record) as $questionStatement) { $statements[] = $questionStatement; }
        return $statements;
    }

PHP;
if (strpos($s, 'public function createStatementsFromEventRecord(array $record): array') === false) {
    $marker = "    /**\n     * @param array<string,mixed> $" . "record\n     * @return array<string,mixed>|null\n     */\n    public function createFromEventRecord(array $" . "record): ?array";
    $pos = strpos($s, $marker); if ($pos === false) { fwrite(STDERR, "Point insertion createStatementsFromEventRecord introuvable\n"); exit(1); }
    $s = substr($s, 0, $pos) . $createMany . substr($s, $pos);
    echo "PATCH: createStatementsFromEventRecord\n";
} else { echo "OK: createStatementsFromEventRecord\n"; }

$questionMethods = <<<'PHP'
    /** @param array<string,mixed> $record @return array<int,array<string,mixed>> */
    private function createQuestionResultStatements(array $record): array
    {
        $component = (string) ($record['component'] ?? '');
        $event = (string) ($record['event_name'] ?? '');
        if ($component !== 'components/ILIAS/Tracking' || $event !== 'updateStatus' || !$this->isTestTrackingEvent($record)) { return []; }
        $payload = $this->decodePayload((string) ($record['payload_json'] ?? ''));
        $status = (int) ($payload['status'] ?? -1);
        $percentage = (float) ($payload['percentage'] ?? 0);
        if (!($status === 2 || $status === 3 || $percentage >= 100)) { return []; }
        require_once __DIR__ . '/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php';
        $questions = (new ilIliasTraxEventBridgeTestQuestionResultExtractor())->extract($record);
        $out = [];
        foreach ($questions as $question) { $out[] = $this->createQuestionResultStatement($record, $question); }
        return $out;
    }

    /** @param array<string,mixed> $record @param array<string,mixed> $question */
    private function createQuestionResultStatement(array $record, array $question): array
    {
        $baseUrl = $this->config->getIliasBaseUrl();
        $refId = (int) ($record['ref_id'] ?? 0);
        $objId = (int) ($record['obj_id'] ?? 0);
        $questionId = (int) ($question['question_id'] ?? 0);
        $questionTitle = trim((string) ($question['question_title'] ?? 'Question ' . $questionId));
        $testTitle = $this->resolveObjectTitle($record, 'Test ILIAS ' . ($refId > 0 ? 'ref_id ' . $refId : 'obj_id ' . $objId));
        $scorePercent = $question['score_percent'] ?? null;
        $points = (float) ($question['points'] ?? 0);
        $maxPoints = (float) ($question['max_points'] ?? 0);
        $result = ['completion' => true, 'extensions' => [
            $baseUrl . '/xapi/extensions/question_id' => $questionId,
            $baseUrl . '/xapi/extensions/question_title' => $questionTitle,
            $baseUrl . '/xapi/extensions/question_type' => (string) ($question['question_type'] ?? ''),
            $baseUrl . '/xapi/extensions/question_points' => $points,
            $baseUrl . '/xapi/extensions/question_max_points' => $maxPoints,
            $baseUrl . '/xapi/extensions/question_score_percent' => is_numeric($scorePercent) ? (float) $scorePercent : null,
            $baseUrl . '/xapi/extensions/test_active_id' => (int) ($question['active_id'] ?? 0),
            $baseUrl . '/xapi/extensions/test_pass' => (int) ($question['pass'] ?? 0),
            $baseUrl . '/xapi/extensions/question_result_source' => (string) ($question['source_table'] ?? ''),
        ]];
        if (is_numeric($scorePercent)) { $result['score'] = ['scaled' => max(0, min(1, ((float) $scorePercent) / 100)), 'raw' => (float) $scorePercent, 'min' => 0, 'max' => 100]; }
        if (is_bool($question['success'] ?? null)) { $result['success'] = (bool) $question['success']; }
        return [
            'id' => $this->uuid4(),
            'actor' => $this->actor((int) ($record['user_id'] ?? 0)),
            'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/answered', 'display' => ['fr-FR' => 'a répondu à la question', 'en-US' => 'answered question']],
            'object' => ['id' => rtrim($this->activityId('tst', $refId, $objId), '/') . '/question/' . max(0, $questionId), 'objectType' => 'Activity', 'definition' => $this->activityDefinition('http://adlnet.gov/expapi/activities/cmi.interaction', $testTitle . ' — ' . $questionTitle, $this->objectUrl('tst', $refId), 'Résultat d’une question du test ILIAS', 'ILIAS test question result')],
            'result' => $result,
            'context' => $this->context($record, 'test_question_result', 'tst'),
            'timestamp' => $this->isoTimestamp((string) ($record['created_at'] ?? '')),
        ];
    }

PHP;
if (strpos($s, 'private function createQuestionResultStatements(array $record): array') === false) {
    $marker = "    /**\n     * @param array<string,mixed> $" . "record\n     * @return array<string,mixed>\n     */\n    private function context(array $" . "record, string $" . "sourceEvent, string $" . "objType): array";
    $pos = strpos($s, $marker); if ($pos === false) { fwrite(STDERR, "Point insertion méthodes question introuvable\n"); exit(1); }
    $s = substr($s, 0, $pos) . $questionMethods . substr($s, $pos);
    echo "PATCH: méthodes questions\n";
} else { echo "OK: méthodes questions\n"; }

rep($s, "        if ($" . "sourceEvent === 'test_tracking_status' || $" . "objType === 'tst') {\n            return 'test_tracking';\n        }", "        if ($" . "sourceEvent === 'test_question_result') {\n            return 'test_question_result';\n        }\n\n        if ($" . "sourceEvent === 'test_tracking_status' || $" . "objType === 'tst') {\n            return 'test_tracking';\n        }", 'statementFamily question');
rep($s, "        if ($" . "sourceEvent === 'test_tracking_status' || $" . "objType === 'tst') {\n            return 'assessment_progress';\n        }", "        if ($" . "sourceEvent === 'test_question_result') {\n            return 'assessment_question';\n        }\n\n        if ($" . "sourceEvent === 'test_tracking_status' || $" . "objType === 'tst') {\n            return 'assessment_progress';\n        }", 'interactionType question');
wf($factory, $old, $s);

$old = rf($router);
$r = $old;
rep($r,
    "        $" . "statement = $" . "this->statementFactory->createFromEventRecord($" . "record);\n        if ($" . "statement !== null) {\n            $" . "this->outboxRepository->enqueue($" . "record, $" . "statement, $" . "eventLogId);\n        } else {\n            $" . "this->logDeniedTrace('unsupported_object_type', $" . "record, 'evnt_evhk_itxeb_log', $" . "eventLogId);\n        }",
    "        $" . "statements = method_exists($" . "this->statementFactory, 'createStatementsFromEventRecord')\n            ? $" . "this->statementFactory->createStatementsFromEventRecord($" . "record)\n            : [$" . "this->statementFactory->createFromEventRecord($" . "record)];\n        $" . "enqueued = 0;\n        foreach ($" . "statements as $" . "statement) {\n            if (is_array($" . "statement)) {\n                $" . "this->outboxRepository->enqueue($" . "record, $" . "statement, $" . "eventLogId);\n                $" . "enqueued++;\n            }\n        }\n        if ($" . "enqueued === 0) {\n            $" . "this->logDeniedTrace('unsupported_object_type', $" . "record, 'evnt_evhk_itxeb_log', $" . "eventLogId);\n        }",
    'router multi-statements'
);
wf($router, $old, $r);
set_version($plugin, '0.21.0-dev');
echo "V0.21.0 appliquee : traces par question de test activees.\n";
