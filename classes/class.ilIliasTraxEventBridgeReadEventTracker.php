<?php

/**
 * Converts ILIAS read_event rows into xAPI exploitation traces.
 *
 * The EventHook is useful for creation/update/download/tracking events, but several
 * repository objects do not emit an EventHook when a learner simply opens them.
 * ILIAS records these consultations in read_event; this tracker is run by the
 * plugin cron before the outbox sender.
 */
class ilIliasTraxEventBridgeReadEventTracker
{
    public const TABLE_NAME = 'evnt_evhk_itxeb_read';

    /** @var ilDBInterface|mixed */
    private $db;

    /** @var ilIliasTraxEventBridgeConfig */
    private $config;

    /** @var ilIliasTraxEventBridgeOutboxRepository */
    private $outboxRepository;

    /** @var ilIliasTraxEventBridgeStatementFactory */
    private $statementFactory;

    /** @var ilIliasTraxEventBridgeCourseContextResolver */
    private $courseContextResolver;

    /** @var ilIliasTraxEventBridgeCourseTrackingRepository */
    private $courseTrackingRepository;

    /** @var ilIliasTraxEventBridgeDenyLogRepository */
    private $denyLogRepository;

    public function __construct(
        ilIliasTraxEventBridgeConfig $config,
        ilIliasTraxEventBridgeOutboxRepository $outboxRepository,
        ?ilIliasTraxEventBridgeStatementFactory $statementFactory = null,
        ?ilIliasTraxEventBridgeCourseContextResolver $courseContextResolver = null,
        ?ilIliasTraxEventBridgeCourseTrackingRepository $courseTrackingRepository = null,
        ?ilIliasTraxEventBridgeDenyLogRepository $denyLogRepository = null
    ) {
        if (isset($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'database')) {
            $this->db = $GLOBALS['DIC']->database();
        } elseif (isset($GLOBALS['ilDB'])) {
            $this->db = $GLOBALS['ilDB'];
        } else {
            throw new RuntimeException('ILIAS database object not available.');
        }

        $this->config = $config;
        $this->outboxRepository = $outboxRepository;
        $this->statementFactory = $statementFactory ?: new ilIliasTraxEventBridgeStatementFactory($config);
        $this->courseContextResolver = $courseContextResolver ?: new ilIliasTraxEventBridgeCourseContextResolver();
        $this->courseTrackingRepository = $courseTrackingRepository ?: new ilIliasTraxEventBridgeCourseTrackingRepository();
        $this->denyLogRepository = $denyLogRepository ?: new ilIliasTraxEventBridgeDenyLogRepository();
    }

    public function scanAndEnqueue(int $limit = 100): int
    {
        if (!$this->config->isLocalXapiGenerationEnabled()) {
            return 0;
        }

        if (!$this->tableExists(self::TABLE_NAME) || !$this->tableExists('read_event') || !$this->tableExists('object_data')) {
            return 0;
        }

        $generated = 0;
        foreach ($this->findReadEvents($limit) as $row) {
            $objId = (int) ($row['obj_id'] ?? 0);
            $usrId = (int) ($row['usr_id'] ?? 0);
            $lastAccess = (int) ($row['last_access'] ?? 0);
            $readCount = (int) ($row['read_count'] ?? 0);

            if ($objId <= 0 || $usrId <= 0 || $lastAccess <= 0) {
                continue;
            }

            if (!$this->needsProcessing($objId, $usrId, $lastAccess, $readCount)) {
                continue;
            }

            $record = $this->buildEventRecord($row);
            $courseContext = $this->courseContextResolver->resolve($record);

            if (!$courseContext['is_in_course']) {
                $this->logDeniedTrace('not_in_course', $record, 'read_event', $objId);
                $this->markProcessed($objId, $usrId, $lastAccess, $readCount);
                continue;
            }

            if ((int) $courseContext['ref_id'] > 0) {
                $record['ref_id'] = (int) $courseContext['ref_id'];
            }
            $record['course_ref_id'] = (int) $courseContext['course_ref_id'];
            $record['course_obj_id'] = (int) $courseContext['course_obj_id'];

            $denialReason = $this->trackingDenialReason((int) $record['course_ref_id'], (int) $record['ref_id']);
            if ($denialReason !== '') {
                $this->logDeniedTrace($denialReason, $record, 'read_event', $objId);
                $this->markProcessed($objId, $usrId, $lastAccess, $readCount);
                continue;
            }

            $statement = $this->statementFactory->createFromEventRecord($record);
            if ($statement !== null) {
                $outboxId = $this->outboxRepository->enqueue($record, $statement, 0);
                if ($outboxId > 0) {
                    $generated++;
                }
            } else {
                $this->logDeniedTrace('unsupported_object_type', $record, 'read_event', $objId);
            }

            $this->markProcessed($objId, $usrId, $lastAccess, $readCount);
        }

        return $generated;
    }

    /** @return array<int,array<string,mixed>> */
    private function findReadEvents(int $limit): array
    {
        $limit = max(1, min(500, $limit));
        $types = $this->supportedObjectTypes();
        $quotedTypes = [];
        foreach ($types as $type) {
            $quotedTypes[] = $this->db->quote($type, 'text');
        }

        $query = 'SELECT re.obj_id, re.usr_id, re.last_access, re.read_count, re.spent_seconds, re.first_access, od.type AS obj_type, od.title AS object_title '
            . 'FROM read_event re '
            . 'JOIN object_data od ON od.obj_id = re.obj_id '
            . 'WHERE re.obj_id > 0 '
            . 'AND re.usr_id > 0 '
            . 'AND re.last_access > 0 '
            . 'AND od.type IN (' . implode(',', $quotedTypes) . ') '
            . 'ORDER BY re.last_access ASC';

        if (method_exists($this->db, 'setLimit')) {
            $this->db->setLimit($limit);
        }

        $rows = [];
        $set = $this->db->query($query);
        $count = 0;
        while (($row = $this->db->fetchAssoc($set)) && $count < $limit) {
            $rows[] = $row;
            $count++;
        }

        return $rows;
    }

    /** @param array<string,mixed> $row */
    private function buildEventRecord(array $row): array
    {
        $objId = (int) ($row['obj_id'] ?? 0);
        $lastAccess = (int) ($row['last_access'] ?? 0);
        $refId = $this->lookupPrimaryRefId($objId);
        $createdAt = $lastAccess > 0 ? date('Y-m-d H:i:s', $lastAccess) : date('Y-m-d H:i:s');
        $firstAccess = (string) ($row['first_access'] ?? '');

        $payload = [
            'source' => 'read_event',
            'obj_id' => $objId,
            'usr_id' => (int) ($row['usr_id'] ?? 0),
            'last_access' => $lastAccess,
            'read_count' => (int) ($row['read_count'] ?? 0),
            'spent_seconds' => (int) ($row['spent_seconds'] ?? 0),
            'first_access' => $firstAccess,
        ];

        return [
            'component' => 'components/ILIAS/ReadEvent',
            'event_name' => 'access',
            'user_id' => (int) ($row['usr_id'] ?? 0),
            'ref_id' => $refId,
            'obj_id' => $objId,
            'obj_type' => (string) ($row['obj_type'] ?? ''),
            'object_title' => (string) ($row['object_title'] ?? ''),
            'param_keys' => 'read_event',
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => $createdAt,
            'created_ts' => $lastAccess > 0 ? $lastAccess : time(),
            'request_uri' => '',
            'http_method' => '',
            'read_count' => (int) ($row['read_count'] ?? 0),
            'spent_seconds' => (int) ($row['spent_seconds'] ?? 0),
            'read_event_last_access' => $lastAccess,
            'read_event_first_access' => $firstAccess,
        ];
    }

    private function needsProcessing(int $objId, int $usrId, int $lastAccess, int $readCount): bool
    {
        $query = 'SELECT last_access, read_count FROM ' . self::TABLE_NAME
            . ' WHERE obj_id = ' . $this->db->quote($objId, 'integer')
            . ' AND usr_id = ' . $this->db->quote($usrId, 'integer');

        $set = $this->db->query($query);
        $row = $this->db->fetchAssoc($set);
        if (!is_array($row)) {
            return true;
        }

        return (int) ($row['last_access'] ?? 0) < $lastAccess
            || (int) ($row['read_count'] ?? 0) < $readCount;
    }

    private function markProcessed(int $objId, int $usrId, int $lastAccess, int $readCount): void
    {
        $processedAt = date('Y-m-d H:i:s');
        if ($this->hasProcessedRow($objId, $usrId)) {
            $this->db->manipulate(
                'UPDATE ' . self::TABLE_NAME
                . ' SET last_access = ' . $this->db->quote($lastAccess, 'integer')
                . ', read_count = ' . $this->db->quote($readCount, 'integer')
                . ', processed_at = ' . $this->db->quote($processedAt, 'text')
                . ' WHERE obj_id = ' . $this->db->quote($objId, 'integer')
                . ' AND usr_id = ' . $this->db->quote($usrId, 'integer')
            );
            return;
        }

        $this->db->insert(self::TABLE_NAME, [
            'obj_id' => ['integer', $objId],
            'usr_id' => ['integer', $usrId],
            'last_access' => ['integer', $lastAccess],
            'read_count' => ['integer', $readCount],
            'processed_at' => ['text', $processedAt],
        ]);
    }

    private function hasProcessedRow(int $objId, int $usrId): bool
    {
        $query = 'SELECT obj_id FROM ' . self::TABLE_NAME
            . ' WHERE obj_id = ' . $this->db->quote($objId, 'integer')
            . ' AND usr_id = ' . $this->db->quote($usrId, 'integer');

        $set = $this->db->query($query);
        return is_array($this->db->fetchAssoc($set));
    }

    private function lookupPrimaryRefId(int $objId): int
    {
        foreach ($this->lookupRefIdsForObject($objId) as $refId) {
            if ($refId > 0) {
                return $refId;
            }
        }

        return 0;
    }

    /** @return array<int,int> */
    private function lookupRefIdsForObject(int $objId): array
    {
        if ($objId <= 0) {
            return [];
        }

        if (class_exists('ilObject') && method_exists('ilObject', '_getAllReferences')) {
            try {
                $references = ilObject::_getAllReferences($objId);
                if (is_array($references)) {
                    $refIds = [];
                    foreach ($references as $key => $value) {
                        if (is_scalar($value) && (int) $value > 0) {
                            $refIds[] = (int) $value;
                        } elseif (is_scalar($key) && (int) $key > 0) {
                            $refIds[] = (int) $key;
                        }
                    }
                    return array_values(array_unique($refIds));
                }
            } catch (Throwable $ignored) {
                // Try DB fallback below.
            }
        }

        if (!$this->tableExists('object_reference')) {
            return [];
        }

        $query = 'SELECT ref_id FROM object_reference WHERE obj_id = ' . $this->db->quote($objId, 'integer') . ' ORDER BY ref_id ASC';
        $set = $this->db->query($query);
        $refIds = [];
        while ($row = $this->db->fetchAssoc($set)) {
            $refId = (int) ($row['ref_id'] ?? 0);
            if ($refId > 0) {
                $refIds[] = $refId;
            }
        }

        return array_values(array_unique($refIds));
    }

    private function trackingDenialReason(int $courseRefId, int $refId): string
    {
        if ($courseRefId <= 0) {
            return 'missing_course_context';
        }

        if ($refId <= 0) {
            return 'missing_resource_context';
        }

        if (!$this->courseTrackingRepository->isCourseConfigured($courseRefId)) {
            return 'course_not_configured';
        }

        if (!$this->courseTrackingRepository->isCourseEnabled($courseRefId)) {
            return 'course_disabled';
        }

        if (!$this->courseTrackingRepository->isResourceConfigured($courseRefId, $refId)) {
            return 'resource_not_configured';
        }

        if (!$this->courseTrackingRepository->isResourceEnabled($courseRefId, $refId)) {
            return 'resource_disabled';
        }

        return '';
    }

    /** @param array<string,mixed> $record */
    private function logDeniedTrace(string $reason, array $record, string $sourceTable, int $sourceId): void
    {
        try {
            $this->denyLogRepository->log($reason, $record, $sourceTable, $sourceId);
        } catch (Throwable $ignored) {
            // Deny logging must never block cron execution.
        }
    }

    /** @return array<int,string> */
    private function supportedObjectTypes(): array
    {
        return ['blog', 'webr', 'mcst', 'frm', 'wiki', 'htlm', 'lm', 'sahs'];
    }

    private function tableExists(string $table): bool
    {
        return method_exists($this->db, 'tableExists') && $this->db->tableExists($table);
    }
}
