<?php

use ILIAS\Cron\Schedule\CronJobScheduleType;

require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeOutboxRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeOutboxSender.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeStatementFactory.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseContextResolver.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseTrackingRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeReadEventTracker.php';

/**
 * ILIAS cron job for automatic TRAX delivery.
 */
class ilIliasTraxEventBridgeCron extends ilCronJob
{
    public const JOB_ID = 'itxeb_send_outbox_to_trax';

    public function getId(): string
    {
        return self::JOB_ID;
    }

    public function getTitle(): string
    {
        return 'IliasTraxEventBridge — envoi outbox vers TRAX';
    }

    public function getDescription(): string
    {
        return 'Génère les traces de consultation read_event autorisées, puis envoie automatiquement les statements xAPI generated/failed vers TRAX.';
    }

    public function getDefaultScheduleType(): CronJobScheduleType
    {
        return CronJobScheduleType::SCHEDULE_TYPE_IN_MINUTES;
    }

    public function getDefaultScheduleValue(): int
    {
        return 5;
    }

    public function hasAutoActivation(): bool
    {
        return false;
    }

    public function hasFlexibleSchedule(): bool
    {
        return true;
    }

    public function run(): ilCronJobResult
    {
        $result = new ilCronJobResult();
        $config = new ilIliasTraxEventBridgeConfig();

        if (!$config->isEnabled() || !$config->isCronEnabled()) {
            $message = 'Cron IliasTraxEventBridge désactivé dans la configuration du plugin.';
            $config->setLastCronResult(true, 0, $message);
            $result->setStatus(ilCronJobResult::STATUS_OK);
            $result->setMessage($message);
            return $result;
        }

        try {
            $outbox = new ilIliasTraxEventBridgeOutboxRepository();
            $outbox->resetStuckSending();

            $readGenerated = (new ilIliasTraxEventBridgeReadEventTracker(
                $config,
                $outbox,
                new ilIliasTraxEventBridgeStatementFactory($config),
                new ilIliasTraxEventBridgeCourseContextResolver(),
                new ilIliasTraxEventBridgeCourseTrackingRepository()
            ))->scanAndEnqueue(100);

            $sendResult = (new ilIliasTraxEventBridgeOutboxSender($config, $outbox))->sendBatch();
            $message = 'read_event générés: ' . $readGenerated . ' ; ' . (string) $sendResult['message'];
            $config->setLastCronResult((bool) $sendResult['success'], (int) $sendResult['http_status'], $message);
            $result->setStatus($sendResult['success'] ? ilCronJobResult::STATUS_OK : ilCronJobResult::STATUS_FAIL);
            $result->setMessage($message);
        } catch (Throwable $e) {
            $config->setLastCronResult(false, 0, $e->getMessage());
            $result->setStatus(ilCronJobResult::STATUS_FAIL);
            $result->setMessage($e->getMessage());
        }

        return $result;
    }
}
