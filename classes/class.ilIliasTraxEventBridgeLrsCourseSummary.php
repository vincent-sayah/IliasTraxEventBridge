<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeLrsReadClient.php';

/**
 * Minimal read-only LRS summary for one ILIAS course.
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
        $activity = $this->courseActivityId($courseRefId, $courseObjId);
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - ($days * 86400));

        $summary = [
            'available' => false,
            'http_status' => 0,
            'error' => '',
            'activity_id' => $activity,
            'since' => $since,
            'returned' => 0,
            'more' => '',
            'pages' => 0,
            'pagination_complete' => true,
            'pagination_limit_reached' => false,
            'pagination_error' => '',
            'by_verb' => [],
            'by_resource' => [],
            'statement_ids' => [],
        ];

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

        $this->consumeResult($summary, $result, true);
        if (!$summary['available']) {
            return $summary;
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
            $this->consumeResult($summary, $next, false);
            if ((string) ($summary['pagination_error'] ?? '') !== '') {
                break;
            }
        }

        if ((string) ($summary['more'] ?? '') !== '' && (int) ($summary['pages'] ?? 0) >= $maxPages) {
            $summary['pagination_complete'] = false;
            $summary['pagination_limit_reached'] = true;
        }

        uasort($summary['by_verb'], static function (array $a, array $b): int {
            return (int) ($b['count'] ?? 0) <=> (int) ($a['count'] ?? 0);
        });
        uasort($summary['by_resource'], static function (array $a, array $b): int {
            return (int) ($b['count'] ?? 0) <=> (int) ($a['count'] ?? 0);
        });

        return $summary;
    }

    /** @param array<string,mixed> $summary */
    private function consumeResult(array &$summary, ilIliasTraxEventBridgeHttpResult $result, bool $firstPage): void
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
        $summary['returned'] = (int) ($summary['returned'] ?? 0) + count($statements);
        $summary['more'] = is_scalar($json['more'] ?? null) ? (string) $json['more'] : '';

        foreach ($statements as $statement) {
            if (!is_array($statement)) {
                continue;
            }
            $verbId = (string) ($statement['verb']['id'] ?? 'unknown');
            if (!isset($summary['by_verb'][$verbId])) {
                $summary['by_verb'][$verbId] = ['verb_id' => $verbId, 'label' => $this->verbLabel($statement, $verbId), 'count' => 0];
            }
            $summary['by_verb'][$verbId]['count']++;

            $resource = $this->resourceInfo($statement);
            $resourceKey = (string) ($resource['key'] ?? 'unknown');
            if (!isset($summary['by_resource'][$resourceKey])) {
                $summary['by_resource'][$resourceKey] = $resource;
                $summary['by_resource'][$resourceKey]['count'] = 0;
            }
            $summary['by_resource'][$resourceKey]['count']++;

            $statementId = is_scalar($statement['id'] ?? null) ? trim((string) $statement['id']) : '';
            if ($statementId !== '') {
                $summary['statement_ids'][$statementId] = [
                    'uuid' => $statementId,
                    'ref_id' => (int) ($resource['ref_id'] ?? 0),
                    'obj_type' => (string) ($resource['obj_type'] ?? ''),
                    'title' => (string) ($resource['title'] ?? ''),
                    'object_id' => (string) ($resource['object_id'] ?? ''),
                    'verb_id' => $verbId,
                    'verb_label' => $this->verbLabel($statement, $verbId),
                ];
            }
        }
    }

    private function courseActivityId(int $courseRefId, int $courseObjId): string
    {
        $base = $this->config->getIliasBaseUrl();
        if ($courseRefId > 0) {
            return $base . '/xapi/activity/course/ref/' . $courseRefId;
        }
        if ($courseObjId > 0) {
            return $base . '/xapi/activity/course/obj/' . $courseObjId;
        }
        return '';
    }

    /** @param array<string,mixed> $statement */
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
            'title' => $this->resourceTitle($statement, $key),
            'object_id' => $objectId,
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
}
