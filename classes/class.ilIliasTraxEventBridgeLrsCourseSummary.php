<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeLrsReadClient.php';

/**
 * Read-only LRS analytics for one ILIAS course.
 *
 * This class is the source for the course xAPI dashboard, analysis and expert
 * views. The local outbox remains a technical sending queue only.
 */
class ilIliasTraxEventBridgeLrsCourseSummary
{
    /** @var ilIliasTraxEventBridgeConfig */
    private $config;

    /** @var ilIliasTraxEventBridgeLrsReadClient */
    private $client;

    public function __construct()
    {
        $this->config = new ilIliasTraxEventBridgeConfig();
        $this->client = new ilIliasTraxEventBridgeLrsReadClient($this->config);
    }

    /**
     * @param array<string,mixed> $course
     * @return array<string,mixed>
     */
    public function build(array $course, int $days = 30): array
    {
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $courseObjId = (int) ($course['course_obj_id'] ?? 0);
        $days = max(1, min(365, $days));
        $periodStartTs = time() - ($days * 86400);
        // TRAX/LRS peut retourner une page vide sur une fenêtre courte alors que
        // les mêmes statements existent dans une fenêtre plus large. Pour éviter
        // des KPIs à 0 incohérents, on interroge plus large pour 7/30 jours puis
        // on post-filtre chaque statement sur la vraie période demandée.
        $queryDays = $days <= 30 ? min(365, max($days * 2, 14)) : $days;
        $activity = $this->courseActivityId($courseRefId, $courseObjId);
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - ($queryDays * 86400));
        $allowedResources = $this->allowedResources($course);

        $summary = [
            'available' => false,
            'http_status' => 0,
            'error' => '',
            'activity_id' => $activity,
            'since' => gmdate('Y-m-d\TH:i:s\Z', $periodStartTs),
            'query_since' => $since,
            'period_days' => $days,
            'period_start_ts' => $periodStartTs,
            'returned' => 0,
            'more' => '',
            'pages' => 0,
            'pagination_complete' => true,
            'pagination_limit_reached' => false,
            'pagination_error' => '',
            'learners' => [],
            'scores' => [],
            'summary' => [
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'active_learners' => 0,
                'resources_total' => count($allowedResources),
                'resources_with_traces' => 0,
                'avg_score_raw' => null,
                'tests_attempted' => 0,
                'tests_passed' => 0,
                'tests_failed' => 0,
                'mediacast_internal_played' => 0,
                'mediacast_external_opened' => 0,
                'mediacast_media_total' => 0,
                'mediacast_media_unique' => 0,
                'mediacast_media_learners' => 0,
            ],
            'pedagogy' => [
                'ok_count' => 0,
                'watch_count' => 0,
                'critical_count' => 0,
                'disabled_count' => 0,
                'resources_without_trace' => 0,
                'high_failure_resources' => 0,
                'low_score_resources' => 0,
                'synthesis_lines' => [],
            ],
            'by_day' => [],
            'by_verb' => [],
            'by_status' => [],
            'by_resource' => [],
            'by_mediacast_media' => [],
            'mediacast_media_learners' => [],
            'expert_rows' => [],
        ];

        foreach ($allowedResources as $refId => $resource) {
            $summary['by_resource']['ref:' . $refId] = [
                'key' => 'ref:' . $refId,
                'ref_id' => $refId,
                'obj_id' => (int) ($resource['obj_id'] ?? 0),
                'obj_type' => (string) ($resource['obj_type'] ?? ''),
                'resource_family' => (string) ($resource['resource_family'] ?? ''),
                'title' => (string) ($resource['title'] ?? ('ref_id ' . $refId)),
                'path' => (string) ($resource['path'] ?? ''),
                'object_id' => '',
                'enabled' => !empty($resource['enabled']),
                'count' => 0,
                'traces' => 0,
                'learners' => [],
                'learners_count' => 0,
                'last_at' => '',
                'avg_score_raw' => null,
                'scores' => [],
                'test_attempts' => 0,
                'test_passed' => 0,
                'test_failed' => 0,
                'failure_rate' => null,
                'score_status' => 'none',
                'pedagogical_status' => !empty($resource['enabled']) ? 'watch' : 'disabled',
                'pedagogical_label' => !empty($resource['enabled']) ? 'À surveiller' : 'Désactivée',
                'pedagogical_reason' => !empty($resource['enabled']) ? 'Ressource activée sans trace TRAX.' : 'Ressource désactivée dans le suivi xAPI.',
                'signal' => !empty($resource['enabled']) ? 'activée sans trace' : 'désactivée',
            ];
        }

        if ($activity === '') {
            $summary['error'] = 'Activité cours xAPI introuvable.';
            return $summary;
        }
        if (!$this->config->isTraxConfigured()) {
            $summary['error'] = 'Configuration TRAX incomplète.';
            return $summary;
        }

        $result = $this->client->queryStatements([
            'activity' => $activity,
            'related_activities' => true,
            'since' => $since,
            'limit' => 100,
        ]);

        $this->consumeResult($summary, $result, true, $allowedResources);
        if (!$summary['available']) {
            return $this->finalize($summary);
        }

        $maxPages = 5;
        $seenMore = [];
        while ((string) ($summary['more'] ?? '') !== '' && (int) ($summary['pages'] ?? 0) < $maxPages) {
            $more = (string) $summary['more'];
            if (isset($seenMore[$more])) {
                $summary['pagination_complete'] = false;
                $summary['pagination_error'] = 'Boucle détectée dans le champ more LRS.';
                break;
            }
            $seenMore[$more] = true;
            $next = $this->client->queryMore($more);
            $this->consumeResult($summary, $next, false, $allowedResources);
            if ((string) ($summary['pagination_error'] ?? '') !== '') {
                break;
            }
        }

        if ((string) ($summary['more'] ?? '') !== '' && (int) ($summary['pages'] ?? 0) >= $maxPages) {
            $summary['pagination_complete'] = false;
            $summary['pagination_limit_reached'] = true;
        }

        return $this->finalize($summary);
    }

    /** @param array<string,mixed> $course @return array<int,array<string,mixed>> */
    private function allowedResources(array $course): array
    {
        $resources = [];
        foreach ((array) ($course['resources'] ?? []) as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $refId = (int) ($resource['ref_id'] ?? 0);
            if ($refId > 0) {
                $resources[$refId] = $resource;
            }
        }
        return $resources;
    }

    /** @param array<string,mixed> $summary @param array<int,array<string,mixed>> $allowedResources */
    private function consumeResult(array &$summary, ilIliasTraxEventBridgeHttpResult $result, bool $firstPage, array $allowedResources): void
    {
        $summary['http_status'] = $result->getHttpStatus();
        if (!$result->isSuccess()) {
            if ($firstPage) {
                $summary['available'] = false;
                $summary['error'] = $result->getShortMessage();
            } else {
                $summary['pagination_complete'] = false;
                $summary['pagination_error'] = $result->getShortMessage();
                $summary['more'] = '';
            }
            return;
        }

        $json = json_decode($result->getBody(), true);
        if (!is_array($json)) {
            if ($firstPage) {
                $summary['available'] = false;
                $summary['error'] = 'Réponse JSON LRS invalide.';
            } else {
                $summary['pagination_complete'] = false;
                $summary['pagination_error'] = 'Réponse JSON LRS invalide sur une page more.';
                $summary['more'] = '';
            }
            return;
        }

        $statements = is_array($json['statements'] ?? null) ? $json['statements'] : [];
        $summary['available'] = true;
        $summary['pages'] = (int) ($summary['pages'] ?? 0) + 1;
        $summary['more'] = is_scalar($json['more'] ?? null) ? (string) $json['more'] : '';

        foreach ($statements as $statement) {
            if (!is_array($statement)) {
                continue;
            }
            if (!$this->isStatementInRequestedPeriod($summary, $statement)) {
                continue;
            }
            $resource = $this->resourceInfo($statement);
            $refId = (int) ($resource['ref_id'] ?? 0);
            if (count($allowedResources) > 0 && $refId > 0 && !isset($allowedResources[$refId])) {
                continue;
            }
            $this->addStatement($summary, $statement, $resource);
        }
    }

    /** @param array<string,mixed> $summary @param array<string,mixed> $statement */
    private function isStatementInRequestedPeriod(array $summary, array $statement): bool
    {
        $start = (int) ($summary['period_start_ts'] ?? 0);
        if ($start <= 0) {
            return true;
        }
        $timestamp = (string) ($statement['timestamp'] ?? ($statement['stored'] ?? ''));
        if ($timestamp === '') {
            return true;
        }
        $ts = strtotime($timestamp);
        if ($ts === false) {
            return true;
        }
        return $ts >= $start;
    }
    /** @param array<string,mixed> $summary */
    private function addStatement(array &$summary, array $statement, array $resource): void
    {
        $summary['returned'] = (int) ($summary['returned'] ?? 0) + 1;

        $timestamp = (string) ($statement['timestamp'] ?? ($statement['stored'] ?? ''));
        $day = $this->dayFromTimestamp($timestamp);
        $summary['by_day'][$day] = (int) ($summary['by_day'][$day] ?? 0) + 1;

        $actorKey = $this->actorKey($statement);
        if ($actorKey !== '') {
            $summary['learners'][$actorKey] = true;
        }

        $verbId = (string) ($statement['verb']['id'] ?? 'unknown');
        if (!isset($summary['by_verb'][$verbId])) {
            $summary['by_verb'][$verbId] = ['verb_id' => $verbId, 'label' => $this->verbLabel($statement, $verbId), 'count' => 0];
        }
        $summary['by_verb'][$verbId]['count']++;

        $this->addMediaCastMediaStatement($summary, $statement, $resource, $actorKey, $timestamp, $verbId);

        $key = (string) ($resource['key'] ?? 'unknown');
        if (!isset($summary['by_resource'][$key])) {
            $summary['by_resource'][$key] = $resource + [
                'count' => 0,
                'traces' => 0,
                'learners' => [],
                'learners_count' => 0,
                'last_at' => '',
                'avg_score_raw' => null,
                'scores' => [],
                'test_attempts' => 0,
                'test_passed' => 0,
                'test_failed' => 0,
                'failure_rate' => null,
                'score_status' => 'none',
                'pedagogical_status' => 'ok',
                'pedagogical_label' => 'OK',
                'pedagogical_reason' => 'Ressource utilisée.',
                'signal' => 'utilisée',
                'enabled' => true,
                'resource_family' => '',
                'path' => '',
            ];
        }
        $summary['by_resource'][$key]['count']++;
        $summary['by_resource'][$key]['traces']++;
        if ($actorKey !== '') {
            $summary['by_resource'][$key]['learners'][$actorKey] = true;
        }
        if ($timestamp !== '' && ((string) ($summary['by_resource'][$key]['last_at'] ?? '') === '' || strcmp($timestamp, (string) $summary['by_resource'][$key]['last_at']) > 0)) {
            $summary['by_resource'][$key]['last_at'] = $timestamp;
        }

        $score = $this->scoreRaw($statement);
        if ($score !== null) {
            $summary['scores'][] = $score;
            $summary['by_resource'][$key]['scores'][] = $score;
        }

        $success = $this->successValue($statement);
        $verbLabel = $this->verbLabel($statement, $verbId);
        if ($this->isTestStatement($resource, $verbId)) {
            if (strpos($verbId, 'attempted') !== false) {
                $summary['summary']['tests_attempted']++;
                $summary['by_resource'][$key]['test_attempts']++;
            }
            if ($success === true || strpos($verbId, 'passed') !== false) {
                $summary['summary']['tests_passed']++;
                $summary['by_resource'][$key]['test_passed']++;
            }
            if ($success === false || strpos($verbId, 'failed') !== false) {
                $summary['summary']['tests_failed']++;
                $summary['by_resource'][$key]['test_failed']++;
            }
        }

        $summary['expert_rows'][] = [
            'created_at' => $timestamp,
            'user_id' => $actorKey === '' ? '' : substr(sha1($actorKey), 0, 10),
            'verb_label' => $verbLabel,
            'verb_id' => $verbId,
            'object_title' => (string) ($resource['title'] ?? ''),
            'ref_id' => (int) ($resource['ref_id'] ?? 0),
            'obj_id' => (int) ($resource['obj_id'] ?? 0),
            'obj_type' => (string) ($resource['obj_type'] ?? ''),
            'score_raw' => $score,
            'completion' => $this->completionValue($statement),
            'success' => $success,
            'status' => 'TRAX',
            'outbox_id' => 0,
            'statement_uuid' => is_scalar($statement['id'] ?? null) ? (string) $statement['id'] : '',
            'last_error' => '',
        ];
    }

    /** @param array<string,mixed> $summary @param array<string,mixed> $statement @param array<string,mixed> $resource */
    private function addMediaCastMediaStatement(array &$summary, array $statement, array $resource, string $actorKey, string $timestamp, string $verbId): void
    {
        $resultExtensions = is_array($statement['result']['extensions'] ?? null) ? $statement['result']['extensions'] : [];
        $mediaEvent = (string) $this->extensionValue($resultExtensions, '/media_client_event');
        $mediaTitle = trim((string) $this->extensionValue($resultExtensions, '/media_title'));
        $mediaProvider = trim((string) $this->extensionValue($resultExtensions, '/media_provider'));
        $mediaMime = trim((string) $this->extensionValue($resultExtensions, '/media_mime'));
        $mediaUrl = trim((string) $this->extensionValue($resultExtensions, '/media_url'));
        $mediaId = trim((string) $this->extensionValue($resultExtensions, '/media_id'));

        $isInternal = $mediaEvent === 'media_played' || stripos($verbId, '/played-media') !== false;
        $isExternal = $mediaEvent === 'external_media_opened' || stripos($verbId, '/opened-external-media') !== false;
        if (!$isInternal && !$isExternal) {
            return;
        }

        if ($mediaTitle === '') {
            $mediaTitle = $this->resourceTitle($statement, 'Média MediaCast');
        }
        if ($mediaProvider === '') {
            $mediaProvider = $isExternal ? 'external' : 'ilias';
        }

        $parentRefId = (int) ($resource['ref_id'] ?? 0);
        $parentTitle = trim((string) ($resource['title'] ?? ''));
        if ($parentTitle === '') {
            $parentTitle = $parentRefId > 0 ? 'MediaCast ref_id ' . $parentRefId : 'MediaCast';
        }

        $identity = $mediaId !== '' ? $mediaId : ($mediaUrl !== '' ? $mediaUrl : $mediaTitle);
        $type = $isExternal ? 'external' : 'internal';
        $key = 'mcst:' . $parentRefId . ':' . $type . ':' . sha1($identity);

        if (!isset($summary['by_mediacast_media']) || !is_array($summary['by_mediacast_media'])) {
            $summary['by_mediacast_media'] = [];
        }
        if (!isset($summary['mediacast_media_learners']) || !is_array($summary['mediacast_media_learners'])) {
            $summary['mediacast_media_learners'] = [];
        }
        if (!isset($summary['by_mediacast_media'][$key])) {
            $summary['by_mediacast_media'][$key] = [
                'key' => $key,
                'media_id' => $mediaId,
                'media_title' => $mediaTitle,
                'media_type' => $type,
                'media_provider' => $mediaProvider,
                'media_mime' => $mediaMime,
                'media_url' => $mediaUrl,
                'parent_ref_id' => $parentRefId,
                'parent_obj_id' => (int) ($resource['obj_id'] ?? 0),
                'parent_title' => $parentTitle,
                'total' => 0,
                'played_internal' => 0,
                'opened_external' => 0,
                'learners' => [],
                'learners_count' => 0,
                'last_at' => '',
            ];
        }

        $summary['by_mediacast_media'][$key]['total']++;
        if ($isExternal) {
            $summary['by_mediacast_media'][$key]['opened_external']++;
            $summary['summary']['mediacast_external_opened'] = (int) ($summary['summary']['mediacast_external_opened'] ?? 0) + 1;
        } else {
            $summary['by_mediacast_media'][$key]['played_internal']++;
            $summary['summary']['mediacast_internal_played'] = (int) ($summary['summary']['mediacast_internal_played'] ?? 0) + 1;
        }
        $summary['summary']['mediacast_media_total'] = (int) ($summary['summary']['mediacast_media_total'] ?? 0) + 1;

        if ($actorKey !== '') {
            $summary['by_mediacast_media'][$key]['learners'][$actorKey] = true;
            $summary['mediacast_media_learners'][$actorKey] = true;
        }
        if ($timestamp !== '' && ((string) ($summary['by_mediacast_media'][$key]['last_at'] ?? '') === '' || strcmp($timestamp, (string) $summary['by_mediacast_media'][$key]['last_at']) > 0)) {
            $summary['by_mediacast_media'][$key]['last_at'] = $timestamp;
        }
    }
    /** @param array<string,mixed> $summary @return array<string,mixed> */
    private function finalize(array $summary): array
    {
        ksort($summary['by_day']);
        uasort($summary['by_verb'], static function (array $a, array $b): int {
            return (int) ($b['count'] ?? 0) <=> (int) ($a['count'] ?? 0);
        });

        $pedagogy = [
            'ok_count' => 0,
            'watch_count' => 0,
            'critical_count' => 0,
            'disabled_count' => 0,
            'resources_without_trace' => 0,
            'high_failure_resources' => 0,
            'low_score_resources' => 0,
            'synthesis_lines' => [],
        ];

        foreach ($summary['by_resource'] as &$resource) {
            $resource['learners_count'] = count((array) ($resource['learners'] ?? []));
            $scores = (array) ($resource['scores'] ?? []);
            $resource['avg_score_raw'] = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : null;
            $this->decorateResourceWithPedagogicalStatus($resource, $pedagogy);
            unset($resource['learners'], $resource['scores']);
        }
        unset($resource);

        $summary['pedagogy'] = $this->finalizePedagogy($pedagogy, $summary);

        if (isset($summary['by_mediacast_media']) && is_array($summary['by_mediacast_media'])) {
            foreach ($summary['by_mediacast_media'] as &$media) {
                $media['learners_count'] = count((array) ($media['learners'] ?? []));
                unset($media['learners']);
            }
            unset($media);
            uasort($summary['by_mediacast_media'], static function (array $a, array $b): int {
                $total = (int) ($b['total'] ?? 0) <=> (int) ($a['total'] ?? 0);
                if ($total !== 0) { return $total; }
                return strcmp((string) ($a['media_title'] ?? ''), (string) ($b['media_title'] ?? ''));
            });
            $summary['summary']['mediacast_media_unique'] = count($summary['by_mediacast_media']);
        }
        $summary['summary']['mediacast_media_learners'] = count((array) ($summary['mediacast_media_learners'] ?? []));
        unset($summary['mediacast_media_learners']);

        uasort($summary['by_resource'], static function (array $a, array $b): int {
            $rank = ['critical' => 4, 'watch' => 3, 'ok' => 2, 'disabled' => 1];
            $rankA = $rank[(string) ($a['pedagogical_status'] ?? '')] ?? 0;
            $rankB = $rank[(string) ($b['pedagogical_status'] ?? '')] ?? 0;
            if ($rankA !== $rankB) {
                return $rankB <=> $rankA;
            }
            return (int) ($b['traces'] ?? 0) <=> (int) ($a['traces'] ?? 0);
        });
        usort($summary['expert_rows'], static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });
        $summary['expert_rows'] = array_slice($summary['expert_rows'], 0, 200);

        $summary['summary']['total'] = (int) ($summary['returned'] ?? 0);
        $summary['summary']['sent'] = (int) ($summary['returned'] ?? 0);
        $summary['summary']['failed'] = 0;
        $summary['summary']['active_learners'] = count((array) ($summary['learners'] ?? []));
        $summary['summary']['resources_with_traces'] = count(array_filter($summary['by_resource'], static function (array $r): bool {
            return (int) ($r['traces'] ?? 0) > 0;
        }));
        $scores = (array) ($summary['scores'] ?? []);
        $summary['summary']['avg_score_raw'] = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : null;
        unset($summary['learners'], $summary['scores']);
        return $summary;
    }

    /** @param array<string,mixed> $resource @param array<string,mixed> $pedagogy */
    private function decorateResourceWithPedagogicalStatus(array &$resource, array &$pedagogy): void
    {
        $enabled = !empty($resource['enabled']);
        $traces = (int) ($resource['traces'] ?? 0);
        $passed = (int) ($resource['test_passed'] ?? 0);
        $failed = (int) ($resource['test_failed'] ?? 0);
        $attempts = (int) ($resource['test_attempts'] ?? 0);
        $avgScore = $resource['avg_score_raw'];
        $evaluatedAttempts = $passed + $failed;
        $failureRate = $evaluatedAttempts > 0 ? round(($failed / $evaluatedAttempts) * 100, 1) : null;

        $resource['failure_rate'] = $failureRate;
        $resource['score_status'] = 'none';

        if (!$enabled) {
            $resource['signal'] = 'désactivée';
            $resource['pedagogical_status'] = 'disabled';
            $resource['pedagogical_label'] = 'Désactivée';
            $resource['pedagogical_reason'] = 'Ressource désactivée dans le suivi xAPI.';
            $pedagogy['disabled_count']++;
            return;
        }

        if ($traces <= 0) {
            $resource['signal'] = 'aucune trace TRAX';
            $resource['pedagogical_status'] = 'watch';
            $resource['pedagogical_label'] = 'À surveiller';
            $resource['pedagogical_reason'] = 'Ressource activée mais aucune trace TRAX n’a été trouvée sur la période.';
            $pedagogy['watch_count']++;
            $pedagogy['resources_without_trace']++;
            return;
        }

        $status = 'ok';
        $label = 'OK';
        $reason = 'Ressource utilisée sur la période.';

        if ($failureRate !== null) {
            if ($failed >= 2 && $failureRate >= 50.0) {
                $status = 'critical';
                $label = 'Critique';
                $reason = 'Taux d’échec élevé sur cette ressource.';
                $pedagogy['high_failure_resources']++;
            } elseif ($failed >= 1 && $failureRate >= 30.0) {
                $status = 'watch';
                $label = 'À surveiller';
                $reason = 'Des échecs sont observés sur cette ressource.';
                $pedagogy['high_failure_resources']++;
            }
        }

        if (is_numeric($avgScore)) {
            if ((float) $avgScore < 50.0) {
                $resource['score_status'] = 'low';
                $pedagogy['low_score_resources']++;
                if ($status !== 'critical') {
                    $status = 'critical';
                    $label = 'Critique';
                    $reason = 'Score moyen faible sur cette ressource.';
                }
            } elseif ((float) $avgScore < 70.0) {
                $resource['score_status'] = 'medium';
                if ($status === 'ok') {
                    $status = 'watch';
                    $label = 'À surveiller';
                    $reason = 'Score moyen à surveiller sur cette ressource.';
                }
            } else {
                $resource['score_status'] = 'good';
            }
        }

        $resource['signal'] = $status === 'ok' ? 'utilisée' : ($status === 'critical' ? 'critique' : 'à surveiller');
        $resource['pedagogical_status'] = $status;
        $resource['pedagogical_label'] = $label;
        $resource['pedagogical_reason'] = $reason;
        $resource['test_attempts'] = max($attempts, $evaluatedAttempts);

        if ($status === 'critical') {
            $pedagogy['critical_count']++;
        } elseif ($status === 'watch') {
            $pedagogy['watch_count']++;
        } else {
            $pedagogy['ok_count']++;
        }
    }

    /** @param array<string,mixed> $pedagogy @param array<string,mixed> $summary @return array<string,mixed> */
    private function finalizePedagogy(array $pedagogy, array $summary): array
    {
        $lines = [];
        $activeLearners = count((array) ($summary['learners'] ?? []));
        $totalStatements = (int) ($summary['returned'] ?? 0);

        if ($totalStatements <= 0) {
            $lines[] = 'Aucune trace TRAX/LRS trouvée sur la période.';
        } else {
            $lines[] = $totalStatements . ' trace(s) TRAX/LRS analysée(s) sur la période.';
        }

        if ($activeLearners > 0) {
            $lines[] = $activeLearners . ' apprenant(s) actif(s) détecté(s).';
        }

        if ((int) $pedagogy['resources_without_trace'] > 0) {
            $lines[] = $pedagogy['resources_without_trace'] . ' ressource(s) activée(s) ne présentent aucune trace TRAX sur la période.';
        }

        $mediaTotal = (int) ($summary['summary']['mediacast_media_total'] ?? 0);
        if ($mediaTotal > 0) {
            $lines[] = $mediaTotal . ' action(s) MediaCast détectée(s) sur des vidéos ou médias externes.';
        }

        if ((int) $pedagogy['critical_count'] > 0) {
            $lines[] = $pedagogy['critical_count'] . ' ressource(s) sont en statut critique.';
        }

        if ((int) $pedagogy['watch_count'] > 0) {
            $lines[] = $pedagogy['watch_count'] . ' ressource(s) sont à surveiller.';
        }

        if ((int) $pedagogy['critical_count'] === 0 && (int) $pedagogy['watch_count'] === 0 && $totalStatements > 0) {
            $lines[] = 'Aucun signal pédagogique défavorable détecté sur la période.';
        }

        $pedagogy['synthesis_lines'] = $lines;
        return $pedagogy;
    }

    private function courseActivityId(int $courseRefId, int $courseObjId): string
    {
        $base = $this->config->getIliasBaseUrl();
        if ($courseRefId > 0) {
            return $base . '/xapi/activity/course/ref/' . $courseRefId;
        }
        if ($courseObjId > 0) {
            return $base . '/xapi/activity/course/obj/' . max(0, $courseObjId);
        }
        return '';
    }

    /** @param array<string,mixed> $statement @return array<string,mixed> */
    private function resourceInfo(array $statement): array
    {
        $extensions = is_array($statement['context']['extensions'] ?? null) ? $statement['context']['extensions'] : [];
        $refId = $this->extensionValue($extensions, '/ref_id');
        $objId = $this->extensionValue($extensions, '/obj_id');
        $objType = $this->extensionValue($extensions, '/obj_type');
        $objectId = is_scalar($statement['object']['id'] ?? null) ? (string) $statement['object']['id'] : '';
        $key = is_numeric($refId) && (int) $refId > 0 ? 'ref:' . (int) $refId : ($objectId !== '' ? $objectId : 'unknown');

        return [
            'key' => $key,
            'ref_id' => is_numeric($refId) ? (int) $refId : 0,
            'obj_id' => is_numeric($objId) ? (int) $objId : 0,
            'obj_type' => is_scalar($objType) ? (string) $objType : '',
            'resource_family' => is_scalar($objType) ? (string) $objType : '',
            'title' => $this->resourceTitle($statement, $key),
            'path' => '',
            'object_id' => $objectId,
            'enabled' => true,
        ];
    }

    /** @param array<string,mixed> $statement */
    private function resourceTitle(array $statement, string $fallback): string
    {
        $name = $statement['object']['definition']['name'] ?? [];
        if (is_array($name)) {
            foreach (['fr-FR', 'fr', 'en-US', 'en'] as $locale) {
                if (isset($name[$locale]) && is_scalar($name[$locale]) && trim((string) $name[$locale]) !== '') {
                    return (string) $name[$locale];
                }
            }
        }
        return $fallback;
    }

    /** @param array<string,mixed> $extensions */
    private function extensionValue(array $extensions, string $suffix)
    {
        foreach ($extensions as $key => $value) {
            if (is_string($key) && substr($key, -strlen($suffix)) === $suffix) {
                return $value;
            }
        }
        return null;
    }

    /** @param array<string,mixed> $statement */
    private function verbLabel(array $statement, string $verbId): string
    {
        $display = $statement['verb']['display'] ?? [];
        if (is_array($display)) {
            foreach (['fr-FR', 'fr', 'en-US', 'en'] as $locale) {
                if (isset($display[$locale]) && is_scalar($display[$locale]) && trim((string) $display[$locale]) !== '') {
                    return (string) $display[$locale];
                }
            }
        }
        $parts = preg_split('/[\/#]/', $verbId);
        $last = is_array($parts) ? end($parts) : false;
        return is_string($last) && $last !== '' ? $last : $verbId;
    }

    /** @param array<string,mixed> $statement */
    private function actorKey(array $statement): string
    {
        $name = $statement['actor']['account']['name'] ?? '';
        if (is_scalar($name) && trim((string) $name) !== '') {
            return 'account:' . (string) $name;
        }
        $mbox = $statement['actor']['mbox'] ?? '';
        if (is_scalar($mbox) && trim((string) $mbox) !== '') {
            return 'mbox:' . (string) $mbox;
        }
        return '';
    }

    /** @param array<string,mixed> $statement */
    private function scoreRaw(array $statement): ?float
    {
        $raw = $statement['result']['score']['raw'] ?? null;
        if (is_numeric($raw)) {
            return (float) $raw;
        }
        $scaled = $statement['result']['score']['scaled'] ?? null;
        return is_numeric($scaled) ? round(((float) $scaled) * 100, 2) : null;
    }

    /** @param array<string,mixed> $statement */
    private function successValue(array $statement): ?bool
    {
        return is_bool($statement['result']['success'] ?? null) ? (bool) $statement['result']['success'] : null;
    }

    /** @param array<string,mixed> $statement */
    private function completionValue(array $statement): ?bool
    {
        return is_bool($statement['result']['completion'] ?? null) ? (bool) $statement['result']['completion'] : null;
    }

    /** @param array<string,mixed> $resource */
    private function isTestStatement(array $resource, string $verbId): bool
    {
        return (string) ($resource['obj_type'] ?? '') === 'tst'
            || strpos($verbId, 'passed') !== false
            || strpos($verbId, 'failed') !== false
            || strpos($verbId, 'attempted') !== false;
    }

    private function dayFromTimestamp(string $timestamp): string
    {
        $ts = strtotime($timestamp);
        return $ts === false ? 'inconnue' : gmdate('Y-m-d', $ts);
    }
}
