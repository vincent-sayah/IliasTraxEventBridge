<?php

/**
 * V0.1 router: persist every received event in debug mode.
 * Mapping to xAPI will be introduced after observing real ILIAS 10 events.
 */
class ilIliasTraxEventBridgeEventRouter
{
    /** @var ilIliasTraxEventBridgeConfig */
    private $config;

    /** @var ilIliasTraxEventBridgeEventDebugRepository */
    private $repository;

    /** @var ilIliasTraxEventBridgePlugin */
    private $plugin;

    public function __construct(
        ilIliasTraxEventBridgeConfig $config,
        ilIliasTraxEventBridgeEventDebugRepository $repository,
        ilIliasTraxEventBridgePlugin $plugin
    ) {
        $this->config = $config;
        $this->repository = $repository;
        $this->plugin = $plugin;
    }

    public function handle(string $component, string $event, array $params): void
    {
        $record = [
            'component' => $component,
            'event_name' => $event,
            'user_id' => $this->detectCurrentUserId($params),
            'ref_id' => $this->detectInt($params, ['ref_id', 'refId', 'reference_id', 'test_ref_id', 'course_ref_id']),
            'obj_id' => $this->detectInt($params, ['obj_id', 'objId', 'object_id', 'test_obj_id', 'course_obj_id']),
            'obj_type' => $this->detectString($params, ['obj_type', 'type', 'object_type']),
            'param_keys' => implode(',', array_slice(array_keys($params), 0, 80)),
            'payload_json' => $this->safeJson($params, $this->config->getMaxPayloadChars()),
            'created_at' => date('Y-m-d H:i:s'),
            'created_ts' => time(),
            'request_uri' => $this->getServerValue('REQUEST_URI'),
            'http_method' => $this->getServerValue('REQUEST_METHOD'),
        ];

        $this->repository->insert($record);
    }

    private function detectCurrentUserId(array $params): int
    {
        $fromParams = $this->detectInt($params, ['usr_id', 'user_id', 'userId', 'member_id', 'active_id']);
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

    private function getServerValue(string $key): string
    {
        return isset($_SERVER[$key]) && is_scalar($_SERVER[$key]) ? substr((string) $_SERVER[$key], 0, 512) : '';
    }
}
