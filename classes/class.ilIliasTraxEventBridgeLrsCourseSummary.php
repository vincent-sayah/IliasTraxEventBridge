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
            'by_verb' => [],
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

        $summary['http_status'] = $result->getHttpStatus();
        if (!$result->isSuccess()) {
            $summary['error'] = $result->getShortMessage();
            return $summary;
        }

        $json = json_decode($result->getBody(), true);
        if (!is_array($json)) {
            $summary['error'] = 'Réponse JSON LRS invalide.';
            return $summary;
        }

        $statements = is_array($json['statements'] ?? null) ? $json['statements'] : [];
        $summary['available'] = true;
        $summary['returned'] = count($statements);
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
        }

        uasort($summary['by_verb'], static function (array $a, array $b): int {
            return (int) ($b['count'] ?? 0) <=> (int) ($a['count'] ?? 0);
        });

        return $summary;
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
