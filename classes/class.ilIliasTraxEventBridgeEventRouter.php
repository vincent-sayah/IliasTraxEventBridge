<?php

/**
 * V0.8 router:
 * - persists every received event in debug mode;
 * - generates local xAPI statements only for reliable events contained in a course;
 * - applies explicit course/resource xAPI configuration before outbox insertion;
 * - stores accepted statements in the outbox;
 * - logs denied statements in evnt_evhk_itxeb_dlog only when deny diagnostics are enabled.
 */
class ilIliasTraxEventBridgeEventRouter
{
    /** @var ilIliasTraxEventBridgeConfig */
    private $config;

    /** @var ilIliasTraxEventBridgeEventDebugRepository */
    private $repository;

    /** @var ilIliasTraxEventBridgePlugin */
    private $plugin;

    /** @var ilIliasTraxEventBridgeStatementFactory|null */
    private $statementFactory;

    /** @var ilIliasTraxEventBridgeOutboxRepository|null */
    private $outboxRepository;

    /** @var ilIliasTraxEventBridgeCourseContextResolver|null */
    private $courseContextResolver;

    /** @var ilIliasTraxEventBridgeCourseTrackingRepository|null */
    private $courseTrackingRepository;

    /** @var ilIliasTraxEventBridgeDenyLogRepository|null */
    private $denyLogRepository;

    public function __construct(
        ilIliasTraxEventBridgeConfig $config,
        ilIliasTraxEventBridgeEventDebugRepository $repository,
        ilIliasTraxEventBridgePlugin $plugin,
        ?ilIliasTraxEventBridgeStatementFactory $statementFactory = null,
        ?ilIliasTraxEventBridgeOutboxRepository $outboxRepository = null,
        ?ilIliasTraxEventBridgeCourseContextResolver $courseContextResolver = null,
        ?ilIliasTraxEventBridgeCourseTrackingRepository $courseTrackingRepository = null,
        ?ilIliasTraxEventBridgeDenyLogRepository $denyLogRepository = null
    ) {
        $this->config = $config;
        $this->repository = $repository;
        $this->plugin = $plugin;
        $this->statementFactory = $statementFactory;
        $this->outboxRepository = $outboxRepository;
        $this->courseContextResolver = $courseContextResolver;
        $this->courseTrackingRepository = $courseTrackingRepository;
        $this->denyLogRepository = $denyLogRepository;
    }

    public function handle(string $component, string $event, array $params): void
    {
        $record = [
            'component' => $component,
            'event_name' => $event,
            'user_id' => $this->detectCurrentUserId($params),
            'ref_id' => $this->detectRefId($params),
            'obj_id' => $this->detectObjId($params),
            'obj_type' => $this->detectObjType($params),
            'param_keys' => implode(',', array_slice(array_keys($params), 0, 80)),
            'payload_json' => $this->safeJson($params, $this->config->getMaxPayloadChars()),
            'created_at' => date('Y-m-d H:i:s'),
            'created_ts' => time(),
            'request_uri' => $this->getServerValue('REQUEST_URI'),
            'http_method' => $this->getServerValue('REQUEST_METHOD'),
        ];

        $eventLogId = $this->repository->insert($record);

        if (!$this->config->isLocalXapiGenerationEnabled()) {
            return;
        }

        if ($this->statementFactory === null || $this->outboxRepository === null) {
            return;
        }

        $courseContext = $this->getCourseContextResolver()->resolve($record);
        if (!$courseContext['is_in_course']) {
            $this->logDeniedTrace('not_in_course', $record, 'evnt_evhk_itxeb_log', $eventLogId);
            return;
        }

        if ((int) $record['ref_id'] <= 0 && (int) $courseContext['ref_id'] > 0) {
            $record['ref_id'] = (int) $courseContext['ref_id'];
        }

        if ((string) $record['obj_type'] === '') {
            $repoType = $this->detectObjTypeFromRepository($record);
            if ($repoType !== '') {
                $record['obj_type'] = $repoType;
            }
        }

        $record['course_ref_id'] = (int) $courseContext['course_ref_id'];
        $record['course_obj_id'] = (int) $courseContext['course_obj_id'];

        $denialReason = $this->trackingDenialReason((int) $record['course_ref_id'], (int) $record['ref_id']);
        if ($denialReason !== '') {
            $this->logDeniedTrace($denialReason, $record, 'evnt_evhk_itxeb_log', $eventLogId);
            return;
        }

        $statements = method_exists($this->statementFactory, 'createStatementsFromEventRecord')
            ? $this->statementFactory->createStatementsFromEventRecord($record)
            : [$this->statementFactory->createFromEventRecord($record)];
        $enqueued = 0;
        foreach ($statements as $statement) {
            if (is_array($statement)) {
                $this->outboxRepository->enqueue($record, $statement, $eventLogId);
                $enqueued++;
            }
        }
        if ($enqueued === 0) {
            $this->logDeniedTrace('unsupported_object_type', $record, 'evnt_evhk_itxeb_log', $eventLogId);
        }
    }

    private function detectCurrentUserId(array $params): int
    {
        $fromParams = $this->detectInt($params, ['usr_id', 'user_id', 'userId', 'member_id']);
        if ($fromParams > 0) {
            return $fromParams;
        }

        try {
            if (isset($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'user')) {
                $user = $GLOBALS['DIC']->user();
                if (is_object($user) && method_exists($user, 'getId')) {
                    return (int) $user->getId();
                }
            }
        } catch (Throwable $ignored) {
            // keep fallback below
        }

        if (isset($GLOBALS['ilUser']) && is_object($GLOBALS['ilUser']) && method_exists($GLOBALS['ilUser'], 'getId')) {
            return (int) $GLOBALS['ilUser']->getId();
        }

        return 0;
    }

    private function detectRefId(array $params): int
    {
        $fromParams = $this->detectInt($params, ['ref_id', 'refId', 'reference_id', 'test_ref_id', 'course_ref_id']);
        if ($fromParams > 0) {
            return $fromParams;
        }

        return $this->detectRequestInt(['ref_id', 'refId', 'reference_id']);
    }

    private function detectObjId(array $params): int
    {
        return $this->detectInt($params, ['obj_id', 'objId', 'object_id', 'test_obj_id', 'course_obj_id']);
    }

    private function detectObjType(array $params): string
    {
        $fromParams = $this->detectString($params, ['obj_type', 'type', 'object_type']);
        if ($fromParams !== '') {
            return $fromParams;
        }

        $cmdClass = $this->detectRequestString(['cmdClass']);
        $map = [
            'ilObjCourseGUI' => 'crs',
            'ilObjFileGUI' => 'file',
            'ilObjTestGUI' => 'tst',
            'ilTestPlayerFixedQuestionSetGUI' => 'tst',
            'ilTestPlayerDynamicQuestionSetGUI' => 'tst',
            'ilObjLearningModuleGUI' => 'lm',
            'ilObjFileBasedLMGUI' => 'htlm',
            'ilObjWikiGUI' => 'wiki',
            'ilObjForumGUI' => 'frm',
            'ilObjExerciseGUI' => 'exc',
            'ilObjSCORM2004LearningModuleGUI' => 'sahs',
            'ilObjSAHSLearningModuleGUI' => 'sahs',
            'ilObjBlogGUI' => 'blog',
            'ilObjLinkResourceGUI' => 'webr',
            'ilLinkResourceHandlerGUI' => 'webr',
            'ilObjMediaCastGUI' => 'mcst',
            'ilMediaCastHandlerGUI' => 'mcst',
        ];

        return $map[$cmdClass] ?? '';
    }

    private function detectObjTypeFromRepository(array $record): string
    {
        if (!class_exists('ilObject') || !method_exists('ilObject', '_lookupType')) {
            return '';
        }

        $refId = (int) ($record['ref_id'] ?? 0);
        if ($refId > 0) {
            try {
                $type = ilObject::_lookupType($refId, true);
                if (is_scalar($type) && (string) $type !== '') {
                    return (string) $type;
                }
            } catch (Throwable $ignored) {
                // Try obj_id below.
            }
        }

        $objId = (int) ($record['obj_id'] ?? 0);
        if ($objId > 0) {
            try {
                $type = ilObject::_lookupType($objId);
                if (is_scalar($type) && (string) $type !== '') {
                    return (string) $type;
                }
            } catch (Throwable $ignored) {
                return '';
            }
        }

        return '';
    }

    private function detectInt(array $params, array $keys): int
    {
        foreach ($keys as $key) {
            if (isset($params[$key]) && is_scalar($params[$key])) {
                return (int) $params[$key];
            }
        }

        return 0;
    }

    private function detectString(array $params, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($params[$key]) && is_scalar($params[$key])) {
                return substr((string) $params[$key], 0, 64);
            }
        }

        return '';
    }

    private function safeJson(array $params, int $maxChars): string
    {
        $normalized = $this->normalize($params, 0);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if (!is_string($json)) {
            $json = json_encode(['error' => 'json_encode_failed'], JSON_UNESCAPED_SLASHES);
        }

        if (!is_string($json)) {
            return '{"error":"json_encode_failed"}';
        }

        if (strlen($json) > $maxChars) {
            return substr($json, 0, $maxChars) . '...<truncated>';
        }

        return $json;
    }

    /**
     * Normalize event params without trying to serialize huge ILIAS objects.
     */
    private function normalize($value, int $depth)
    {
        if ($depth > 3) {
            return '...<max_depth>';
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            $out = [];
            $count = 0;
            foreach ($value as $k => $v) {
                if ($count++ >= 50) {
                    $out['...'] = '<too_many_items>';
                    break;
                }
                $out[(string) $k] = $this->normalize($v, $depth + 1);
            }
            return $out;
        }

        if (is_object($value)) {
            $summary = ['__class' => get_class($value)];
            foreach (['getId', 'getRefId', 'getObjId', 'getType', 'getTitle', 'getLogin'] as $method) {
                if (method_exists($value, $method)) {
                    try {
                        $summary[$method] = $value->$method();
                    } catch (Throwable $ignored) {
                        $summary[$method] = '<unavailable>';
                    }
                }
            }
            return $summary;
        }

        return gettype($value);
    }

    private function detectRequestInt(array $keys): int
    {
        foreach ($keys as $key) {
            if (isset($_GET[$key]) && is_scalar($_GET[$key])) {
                return (int) $_GET[$key];
            }
        }

        $query = parse_url($this->getServerValue('REQUEST_URI'), PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return 0;
        }

        parse_str($query, $values);
        foreach ($keys as $key) {
            if (isset($values[$key]) && is_scalar($values[$key])) {
                return (int) $values[$key];
            }
        }

        return 0;
    }

    private function detectRequestString(array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($_GET[$key]) && is_scalar($_GET[$key])) {
                return substr((string) $_GET[$key], 0, 128);
            }
        }

        $query = parse_url($this->getServerValue('REQUEST_URI'), PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return '';
        }

        parse_str($query, $values);
        foreach ($keys as $key) {
            if (isset($values[$key]) && is_scalar($values[$key])) {
                return substr((string) $values[$key], 0, 128);
            }
        }

        return '';
    }

    private function getCourseContextResolver(): ilIliasTraxEventBridgeCourseContextResolver
    {
        if ($this->courseContextResolver === null) {
            $this->courseContextResolver = new ilIliasTraxEventBridgeCourseContextResolver();
        }

        return $this->courseContextResolver;
    }

    private function getCourseTrackingRepository(): ilIliasTraxEventBridgeCourseTrackingRepository
    {
        if ($this->courseTrackingRepository === null) {
            $this->courseTrackingRepository = new ilIliasTraxEventBridgeCourseTrackingRepository();
        }

        return $this->courseTrackingRepository;
    }

    private function getDenyLogRepository(): ilIliasTraxEventBridgeDenyLogRepository
    {
        if ($this->denyLogRepository === null) {
            $this->denyLogRepository = new ilIliasTraxEventBridgeDenyLogRepository();
        }

        return $this->denyLogRepository;
    }

    private function trackingDenialReason(int $courseRefId, int $refId): string
    {
        if ($courseRefId <= 0) {
            return 'missing_course_context';
        }

        if ($refId <= 0) {
            return 'missing_resource_context';
        }

        $repository = $this->getCourseTrackingRepository();
        if (!$repository->isCourseConfigured($courseRefId)) {
            return 'course_not_configured';
        }

        if (!$repository->isCourseEnabled($courseRefId)) {
            return 'course_disabled';
        }

        if (!$repository->isResourceConfigured($courseRefId, $refId)) {
            return 'resource_not_configured';
        }

        if (!$repository->isResourceEnabled($courseRefId, $refId)) {
            return 'resource_disabled';
        }

        return '';
    }

    /** @param array<string,mixed> $record */
    private function logDeniedTrace(string $reason, array $record, string $sourceTable, int $sourceId): void
    {
        if (!$this->config->isDenyLogEnabled()) {
            return;
        }

        try {
            $this->getDenyLogRepository()->log($reason, $record, $sourceTable, $sourceId);
        } catch (Throwable $ignored) {
            // Deny logging must never block ILIAS navigation or statement generation.
        }
    }

    private function getServerValue(string $key): string
    {
        return isset($_SERVER[$key]) && is_scalar($_SERVER[$key]) ? substr((string) $_SERVER[$key], 0, 1024) : '';
    }
}
