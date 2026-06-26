<?php

/**
 * V0.8 deny log repository.
 *
 * Stores non-blocking diagnostics when an ILIAS event or read_event row is
 * intentionally refused before xAPI statement generation / outbox insertion.
 */
class ilIliasTraxEventBridgeDenyLogRepository
{
    public const TABLE_NAME = 'evnt_evhk_itxeb_dlog';

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

    /** @param array<string,mixed> $eventRecord */
    public function log(string $reason, array $eventRecord, string $sourceTable = '', int $sourceId = 0): int
    {
        if (!$this->tableExists()) {
            return 0;
        }

        $id = (int) $this->db->nextId(self::TABLE_NAME);
        $now = date('Y-m-d H:i:s');
        $createdAt = (string) ($eventRecord['created_at'] ?? $now);
        $createdTs = (int) ($eventRecord['created_ts'] ?? time());

        $this->db->insert(self::TABLE_NAME, [
            'id' => ['integer', $id],
            'created_at' => ['text', $createdAt !== '' ? substr($createdAt, 0, 19) : $now],
            'created_ts' => ['integer', $createdTs > 0 ? $createdTs : time()],
            'reason' => ['text', $this->short($reason, 64)],
            'event_type' => ['text', $this->short($this->detectEventType($eventRecord), 64)],
            'component' => ['text', $this->short((string) ($eventRecord['component'] ?? ''), 255)],
            'event_name' => ['text', $this->short((string) ($eventRecord['event_name'] ?? ''), 255)],
            'user_id' => ['integer', (int) ($eventRecord['user_id'] ?? 0)],
            'course_ref_id' => ['integer', (int) ($eventRecord['course_ref_id'] ?? 0)],
            'course_obj_id' => ['integer', (int) ($eventRecord['course_obj_id'] ?? 0)],
            'ref_id' => ['integer', (int) ($eventRecord['ref_id'] ?? 0)],
            'obj_id' => ['integer', (int) ($eventRecord['obj_id'] ?? 0)],
            'obj_type' => ['text', $this->short((string) ($eventRecord['obj_type'] ?? ''), 64)],
            'source_table' => ['text', $this->short($sourceTable, 64)],
            'source_id' => ['integer', max(0, $sourceId)],
            'payload_json' => ['clob', $this->payloadJson($eventRecord)],
            'request_uri' => ['clob', (string) ($eventRecord['request_uri'] ?? '')],
            'http_method' => ['text', $this->short((string) ($eventRecord['http_method'] ?? ''), 16)],
        ]);

        return $id;
    }

    /** @return array<int,array<string,mixed>> */
    public function findRecent(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $rows = [];
        if (!$this->tableExists()) {
            return $rows;
        }

        $query = 'SELECT id, created_at, created_ts, reason, event_type, component, event_name, user_id, course_ref_id, course_obj_id, ref_id, obj_id, obj_type, source_table, source_id, payload_json, request_uri, http_method '
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
        return is_array($row) ? (int) ($row['cnt'] ?? 0) : 0;
    }

    public function countByReason(string $reason): int
    {
        if (!$this->tableExists()) {
            return 0;
        }

        $set = $this->db->query(
            'SELECT COUNT(*) cnt FROM ' . self::TABLE_NAME
            . ' WHERE reason = ' . $this->db->quote($this->short($reason, 64), 'text')
        );
        $row = $this->db->fetchAssoc($set);
        return is_array($row) ? (int) ($row['cnt'] ?? 0) : 0;
    }

    private function tableExists(): bool
    {
        return method_exists($this->db, 'tableExists') && $this->db->tableExists(self::TABLE_NAME);
    }

    /** @param array<string,mixed> $eventRecord */
    private function detectEventType(array $eventRecord): string
    {
        if (isset($eventRecord['event_type']) && is_scalar($eventRecord['event_type'])) {
            return (string) $eventRecord['event_type'];
        }

        $component = (string) ($eventRecord['component'] ?? '');
        $eventName = (string) ($eventRecord['event_name'] ?? '');
        $objType = (string) ($eventRecord['obj_type'] ?? '');

        if ($component === 'components/ILIAS/ReadEvent') {
            return 'repository_object_access';
        }

        if (stripos($eventName, 'sendfile') !== false || stripos($eventName, 'download') !== false) {
            return 'file_downloaded';
        }

        if ($objType === 'tst' && stripos($eventName, 'updateStatus') !== false) {
            return 'test_tracking_status';
        }

        return $eventName !== '' ? $eventName : 'unknown';
    }

    /** @param array<string,mixed> $eventRecord */
    private function payloadJson(array $eventRecord): string
    {
        $payload = [
            'component' => (string) ($eventRecord['component'] ?? ''),
            'event_name' => (string) ($eventRecord['event_name'] ?? ''),
            'param_keys' => (string) ($eventRecord['param_keys'] ?? ''),
            'payload_json' => (string) ($eventRecord['payload_json'] ?? ''),
            'course_ref_id' => (int) ($eventRecord['course_ref_id'] ?? 0),
            'course_obj_id' => (int) ($eventRecord['course_obj_id'] ?? 0),
            'ref_id' => (int) ($eventRecord['ref_id'] ?? 0),
            'obj_id' => (int) ($eventRecord['obj_id'] ?? 0),
            'obj_type' => (string) ($eventRecord['obj_type'] ?? ''),
            'request_uri' => (string) ($eventRecord['request_uri'] ?? ''),
            'http_method' => (string) ($eventRecord['http_method'] ?? ''),
        ];

        foreach (['read_count', 'spent_seconds', 'read_event_last_access', 'read_event_first_access'] as $key) {
            if (array_key_exists($key, $eventRecord)) {
                $payload[$key] = $eventRecord[$key];
            }
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if (!is_string($json)) {
            return '{"error":"json_encode_failed"}';
        }

        return strlen($json) > 12000 ? substr($json, 0, 12000) . '...<truncated>' : $json;
    }

    private function short(string $value, int $length): string
    {
        return substr($value, 0, max(1, $length));
    }
}
