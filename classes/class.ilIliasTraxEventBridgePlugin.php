<?php

use ILIAS\Cron\Job\JobProvider;

/**
 * ILIAS 10 EventHook plugin.
 *
 * V0.4 objective:
 * - listen to active ILIAS events through EventHook;
 * - persist compact debug records into evnt_evhk_itxeb_log;
 * - generate local xAPI statements into evnt_evhk_itxeb_out;
 * - send the outbox manually or automatically through ILIAS cron;
 * - never block or break ILIAS user navigation.
 */
class ilIliasTraxEventBridgePlugin extends ilEventHookPlugin implements JobProvider
{
    public const PLUGIN_NAME = 'IliasTraxEventBridge';

    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }

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
            $this->logNonBlockingError($e);
        }
    }

    /** @return array<int, ilCronJob> */
    public function getCronJobInstances(): array
    {
        require_once __DIR__ . '/class.ilIliasTraxEventBridgeCron.php';
        return [new ilIliasTraxEventBridgeCron()];
    }

    /**
     * @throws OutOfBoundsException if the requested cron job does not exist.
     */
    public function getCronJobInstance(string $a_job_id): ilCronJob
    {
        require_once __DIR__ . '/class.ilIliasTraxEventBridgeCron.php';
        if ($a_job_id === ilIliasTraxEventBridgeCron::JOB_ID) {
            return new ilIliasTraxEventBridgeCron();
        }

        throw new OutOfBoundsException('Unknown IliasTraxEventBridge cron job: ' . $a_job_id);
    }

    protected function afterUninstall(): void
    {
        if (class_exists('ilSetting')) {
            $settings = new ilSetting('itxeb');
            $settings->deleteAll();
        }
    }

    private function logNonBlockingError(Throwable $e): void
    {
        if (class_exists('ilLoggerFactory')) {
            try {
                ilLoggerFactory::getLogger('itxeb')->error($e->getMessage());
                return;
            } catch (Throwable $ignored) {
                // Fallback below.
            }
        }
        error_log('[IliasTraxEventBridge] ' . $e->getMessage());
    }
}
