<?php

/**
 * Builds local xAPI statements from reliable ILIAS 10 events.
 *
 * V0.2.1 cleanup:
 * - ignores test administration events such as deleting participant results;
 * - generates test statements only for real test-player tracking events or obj_type=tst;
 * - ignores ambiguous Tracking/updateStatus events with empty obj_type unless they clearly come from the test player.
 */
class ilIliasTraxEventBridgeStatementFactory
{
    /** @var ilIliasTraxEventBridgeConfig */
    private $config;

    public function __construct(ilIliasTraxEventBridgeConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @param array<string,mixed> $record
     * @return array<string,mixed>|null
     */
    public function createFromEventRecord(array $record): ?array
    {
        $component = (string) ($record['component'] ?? '');
        $event = (string) ($record['event_name'] ?? '');
        $type = (string) ($record['obj_type'] ?? '');
        $uri = (string) ($record['request_uri'] ?? '');

        if ($this->isIgnoredForXapi($record)) {
            return null;
        }

        if ($component === 'components/ILIAS/ILIASObject'
            && $event === 'update'
            && $type === 'file'
            && strpos($uri, 'cmd=sendfile') !== false
        ) {
            return $this->createFileDownloadStatement($record);
        }

        if ($component === 'components/ILIAS/Tracking' && $event === 'updateStatus') {
            if ($this->isTestTrackingEvent($record)) {
                $record['obj_type'] = 'tst';
                return $this->createTrackingStatement($record);
            }

            // Avoid false learning traces caused by admin maintenance actions or unknown empty types.
            // Non-test learning tracking is kept only when ILIAS gives us a concrete object type.
            if ($type !== '') {
                return $this->createTrackingStatement($record);
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $record
     */
    public function getIgnoreReason(array $record): string
    {
        $uri = (string) ($record['request_uri'] ?? '');

        if (strpos($uri, 'cmdClass=ilTestParticipantsGUI') !== false) {
            return 'ignored: test administration';
        }

        if (strpos($uri, 'pt_action=delete_results') !== false || strpos($uri, 'delete_results') !== false) {
            return 'ignored: test results deletion';
        }

        if (strpos($uri, 'cmd=executeTableAction') !== false) {
            return 'ignored: admin table action';
        }

        return '';
    }

    /**
     * @param array<string,mixed> $record
     */
    private function isIgnoredForXapi(array $record): bool
    {
        return $this->getIgnoreReason($record) !== '';
    }

    /**
     * @param array<string,mixed> $record
     */
    private function isTestTrackingEvent(array $record): bool
    {
        $type = (string) ($record['obj_type'] ?? '');
        $uri = (string) ($record['request_uri'] ?? '');

        if ($type === 'tst') {
            return true;
        }

        return strpos($uri, 'cmdClass=ilTestPlayerFixedQuestionSetGUI') !== false
            || strpos($uri, 'cmdClass=ilTestPlayerDynamicQuestionSetGUI') !== false
            || strpos($uri, 'cmd=startTest') !== false
            || strpos($uri, 'cmd=finishTest') !== false;
    }

    /**
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    private function createFileDownloadStatement(array $record): array
    {
        $statementId = $this->uuid4();
        $userId = (int) ($record['user_id'] ?? 0);
        $refId = (int) ($record['ref_id'] ?? 0);
        $objId = (int) ($record['obj_id'] ?? 0);
        $baseUrl = $this->config->getIliasBaseUrl();

        return [
            'id' => $statementId,
            'actor' => $this->actor($userId),
            'verb' => [
                'id' => 'http://adlnet.gov/expapi/verbs/experienced',
                'display' => [
                    'fr-FR' => 'a consulté',
                    'en-US' => 'experienced'
                ]
            ],
            'object' => [
                'id' => $this->activityId('file', $refId, $objId),
                'objectType' => 'Activity',
                'definition' => [
                    'type' => $baseUrl . '/xapi/activity-type/ilias-file',
                    'name' => [
                        'fr-FR' => 'Fichier ILIAS ' . ($refId > 0 ? 'ref_id ' . $refId : 'obj_id ' . $objId)
                    ]
                ]
            ],
            'context' => [
                'platform' => 'ILIAS 10',
                'extensions' => [
                    $baseUrl . '/xapi/extensions/source_event' => 'file_downloaded',
                    $baseUrl . '/xapi/extensions/component' => (string) ($record['component'] ?? ''),
                    $baseUrl . '/xapi/extensions/event_name' => (string) ($record['event_name'] ?? ''),
                    $baseUrl . '/xapi/extensions/ref_id' => $refId,
                    $baseUrl . '/xapi/extensions/obj_id' => $objId,
                    $baseUrl . '/xapi/extensions/obj_type' => 'file',
                    $baseUrl . '/xapi/extensions/request_uri' => (string) ($record['request_uri'] ?? '')
                ]
            ],
            'timestamp' => $this->isoTimestamp((string) ($record['created_at'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    private function createTrackingStatement(array $record): array
    {
        $payload = $this->decodePayload((string) ($record['payload_json'] ?? ''));
        $status = (int) ($payload['status'] ?? -1);
        $oldStatus = (int) ($payload['old_status'] ?? -1);
        $percentage = (float) ($payload['percentage'] ?? 0);

        $objType = (string) ($record['obj_type'] ?? '');
        $refId = (int) ($record['ref_id'] ?? 0);
        $objId = (int) ($record['obj_id'] ?? 0);
        $userId = (int) ($payload['usr_id'] ?? ($record['user_id'] ?? 0));
        $baseUrl = $this->config->getIliasBaseUrl();

        $verb = $this->trackingVerb($objType, $status, $percentage);

        $definitionType = $objType === 'tst'
            ? 'http://adlnet.gov/expapi/activities/assessment'
            : $baseUrl . '/xapi/activity-type/ilias-learning-progress';

        $statement = [
            'id' => $this->uuid4(),
            'actor' => $this->actor($userId),
            'verb' => $verb,
            'object' => [
                'id' => $this->activityId($objType !== '' ? $objType : 'object', $refId, $objId),
                'objectType' => 'Activity',
                'definition' => [
                    'type' => $definitionType,
                    'name' => [
                        'fr-FR' => $objType === 'tst'
                            ? 'Test ILIAS ' . ($refId > 0 ? 'ref_id ' . $refId : 'obj_id ' . $objId)
                            : 'Objet ILIAS ' . ($refId > 0 ? 'ref_id ' . $refId : 'obj_id ' . $objId)
                    ]
                ]
            ],
            'result' => [
                'completion' => $status === 2 || $percentage >= 100,
                'score' => [
                    'scaled' => max(0, min(1, $percentage / 100)),
                    'raw' => $percentage,
                    'min' => 0,
                    'max' => 100
                ],
                'extensions' => [
                    $baseUrl . '/xapi/extensions/ilias_status' => $status,
                    $baseUrl . '/xapi/extensions/ilias_old_status' => $oldStatus,
                    $baseUrl . '/xapi/extensions/ilias_percentage' => $percentage
                ]
            ],
            'context' => [
                'platform' => 'ILIAS 10',
                'extensions' => [
                    $baseUrl . '/xapi/extensions/source_event' => 'tracking_update_status',
                    $baseUrl . '/xapi/extensions/component' => (string) ($record['component'] ?? ''),
                    $baseUrl . '/xapi/extensions/event_name' => (string) ($record['event_name'] ?? ''),
                    $baseUrl . '/xapi/extensions/ref_id' => $refId,
                    $baseUrl . '/xapi/extensions/obj_id' => $objId,
                    $baseUrl . '/xapi/extensions/obj_type' => $objType,
                    $baseUrl . '/xapi/extensions/request_uri' => (string) ($record['request_uri'] ?? '')
                ]
            ],
            'timestamp' => $this->isoTimestamp((string) ($record['created_at'] ?? '')),
        ];

        if ($objType === 'tst') {
            if ($status === 2 || $percentage >= 100) {
                $statement['result']['success'] = true;
            } elseif ($status === 3) {
                $statement['result']['success'] = false;
            }
        }

        return $statement;
    }

    /**
     * @return array<string,mixed>
     */
    private function trackingVerb(string $objType, int $status, float $percentage): array
    {
        if ($objType === 'tst') {
            if ($status === 2 || $percentage >= 100) {
                return [
                    'id' => 'http://adlnet.gov/expapi/verbs/passed',
                    'display' => [
                        'fr-FR' => 'a réussi',
                        'en-US' => 'passed'
                    ]
                ];
            }

            if ($status === 3) {
                return [
                    'id' => 'http://adlnet.gov/expapi/verbs/failed',
                    'display' => [
                        'fr-FR' => 'a échoué',
                        'en-US' => 'failed'
                    ]
                ];
            }

            return [
                'id' => 'http://adlnet.gov/expapi/verbs/attempted',
                'display' => [
                    'fr-FR' => 'a commencé',
                    'en-US' => 'attempted'
                ]
            ];
        }

        if ($status === 2 || $percentage >= 100) {
            return [
                'id' => 'http://adlnet.gov/expapi/verbs/completed',
                'display' => [
                    'fr-FR' => 'a terminé',
                    'en-US' => 'completed'
                ]
            ];
        }

        return [
            'id' => 'http://adlnet.gov/expapi/verbs/progressed',
            'display' => [
                'fr-FR' => 'a progressé',
                'en-US' => 'progressed'
            ]
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function actor(int $userId): array
    {
        return [
            'objectType' => 'Agent',
            'account' => [
                'homePage' => $this->config->getActorHomePage(),
                'name' => 'ilias-user-' . max(0, $userId)
            ]
        ];
    }

    private function activityId(string $type, int $refId, int $objId): string
    {
        $safeType = preg_replace('/[^a-zA-Z0-9_-]/', '-', $type);
        if (!is_string($safeType) || $safeType === '') {
            $safeType = 'object';
        }

        if ($refId > 0) {
            return $this->config->getIliasBaseUrl() . '/xapi/activity/' . $safeType . '/ref/' . $refId;
        }

        return $this->config->getIliasBaseUrl() . '/xapi/activity/' . $safeType . '/obj/' . max(0, $objId);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodePayload(string $payload): array
    {
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function isoTimestamp(string $createdAt): string
    {
        $ts = strtotime($createdAt);
        if ($ts === false) {
            $ts = time();
        }

        return date('c', $ts);
    }

    private function uuid4(): string
    {
        try {
            $data = random_bytes(16);
        } catch (Throwable $e) {
            $data = openssl_random_pseudo_bytes(16);
            if ($data === false) {
                $data = md5(uniqid('', true), true);
            }
        }

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
