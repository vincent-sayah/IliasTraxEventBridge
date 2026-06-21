<?php

/**
 * Local outbox for generated xAPI statements.
 *
 * V0.2 does not send statements to TRAX yet. It only stores them locally so the
 * mapping can be validated before HTTP transmission is introduced.
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
        $statementJson = json_encode($statement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!is_string($statementJson)) {
            $statementJson = '{}';
        }

        $verbId = '';
        if (isset($statement['verb']) && is_array($statement['verb']) && isset($statement['verb']['id'])) {
            $verbId = (string) $statement['verb']['id'];
        }

        $eventType = $this->detectEventType($eventRecord);

        $this->db->insert(self::TABLE_NAME, [
            'id' => ['integer', $id],
            'event_log_id' => ['integer', $eventLogId],
            'statement_uuid' => ['text', (string) ($statement['id'] ?? '')],
            'event_type' => ['text', $eventType],
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
        ]);

        return $id;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function findRecent(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $rows = [];

        if (!$this->tableExists()) {
            return $rows;
        }

        $query = 'SELECT id, event_log_id, statement_uuid, event_type, verb_id, user_id, ref_id, obj_id, obj_type, statement_json, status, created_at '
            . 'FROM ' . self::TABLE_NAME . ' ORDER BY id DESC';

        if (method_exists($this->db, 'setLimit')) {
            $this->db->setLimit($limit);
        }

        $set = $this->db->query($query);

        $count = 0;
        while (($row = $this->db->fetchAssoc($set)) && $count < $limit) {
            $rows[] = $row;
            $count++;
        }

        return $rows;
    }

    public function countAll(): int
    {
        if (!$this->tableExists()) {
            return 0;
        }

        $set = $this->db->query('SELECT COUNT(*) cnt FROM ' . self::TABLE_NAME);
        $row = $this->db->fetchAssoc($set);

        if (!is_array($row)) {
            return 0;
        }

        return (int) ($row['cnt'] ?? 0);
    }

    public function clear(): void
    {
        if (!$this->tableExists()) {
            return;
        }

        $this->db->manipulate('DELETE FROM ' . self::TABLE_NAME);
    }

    private function tableExists(): bool
    {
        return method_exists($this->db, 'tableExists') && $this->db->tableExists(self::TABLE_NAME);
    }

    /**
     * @param array<string,mixed> $eventRecord
     */
    private function detectEventType(array $eventRecord): string
    {
        $component = (string) ($eventRecord['component'] ?? '');
        $event = (string) ($eventRecord['event_name'] ?? '');
        $type = (string) ($eventRecord['obj_type'] ?? '');
        $uri = (string) ($eventRecord['request_uri'] ?? '');

        if ($component === 'components/ILIAS/ILIASObject'
            && $event === 'update'
            && $type === 'file'
            && strpos($uri, 'cmd=sendfile') !== false
        ) {
            return 'file_downloaded';
        }

        if ($component === 'components/ILIAS/Tracking' && $event === 'updateStatus') {
            return $type === 'tst' ? 'test_tracking_status' : 'learning_tracking_status';
        }

        return 'unknown';
    }
}
