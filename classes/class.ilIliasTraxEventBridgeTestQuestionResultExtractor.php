<?php

/**
 * V0.21.1 : extraction des résultats par question d'un test ILIAS 10.
 *
 * Schéma validé sur l'instance :
 * - tst_tests(test_id, obj_fi)
 * - tst_active(active_id, user_fi, test_fi, last_finished_pass, tries)
 * - tst_test_result(active_fi, question_fi, points, pass, answered, test_result_id)
 * - qpl_questions(question_id, title, points, question_type_fi)
 */
class ilIliasTraxEventBridgeTestQuestionResultExtractor
{
    /** @var ilDBInterface|mixed|null */
    private $db;

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

    /** @param array<string,mixed> $record @return array<int,array<string,mixed>> */
    public function extract(array $record): array
    {
        if ($this->db === null || !$this->requiredTablesExist()) {
            return [];
        }

        $testRefId = (int) ($record['ref_id'] ?? 0);
        $testObjId = (int) ($record['obj_id'] ?? 0);
        if ($testObjId <= 0 && $testRefId > 0) {
            $testObjId = $this->lookupObjectIdByRefId($testRefId);
        }
        $userId = $this->detectUserId($record);
        if ($testObjId <= 0 || $userId <= 0) {
            return [];
        }

        $testId = $this->findTestId($testObjId);
        if ($testId <= 0) {
            return [];
        }

        $active = $this->findActiveRow($testId, $userId);
        if ($active === []) {
            return [];
        }

        $activeId = (int) ($active['active_id'] ?? 0);
        if ($activeId <= 0) {
            return [];
        }

        $pass = $this->detectPass($activeId, $active);
        $rows = $this->findQuestionResultRows($activeId, $pass);
        if ($rows === [] && $pass > 0) {
            $pass = $this->maxResultPass($activeId);
            $rows = $this->findQuestionResultRows($activeId, $pass);
        }

        return $this->normalizeRows($rows, $activeId, $pass, $testId, $testObjId, $testRefId);
    }

    private function requiredTablesExist(): bool
    {
        return $this->tableExists('tst_tests')
            && $this->tableExists('tst_active')
            && $this->tableExists('tst_test_result')
            && $this->tableExists('qpl_questions');
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

    private function findTestId(int $testObjId): int
    {
        $rows = $this->rows('SELECT test_id FROM tst_tests WHERE obj_fi = ' . (int) $testObjId, 1);
        return isset($rows[0]['test_id']) && is_numeric($rows[0]['test_id']) ? (int) $rows[0]['test_id'] : 0;
    }

    /** @return array<string,mixed> */
    private function findActiveRow(int $testId, int $userId): array
    {
        $rows = $this->rows(
            'SELECT * FROM tst_active WHERE test_fi = ' . (int) $testId
            . ' AND user_fi = ' . (int) $userId
            . ' ORDER BY active_id DESC',
            1
        );
        return $rows[0] ?? [];
    }

    /** @param array<string,mixed> $active */
    private function detectPass(int $activeId, array $active): int
    {
        if (isset($active['last_finished_pass']) && is_numeric($active['last_finished_pass']) && (int) $active['last_finished_pass'] >= 0) {
            return (int) $active['last_finished_pass'];
        }
        return $this->maxResultPass($activeId);
    }

    private function maxResultPass(int $activeId): int
    {
        $rows = $this->rows('SELECT MAX(pass) max_pass FROM tst_test_result WHERE active_fi = ' . (int) $activeId, 1);
        return isset($rows[0]['max_pass']) && is_numeric($rows[0]['max_pass']) ? max(0, (int) $rows[0]['max_pass']) : 0;
    }

    /** @return array<int,array<string,mixed>> */
    private function findQuestionResultRows(int $activeId, int $pass): array
    {
        return $this->rows(
            'SELECT r.test_result_id, r.active_fi, r.question_fi, r.points, r.pass, r.answered, r.tstamp, '
            . 'q.title, q.points AS max_points, q.question_type_fi '
            . 'FROM tst_test_result r '
            . 'LEFT JOIN qpl_questions q ON q.question_id = r.question_fi '
            . 'WHERE r.active_fi = ' . (int) $activeId . ' AND r.pass = ' . (int) $pass . ' '
            . 'ORDER BY r.test_result_id DESC',
            1000
        );
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private function normalizeRows(array $rows, int $activeId, int $pass, int $testId, int $testObjId, int $testRefId): array
    {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $questionId = isset($row['question_fi']) && is_numeric($row['question_fi']) ? (int) $row['question_fi'] : 0;
            if ($questionId <= 0 || isset($seen[$questionId])) {
                continue;
            }
            $seen[$questionId] = true;
            $points = isset($row['points']) && is_numeric($row['points']) ? (float) $row['points'] : 0.0;
            $maxPoints = isset($row['max_points']) && is_numeric($row['max_points']) ? (float) $row['max_points'] : 0.0;
            $scorePercent = $maxPoints > 0 ? round(($points / $maxPoints) * 100, 2) : null;
            $answered = isset($row['answered']) ? ((int) $row['answered'] === 1) : null;
            $success = $scorePercent === null ? null : ($points >= $maxPoints && $maxPoints > 0);
            $out[] = [
                'question_id' => $questionId,
                'question_title' => $this->safeTitle((string) ($row['title'] ?? ''), $questionId),
                'question_type' => (string) ($row['question_type_fi'] ?? ''),
                'points' => $points,
                'max_points' => $maxPoints,
                'score_percent' => $scorePercent,
                'answered' => $answered,
                'success' => $success,
                'active_id' => $activeId,
                'pass' => $pass,
                'test_id' => $testId,
                'test_obj_id' => $testObjId,
                'test_ref_id' => $testRefId,
                'test_result_id' => (int) ($row['test_result_id'] ?? 0),
                'source_table' => 'tst_test_result',
            ];
        }
        return array_reverse($out);
    }

    private function safeTitle(string $title, int $questionId): string
    {
        $title = trim(strip_tags($title));
        return $title !== '' ? $title : 'Question ' . $questionId;
    }

    private function tableExists(string $table): bool
    {
        try {
            return method_exists($this->db, 'tableExists') && $this->db->tableExists($table);
        } catch (Throwable $ignored) {
            return false;
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(string $sql, int $limit): array
    {
        $rows = [];
        try {
            if (method_exists($this->db, 'setLimit')) {
                $this->db->setLimit(max(1, $limit));
            }
            $set = $this->db->query($sql);
            $count = 0;
            while (($row = $this->db->fetchAssoc($set)) && $count < $limit) {
                if (is_array($row)) {
                    $rows[] = $row;
                    $count++;
                }
            }
        } catch (Throwable $ignored) {
            return [];
        }
        return $rows;
    }

    private function lookupObjectIdByRefId(int $refId): int
    {
        if ($refId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupObjectId')) {
            return 0;
        }
        try {
            return (int) ilObject::_lookupObjectId($refId);
        } catch (Throwable $ignored) {
            return 0;
        }
    }
}
