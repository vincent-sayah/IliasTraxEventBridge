<?php

/**
 * Persists received ILIAS events for discovery/debugging.
 */
class ilIliasTraxEventBridgeEventDebugRepository
{
    public const TABLE_NAME = 'evnt_evhk_itxeb_log';

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
     * @param array<string,mixed> $record
     */
    public function insert(array $record): void
    {
        if (!$this->tableExists()) {
            return;
        }

        $id = (int) $this->db->nextId(self::TABLE_NAME);

        $this->db->insert(self::TABLE_NAME, [
            'id' => ['integer', $id],
            'component' => ['text', (string) $record['component']],
            'event_name' => ['text', (string) $record['event_name']],
            'user_id' => ['integer', (int) $record['user_id']],
            'ref_id' => ['integer', (int) $record['ref_id']],
            'obj_id' => ['integer', (int) $record['obj_id']],
            'obj_type' => ['text', (string) $record['obj_type']],
            'param_keys' => ['clob', (string) $record['param_keys']],
            'payload_json' => ['clob', (string) $record['payload_json']],
            'request_uri' => ['clob', (string) $record['request_uri']],
            'http_method' => ['text', (string) $record['http_method']],
            'created_at' => ['text', (string) $record['created_at']],
            'created_ts' => ['integer', (int) $record['created_ts']],
        ]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function findRecent(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $rows = [];

        if (!$this->tableExists()) {
            return $rows;
        }

        $query = 'SELECT id, component, event_name, user_id, ref_id, obj_id, obj_type, param_keys, payload_json, request_uri, http_method, created_at '
            . 'FROM ' . self::TABLE_NAME . ' ORDER BY id DESC';

        // Portable ILIAS limit. If unavailable, the PHP loop below still caps the display.
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

    public function deleteOlderThanDays(int $days): void
    {
        if (!$this->tableExists()) {
            return;
        }

        $threshold = time() - (max(1, $days) * 86400);
        $this->db->manipulate(
            'DELETE FROM ' . self::TABLE_NAME . ' WHERE created_ts < ' . $this->db->quote($threshold, 'integer')
        );
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
}
