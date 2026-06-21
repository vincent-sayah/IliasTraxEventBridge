<?php

/**
 * Builds local xAPI statements from reliable ILIAS 10 events.
 *
 * V0.5 cleanup:
 * - ignores test administration events such as deleting participant results;
 * - generates statements only after the router has confirmed a course context;
 * - adds course identifiers to xAPI context extensions when available.
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

        if ($this->isRepositoryObjectEventSupported($event, $type)) {
            return $this->createRepositoryObjectStatement($record, $this->repositorySourceEvent($event));
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

    private function isRepositoryObjectEventSupported(string $event, string $type): bool
    {
        if (!$this->isRepositoryObjectStatementSupported($type)) {
            return false;
        }

        // ILIAS object families do not all emit the same component name. For V0.5,
        // accept the reliable high-level object lifecycle events once the router has
        // already confirmed that the object is contained in a course.
        return in_array($event, ['create', 'update'], true);
    }

    private function isRepositoryObjectStatementSupported(string $type): bool
    {
        return in_array($type, ['blog', 'webr', 'mcst', 'frm', 'wiki', 'htlm', 'lm', 'sahs'], true);
    }

    private function repositorySourceEvent(string $event): string
    {
        if ($event === 'create') {
            return 'repository_object_create';
        }

        return 'repository_object_update';
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
                'extensions' => $this->contextExtensions($record, 'file_downloaded', 'file')
            ],
            'timestamp' => $this->isoTimestamp((string) ($record['created_at'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    private function createRepositoryObjectStatement(array $record, string $sourceEvent): array
    {
        $objType = (string) ($record['obj_type'] ?? 'object');
        $userId = (int) ($record['user_id'] ?? 0);
        $refId = (int) ($record['ref_id'] ?? 0);
        $objId = (int) ($record['obj_id'] ?? 0);

        return [
            'id' => $this->uuid4(),
            'actor' => $this->actor($userId),
            'verb' => [
                'id' => 'http://adlnet.gov/expapi/verbs/interacted',
                'display' => [
                    'fr-FR' => 'a interagi avec',
                    'en-US' => 'interacted'
                ]
            ],
            'object' => [
                'id' => $this->activityId($objType !== '' ? $objType : 'object', $refId, $objId),
                'objectType' => 'Activity',
                'definition' => [
                    'type' => $this->repositoryActivityType($objType),
                    'name' => [
                        'fr-FR' => $this->repositoryObjectLabel($objType) . ' ' . ($refId > 0 ? 'ref_id ' . $refId : 'obj_id ' . $objId)
                    ]
                ]
            ],
            'context' => [
                'platform' => 'ILIAS 10',
                'extensions' => $this->contextExtensions($record, $sourceEvent, $objType)
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
                'extensions' => $this->contextExtensions($record, 'tracking_update_status', $objType)
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
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    private function contextExtensions(array $record, string $sourceEvent, string $objType): array
    {
        $baseUrl = $this->config->getIliasBaseUrl();

        return [
            $baseUrl . '/xapi/extensions/source_event' => $sourceEvent,
            $baseUrl . '/xapi/extensions/component' => (string) ($record['component'] ?? ''),
            $baseUrl . '/xapi/extensions/event_name' => (string) ($record['event_name'] ?? ''),
            $baseUrl . '/xapi/extensions/ref_id' => (int) ($record['ref_id'] ?? 0),
            $baseUrl . '/xapi/extensions/obj_id' => (int) ($record['obj_id'] ?? 0),
            $baseUrl . '/xapi/extensions/obj_type' => $objType,
            $baseUrl . '/xapi/extensions/course_ref_id' => (int) ($record['course_ref_id'] ?? 0),
            $baseUrl . '/xapi/extensions/course_obj_id' => (int) ($record['course_obj_id'] ?? 0),
            $baseUrl . '/xapi/extensions/request_uri' => (string) ($record['request_uri'] ?? '')
        ];
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

    private function repositoryActivityType(string $objType): string
    {
        $baseUrl = $this->config->getIliasBaseUrl();
        $map = [
            'blog' => $baseUrl . '/xapi/activity-type/ilias-blog',
            'webr' => $baseUrl . '/xapi/activity-type/ilias-web-link',
            'mcst' => $baseUrl . '/xapi/activity-type/ilias-mediacast',
            'frm' => $baseUrl . '/xapi/activity-type/ilias-forum',
            'wiki' => $baseUrl . '/xapi/activity-type/ilias-wiki',
            'htlm' => $baseUrl . '/xapi/activity-type/ilias-html-learning-module',
            'lm' => $baseUrl . '/xapi/activity-type/ilias-learning-module',
            'sahs' => $baseUrl . '/xapi/activity-type/ilias-scorm-module',
        ];

        return $map[$objType] ?? $baseUrl . '/xapi/activity-type/ilias-repository-object';
    }

    private function repositoryObjectLabel(string $objType): string
    {
        $map = [
            'blog' => 'Blog ILIAS',
            'webr' => 'Lien web ILIAS',
            'mcst' => 'Mediacast ILIAS',
            'frm' => 'Forum ILIAS',
            'wiki' => 'Wiki ILIAS',
            'htlm' => 'Module HTML ILIAS',
            'lm' => 'Module web ILIAS',
            'sahs' => 'Module SCORM ILIAS',
        ];

        return $map[$objType] ?? 'Objet ILIAS';
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
