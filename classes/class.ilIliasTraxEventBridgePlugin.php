<?php

/**
 * ILIAS 10 EventHook plugin.
 *
 * V0.2 objective:
 * - listen to active ILIAS events through EventHook;
 * - persist compact debug records into evnt_evhk_itxeb_log;
 * - generate local xAPI statements for confirmed events into evnt_evhk_itxeb_out;
 * - never block or break ILIAS user navigation.
 */
class ilIliasTraxEventBridgePlugin extends ilEventHookPlugin
{
    public const PLUGIN_NAME = 'IliasTraxEventBridge';

    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }

    /**
     * Called by ILIAS for events received by active EventHook plugins.
     *
     * @param string $a_component Example: "components/ILIAS/Tracking".
     * @param string $a_event     Example: "updateStatus".
     * @param array  $a_parameter Event-specific payload.
     */
    public function handleEvent(string $a_component, string $a_event, array $a_parameter): void
    {
        try {
            require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
            require_once __DIR__ . '/class.ilIliasTraxEventBridgeEventDebugRepository.php';
            require_once __DIR__ . '/class.ilIliasTraxEventBridgeStatementFactory.php';
            require_once __DIR__ . '/class.ilIliasTraxEventBridgeOutboxRepository.php';
            require_once __DIR__ . '/class.ilIliasTraxEventBridgeEventRouter.php';

            $config = new ilIliasTraxEventBridgeConfig();

            if (!$config->isEnabled() || !$config->isDebugEnabled()) {
                return;
            }

            $router = new ilIliasTraxEventBridgeEventRouter(
                $config,
                new ilIliasTraxEventBridgeEventDebugRepository(),
                $this,
                new ilIliasTraxEventBridgeStatementFactory($config),
                new ilIliasTraxEventBridgeOutboxRepository()
            );

            $router->handle($a_component, $a_event, $a_parameter);
        } catch (Throwable $e) {
            // Critical rule: this plugin must never interrupt normal ILIAS execution.
            if (class_exists('ilLoggerFactory')) {
                try {
                    ilLoggerFactory::getLogger('itxeb')->error($e->getMessage());
                } catch (Throwable $ignored) {
                    error_log('[IliasTraxEventBridge] ' . $e->getMessage());
                }
            } else {
                error_log('[IliasTraxEventBridge] ' . $e->getMessage());
            }
        }
    }

    /**
     * Called after uninstall. Removes plugin settings only.
     * Debug/outbox tables are intentionally kept in V0.2.
     */
    protected function afterUninstall(): void
    {
        if (class_exists('ilSetting')) {
            $settings = new ilSetting('itxeb');
            $settings->deleteAll();
        }
    }
}
