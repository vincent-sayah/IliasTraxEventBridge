<?php

/**
 * Builds local xAPI statements from reliable ILIAS 10 events.
 *
 * V0.6 enrichment:
 * - enriches activities with object/course titles when ILIAS lookups are available;
 * - adds object/course URLs and parent course contextActivities;
 * - exposes read_event metrics in result/context extensions for TRAX analysis;
 * - classifies statements by family and uses more specific verbs per object type.
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

            // V0.5.5: do not generate generic learning-progress statements for course/root updates.
            // Consultations of repository objects are now tracked through read_event instead.
            return null;
        }

        if ($this->isRepositoryObjectAccessEvent($component, $event, $type)) {
            return $this->createRepositoryObjectAccessStatement($record);
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

    private function isRepositoryObjectAccessEvent(string $component, string $event, string $type): bool
    {
        return $component === 'components/ILIAS/ReadEvent'
            && $event === 'access'
            && $this->isRepositoryObjectStatementSupported($type);
    }

    private function isRepositoryObjectStatementSupported(string $type): bool
    {
        return in_array($type, ['blog', 'webr', 'mcst', 'frm', 'wiki', 'htlm', 'lm', 'sahs'], true);
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
        $objectTitle = $this->resolveObjectTitle(
            $record,
            'Fichier ILIAS ' . ($refId > 0 ? 'ref_id ' . $refId : 'obj_id ' . $objId)
        );

        return [
            'id' => $statementId,
            'actor' => $this->actor($userId),
            'verb' => $this->fileDownloadVerb(),
            'object' => [
                'id' => $this->activityId('file', $refId, $objId),
                'objectType' => 'Activity',
                'definition' => $this->activityDefinition(
                    $baseUrl . '/xapi/activity-type/ilias-file',
                    $objectTitle,
                    $this->objectUrl('file', $refId),
                    'Fichier téléchargé depuis ILIAS'
                )
            ],
            'context' => $this->context($record, 'file_downloaded', 'file'),
            'timestamp' => $this->isoTimestamp((string) ($record['created_at'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    private function createRepositoryObjectAccessStatement(array $record): array
    {
        $objType = (string) ($record['obj_type'] ?? 'object');
        $userId = (int) ($record['user_id'] ?? 0);
        $refId = (int) ($record['ref_id'] ?? 0);
        $objId = (int) ($record['obj_id'] ?? 0);
        $baseUrl = $this->config->getIliasBaseUrl();
        $objectTitle = $this->resolveObjectTitle(
            $record,
            $this->repositoryObjectLabel($objType) . ' ' . ($refId > 0 ? 'ref_id ' . $refId : 'obj_id ' . $objId)
        );

        $resultExtensions = [
            $baseUrl . '/xapi/extensions/read_count' => (int) ($record['read_count'] ?? 0),
            $baseUrl . '/xapi/extensions/spent_seconds' => (int) ($record['spent_seconds'] ?? 0),
            $baseUrl . '/xapi/extensions/read_event_last_access' => (int) ($record['read_event_last_access'] ?? 0),
        ];

        if (isset($record['read_event_first_access'])) {
            $resultExtensions[$baseUrl . '/xapi/extensions/read_event_first_access'] = (string) $record['read_event_first_access'];
        }

        $result = [
            'extensions' => $resultExtensions
        ];

        $duration = $this->durationFromSeconds((int) ($record['spent_seconds'] ?? 0));
        if ($duration !== '') {
            $result['duration'] = $duration;
        }

        return [
            'id' => $this->uuid4(),
            'actor' => $this->actor($userId),
            'verb' => $this->repositoryAccessVerb($objType),
            'object' => [
                'id' => $this->activityId($objType !== '' ? $objType : 'object', $refId, $objId),
                'objectType' => 'Activity',
                'definition' => $this->activityDefinition(
                    $this->repositoryActivityType($objType),
                    $objectTitle,
                    $this->objectUrl($objType, $refId),
                    $this->repositoryObjectDescription($objType)
                )
            ],
            'result' => $result,
            'context' => $this->context($record, 'repository_object_access', $objType),
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

        $objectTitle = $this->resolveObjectTitle(
            $record,
            $objType === 'tst'
                ? 'Test ILIAS ' . ($refId > 0 ? 'ref_id ' . $refId : 'obj_id ' . $objId)
                : 'Objet ILIAS ' . ($refId > 0 ? 'ref_id ' . $refId : 'obj_id ' . $objId)
        );

        $sourceEvent = $objType === 'tst' ? 'test_tracking_status' : 'tracking_update_status';

        $statement = [
            'id' => $this->uuid4(),
            'actor' => $this->actor($userId),
            'verb' => $verb,
            'object' => [
                'id' => $this->activityId($objType !== '' ? $objType : 'object', $refId, $objId),
                'objectType' => 'Activity',
                'definition' => $this->activityDefinition(
                    $definitionType,
                    $objectTitle,
                    $this->objectUrl($objType, $refId),
                    $objType === 'tst' ? 'Test ILIAS suivi par le learning progress' : 'Progression ILIAS'
                )
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
                    $baseUrl . '/xapi/extensions/ilias_percentage' => $percentage,
                    $baseUrl . '/xapi/extensions/ilias_status_label' => $this->trackingStatusLabel($status, $percentage),
                ]
            ],
            'context' => $this->context($record, $sourceEvent, $objType),
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
    private function context(array $record, string $sourceEvent, string $objType): array
    {
        $context = [
            'platform' => 'ILIAS 10',
            'extensions' => $this->contextExtensions($record, $sourceEvent, $objType)
        ];

        $courseActivity = $this->courseActivity($record);
        if ($courseActivity !== null) {
            $context['contextActivities'] = [
                'parent' => [$courseActivity]
            ];
        }

        return $context;
    }

    /**
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    private function contextExtensions(array $record, string $sourceEvent, string $objType): array
    {
        $baseUrl = $this->config->getIliasBaseUrl();
        $refId = (int) ($record['ref_id'] ?? 0);
        $courseRefId = (int) ($record['course_ref_id'] ?? 0);

        $extensions = [
            $baseUrl . '/xapi/extensions/source_event' => $sourceEvent,
            $baseUrl . '/xapi/extensions/statement_family' => $this->statementFamily($sourceEvent, $objType),
            $baseUrl . '/xapi/extensions/interaction_type' => $this->interactionType($sourceEvent, $objType),
            $baseUrl . '/xapi/extensions/component' => (string) ($record['component'] ?? ''),
            $baseUrl . '/xapi/extensions/event_name' => (string) ($record['event_name'] ?? ''),
            $baseUrl . '/xapi/extensions/ref_id' => $refId,
            $baseUrl . '/xapi/extensions/obj_id' => (int) ($record['obj_id'] ?? 0),
            $baseUrl . '/xapi/extensions/obj_type' => $objType,
            $baseUrl . '/xapi/extensions/course_ref_id' => $courseRefId,
            $baseUrl . '/xapi/extensions/course_obj_id' => (int) ($record['course_obj_id'] ?? 0),
            $baseUrl . '/xapi/extensions/request_uri' => (string) ($record['request_uri'] ?? '')
        ];

        if ($sourceEvent === 'repository_object_access') {
            $extensions[$baseUrl . '/xapi/extensions/repository_object_family'] = $this->repositoryObjectFamily($objType);
        }

        $objectTitle = $this->resolveObjectTitle($record, '');
        if ($objectTitle !== '') {
            $extensions[$baseUrl . '/xapi/extensions/object_title'] = $objectTitle;
        }

        $objectUrl = $this->objectUrl($objType, $refId);
        if ($objectUrl !== '') {
            $extensions[$baseUrl . '/xapi/extensions/object_url'] = $objectUrl;
        }

        $courseTitle = $this->resolveCourseTitle($record);
        if ($courseTitle !== '') {
            $extensions[$baseUrl . '/xapi/extensions/course_title'] = $courseTitle;
        }

        $courseUrl = $this->courseUrl($courseRefId);
        if ($courseUrl !== '') {
            $extensions[$baseUrl . '/xapi/extensions/course_url'] = $courseUrl;
        }

        if (isset($record['read_count'])) {
            $extensions[$baseUrl . '/xapi/extensions/read_count'] = (int) $record['read_count'];
        }
        if (isset($record['spent_seconds'])) {
            $extensions[$baseUrl . '/xapi/extensions/spent_seconds'] = (int) $record['spent_seconds'];
        }
        if (isset($record['read_event_last_access'])) {
            $extensions[$baseUrl . '/xapi/extensions/read_event_last_access'] = (int) $record['read_event_last_access'];
        }
        if (isset($record['read_event_first_access'])) {
            $extensions[$baseUrl . '/xapi/extensions/read_event_first_access'] = (string) $record['read_event_first_access'];
        }

        return $extensions;
    }

    /**
     * @return array<string,mixed>
     */
    private function fileDownloadVerb(): array
    {
        return [
            'id' => $this->config->getIliasBaseUrl() . '/xapi/verbs/downloaded',
            'display' => [
                'fr-FR' => 'a téléchargé',
                'en-US' => 'downloaded'
            ]
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function repositoryAccessVerb(string $objType): array
    {
        $baseUrl = $this->config->getIliasBaseUrl();
        $map = [
            'blog' => [$baseUrl . '/xapi/verbs/read', 'a lu le blog', 'read blog'],
            'wiki' => [$baseUrl . '/xapi/verbs/read', 'a lu le wiki', 'read wiki'],
            'htlm' => [$baseUrl . '/xapi/verbs/read', 'a lu le module HTML', 'read HTML module'],
            'lm' => [$baseUrl . '/xapi/verbs/read', 'a lu le module', 'read learning module'],
            'sahs' => [$baseUrl . '/xapi/verbs/launched', 'a lancé le module SCORM', 'launched SCORM module'],
            'webr' => [$baseUrl . '/xapi/verbs/visited', 'a visité le lien web', 'visited web link'],
            'mcst' => [$baseUrl . '/xapi/verbs/viewed', 'a visionné le médiacast', 'viewed mediacast'],
            'frm' => [$baseUrl . '/xapi/verbs/interacted', 'a interagi avec le forum', 'interacted with forum'],
        ];

        $verb = $map[$objType] ?? ['http://adlnet.gov/expapi/verbs/experienced', 'a consulté', 'experienced'];

        return [
            'id' => $verb[0],
            'display' => [
                'fr-FR' => $verb[1],
                'en-US' => $verb[2]
            ]
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
                        'fr-FR' => 'a réussi le test',
                        'en-US' => 'passed test'
                    ]
                ];
            }

            if ($status === 3) {
                return [
                    'id' => 'http://adlnet.gov/expapi/verbs/failed',
                    'display' => [
                        'fr-FR' => 'a échoué au test',
                        'en-US' => 'failed test'
                    ]
                ];
            }

            return [
                'id' => 'http://adlnet.gov/expapi/verbs/attempted',
                'display' => [
                    'fr-FR' => 'a commencé le test',
                    'en-US' => 'attempted test'
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

    private function trackingStatusLabel(int $status, float $percentage): string
    {
        if ($status === 2 || $percentage >= 100) {
            return 'completed';
        }

        if ($status === 3) {
            return 'failed';
        }

        if ($percentage > 0) {
            return 'in_progress';
        }

        return 'attempted';
    }

    private function statementFamily(string $sourceEvent, string $objType): string
    {
        if ($sourceEvent === 'file_downloaded') {
            return 'file_download';
        }

        if ($sourceEvent === 'test_tracking_status' || $objType === 'tst') {
            return 'test_tracking';
        }

        if ($sourceEvent === 'repository_object_access') {
            return 'repository_' . $this->repositoryObjectFamily($objType) . '_access';
        }

        return $sourceEvent !== '' ? $sourceEvent : 'unknown';
    }

    private function interactionType(string $sourceEvent, string $objType): string
    {
        if ($sourceEvent === 'file_downloaded') {
            return 'download';
        }

        if ($sourceEvent === 'test_tracking_status' || $objType === 'tst') {
            return 'assessment_progress';
        }

        $map = [
            'blog' => 'read',
            'wiki' => 'read',
            'htlm' => 'read',
            'lm' => 'read',
            'sahs' => 'launch',
            'webr' => 'visit',
            'mcst' => 'view',
            'frm' => 'interact',
        ];

        return $map[$objType] ?? 'experience';
    }

    private function repositoryObjectFamily(string $objType): string
    {
        $map = [
            'blog' => 'blog',
            'webr' => 'web_link',
            'mcst' => 'media',
            'frm' => 'forum',
            'wiki' => 'wiki',
            'htlm' => 'html_module',
            'lm' => 'learning_module',
            'sahs' => 'scorm_module',
        ];

        return $map[$objType] ?? 'repository_object';
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

    private function repositoryObjectDescription(string $objType): string
    {
        $map = [
            'blog' => 'Consultation d’un blog ILIAS dans un cours',
            'webr' => 'Ouverture d’un lien web ILIAS dans un cours',
            'mcst' => 'Consultation d’un médiacast ILIAS dans un cours',
            'frm' => 'Consultation ou interaction avec un forum ILIAS dans un cours',
            'wiki' => 'Consultation d’un wiki ILIAS dans un cours',
            'htlm' => 'Consultation d’un module HTML ILIAS dans un cours',
            'lm' => 'Consultation d’un module web ILIAS dans un cours',
            'sahs' => 'Lancement ou consultation d’un module SCORM ILIAS dans un cours',
        ];

        return $map[$objType] ?? 'Consultation d’un objet de dépôt ILIAS dans un cours';
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
    private function activityDefinition(string $type, string $name, string $moreInfo = '', string $description = ''): array
    {
        $definition = [
            'type' => $type,
            'name' => [
                'fr-FR' => $name,
                'en-US' => $name
            ]
        ];

        if ($description !== '') {
            $definition['description'] = [
                'fr-FR' => $description,
                'en-US' => $description
            ];
        }

        if ($moreInfo !== '') {
            $definition['moreInfo'] = $moreInfo;
        }

        return $definition;
    }

    /**
     * @param array<string,mixed> $record
     * @return array<string,mixed>|null
     */
    private function courseActivity(array $record): ?array
    {
        $courseRefId = (int) ($record['course_ref_id'] ?? 0);
        $courseObjId = (int) ($record['course_obj_id'] ?? 0);

        if ($courseRefId <= 0 && $courseObjId <= 0) {
            return null;
        }

        return [
            'id' => $this->activityId('course', $courseRefId, $courseObjId),
            'objectType' => 'Activity',
            'definition' => $this->activityDefinition(
                $this->config->getIliasBaseUrl() . '/xapi/activity-type/ilias-course',
                $this->resolveCourseTitle($record),
                $this->courseUrl($courseRefId),
                'Cours parent ILIAS'
            )
        ];
    }

    /**
     * @param array<string,mixed> $record
     */
    private function resolveObjectTitle(array $record, string $fallback): string
    {
        $title = trim((string) ($record['object_title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $objId = (int) ($record['obj_id'] ?? 0);
        $title = $this->lookupTitleByObjId($objId);
        if ($title !== '') {
            return $title;
        }

        $refId = (int) ($record['ref_id'] ?? 0);
        if ($refId > 0) {
            $title = $this->lookupTitleByObjId($this->lookupObjectIdByRefId($refId));
            if ($title !== '') {
                return $title;
            }
        }

        return $fallback;
    }

    /**
     * @param array<string,mixed> $record
     */
    private function resolveCourseTitle(array $record): string
    {
        $title = trim((string) ($record['course_title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $courseObjId = (int) ($record['course_obj_id'] ?? 0);
        $title = $this->lookupTitleByObjId($courseObjId);
        if ($title !== '') {
            return $title;
        }

        $courseRefId = (int) ($record['course_ref_id'] ?? 0);
        if ($courseRefId > 0) {
            $title = $this->lookupTitleByObjId($this->lookupObjectIdByRefId($courseRefId));
            if ($title !== '') {
                return $title;
            }
        }

        return $courseRefId > 0 ? 'Cours ILIAS ref_id ' . $courseRefId : 'Cours ILIAS';
    }

    private function objectUrl(string $objType, int $refId): string
    {
        if ($refId <= 0 || $objType === '') {
            return '';
        }

        return $this->config->getIliasBaseUrl() . '/goto.php?target=' . rawurlencode($objType . '_' . $refId);
    }

    private function courseUrl(int $courseRefId): string
    {
        if ($courseRefId <= 0) {
            return '';
        }

        return $this->config->getIliasBaseUrl() . '/goto.php?target=' . rawurlencode('crs_' . $courseRefId);
    }

    private function lookupTitleByObjId(int $objId): string
    {
        if ($objId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupTitle')) {
            return '';
        }

        try {
            $title = ilObject::_lookupTitle($objId);
            return is_scalar($title) ? trim((string) $title) : '';
        } catch (Throwable $ignored) {
            return '';
        }
    }

    private function lookupObjectIdByRefId(int $refId): int
    {
        if ($refId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupObjectId')) {
            return 0;
        }

        try {
            return (int) ilObject::_lookupObjectId($refId);
        } catch (Throwable $ignored) {
            return 0;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function decodePayload(string $payload): array
    {
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function durationFromSeconds(int $seconds): string
    {
        if ($seconds <= 0) {
            return '';
        }

        return 'PT' . $seconds . 'S';
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
