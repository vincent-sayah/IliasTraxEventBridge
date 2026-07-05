<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeAiClient.php';

/**
 * Service V0.13 de préparation et d'appel IA pour une analyse de cours.
 *
 * Les données envoyées sont agrégées. Aucun nom, courriel ou identifiant nominatif
 * d'apprenant ne doit être injecté dans le prompt.
 */
class ilIliasTraxEventBridgeCourseAiAnalyzer
{
    /** @var ilIliasTraxEventBridgeConfig */
    private $config;
    /** @var ilIliasTraxEventBridgeAiClient */
    private $client;

    public function __construct(?ilIliasTraxEventBridgeConfig $config = null, ?ilIliasTraxEventBridgeAiClient $client = null)
    {
        $this->config = $config ?: new ilIliasTraxEventBridgeConfig();
        $this->client = $client ?: new ilIliasTraxEventBridgeAiClient($this->config);
    }

    /**
     * @param array<string,mixed> $course
     * @param array<string,mixed> $dashboard
     * @return array<string,mixed>
     */
    public function analyze(array $course, array $dashboard): array
    {
        $payload = $this->buildAnonymizedPayload($course, $dashboard);
        $messages = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt(),
            ],
            [
                'role' => 'user',
                'content' => "Analyse les données agrégées suivantes et produis une synthèse pédagogique exploitable.\n\n" . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ],
        ];

        $result = $this->client->sendChatMessages($messages, 1200);
        if (!$result->isSuccess()) {
            return [
                'success' => false,
                'http_status' => $result->getHttpStatus(),
                'message' => $result->getShortMessage(),
                'analysis' => '',
                'payload_summary' => $this->payloadSummary($payload),
            ];
        }

        return [
            'success' => true,
            'http_status' => $result->getHttpStatus(),
            'message' => $result->getShortMessage(),
            'analysis' => $this->extractAssistantText($result->getBody()),
            'payload_summary' => $this->payloadSummary($payload),
        ];
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            'Tu es un assistant pédagogique pour un LMS ILIAS connecté à un LRS TRAX.',
            'Tu analyses uniquement des données agrégées xAPI.',
            'Tu ne dois jamais inventer de données absentes.',
            'Tu ne dois pas identifier ou évaluer nominativement un apprenant.',
            'Tu dois répondre en français, de façon structurée et opérationnelle.',
            'Format attendu :',
            '1. Synthèse courte',
            '2. Points positifs',
            '3. Points à surveiller',
            '4. Ressources prioritaires',
            '5. Recommandations pédagogiques',
            '6. Limites de l’analyse',
        ]);
    }

    /**
     * @param array<string,mixed> $course
     * @param array<string,mixed> $dashboard
     * @return array<string,mixed>
     */
    private function buildAnonymizedPayload(array $course, array $dashboard): array
    {
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $pedagogy = is_array($dashboard['pedagogy'] ?? null) ? $dashboard['pedagogy'] : [];
        $resources = is_array($dashboard['by_resource'] ?? null) ? $dashboard['by_resource'] : [];
        $verbs = is_array($dashboard['by_verb'] ?? null) ? $dashboard['by_verb'] : [];
        $byDay = is_array($dashboard['by_day'] ?? null) ? $dashboard['by_day'] : [];

        return [
            'course' => [
                'course_ref_id' => (int) ($course['course_ref_id'] ?? 0),
                'course_obj_id' => (int) ($course['course_obj_id'] ?? 0),
                'course_title' => (string) ($course['course_title'] ?? ''),
            ],
            'ai_policy' => [
                'anonymization_mode' => $this->config->getAiAnonymizationMode(),
                'contains_nominal_learner_identity' => false,
                'source' => 'aggregated_xapi_from_lrs',
            ],
            'summary' => $this->filterSummary($summary),
            'pedagogy' => $this->filterPedagogy($pedagogy),
            'verb_distribution' => array_slice($verbs, 0, 12),
            'activity_by_day' => array_slice($byDay, -30, null, true),
            'resources' => $this->filterResources($resources),
        ];
    }

    /** @param array<string,mixed> $summary */
    private function filterSummary(array $summary): array
    {
        $keys = ['total', 'active_learners', 'resources_total', 'resources_with_traces', 'avg_score_raw', 'tests_attempted', 'tests_passed', 'tests_failed'];
        return $this->keepKeys($summary, $keys);
    }

    /** @param array<string,mixed> $pedagogy */
    private function filterPedagogy(array $pedagogy): array
    {
        $keys = ['ok_count', 'watch_count', 'critical_count', 'resources_without_trace', 'synthesis_lines'];
        return $this->keepKeys($pedagogy, $keys);
    }

    /**
     * @param array<int|string,mixed> $resources
     * @return array<int,array<string,mixed>>
     */
    private function filterResources(array $resources): array
    {
        $out = [];
        $limit = $this->config->getAiTraceLimit();
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $out[] = $this->keepKeys($resource, [
                'ref_id', 'obj_id', 'obj_type', 'resource_family', 'title', 'path', 'enabled',
                'traces', 'learners_count', 'last_at', 'avg_score_raw', 'test_attempts',
                'test_passed', 'test_failed', 'failure_rate', 'pedagogical_status',
                'pedagogical_label', 'pedagogical_reason'
            ]);
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $source
     * @param array<int,string> $keys
     * @return array<string,mixed>
     */
    private function keepKeys(array $source, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                $out[$key] = $source[$key];
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $payload */
    private function payloadSummary(array $payload): string
    {
        $resources = is_array($payload['resources'] ?? null) ? count($payload['resources']) : 0;
        $total = is_array($payload['summary'] ?? null) ? (int) ($payload['summary']['total'] ?? 0) : 0;
        return 'Payload IA agrégé : ' . $resources . ' ressource(s), ' . $total . ' statement(s).';
    }

    private function extractAssistantText(string $body): string
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $choices = $decoded['choices'] ?? null;
            if (is_array($choices) && isset($choices[0]) && is_array($choices[0])) {
                $message = $choices[0]['message'] ?? null;
                if (is_array($message) && isset($message['content']) && is_scalar($message['content'])) {
                    return trim((string) $message['content']);
                }
                if (isset($choices[0]['text']) && is_scalar($choices[0]['text'])) {
                    return trim((string) $choices[0]['text']);
                }
            }
        }
        return trim($body);
    }
}
