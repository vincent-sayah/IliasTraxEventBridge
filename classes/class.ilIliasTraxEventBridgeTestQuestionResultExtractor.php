<?php

/**
 * V0.21.0 : extraction défensive des résultats par question d'un test ILIAS.
 *
 * Objectif : alimenter TRAX avec une trace par question sans bloquer ILIAS si le
 * schéma local diffère. Si une table/colonne attendue est absente, la classe
 * retourne simplement une liste vide.
 */
class ilIliasTraxEventBridgeTestQuestionResultExtractor
{
    /** @var ilDBInterface|mixed|null */
    private $db;
    /** @var array<string,array<int,string>> */
    private array $columns = [];

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
        if ($this->db === null) { return []; }

        $refId = (int) ($record['ref_id'] ?? 0);
        $objId = (int) ($record['obj_id'] ?? 0);
        if ($objId <= 0 && $refId > 0) { $objId = $this->lookupObjectIdByRefId($refId); }
        $userId = $this->userId($record);
        if ($objId <= 0 || $userId <= 0) { return []; }

        $testIds = $this->testIds($objId);
        if ($testIds === []) { $testIds = [$objId]; }

        foreach ($this->activeRows($testIds, $userId) as $active) {
            $activeId = $this->firstInt($active, ['active_id', 'id']);
            if ($activeId <= 0) { continue; }
            $pass = $this->passForActive($activeId, $active);
            $rows = $this->questionRows($activeId, $pass);
            if ($rows !== []) { return $this->normalize($rows, $activeId, $pass, $refId, $objId); }
        }
        return [];
    }

    /** @param array<string,mixed> $record */
    private function userId(array $record): int
    {
        $payload = json_decode((string) ($record['payload_json'] ?? ''), true);
        if (is_array($payload)) {
            foreach (['usr_id', 'user_id', 'userId'] as $k) {
                if (isset($payload[$k]) && is_numeric($payload[$k])) { return (int) $payload[$k]; }
            }
        }
        return (int) ($record['user_id'] ?? 0);
    }

    /** @return array<int,int> */
    private function testIds(int $objId): array
    {
        if (!$this->tableExists('tst_tests')) { return []; }
        $id = $this->column('tst_tests', ['test_id', 'id']);
        $obj = $this->column('tst_tests', ['obj_fi', 'obj_id']);
        if ($id === '' || $obj === '') { return []; }
        return $this->intColumn('SELECT ' . $id . ' FROM tst_tests WHERE ' . $obj . ' = ' . $objId, $id);
    }

    /** @param array<int,int> $testIds @return array<int,array<string,mixed>> */
    private function activeRows(array $testIds, int $userId): array
    {
        if (!$this->tableExists('tst_active')) { return []; }
        $a = $this->column('tst_active', ['active_id', 'id']);
        $t = $this->column('tst_active', ['test_fi', 'test_id']);
        $u = $this->column('tst_active', ['user_fi', 'usr_id', 'user_id']);
        if ($a === '' || $t === '' || $u === '') { return []; }
        $ids = implode(',', array_map('intval', array_values(array_unique($testIds))));
        return $this->rows('SELECT * FROM tst_active WHERE ' . $t . ' IN (' . $ids . ') AND ' . $u . ' = ' . $userId . ' ORDER BY ' . $a . ' DESC', 5);
    }

    /** @param array<string,mixed> $active */
    private function passForActive(int $activeId, array $active): int
    {
        foreach (['last_finished_pass', 'last_pass', 'pass', 'tries'] as $k) {
            if (isset($active[$k]) && is_numeric($active[$k])) { return max(0, (int) $active[$k]); }
        }
        $info = $this->resultInfo();
        if ($info === [] || $info['pass'] === '') { return 0; }
        $rows = $this->rows('SELECT MAX(' . $info['pass'] . ') max_pass FROM ' . $info['table'] . ' WHERE ' . $info['active'] . ' = ' . $activeId, 1);
        return isset($rows[0]['max_pass']) && is_numeric($rows[0]['max_pass']) ? max(0, (int) $rows[0]['max_pass']) : 0;
    }

    /** @return array<string,string> */
    private function resultInfo(): array
    {
        foreach (['tst_pass_result', 'tst_test_result', 'tst_result_cache'] as $table) {
            if (!$this->tableExists($table)) { continue; }
            $active = $this->column($table, ['active_fi', 'active_id']);
            $question = $this->column($table, ['question_fi', 'question_id', 'qid']);
            if ($active === '' || $question === '') { continue; }
            return [
                'table' => $table,
                'active' => $active,
                'question' => $question,
                'pass' => $this->column($table, ['pass', 'pass_fi']),
                'points' => $this->column($table, ['points', 'reached_points', 'received_points', 'score']),
                'max' => $this->column($table, ['maxpoints', 'max_points', 'points_max', 'maximum_points']),
            ];
        }
        return [];
    }

    /** @return array<int,array<string,mixed>> */
    private function questionRows(int $activeId, int $pass): array
    {
        $i = $this->resultInfo();
        if ($i === []) { return []; }
        $where = $i['active'] . ' = ' . $activeId;
        if ($i['pass'] !== '') { $where .= ' AND ' . $i['pass'] . ' = ' . $pass; }
        return $this->rows('SELECT * FROM ' . $i['table'] . ' WHERE ' . $where, 500);
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private function normalize(array $rows, int $activeId, int $pass, int $refId, int $objId): array
    {
        $i = $this->resultInfo();
        if ($i === []) { return []; }
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $qid = isset($row[$i['question']]) && is_numeric($row[$i['question']]) ? (int) $row[$i['question']] : 0;
            if ($qid <= 0 || isset($seen[$qid])) { continue; }
            $seen[$qid] = true;
            $q = $this->questionInfo($qid);
            $points = $i['points'] !== '' && isset($row[$i['points']]) && is_numeric($row[$i['points']]) ? (float) $row[$i['points']] : 0.0;
            $max = $i['max'] !== '' && isset($row[$i['max']]) && is_numeric($row[$i['max']]) ? (float) $row[$i['max']] : (float) $q['max_points'];
            $pct = $max > 0 ? round(($points / $max) * 100, 2) : null;
            $out[] = [
                'question_id' => $qid,
                'question_title' => (string) $q['title'],
                'question_type' => (string) $q['type'],
                'points' => $points,
                'max_points' => $max,
                'score_percent' => $pct,
                'success' => is_numeric($pct) ? ((float) $pct >= 50.0) : null,
                'active_id' => $activeId,
                'pass' => $pass,
                'test_ref_id' => $refId,
                'test_obj_id' => $objId,
                'source_table' => $i['table'],
            ];
        }
        return $out;
    }

    /** @return array{title:string,max_points:float,type:string} */
    private function questionInfo(int $qid): array
    {
        if (!$this->tableExists('qpl_questions')) { return ['title' => 'Question ' . $qid, 'max_points' => 0.0, 'type' => '']; }
        $id = $this->column('qpl_questions', ['question_id', 'id']);
        if ($id === '') { return ['title' => 'Question ' . $qid, 'max_points' => 0.0, 'type' => '']; }
        $row = $this->rows('SELECT * FROM qpl_questions WHERE ' . $id . ' = ' . $qid, 1)[0] ?? [];
        $title = '';
        foreach (['title', 'question_title', 'label'] as $c) { if (isset($row[$c]) && trim((string) $row[$c]) !== '') { $title = trim((string) $row[$c]); break; } }
        $max = 0.0;
        foreach (['points', 'maxpoints', 'max_points'] as $c) { if (isset($row[$c]) && is_numeric($row[$c])) { $max = (float) $row[$c]; break; } }
        $type = '';
        foreach (['question_type_fi', 'question_type', 'type'] as $c) { if (isset($row[$c]) && is_scalar($row[$c])) { $type = (string) $row[$c]; break; } }
        return ['title' => $title !== '' ? $title : 'Question ' . $qid, 'max_points' => $max, 'type' => $type];
    }

    private function tableExists(string $table): bool { try { return method_exists($this->db, 'tableExists') && $this->db->tableExists($table); } catch (Throwable $e) { return false; } }

    /** @param array<int,string> $candidates */
    private function column(string $table, array $candidates): string
    {
        $cols = $this->columns($table);
        foreach ($candidates as $c) { if (in_array($c, $cols, true)) { return $c; } }
        return '';
    }

    /** @return array<int,string> */
    private function columns(string $table): array
    {
        if (isset($this->columns[$table])) { return $this->columns[$table]; }
        $cols = [];
        foreach (['SHOW COLUMNS FROM ' . $table, 'DESCRIBE ' . $table] as $q) {
            try {
                $set = $this->db->query($q);
                while ($r = $this->db->fetchAssoc($set)) {
                    if (isset($r['Field'])) { $cols[] = (string) $r['Field']; }
                    elseif (isset($r['field'])) { $cols[] = (string) $r['field']; }
                }
                if ($cols !== []) { break; }
            } catch (Throwable $ignored) {}
        }
        $this->columns[$table] = array_values(array_unique($cols));
        return $this->columns[$table];
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(string $sql, int $limit): array
    {
        $rows = [];
        try {
            if (method_exists($this->db, 'setLimit')) { $this->db->setLimit(max(1, $limit)); }
            $set = $this->db->query($sql);
            $i = 0;
            while (($row = $this->db->fetchAssoc($set)) && $i < $limit) { if (is_array($row)) { $rows[] = $row; $i++; } }
        } catch (Throwable $ignored) {}
        return $rows;
    }

    /** @return array<int,int> */
    private function intColumn(string $sql, string $column): array
    {
        $out = [];
        foreach ($this->rows($sql, 50) as $row) { if (isset($row[$column]) && is_numeric($row[$column])) { $out[] = (int) $row[$column]; } }
        return array_values(array_unique(array_filter($out, static fn(int $v): bool => $v > 0)));
    }

    /** @param array<string,mixed> $row @param array<int,string> $keys */
    private function firstInt(array $row, array $keys): int
    {
        foreach ($keys as $k) { if (isset($row[$k]) && is_numeric($row[$k])) { return (int) $row[$k]; } }
        return 0;
    }

    private function lookupObjectIdByRefId(int $refId): int
    {
        if ($refId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupObjectId')) { return 0; }
        try { return (int) ilObject::_lookupObjectId($refId); } catch (Throwable $ignored) { return 0; }
    }
}
