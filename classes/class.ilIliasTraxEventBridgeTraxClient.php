<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeHttpResult.php';

/**
 * Minimal xAPI HTTP client for TRAX 3.
 *
 * V0.3 only supports:
 * - connection test via GET /statements?limit=1
 * - manual POST /statements with a batch of statements
 */
class ilIliasTraxEventBridgeTraxClient
{
    /** @var ilIliasTraxEventBridgeConfig */
    private $config;

    public function __construct(ilIliasTraxEventBridgeConfig $config)
    {
        $this->config = $config;
    }

    public function testConnection(): ilIliasTraxEventBridgeHttpResult
    {
        if (!$this->config->isTraxConfigured()) {
            return new ilIliasTraxEventBridgeHttpResult(false, 0, '', 'Configuration TRAX incomplète.');
        }

        $url = $this->config->getStatementsEndpoint();
        $separator = strpos($url, '?') === false ? '?' : '&';
        $url .= $separator . 'limit=1';

        return $this->request('GET', $url, null);
    }

    /**
     * @param array<int,array<string,mixed>> $statements
     */
    public function sendStatements(array $statements): ilIliasTraxEventBridgeHttpResult
    {
        if (!$this->config->isTraxConfigured()) {
            return new ilIliasTraxEventBridgeHttpResult(false, 0, '', 'Configuration TRAX incomplète.');
        }

        if (count($statements) === 0) {
            return new ilIliasTraxEventBridgeHttpResult(true, 0, '', 'Aucun statement à envoyer.');
        }

        $payload = json_encode($statements, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) {
            return new ilIliasTraxEventBridgeHttpResult(false, 0, '', 'Impossible d’encoder le batch xAPI en JSON.');
        }

        return $this->request('POST', $this->config->getStatementsEndpoint(), $payload);
    }

    private function request(string $method, string $url, ?string $payload): ilIliasTraxEventBridgeHttpResult
    {
        if (function_exists('curl_init')) {
            return $this->requestWithCurl($method, $url, $payload);
        }

        return $this->requestWithStream($method, $url, $payload);
    }

    private function requestWithCurl(string $method, string $url, ?string $payload): ilIliasTraxEventBridgeHttpResult
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return new ilIliasTraxEventBridgeHttpResult(false, 0, '', 'curl_init a échoué.');
        }

        $headers = $this->headers();
        if ($payload !== null) {
            $headers[] = 'Content-Length: ' . strlen($payload);
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERPWD, $this->config->getTraxUsername() . ':' . $this->config->getTraxPassword());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->getHttpTimeout());
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $this->config->getHttpTimeout()));
        curl_setopt($ch, CURLOPT_HEADER, false);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

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

    private function requestWithStream(string $method, string $url, ?string $payload): ilIliasTraxEventBridgeHttpResult
    {
        $headers = $this->headers();
        $headers[] = 'Authorization: Basic ' . base64_encode($this->config->getTraxUsername() . ':' . $this->config->getTraxPassword());

        $context = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => $this->config->getHttpTimeout(),
                'ignore_errors' => true,
            ]
        ];

        if ($payload !== null) {
            $context['http']['content'] = $payload;
        }

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
            'X-Experience-API-Version: ' . $this->config->getXapiVersion(),
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
