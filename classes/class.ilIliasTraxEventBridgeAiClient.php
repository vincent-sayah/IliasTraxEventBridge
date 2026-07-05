<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeHttpResult.php';

/**
 * Client HTTP IA V0.13.
 *
 * Utilise la clé configurée dans le plugin ou, en secours, la variable serveur
 * ITXEB_AI_API_KEY. Les appels de test n'envoient aucune trace xAPI réelle.
 */
class ilIliasTraxEventBridgeAiClient
{
    /** @var ilIliasTraxEventBridgeConfig */
    private $config;

    public function __construct(ilIliasTraxEventBridgeConfig $config)
    {
        $this->config = $config;
    }

    public function testConnection(): ilIliasTraxEventBridgeHttpResult
    {
        return $this->sendChatMessages([
            [
                'role' => 'system',
                'content' => 'Tu es un service de test technique. Réponds très brièvement.'
            ],
            [
                'role' => 'user',
                'content' => 'Réponds uniquement par OK_V013_AI_TEST.'
            ]
        ], 32);
    }

    /**
     * @param array<int,array<string,string>> $messages
     */
    public function sendChatMessages(array $messages, int $maxTokens = 900): ilIliasTraxEventBridgeHttpResult
    {
        $validation = $this->validateConfiguration();
        if ($validation !== null) {
            return $validation;
        }

        $payload = [
            'model' => $this->config->getAiModel(),
            'messages' => $messages,
            'temperature' => 0.2,
            'max_tokens' => max(32, min(3000, $maxTokens))
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            return new ilIliasTraxEventBridgeHttpResult(false, 0, '', 'Impossible d’encoder le payload IA.');
        }

        return $this->request('POST', $this->config->getAiApiUrl(), $encoded);
    }

    private function validateConfiguration(): ?ilIliasTraxEventBridgeHttpResult
    {
        if (!$this->config->isAiEnabled()) {
            return new ilIliasTraxEventBridgeHttpResult(false, 0, '', 'Analyse IA désactivée.');
        }

        if (!$this->config->hasAiApiKey()) {
            return new ilIliasTraxEventBridgeHttpResult(false, 0, '', 'Clé API IA absente.');
        }

        if ($this->config->getAiApiUrl() === '') {
            return new ilIliasTraxEventBridgeHttpResult(false, 0, '', 'URL API IA absente.');
        }

        if ($this->config->getAiModel() === '') {
            return new ilIliasTraxEventBridgeHttpResult(false, 0, '', 'Modèle IA absent.');
        }

        return null;
    }

    private function request(string $method, string $url, string $payload): ilIliasTraxEventBridgeHttpResult
    {
        if (function_exists('curl_init')) {
            return $this->requestWithCurl($method, $url, $payload);
        }

        return $this->requestWithStream($method, $url, $payload);
    }

    private function requestWithCurl(string $method, string $url, string $payload): ilIliasTraxEventBridgeHttpResult
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return new ilIliasTraxEventBridgeHttpResult(false, 0, '', 'curl_init a échoué.');
        }

        $headers = $this->headers();
        $headers[] = 'Content-Length: ' . strlen($payload);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->getAiTimeout());
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $this->config->getAiTimeout()));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return new ilIliasTraxEventBridgeHttpResult(false, $status, '', $error !== '' ? $error : 'Erreur cURL inconnue.');
        }

        $success = $status >= 200 && $status < 300;
        return new ilIliasTraxEventBridgeHttpResult($success, $status, (string) $body, $success ? '' : $this->extractError((string) $body));
    }

    private function requestWithStream(string $method, string $url, string $payload): ilIliasTraxEventBridgeHttpResult
    {
        $headers = $this->headers();
        $headers[] = 'Content-Length: ' . strlen($payload);

        $context = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => $this->config->getAiTimeout(),
                'ignore_errors' => true,
                'content' => $payload,
            ]
        ];

        $body = @file_get_contents($url, false, stream_context_create($context));
        $status = $this->extractStatusFromHttpResponseHeader();

        if ($body === false) {
            $error = error_get_last();
            return new ilIliasTraxEventBridgeHttpResult(false, $status, '', is_array($error) ? (string) ($error['message'] ?? '') : 'Erreur HTTP stream.');
        }

        $success = $status >= 200 && $status < 300;
        return new ilIliasTraxEventBridgeHttpResult($success, $status, (string) $body, $success ? '' : $this->extractError((string) $body));
    }

    /**
     * @return array<int,string>
     */
    private function headers(): array
    {
        return [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->config->getAiApiKey(),
        ];
    }

    private function extractError(string $body): string
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (isset($decoded['error']) && is_array($decoded['error'])) {
                foreach (['message', 'type', 'code'] as $key) {
                    if (isset($decoded['error'][$key]) && is_scalar($decoded['error'][$key])) {
                        return substr((string) $decoded['error'][$key], 0, 1000);
                    }
                }
            }

            foreach (['message', 'error', 'detail', 'details'] as $key) {
                if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                    return substr((string) $decoded[$key], 0, 1000);
                }
            }
        }

        return substr($body, 0, 1000);
    }

    private function extractStatusFromHttpResponseHeader(): int
    {
        if (!isset($GLOBALS['http_response_header']) || !is_array($GLOBALS['http_response_header'])) {
            return 0;
        }

        foreach ($GLOBALS['http_response_header'] as $header) {
            if (is_string($header) && preg_match('~^HTTP/\S+\s+(\d{3})~', $header, $m)) {
                return (int) $m[1];
            }
        }

        return 0;
    }
}
