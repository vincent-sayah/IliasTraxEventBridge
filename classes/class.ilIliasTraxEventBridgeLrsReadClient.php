<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeHttpResult.php';

/**
 * Read-only xAPI client used by course feedback dashboards.
 *
 * It never sends statements. It only calls GET /statements with xAPI query
 * parameters such as activity, related_activities, since and limit.
 */
class ilIliasTraxEventBridgeLrsReadClient
{
    /** @var ilIliasTraxEventBridgeConfig */
    private $config;

    public function __construct(ilIliasTraxEventBridgeConfig $config)
    {
        $this->config = $config;
    }

    /** @param array<string,string|int|bool|float|null> $params */
    public function queryStatements(array $params): ilIliasTraxEventBridgeHttpResult
    {
        if (!$this->config->isTraxConfigured()) {
            return new ilIliasTraxEventBridgeHttpResult(false, 0, '', 'Configuration TRAX incomplète.');
        }

        $endpoint = $this->config->getStatementsEndpoint();
        if ($endpoint === '') {
            return new ilIliasTraxEventBridgeHttpResult(false, 0, '', 'Endpoint /statements introuvable.');
        }

        return $this->request($this->appendQuery($endpoint, $params));
    }

    /** @param array<string,string|int|bool|float|null> $params */
    private function appendQuery(string $url, array $params): string
    {
        $clean = [];
        foreach ($params as $key => $value) {
            if (!is_string($key) || $key === '' || $value === null) {
                continue;
            }
            $clean[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        if (count($clean) === 0) {
            return $url;
        }

        return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($clean, '', '&', PHP_QUERY_RFC3986);
    }

    private function request(string $url): ilIliasTraxEventBridgeHttpResult
    {
        if (function_exists('curl_init')) {
            return $this->requestWithCurl($url);
        }
        return $this->requestWithStream($url);
    }

    private function requestWithCurl(string $url): ilIliasTraxEventBridgeHttpResult
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return new ilIliasTraxEventBridgeHttpResult(false, 0, '', 'curl_init a échoué.');
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers());
        curl_setopt($ch, CURLOPT_USERPWD, $this->config->getTraxUsername() . ':' . $this->config->getTraxPassword());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->getHttpTimeout());
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $this->config->getHttpTimeout()));
        curl_setopt($ch, CURLOPT_HEADER, false);

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

    private function requestWithStream(string $url): ilIliasTraxEventBridgeHttpResult
    {
        $headers = $this->headers();
        $headers[] = 'Authorization: Basic ' . base64_encode($this->config->getTraxUsername() . ':' . $this->config->getTraxPassword());

        $context = [
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => $this->config->getHttpTimeout(),
                'ignore_errors' => true,
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

    /** @return array<int,string> */
    private function headers(): array
    {
        return [
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
