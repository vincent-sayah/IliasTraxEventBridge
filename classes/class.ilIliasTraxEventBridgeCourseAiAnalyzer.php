<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeAiClient.php';

/**
 * Service V0.14.1 de préparation et d'appel IA pour une analyse de cours.
 *
 * Les données envoyées sont agrégées depuis TRAX/LRS. Aucun nom, courriel,
 * UUID de statement ou identifiant nominatif d'apprenant ne doit être injecté
 * dans le prompt.
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
        $encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!is_string($encodedPayload)) {
            return [
                'success' => false,
                'http_status' => 0,
                'message' => 'Impossible d’encoder le payload IA structuré.',
                'analysis' => '',
                'payload_summary' => $this->payloadSummary($payload),
            ];
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt(),
            ],
            [
                'role' => 'user',
                'content' => $this->userPrompt($encodedPayload),
            ],
        ];

        $result = $this->client->sendChatMessages($messages, 1800);
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
            'Tu es un assistant pédagogique pour un formateur utilisant ILIAS connecté à un LRS TRAX.',
            'Tu analyses uniquement des indicateurs xAPI agrégés, anonymisés et déjà filtrés côté serveur.',
            'Tu ne dois jamais inventer de chiffres, de ressources, de noms, de profils ou de causes absentes du payload.',
            'Tu ne dois jamais identifier, classer ou évaluer nominativement un apprenant.',
            'Tu peux citer les titres de ressources pédagogiques, car ils servent au plan d’action du formateur.',
            'Tu dois distinguer clairement les constats mesurés, les hypothèses pédagogiques prudentes et les actions recommandées.',
            'Tu dois répondre en français, en Markdown, avec des formulations opérationnelles et directement exploitables.',
            'Respecte exactement la structure suivante :',
            '## 1. Synthèse opérationnelle',
            '## 2. Lecture des indicateurs',
            '## 3. Priorités formateur',
            '## 4. Ressources à traiter',
            '## 5. Actions pédagogiques recommandées',
            '## 6. Points d’attention anonymisés',
            '## 7. Limites et fiabilité',
            'Dans chaque section, reste concis. Utilise des puces actionnables lorsque c’est pertinent.',
            'Si les données sont insuffisantes, indique-le explicitement au lieu de produire une conclusion forte.',
        ]);
    }

    private function userPrompt(string $encodedPayload): string
    {
        return implode("\n", [
            'Produis une analyse IA structurée pour un formateur.',
            'Le payload ci-dessous est la seule source autorisée.',
            'Les données sont issues de TRAX/LRS et agrégées par cours, ressource, verbe, jour et signaux pédagogiques.',
            'Ne fais aucune recommandation qui nécessiterait des données absentes.',
            'Ne mentionne aucun identifiant technique inutile dans la réponse finale.',
            '',
            'Payload JSON agrégé :',
            '```json',
            $encodedPayload,
            '```',
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
        $expertRows = is_array($dashboard['expert_rows'] ?? null) ? $dashboard['expert_rows'] : [];

        $filteredResources = $this->filterResources($resources);

        return [
            'schema_version' => 'itxeb.ai.course_analysis.v0.14.1',
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'course' => [
                'course_ref_id' => (int) ($course['course_ref_id'] ?? 0),
                'course_obj_id' => (int) ($course['course_obj_id'] ?? 0),
                'course_title' => (string) ($course['course_title'] ?? ''),
            ],
            'source_context' => $this->sourceContext($dashboard),
            'privacy_policy' => [
                'anonymization_mode' => $this->config->getAiAnonymizationMode(),
                'contains_nominal_learner_identity' => false,
                'contains_email' => false,
                'contains_raw_xapi_statement' => false,
                'contains_statement_uuid' => false,
                'learner_level_data_sent' => false,
                'source' => 'aggregated_xapi_from_trax_lrs',
            ],
            'global_indicators' => $this->filterSummary($summary),
            'pedagogical_indicators' => $this->filterPedagogy($pedagogy),
            'deterministic_findings' => $this->deterministicFindings($summary, $pedagogy, $filteredResources),
            'activity_trend' => $this->filterActivityByDay($byDay),
            'verb_distribution' => $this->filterVerbs($verbs),
            'resource_analysis' => $filteredResources,
            'learner_risk_aggregate' => $this->aggregateLearnerRisks($expertRows),
        ];
    }

    /** @param array<string,mixed> $dashboard @return array<string,mixed> */
    private function sourceContext(array $dashboard): array
    {
        return [
            'lrs_available' => !empty($dashboard['available']),
            'http_status' => (int) ($dashboard['http_status'] ?? 0),
            'period_days' => (int) ($dashboard['period_days'] ?? 0),
            'since' => (string) ($dashboard['since'] ?? ''),
            'query_since' => (string) ($dashboard['query_since'] ?? ''),
            'pages' => (int) ($dashboard['pages'] ?? 0),
            'pagination_complete' => array_key_exists('pagination_complete', $dashboard) ? !empty($dashboard['pagination_complete']) : null,
            'pagination_limit_reached' => !empty($dashboard['pagination_limit_reached']),
            'pagination_error' => (string) ($dashboard['pagination_error'] ?? ''),
            'lrs_error' => (string) ($dashboard['error'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $summary @return array<string,mixed> */
    private function filterSummary(array $summary): array
    {
        $keys = [
            'total', 'sent', 'failed', 'active_learners', 'resources_total',
            'resources_with_traces', 'avg_score_raw', 'tests_attempted',
            'tests_passed', 'tests_failed'
        ];
        return $this->keepKeys($summary, $keys);
    }

    /** @param array<string,mixed> $pedagogy @return array<string,mixed> */
    private function filterPedagogy(array $pedagogy): array
    {
        $keys = [
            'ok_count', 'watch_count', 'critical_count', 'disabled_count',
            'resources_without_trace', 'high_failure_resources',
            'low_score_resources', 'synthesis_lines'
        ];
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
            $row = $this->keepKeys($resource, [
                'ref_id', 'obj_id', 'obj_type', 'resource_family', 'title', 'path', 'enabled',
                'traces', 'learners_count', 'last_at', 'avg_score_raw', 'test_attempts',
                'test_passed', 'test_failed', 'failure_rate', 'pedagogical_status',
                'pedagogical_label', 'pedagogical_reason', 'signal', 'score_status'
            ]);
            $row['action_priority'] = $this->resourcePriority($row);
            $out[] = $row;
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $resource */
    private function resourcePriority(array $resource): int
    {
        $status = (string) ($resource['pedagogical_status'] ?? '');
        if ($status === 'critical') {
            return 1;
        }
        if ($status === 'watch') {
            return 2;
        }
        if ($status === 'ok') {
            return 3;
        }
        return 4;
    }

    /** @param array<int|string,mixed> $verbs @return array<int,array<string,mixed>> */
    private function filterVerbs(array $verbs): array
    {
        $out = [];
        foreach ($verbs as $verb) {
            if (!is_array($verb)) {
                continue;
            }
            $out[] = $this->keepKeys($verb, ['verb_id', 'label', 'count']);
            if (count($out) >= 12) {
                break;
            }
        }
        return $out;
    }

    /** @param array<int|string,mixed> $byDay @return array<string,int> */
    private function filterActivityByDay(array $byDay): array
    {
        $items = array_slice($byDay, -30, null, true);
        $out = [];
        foreach ($items as $day => $count) {
            if (is_scalar($day)) {
                $out[(string) $day] = (int) $count;
            }
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $pedagogy
     * @param array<int,array<string,mixed>> $resources
     * @return array<int,string>
     */
    private function deterministicFindings(array $summary, array $pedagogy, array $resources): array
    {
        $findings = [];
        $total = (int) ($summary['total'] ?? 0);
        $activeLearners = (int) ($summary['active_learners'] ?? 0);
        $resourcesTotal = (int) ($summary['resources_total'] ?? count($resources));
        $resourcesWithTraces = (int) ($summary['resources_with_traces'] ?? 0);
        $critical = (int) ($pedagogy['critical_count'] ?? 0);
        $watch = (int) ($pedagogy['watch_count'] ?? 0);
        $withoutTrace = (int) ($pedagogy['resources_without_trace'] ?? 0);
        $testsPassed = (int) ($summary['tests_passed'] ?? 0);
        $testsFailed = (int) ($summary['tests_failed'] ?? 0);

        if ($total <= 0) {
            $findings[] = 'Aucune trace TRAX/LRS n’est disponible sur la période analysée.';
            return $findings;
        }

        $findings[] = $total . ' statement(s) TRAX/LRS analysé(s).';
        $findings[] = $activeLearners . ' apprenant(s) actif(s) détecté(s) sous forme agrégée.';
        $findings[] = $resourcesWithTraces . ' ressource(s) sur ' . $resourcesTotal . ' présentent au moins une trace.';

        if ($withoutTrace > 0) {
            $findings[] = $withoutTrace . ' ressource(s) activée(s) ne présentent aucune trace sur la période.';
        }
        if ($critical > 0) {
            $findings[] = $critical . ' ressource(s) sont en priorité critique.';
        }
        if ($watch > 0) {
            $findings[] = $watch . ' ressource(s) sont à surveiller.';
        }
        if ($testsPassed + $testsFailed > 0) {
            $findings[] = $testsPassed . ' réussite(s) et ' . $testsFailed . ' échec(s) de test sont observés.';
        }
        if (is_numeric($summary['avg_score_raw'] ?? null)) {
            $findings[] = 'Le score moyen observé est de ' . (string) $summary['avg_score_raw'] . ' %. ';
        }

        return $findings;
    }

    /**
     * @param array<int|string,mixed> $expertRows
     * @return array<string,mixed>
     */
    private function aggregateLearnerRisks(array $expertRows): array
    {
        $learnersWithSignals = [];
        $learnersWithFailedTests = [];
        $learnersWithLowScores = [];
        $resourceSignals = [];
        $riskEvents = 0;
        $failedEvents = 0;
        $lowScoreEvents = 0;

        foreach ($expertRows as $row) {
            if (!is_array($row) || (string) ($row['obj_type'] ?? '') !== 'tst') {
                continue;
            }

            $learnerKey = (string) ($row['user_id'] ?? '');
            if ($learnerKey === '') {
                continue;
            }

            $score = is_numeric($row['score_raw'] ?? null) ? (float) $row['score_raw'] : null;
            $success = $row['success'] ?? null;
            $verbId = (string) ($row['verb_id'] ?? '');
            $failed = ($success === false) || stripos($verbId, 'failed') !== false;
            $lowScore = $score !== null && $score < 50.0;

            if (!$failed && !$lowScore) {
                continue;
            }

            $riskEvents++;
            $learnersWithSignals[$learnerKey] = true;
            if ($failed) {
                $failedEvents++;
                $learnersWithFailedTests[$learnerKey] = true;
            }
            if ($lowScore) {
                $lowScoreEvents++;
                $learnersWithLowScores[$learnerKey] = true;
            }

            $resourceKey = 'ref:' . (string) ((int) ($row['ref_id'] ?? 0));
            if (!isset($resourceSignals[$resourceKey])) {
                $resourceSignals[$resourceKey] = [
                    'ref_id' => (int) ($row['ref_id'] ?? 0),
                    'obj_id' => (int) ($row['obj_id'] ?? 0),
                    'obj_type' => (string) ($row['obj_type'] ?? ''),
                    'title' => (string) ($row['object_title'] ?? ''),
                    'alert_count' => 0,
                    'failed_count' => 0,
                    'low_score_count' => 0,
                ];
            }
            $resourceSignals[$resourceKey]['alert_count']++;
            if ($failed) {
                $resourceSignals[$resourceKey]['failed_count']++;
            }
            if ($lowScore) {
                $resourceSignals[$resourceKey]['low_score_count']++;
            }
        }

        usort($resourceSignals, static function (array $a, array $b): int {
            return ((int) ($b['alert_count'] ?? 0) <=> (int) ($a['alert_count'] ?? 0))
                ?: ((int) ($b['failed_count'] ?? 0) <=> (int) ($a['failed_count'] ?? 0));
        });

        return [
            'risk_events' => $riskEvents,
            'failed_test_events' => $failedEvents,
            'low_score_events' => $lowScoreEvents,
            'learners_with_any_signal_count' => count($learnersWithSignals),
            'learners_with_failed_tests_count' => count($learnersWithFailedTests),
            'learners_with_low_scores_count' => count($learnersWithLowScores),
            'resources_with_concentrated_difficulty' => array_slice($resourceSignals, 0, 10),
            'privacy_note' => 'Agrégation collective uniquement : aucun identifiant apprenant n’est transmis dans ce bloc.',
        ];
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
        $resources = is_array($payload['resource_analysis'] ?? null) ? count($payload['resource_analysis']) : 0;
        $indicators = is_array($payload['global_indicators'] ?? null) ? $payload['global_indicators'] : [];
        $total = (int) ($indicators['total'] ?? 0);
        $period = (int) ($payload['source_context']['period_days'] ?? 0);
        return 'Payload IA V0.14.1 agrégé : ' . $resources . ' ressource(s), ' . $total . ' statement(s)' . ($period > 0 ? ', période ' . $period . ' jour(s).' : '.');
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
