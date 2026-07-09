<?php
$root = getcwd();
$plugin = $root . '/plugin.php';
$factory = $root . '/classes/class.ilIliasTraxEventBridgeStatementFactory.php';
$router = $root . '/classes/class.ilIliasTraxEventBridgeEventRouter.php';
$extractor = $root . '/classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php';

function rf(string $file): string {
    $s = file_get_contents($file);
    if (!is_string($s)) {
        fwrite(STDERR, "Lecture impossible: $file\n");
        exit(1);
    }
    return $s;
}
function wf(string $file, string $old, string $new): void {
    if ($old !== $new) {
        file_put_contents($file, $new);
        echo "WRITE: $file\n";
    } else {
        echo "OK: aucun changement $file\n";
    }
}
function rep(string &$s, string $old, string $new, string $label): void {
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
function set_version(string $file, string $version): void {
    $old = rf($file);
    $new = preg_replace('/\$version = \'[^\']*\';/', '$version = \' . "'" . $version . "';", $old, 1);
    if (!is_string($new)) { fwrite(STDERR, "Version impossible: $file\n"); exit(1); }
    wf($file, $old, $new);
}

$extractorCode = <<<'PHP'
<?php

/**
 * V0.21.0: lecture robuste des résultats détaillés des questions d'un test ILIAS.
 *
 * La classe est volontairement défensive : selon les versions/configurations ILIAS,
 * certaines colonnes peuvent différer. Si le schéma local ne correspond pas, elle
 * retourne simplement une liste vide pour ne jamais bloquer la navigation ILIAS.
 */
class ilIliasTraxEventBridgeTestQuestionResultExtractor
{
    /** @var ilDBInterface|mixed|null */
    private $db;
    /** @var array<string,array<int,string>> */
    private array $columnsCache = [];

    public function __construct()
    {
        if (isset($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'database')) {
            $this->db = $GLOBALS['DIC']->database();
        } elseif (isset($GLOBALS['ilDB'])) {
            $this->db = $GLOBALS['ilDB'];
        } else {
            $this->db = null;
        }
    }

    /**
     * @param array<string,mixed> $record
     * @return array<int,array<string,mixed>>
     */
    public function extract(array $record): array
    {
        if ($this->db === null) {
            return [];
        }

        $testObjId = (int) ($record['obj_id'] ?? 0);
        $testRefId = (int) ($record['ref_id'] ?? 0);
        if ($testObjId <= 0 && $testRefId > 0) {
            $testObjId = $this->lookupObjectIdByRefId($testRefId);
        }
        if ($testObjId <= 0) {
            return [];
        }

        $userId = $this->detectUserId($record);
        if ($userId <= 0) {
            return [];
        }

        $testIds = $this->resolveTestIds($testObjId);
        if ($testIds === []) {
            $testIds = [$testObjId];
        }

        foreach ($this->findActiveRows($testIds, $userId) as $activeRow) {
            $activeId = $this->rowInt($activeRow, ['active_id', 'active_fi', 'id']);
            if ($activeId <= 0) { continue; }
            $pass = $this->detectPass($activeId, $activeRow);
            $questionRows = $this->findQuestionRows($activeId, $pass);
            if ($questionRows !== []) {
                return $this->normalizeQuestionRows($questionRows, $record, $activeId, $pass, $testObjId, $testRefId);
            }
        }

        return [];
    }

    /** @param array<string,mixed> $record */
    private function detectUserId(array $record): int
    {
        $payload = json_decode((string) ($record['payload_json'] ?? ''), true);
        if (is_array($payload)) {
            foreach (['usr_id', 'user_id', 'userId'] as $key) {
                if (isset($payload[$key]) && is_numeric($payload[$key])) {
                    return (int) $payload[$key];
                }
            }
        }
        return (int) ($record['user_id'] ?? 0);
    }

    /** @return array<int,int> */
    private function resolveTestIds(int $testObjId): array
    {
        if (!$this->tableExists('tst_tests')) {
            return [];
        }
        $idColumn = $this->firstExistingColumn('tst_tests', ['test_id', 'id']);
        $objColumn = $this->firstExistingColumn('tst_tests', ['obj_fi', 'obj_id']);
        if ($idColumn === '' || $objColumn === '') {
            return [];
        }
        return $this->fetchIntColumn('SELECT ' . $idColumn . ' FROM tst_tests WHERE ' . $objColumn . ' = ' . (int) $testObjId, $idColumn);
    }

    /** @param array<int,int> $testIds @return array<int,array<string,mixed>> */
    private function findActiveRows(array $testIds, int $userId): array
    {
        if (!$this->tableExists('tst_active')) { return []; }
        $activeColumn = $this->firstExistingColumn('tst_active', ['active_id', 'id']);
        $testColumn = $this->firstExistingColumn('tst_active', ['test_fi', 'test_id']);
        $userColumn = $this->firstExistingColumn('tst_active', ['user_fi', 'usr_id', 'user_id']);
        if ($activeColumn === '' || $testColumn === '' || $userColumn === '') { return []; }

        $ids = implode(',', array_map('intval', array_values(array_unique($testIds))));
        $query = 'SELECT * FROM tst_active WHERE ' . $testColumn . ' IN (' . $ids . ') AND ' . $userColumn . ' = ' . (int) $userId . ' ORDER BY ' . $activeColumn . ' DESC';
        if (method_exists($this->db, 'setLimit')) { $this->db->setLimit(5); }
        return $this->fetchRows($query, 5);
    }

    /** @param array<string,mixed> $activeRow */
    private function detectPass(int $activeId, array $activeRow): int
    {
        foreach (['last_finished_pass', 'last_pass', 'pass', 'tries'] as $column) {
            if (array_key_exists($column, $activeRow) && is_numeric($activeRow[$column])) {
                return max(0, (int) $activeRow[$column]);
            }
        }
        $resultInfo = $this->resultTableInfo();
        if ($resultInfo === []) { return 0; }
        $passColumn = (string) ($resultInfo['pass_column'] ?? '');
        if ($passColumn === '') { return 0; }
        $rows = $this->fetchRows('SELECT MAX(' . $passColumn . ') max_pass FROM ' . $resultInfo['table'] . ' WHERE ' . $resultInfo['active_column'] . ' = ' . (int) $activeId, 1);
        return isset($rows[0]['max_pass']) && is_numeric($rows[0]['max_pass']) ? max(0, (int) $rows[0]['max_pass']) : 0;
    }

    /** @return array<int,array<string,mixed>> */
    private function findQuestionRows(int $activeId, int $pass): array
    {
        $info = $this->resultTableInfo();
        if ($info === []) { return []; }
        $table = (string) $info['table'];
        $activeColumn = (string) $info['active_column'];
        $passColumn = (string) ($info['pass_column'] ?? '');
        $where = $activeColumn . ' = ' . (int) $activeId;
        if ($passColumn !== '') {
            $where .= ' AND ' . $passColumn . ' = ' . (int) $pass;
        }
        $query = 'SELECT * FROM ' . $table . ' WHERE ' . $where;
        return $this->fetchRows($query, 500);
    }

    /** @return array<string,string> */
    private function resultTableInfo(): array
    {
        foreach (['tst_pass_result', 'tst_test_result', 'tst_result_cache'] as $table) {
            if (!$this->tableExists($table)) { continue; }
            $activeColumn = $this->firstExistingColumn($table, ['active_fi', 'active_id']);
            $questionColumn = $this->firstExistingColumn($table, ['question_fi', 'question_id', 'qid']);
            if ($activeColumn === '' || $questionColumn === '') { continue; }
            return [
                'table' => $table,
                'active_column' => $activeColumn,
                'question_column' => $questionColumn,
                'pass_column' => $this->firstExistingColumn($table, ['pass', 'pass_fi']),
                'points_column' => $this->firstExistingColumn($table, ['points', 'reached_points', 'received_points', 'score']),
                'max_points_column' => $this->firstExistingColumn($table, ['maxpoints', 'max_points', 'points_max', 'maximum_points']),
            ];
        }
        return [];
    }

    /** @param array<int,array<string,mixed>> $rows @param array<string,mixed> $record @return array<int,array<string,mixed>> */
    private function normalizeQuestionRows(array $rows, array $record, int $activeId, int $pass, int $testObjId, int $testRefId): array
    {
        $info = $this->resultTableInfo();
        if ($info === []) { return []; }
        $questionColumn = (string) $info['question_column'];
        $pointsColumn = (string) ($info['points_column'] ?? '');
        $maxPointsColumn = (string) ($info['max_points_column'] ?? '');
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $questionId = isset($row[$questionColumn]) && is_numeric($row[$questionColumn]) ? (int) $row[$questionColumn] : 0;
            if ($questionId <= 0 || isset($seen[$questionId])) { continue; }
            $seen[$questionId] = true;
            $question = $this->questionInfo($questionId);
            $points = $pointsColumn !== '' && isset($row[$pointsColumn]) && is_numeric($row[$pointsColumn]) ? (float) $row[$pointsColumn] : 0.0;
            $maxPoints = $maxPointsColumn !== '' && isset($row[$maxPointsColumn]) && is_numeric($row[$maxPointsColumn]) ? (float) $row[$maxPointsColumn] : (float) ($question['max_points'] ?? 0.0);
            $scorePercent = $maxPoints > 0 ? round(($points / $maxPoints) * 100, 2) : null;
            $success = $scorePercent === null ? null : $scorePercent >= 50.0;
            $out[] = [
                'question_id' => $questionId,
                'question_title' => (string) ($question['title'] ?? ('Question ' . $questionId)),
                'question_type' => (string) ($question['type'] ?? ''),
                'points' => $points,
                'max_points' => $maxPoints,
                'score_percent' => $scorePercent,
                'success' => $success,
                'active_id' => $activeId,
                'pass' => $pass,
                'test_obj_id' => $testObjId,
                'test_ref_id' => $testRefId,
                'source_table' => (string) $info['table'],
            ];
        }
        return $out;
    }

    /** @return array<string,mixed> */
    private function questionInfo(int $questionId): array
    {
        if (!$this->tableExists('qpl_questions')) {
            return ['title' => 'Question ' . $questionId, 'max_points' => 0.0, 'type' => ''];
        }
        $idColumn = $this->firstExistingColumn('qpl_questions', ['question_id', 'id']);
        if ($idColumn === '') { return ['title' => 'Question ' . $questionId, 'max_points' => 0.0, 'type' => '']; }
        $rows = $this->fetchRows('SELECT * FROM qpl_questions WHERE ' . $idColumn . ' = ' . (int) $questionId, 1);
        $row = $rows[0] ?? [];
        $title = '';
        foreach (['title', 'question_title', 'label'] as $column) {
            if (isset($row[$column]) && is_scalar($row[$column]) && trim((string) $row[$column]) !== '') { $title = trim((string) $row[$column]); break; }
        }
        $max = 0.0;
        foreach (['points', 'maxpoints', 'max_points'] as $column) {
            if (isset($row[$column]) && is_numeric($row[$column])) { $max = (float) $row[$column]; break; }
        }
        $type = '';
        foreach (['question_type_fi', 'question_type', 'type'] as $column) {
            if (isset($row[$column]) && is_scalar($row[$column])) { $type = (string) $row[$column]; break; }
        }
        return ['title' => $title !== '' ? $title : 'Question ' . $questionId, 'max_points' => $max, 'type' => $type];
    }

    private function tableExists(string $table): bool
    {
        try { return method_exists($this->db, 'tableExists') && $this->db->tableExists($table); } catch (Throwable $e) { return false; }
    }

    /** @param array<int,string> $candidates */
    private function firstExistingColumn(string $table, array $candidates): string
    {
        $columns = $this->columns($table);
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) { return $candidate; }
        }
        return '';
    }

    /** @return array<int,string> */
    private function columns(string $table): array
    {
        if (isset($this->columnsCache[$table])) { return $this->columnsCache[$table]; }
        $columns = [];
        foreach (['SHOW COLUMNS FROM ' . $table, 'DESCRIBE ' . $table] as $query) {
            try {
                $set = $this->db->query($query);
                while ($row = $this->db->fetchAssoc($set)) {
                    if (isset($row['Field']) && is_scalar($row['Field'])) { $columns[] = (string) $row['Field']; }
                    elseif (isset($row['field']) && is_scalar($row['field'])) { $columns[] = (string) $row['field']; }
                }
                if ($columns !== []) { break; }
            } catch (Throwable $ignored) {}
        }
        $this->columnsCache[$table] = array_values(array_unique($columns));
        return $this->columnsCache[$table];
    }

    /** @return array<int,array<string,mixed>> */
    private function fetchRows(string $query, int $limit): array
    {
        $rows = [];
        try {
            if (method_exists($this->db, 'setLimit')) { $this->db->setLimit(max(1, $limit)); }
            $set = $this->db->query($query);
            $count = 0;
            while (($row = $this->db->fetchAssoc($set)) && $count < $limit) { if (is_array($row)) { $rows[] = $row; $count++; } }
        } catch (Throwable $ignored) {}
        return $rows;
    }

    /** @return array<int,int> */
    private function fetchIntColumn(string $query, string $column): array
    {
        $values = [];
        foreach ($this->fetchRows($query, 50) as $row) {
            if (isset($row[$column]) && is_numeric($row[$column])) { $values[] = (int) $row[$column]; }
        }
        return array_values(array_unique(array_filter($values, static fn(int $v): bool => $v > 0)));
    }

    /** @param array<string,mixed> $row @param array<int,string> $keys */
    private function rowInt(array $row, array $keys): int
    {
        foreach ($keys as $key) { if (isset($row[$key]) && is_numeric($row[$key])) { return (int) $row[$key]; } }
        return 0;
    }

    private function lookupObjectIdByRefId(int $refId): int
    {
        if ($refId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupObjectId')) { return 0; }
        try { return (int) ilObject::_lookupObjectId($refId); } catch (Throwable $ignored) { return 0; }
    }
}
PHP;

$oldExtractor = is_file($extractor) ? rf($extractor) : '';
wf($extractor, $oldExtractor, $extractorCode);

$old = rf($factory);
$s = $old;

$method = <<<'PHP'
    /**
     * @param array<string,mixed> $record
     * @return array<int,array<string,mixed>>
     */
    public function createStatementsFromEventRecord(array $record): array
    {
        $statements = [];
        $main = $this->createFromEventRecord($record);
        if ($main !== null) {
            $statements[] = $main;
        }
        foreach ($this->createQuestionResultStatements($record) as $questionStatement) {
            $statements[] = $questionStatement;
        }
        return $statements;
    }

PHP;
if (strpos($s, 'public function createStatementsFromEventRecord(array $record): array') === false) {
    $marker = "    /**\n     * @param array<string,mixed> $" . "record\n     * @return array<string,mixed>|null\n     */\n    public function createFromEventRecord(array $" . "record): ?array";
    $pos = strpos($s, $marker);
    if ($pos === false) { fwrite(STDERR, "Point insertion createStatementsFromEventRecord introuvable\n"); exit(1); }
    $s = substr($s, 0, $pos) . $method . substr($s, $pos);
    echo "PATCH: createStatementsFromEventRecord\n";
} else { echo "OK: createStatementsFromEventRecord\n"; }

$questionMethods = <<<'PHP'
    /**
     * @param array<string,mixed> $record
     * @return array<int,array<string,mixed>>
     */
    private function createQuestionResultStatements(array $record): array
    {
        $component = (string) ($record['component'] ?? '');
        $event = (string) ($record['event_name'] ?? '');
        if ($component !== 'components/ILIAS/Tracking' || $event !== 'updateStatus' || !$this->isTestTrackingEvent($record)) {
            return [];
        }
        $payload = $this->decodePayload((string) ($record['payload_json'] ?? ''));
        $status = (int) ($payload['status'] ?? -1);
        $percentage = (float) ($payload['percentage'] ?? 0);
        if (!($status === 2 || $status === 3 || $percentage >= 100)) {
            return [];
        }
        $path = __DIR__ . '/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php';
        if (is_file($path)) { require_once $path; }
        if (!class_exists('ilIliasTraxEventBridgeTestQuestionResultExtractor')) {
            return [];
        }
        $questions = (new ilIliasTraxEventBridgeTestQuestionResultExtractor())->extract($record);
        $statements = [];
        foreach ($questions as $question) {
            $statements[] = $this->createQuestionResultStatement($record, $question);
        }
        return $statements;
    }

    /** @param array<string,mixed> $record @param array<string,mixed> $question */
    private function createQuestionResultStatement(array $record, array $question): array
    {
        $baseUrl = $this->config->getIliasBaseUrl();
        $refId = (int) ($record['ref_id'] ?? 0);
        $objId = (int) ($record['obj_id'] ?? 0);
        $questionId = (int) ($question['question_id'] ?? 0);
        $questionTitle = trim((string) ($question['question_title'] ?? ''));
        if ($questionTitle === '') { $questionTitle = 'Question ' . $questionId; }
        $testTitle = $this->resolveObjectTitle($record, 'Test ILIAS ' . ($refId > 0 ? 'ref_id ' . $refId : 'obj_id ' . $objId));
        $scorePercent = $question['score_percent'] ?? null;
        $points = (float) ($question['points'] ?? 0);
        $maxPoints = (float) ($question['max_points'] ?? 0);
        $success = $question['success'] ?? null;
        $result = [
            'completion' => true,
            'extensions' => [
                $baseUrl . '/xapi/extensions/question_id' => $questionId,
                $baseUrl . '/xapi/extensions/question_title' => $questionTitle,
                $baseUrl . '/xapi/extensions/question_type' => (string) ($question['question_type'] ?? ''),
                $baseUrl . '/xapi/extensions/question_points' => $points,
                $baseUrl . '/xapi/extensions/question_max_points' => $maxPoints,
                $baseUrl . '/xapi/extensions/question_score_percent' => is_numeric($scorePercent) ? (float) $scorePercent : null,
                $baseUrl . '/xapi/extensions/test_active_id' => (int) ($question['active_id'] ?? 0),
                $baseUrl . '/xapi/extensions/test_pass' => (int) ($question['pass'] ?? 0),
                $baseUrl . '/xapi/extensions/question_result_source' => (string) ($question['source_table'] ?? ''),
            ]
        ];
        if (is_numeric($scorePercent)) {
            $result['score'] = ['scaled' => max(0, min(1, ((float) $scorePercent) / 100)), 'raw' => (float) $scorePercent, 'min' => 0, 'max' => 100];
        }
        if (is_bool($success)) {
            $result['success'] = $success;
        }
        $statement = [
            'id' => $this->uuid4(),
            'actor' => $this->actor((int) ($record['user_id'] ?? 0)),
            'verb' => $this->questionResultVerb(),
            'object' => [
                'id' => rtrim($this->activityId('tst', $refId, $objId), '/') . '/question/' . max(0, $questionId),
                'objectType' => 'Activity',
                'definition' => $this->activityDefinition(
                    'http://adlnet.gov/expapi/activities/cmi.interaction',
                    $testTitle . ' — ' . $questionTitle,
                    $this->objectUrl('tst', $refId),
                    'Résultat d’une question du test ILIAS',
                    'ILIAS test question result'
                )
            ],
            'result' => $result,
            'context' => $this->context($record, 'test_question_result', 'tst'),
            'timestamp' => $this->isoTimestamp((string) ($record['created_at'] ?? '')),
        ];
        return $statement;
    }

    /** @return array<string,mixed> */
    private function questionResultVerb(): array
    {
        return [
            'id' => 'http://adlnet.gov/expapi/verbs/answered',
            'display' => ['fr-FR' => 'a répondu à la question', 'en-US' => 'answered question']
        ];
    }

PHP;
if (strpos($s, 'private function createQuestionResultStatements(array $record): array') === false) {
    $marker = "    /**\n     * @param array<string,mixed> $" . "record\n     * @return array<string,mixed>\n     */\n    private function context(array $" . "record, string $" . "sourceEvent, string $" . "objType): array";
    $pos = strpos($s, $marker);
    if ($pos === false) { fwrite(STDERR, "Point insertion méthodes question introuvable\n"); exit(1); }
    $s = substr($s, 0, $pos) . $questionMethods . substr($s, $pos);
    echo "PATCH: méthodes statements questions\n";
} else { echo "OK: méthodes statements questions\n"; }

rep($s,
    "        if ($" . "sourceEvent === 'test_tracking_status' || $" . "objType === 'tst') {\n            return 'test_tracking';\n        }",
    "        if ($" . "sourceEvent === 'test_question_result') {\n            return 'test_question_result';\n        }\n\n        if ($" . "sourceEvent === 'test_tracking_status' || $" . "objType === 'tst') {\n            return 'test_tracking';\n        }",
    'statementFamily question'
);
rep($s,
    "        if ($" . "sourceEvent === 'test_tracking_status' || $" . "objType === 'tst') {\n            return 'assessment_progress';\n        }",
    "        if ($" . "sourceEvent === 'test_question_result') {\n            return 'assessment_question';\n        }\n\n        if ($" . "sourceEvent === 'test_tracking_status' || $" . "objType === 'tst') {\n            return 'assessment_progress';\n        }",
    'interactionType question'
);
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

echo "V0.21.0 appliquee : generation de traces par question de test activee\n";
