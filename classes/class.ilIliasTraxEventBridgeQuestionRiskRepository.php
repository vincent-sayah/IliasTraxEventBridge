<?php

/**
 * V0.21.2b : calcule les questions problématiques à partir des statements de questions
 * déjà générés dans l'outbox locale.
 */
class ilIliasTraxEventBridgeQuestionRiskRepository
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

    /** @param array<int,int> $allowedRefIds @return array<int,array<string,mixed>> */
    public function build(int $periodDays, array $allowedRefIds = [], int $selectedRefId = 0): array
    {
        if ($this->db === null || !$this->tableExists('evnt_evhk_itxeb_out')) {
            return [];
        }
        $sinceTs = time() - (max(1, min(365, $periodDays)) * 86400);
        $where = ' WHERE obj_type = ' . $this->quote('tst')
            . ' AND created_ts >= ' . (int) $sinceTs
            . ' AND statement_json LIKE ' . $this->quote('%question_id%');
        if ($selectedRefId > 0) {
            $where .= ' AND ref_id = ' . (int) $selectedRefId;
        } elseif ($allowedRefIds !== []) {
            $where .= ' AND ref_id IN (' . implode(',', array_map('intval', array_values(array_unique($allowedRefIds)))) . ')';
        }

        $rows = $this->rows(
            'SELECT id, ref_id, obj_id, statement_json, created_at, created_ts FROM evnt_evhk_itxeb_out'
            . $where . ' ORDER BY id DESC',
            1000
        );
        return $this->aggregate($rows);
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private function aggregate(array $rows): array
    {
        $stats = [];
        foreach ($rows as $row) {
            $statement = json_decode((string) ($row['statement_json'] ?? ''), true);
            if (!is_array($statement)) { continue; }
            $extensions = $statement['result']['extensions'] ?? [];
            if (!is_array($extensions)) { continue; }
            $qid = $this->extensionInt($extensions, '/question_id');
            if ($qid <= 0) { continue; }
            $refId = (int) ($row['ref_id'] ?? 0);
            $key = $refId . ':' . $qid;
            if (!isset($stats[$key])) {
                $stats[$key] = [
                    'question_key' => $key,
                    'question_id' => $qid,
                    'question_title' => $this->extensionString($extensions, '/question_title') ?: ('Question ' . $qid),
                    'ref_id' => $refId,
                    'obj_id' => (int) ($row['obj_id'] ?? 0),
                    'test_title' => $this->statementObjectName($statement),
                    'attempts' => 0,
                    'failed' => 0,
                    'passed' => 0,
                    'unanswered' => 0,
                    'score_sum' => 0.0,
                    'score_count' => 0,
                    'last_ts' => 0,
                    'last_at' => '',
                ];
            }
            $stats[$key]['attempts']++;
            $answered = $this->extensionBool($extensions, '/question_answered');
            if ($answered === false) { $stats[$key]['unanswered']++; }
            $score = $this->extensionFloat($extensions, '/question_score_percent');
            if ($score === null) {
                $score = isset($statement['result']['score']['raw']) && is_numeric($statement['result']['score']['raw']) ? (float) $statement['result']['score']['raw'] : null;
            }
            if ($score !== null) {
                $stats[$key]['score_sum'] += $score;
                $stats[$key]['score_count']++;
                if ($score < 50.0) { $stats[$key]['failed']++; } else { $stats[$key]['passed']++; }
            } elseif (($statement['result']['success'] ?? null) === false || $answered === false) {
                $stats[$key]['failed']++;
            } elseif (($statement['result']['success'] ?? null) === true) {
                $stats[$key]['passed']++;
            }
            $createdTs = (int) ($row['created_ts'] ?? 0);
            if ($createdTs > (int) $stats[$key]['last_ts']) {
                $stats[$key]['last_ts'] = $createdTs;
                $stats[$key]['last_at'] = (string) ($row['created_at'] ?? '');
            }
        }

        $risks = [];
        foreach ($stats as $item) {
            $attempts = (int) ($item['attempts'] ?? 0);
            if ($attempts <= 0) { continue; }
            $bad = (int) ($item['failed'] ?? 0) + (int) ($item['unanswered'] ?? 0);
            $failureRate = round(($bad / max(1, $attempts)) * 100, 2);
            $scoreCount = (int) ($item['score_count'] ?? 0);
            $avgScore = $scoreCount > 0 ? round((float) $item['score_sum'] / $scoreCount, 2) : null;
            if ($failureRate < 50.0 && !($avgScore !== null && $avgScore < 50.0)) { continue; }
            unset($item['score_sum'], $item['score_count'], $item['last_ts']);
            $item['failure_rate'] = $failureRate;
            $item['avg_score'] = $avgScore;
            $item['risk_label'] = $failureRate >= 70.0 ? 'Critique' : 'À surveiller';
            $item['risk_reason'] = $failureRate . ' % d’échec/non-réponse sur ' . $attempts . ' réponse(s).';
            $risks[] = $item;
        }
        usort($risks, static function (array $a, array $b): int {
            $cmp = (float) ($b['failure_rate'] ?? 0) <=> (float) ($a['failure_rate'] ?? 0);
            if ($cmp !== 0) { return $cmp; }
            return (int) ($b['attempts'] ?? 0) <=> (int) ($a['attempts'] ?? 0);
        });
        return array_slice($risks, 0, 20);
    }

    /** @param array<string,mixed> $extensions */
    private function extensionString(array $extensions, string $suffix): string
    {
        $v = $this->extensionValue($extensions, $suffix);
        return is_scalar($v) ? trim((string) $v) : '';
    }

    /** @param array<string,mixed> $extensions */
    private function extensionInt(array $extensions, string $suffix): int
    {
        $v = $this->extensionValue($extensions, $suffix);
        return is_numeric($v) ? (int) $v : 0;
    }

    /** @param array<string,mixed> $extensions */
    private function extensionFloat(array $extensions, string $suffix): ?float
    {
        $v = $this->extensionValue($extensions, $suffix);
        return is_numeric($v) ? (float) $v : null;
    }

    /** @param array<string,mixed> $extensions */
    private function extensionBool(array $extensions, string $suffix): ?bool
    {
        $v = $this->extensionValue($extensions, $suffix);
        if (is_bool($v)) { return $v; }
        if (is_numeric($v)) { return ((int) $v) === 1; }
        if (is_string($v)) {
            $x = strtolower(trim($v));
            if (in_array($x, ['1', 'true', 'yes', 'oui'], true)) { return true; }
            if (in_array($x, ['0', 'false', 'no', 'non'], true)) { return false; }
        }
        return null;
    }

    /** @param array<string,mixed> $extensions */
    private function extensionValue(array $extensions, string $suffix)
    {
        foreach ($extensions as $key => $value) {
            if (is_string($key) && substr($key, -strlen($suffix)) === $suffix) { return $value; }
        }
        return null;
    }

    /** @param array<string,mixed> $statement */
    private function statementObjectName(array $statement): string
    {
        $name = $statement['object']['definition']['name'] ?? [];
        if (is_array($name)) {
            foreach (['fr-FR', 'fr', 'en-US', 'en'] as $locale) {
                if (isset($name[$locale]) && trim((string) $name[$locale]) !== '') { return (string) $name[$locale]; }
            }
        }
        return 'Test ILIAS';
    }

    private function tableExists(string $table): bool
    {
        try { return method_exists($this->db, 'tableExists') && $this->db->tableExists($table); } catch (Throwable $e) { return false; }
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(string $sql, int $limit): array
    {
        $rows = [];
        try {
            if (method_exists($this->db, 'setLimit')) { $this->db->setLimit(max(1, $limit)); }
            $set = $this->db->query($sql);
            $count = 0;
            while (($row = $this->db->fetchAssoc($set)) && $count < $limit) {
                if (is_array($row)) { $rows[] = $row; $count++; }
            }
        } catch (Throwable $e) { return []; }
        return $rows;
    }

    private function quote(string $value): string
    {
        return method_exists($this->db, 'quote') ? $this->db->quote($value, 'text') : "'" . addslashes($value) . "'";
    }
}
