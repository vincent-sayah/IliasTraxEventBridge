<?php

/**
 * ILIAS 10 EventHook plugin skeleton.
 *
 * V0.1 objective:
 * - listen to active ILIAS events through EventHook;
 * - persist a compact debug record into evnt_evhk_itxeb_log;
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
     * @param string $a_component Example: "components/ILIAS/User" or legacy "Services/User".
     * @param string $a_event     Example: "afterUpdate".
     * @param array  $a_parameter Event-specific payload.
     */
    public function handleEvent(string $a_component, string $a_event, array $a_parameter): void
    {
        try {
            $this->includeClass('class.ilIliasTraxEventBridgeConfig.php');
            $this->includeClass('class.ilIliasTraxEventBridgeEventDebugRepository.php');
            $this->includeClass('class.ilIliasTraxEventBridgeEventRouter.php');

            $config = new ilIliasTraxEventBridgeConfig();

            if (!$config->isEnabled() || !$config->isDebugEnabled()) {
                return;
            }

            $router = new ilIliasTraxEventBridgeEventRouter(
                $config,
                new ilIliasTraxEventBridgeEventDebugRepository(),
                $this
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
     * The debug table is removed by sql/dbupdate.php uninstall support in a later version.
     */
    protected function afterUninstall(): void
    {
        if (class_exists('ilSetting')) {
            $settings = new ilSetting('itxeb');
            $settings->deleteAll();
        }
    }
}
