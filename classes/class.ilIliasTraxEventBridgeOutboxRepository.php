<?php

/**
 * Local outbox for generated xAPI statements.
 *
 * V0.4 supports automatic cron delivery with retry counters.
 */
class ilIliasTraxEventBridgeOutboxRepository
{
    public const TABLE_NAME = 'evnt_evhk_itxeb_out';

    /** @var ilDBInterface|mixed */
    private $db;

    public function __construct()
    {
        if (isset($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'database')) {
            $this->db = $GLOBALS['DIC']->database();
        } elseif (isset($GLOBALS['ilDB'])) {
            $this->db = $GLOBALS['ilDB'];
        } else {
            throw new RuntimeException('ILIAS database object not available.');
        }
    }

    /**
     * @param array<string,mixed> $eventRecord
     * @param array<string,mixed> $statement
     */
    public function enqueue(array $eventRecord, array $statement, int $eventLogId = 0): int
    {
        if (!$this->tableExists()) {
            return 0;
        }

        $id = (int) $this->db->nextId(self::TABLE_NAME);
        $statement = $this->addOutboxDiagnosticExtensions($statement, $eventRecord, $eventLogId, $id);
        $statementJson = json_encode($statement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!is_string($statementJson)) {
            $statementJson = '{}';
        }

        $verbId = '';
        if (isset($statement['verb']) && is_array($statement['verb']) && isset($statement['verb']['id'])) {
            $verbId = (string) $statement['verb']['id'];
        }

        $this->db->insert(self::TABLE_NAME, [
            'id' => ['integer', $id],
            'event_log_id' => ['integer', $eventLogId],
            'statement_uuid' => ['text', (string) ($statement['id'] ?? '')],
            'event_type' => ['text', $this->detectEventType($eventRecord)],
            'verb_id' => ['text', $verbId],
            'user_id' => ['integer', (int) ($eventRecord['user_id'] ?? 0)],
            'ref_id' => ['integer', (int) ($eventRecord['ref_id'] ?? 0)],
            'obj_id' => ['integer', (int) ($eventRecord['obj_id'] ?? 0)],
            'obj_type' => ['text', (string) ($eventRecord['obj_type'] ?? '')],
            'statement_json' => ['clob', $statementJson],
            'status' => ['text', 'generated'],
            'created_at' => ['text', (string) ($eventRecord['created_at'] ?? date('Y-m-d H:i:s'))],
            'created_ts' => ['integer', (int) ($eventRecord['created_ts'] ?? time())],
            'sent_at' => ['text', ''],
            'last_error' => ['clob', ''],
            'retry_count' => ['integer', 0],
            'max_retry' => ['integer', 5],
            'last_attempt_at' => ['text', ''],
        ]);

        return $id;
    }

    /** @return array<int,array<string,mixed>> */
    public function findRecent(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $rows = [];
        if (!$this->tableExists()) { return $rows; }

        $query = 'SELECT id, event_log_id, statement_uuid, event_type, verb_id, user_id, ref_id, obj_id, obj_type, statement_json, status, created_at, created_ts, sent_at, last_error, retry_count, max_retry, last_attempt_at '
            . 'FROM ' . self::TABLE_NAME . ' ORDER BY id DESC';

        if (method_exists($this->db, 'setLimit')) { $this->db->setLimit($limit); }
        $set = $this->db->query($query);

        $count = 0;
        while (($row = $this->db->fetchAssoc($set)) && $count < $limit) {
            $rows[] = $row;
            $count++;
        }
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function findSendable(int $limit, int $maxRetry): array
    {
        $limit = max(1, min(100, $limit));
        $maxRetry = max(0, min(50, $maxRetry));
        $rows = [];
        if (!$this->tableExists()) { return $rows; }

        $query = 'SELECT id, statement_json, retry_count FROM ' . self::TABLE_NAME
            . ' WHERE status IN (' . $this->db->quote('generated', 'text') . ', ' . $this->db->quote('failed', 'text') . ')'
            . ' AND retry_count < ' . (int) $maxRetry
            . ' ORDER BY id ASC';

        if (method_exists($this->db, 'setLimit')) { $this->db->setLimit($limit); }
        $set = $this->db->query($query);

        $count = 0;
        while (($row = $this->db->fetchAssoc($set)) && $count < $limit) {
            $rows[] = $row;
            $count++;
        }
        return $rows;
    }

    /** @param array<int,int> $ids */
    public function markSending(array $ids): void
    {
        $this->updateStatusForIds($ids, 'sending', '', false);
    }

    /** @param array<int,int> $ids */
    public function markSent(array $ids): void
    {
        if (count($ids) === 0 || !$this->tableExists()) { return; }
        $this->db->manipulate(
            'UPDATE ' . self::TABLE_NAME
            . ' SET status = ' . $this->db->quote('sent', 'text')
            . ', sent_at = ' . $this->db->quote(date('Y-m-d H:i:s'), 'text')
            . ', last_error = ' . $this->db->quote('', 'clob')
            . ' WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')'
        );
    }

    /** @param array<int,int> $ids */
    public function markFailed(array $ids, string $error): void
    {
        $this->updateStatusForIds($ids, 'failed', substr($error, 0, 4000), true);
    }

    public function resetStuckSending(): int
    {
        if (!$this->tableExists()) { return 0; }
        $this->db->manipulate(
            'UPDATE ' . self::TABLE_NAME
            . ' SET status = ' . $this->db->quote('failed', 'text')
            . ', last_error = ' . $this->db->quote('Réinitialisé depuis status=sending au chargement de la V0.4.', 'clob')
            . ' WHERE status = ' . $this->db->quote('sending', 'text')
        );
        return 0;
    }

    public function resetFailedToGenerated(): int
    {
        if (!$this->tableExists()) { return 0; }
        $count = $this->countByStatus('failed');
        $this->db->manipulate(
            'UPDATE ' . self::TABLE_NAME
            . ' SET status = ' . $this->db->quote('generated', 'text')
            . ', retry_count = 0'
            . ', last_error = ' . $this->db->quote('Réinitialisé manuellement depuis l’écran plugin.', 'clob')
            . ', last_attempt_at = ' . $this->db->quote('', 'text')
            . ' WHERE status = ' . $this->db->quote('failed', 'text')
        );
        return $count;
    }

    public function countAll(): int { return $this->countWhere(''); }
    public function countByStatus(string $status): int { return $this->countWhere(' WHERE status = ' . $this->db->quote($status, 'text')); }
    public function countRetryExhausted(int $maxRetry): int
    {
        return $this->countWhere(' WHERE status = ' . $this->db->quote('failed', 'text') . ' AND retry_count >= ' . (int) $maxRetry);
    }

    public function countCreatedSince(int $sinceTs): int
    {
        return $this->countWhere(' WHERE created_ts >= ' . max(0, $sinceTs));
    }

    public function countByStatusSince(string $status, int $sinceTs): int
    {
        return $this->countWhere(' WHERE status = ' . $this->db->quote($status, 'text') . ' AND created_ts >= ' . max(0, $sinceTs));
    }

    public function countFailedWithError(): int
    {
        return $this->countWhere(' WHERE status = ' . $this->db->quote('failed', 'text') . ' OR last_error <> ' . $this->db->quote('', 'clob'));
    }

    public function clear(): void
    {
        if (!$this->tableExists()) { return; }
        $this->db->manipulate('DELETE FROM ' . self::TABLE_NAME);
    }

    private function tableExists(): bool
    {
        return method_exists($this->db, 'tableExists') && $this->db->tableExists(self::TABLE_NAME);
    }

    /**
     * @param array<string,mixed> $statement
     * @param array<string,mixed> $eventRecord
     * @return array<string,mixed>
     */
    private function addOutboxDiagnosticExtensions(array $statement, array $eventRecord, int $eventLogId, int $outboxId): array
    {
        if (!isset($statement['context']) || !is_array($statement['context'])) {
            $statement['context'] = [];
        }

        if (!isset($statement['context']['extensions']) || !is_array($statement['context']['extensions'])) {
            $statement['context']['extensions'] = [];
        }

        $prefix = $this->extensionPrefix($statement);
        $statement['context']['extensions'][$prefix . 'outbox_id'] = $outboxId;
        $statement['context']['extensions'][$prefix . 'outbox_table'] = self::TABLE_NAME;
        $statement['context']['extensions'][$prefix . 'event_log_id'] = $eventLogId;
        $statement['context']['extensions'][$prefix . 'statement_uuid'] = (string) ($statement['id'] ?? '');
        $statement['context']['extensions'][$prefix . 'event_record_source'] = $this->eventRecordSource($eventRecord, $eventLogId);
        $statement['context']['extensions'][$prefix . 'source_table'] = $this->sourceTable($eventRecord, $eventLogId);
        $statement['context']['extensions'][$prefix . 'deduplication_key'] = $this->deduplicationKey($eventRecord, $eventLogId);

        return $statement;
    }

    /**
     * @param array<string,mixed> $statement
     */
    private function extensionPrefix(array $statement): string
    {
        $extensions = $statement['context']['extensions'] ?? [];
        if (is_array($extensions)) {
            foreach (array_keys($extensions) as $key) {
                if (!is_string($key)) {
                    continue;
                }

                $needle = '/xapi/extensions/';
                $pos = strpos($key, $needle);
                if ($pos !== false) {
                    return substr($key, 0, $pos + strlen($needle));
                }
            }
        }

        $homePage = $statement['actor']['account']['homePage'] ?? '';
        if (is_scalar($homePage) && trim((string) $homePage) !== '') {
            return rtrim((string) $homePage, '/') . '/xapi/extensions/';
        }

        return 'http://ilias.local/xapi/extensions/';
    }

    /** @param array<string,mixed> $eventRecord */
    private function eventRecordSource(array $eventRecord, int $eventLogId): string
    {
        $component = (string) ($eventRecord['component'] ?? '');
        if ($component === 'components/ILIAS/ReadEvent') {
            return 'read_event_tracker';
        }

        if ($eventLogId > 0) {
            return 'event_hook_log';
        }

        return 'synthetic';
    }

    /** @param array<string,mixed> $eventRecord */
    private function sourceTable(array $eventRecord, int $eventLogId): string
    {
        $component = (string) ($eventRecord['component'] ?? '');
        if ($component === 'components/ILIAS/ReadEvent') {
            return 'read_event';
        }

        if ($eventLogId > 0) {
            return 'evnt_evhk_itxeb_log';
        }

        return '';
    }

    /** @param array<string,mixed> $eventRecord */
    private function deduplicationKey(array $eventRecord, int $eventLogId): string
    {
        $component = (string) ($eventRecord['component'] ?? '');
        if ($component === 'components/ILIAS/ReadEvent') {
            return implode(':', [
                'read_event',
                (int) ($eventRecord['obj_id'] ?? 0),
                (int) ($eventRecord['user_id'] ?? 0),
                (int) ($eventRecord['read_event_last_access'] ?? 0),
                (int) ($eventRecord['read_count'] ?? 0),
            ]);
        }

        if ($eventLogId > 0) {
            return 'event_log:' . $eventLogId;
        }

        return implode(':', [
            'event',
            $component,
            (string) ($eventRecord['event_name'] ?? ''),
            (int) ($eventRecord['user_id'] ?? 0),
            (int) ($eventRecord['ref_id'] ?? 0),
            (int) ($eventRecord['obj_id'] ?? 0),
            (string) ($eventRecord['obj_type'] ?? ''),
            (int) ($eventRecord['created_ts'] ?? 0),
        ]);
    }

    /** @param array<int,int> $ids */
    private function updateStatusForIds(array $ids, string $status, string $error, bool $incrementRetry): void
    {
        if (count($ids) === 0 || !$this->tableExists()) { return; }
        $this->db->manipulate(
            'UPDATE ' . self::TABLE_NAME
            . ' SET status = ' . $this->db->quote($status, 'text')
            . ', last_error = ' . $this->db->quote($error, 'clob')
            . ', last_attempt_at = ' . $this->db->quote(date('Y-m-d H:i:s'), 'text')
            . ($incrementRetry ? ', retry_count = retry_count + 1' : '')
            . ' WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')'
        );
    }

    private function countWhere(string $where): int
    {
        if (!$this->tableExists()) { return 0; }
        $set = $this->db->query('SELECT COUNT(*) cnt FROM ' . self::TABLE_NAME . $where);
        $row = $this->db->fetchAssoc($set);
        return is_array($row) ? (int) ($row['cnt'] ?? 0) : 0;
    }

    /** @param array<string,mixed> $eventRecord */
    private function detectEventType(array $eventRecord): string
    {
        $component = (string) ($eventRecord['component'] ?? '');
        $event = (string) ($eventRecord['event_name'] ?? '');
        $type = (string) ($eventRecord['obj_type'] ?? '');
        $uri = (string) ($eventRecord['request_uri'] ?? '');

        if ($component === 'components/ILIAS/ReadEvent' && $event === 'access' && $type === 'mcst' && strpos($uri, 'itxeb_mcst_event=') !== false) {
            return 'mediacast_media_client_event';
        }

        if ($component === 'components/ILIAS/ILIASObject' && $event === 'update' && $type === 'file' && strpos($uri, 'cmd=sendfile') !== false) {
            return 'file_downloaded';
        }
        if ($component === 'components/ILIAS/ReadEvent' && $event === 'access' && $this->isRepositoryObjectStatementSupported($type)) {
            return 'repository_object_access';
        }
        if ($component === 'components/ILIAS/Tracking' && $event === 'updateStatus') {
            if ($type === 'tst' || strpos($uri, 'cmdClass=ilTestPlayerFixedQuestionSetGUI') !== false || strpos($uri, 'cmdClass=ilTestPlayerDynamicQuestionSetGUI') !== false || strpos($uri, 'cmd=startTest') !== false || strpos($uri, 'cmd=finishTest') !== false) {
                return 'test_tracking_status';
            }
            return 'learning_tracking_status';
        }
        return 'unknown';
    }

    private function isRepositoryObjectStatementSupported(string $type): bool
    {
        return in_array($type, ['blog', 'webr', 'mcst', 'frm', 'wiki', 'htlm', 'lm', 'sahs'], true);
    }
}
