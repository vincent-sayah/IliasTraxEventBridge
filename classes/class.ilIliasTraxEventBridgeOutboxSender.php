<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeOutboxRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeTraxClient.php';

/**
 * Shared outbox sending service used by manual admin action and cron.
 */
class ilIliasTraxEventBridgeOutboxSender
{
    /** @var ilIliasTraxEventBridgeConfig */
    private $config;

    /** @var ilIliasTraxEventBridgeOutboxRepository */
    private $outbox;

    public function __construct(ilIliasTraxEventBridgeConfig $config, ilIliasTraxEventBridgeOutboxRepository $outbox)
    {
        $this->config = $config;
        $this->outbox = $outbox;
    }

    /** @return array{success:bool,http_status:int,message:string,processed:int} */
    public function sendBatch(): array
    {
        if (!$this->config->isTraxConfigured()) {
            return ['success' => false, 'http_status' => 0, 'message' => 'Configuration TRAX incomplète.', 'processed' => 0];
        }

        $rows = $this->outbox->findSendable($this->config->getBatchSize(), $this->config->getMaxRetry());
        if (count($rows) === 0) {
            return ['success' => true, 'http_status' => 0, 'message' => 'Aucun statement éligible à envoyer.', 'processed' => 0];
        }

        $validIds = [];
        $invalidIds = [];
        $statements = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $decoded = json_decode((string) ($row['statement_json'] ?? ''), true);
            if ($id > 0 && is_array($decoded)) {
                $validIds[] = $id;
                $statements[] = $decoded;
            } elseif ($id > 0) {
                $invalidIds[] = $id;
            }
        }

        if (count($invalidIds) > 0) {
            $this->outbox->markFailed($invalidIds, 'Statement JSON invalide dans l’outbox.');
        }

        if (count($validIds) === 0) {
            return ['success' => false, 'http_status' => 0, 'message' => 'Aucun statement JSON valide dans le batch.', 'processed' => count($invalidIds)];
        }

        $this->outbox->markSending($validIds);
        $client = new ilIliasTraxEventBridgeTraxClient($this->config);
        $result = $client->sendStatements($statements);

        if ($result->isSuccess()) {
            $this->outbox->markSent($validIds);
            return [
                'success' => true,
                'http_status' => $result->getHttpStatus(),
                'message' => count($validIds) . ' statement(s) envoyé(s) vers TRAX. ' . $result->getShortMessage(),
                'processed' => count($validIds) + count($invalidIds),
            ];
        }

        $this->outbox->markFailed($validIds, $result->getShortMessage());
        return [
            'success' => false,
            'http_status' => $result->getHttpStatus(),
            'message' => 'Envoi TRAX échoué : ' . $result->getShortMessage(),
            'processed' => count($validIds) + count($invalidIds),
        ];
    }
}
