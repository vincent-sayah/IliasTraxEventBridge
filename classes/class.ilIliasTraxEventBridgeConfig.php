<?php

/**
 * Configuration wrapper.
 *
 * V0.11 adds persistent diagnostics for TRAX/LRS read and write tests.
 */
class ilIliasTraxEventBridgeConfig
{
    private const MODULE = 'itxeb';

    /** @var ilSetting|null */
    private $settings;

    public function __construct()
    {
        $this->settings = class_exists('ilSetting') ? new ilSetting(self::MODULE) : null;
    }

    public function isEnabled(): bool { return $this->getBool('enabled', true); }
    public function setEnabled(bool $enabled): void { $this->setBool('enabled', $enabled); }

    public function isDebugEnabled(): bool { return $this->getBool('debug_enabled', true); }
    public function setDebugEnabled(bool $enabled): void { $this->setBool('debug_enabled', $enabled); }

    public function isLocalXapiGenerationEnabled(): bool { return $this->getBool('local_xapi_generation_enabled', true); }
    public function setLocalXapiGenerationEnabled(bool $enabled): void { $this->setBool('local_xapi_generation_enabled', $enabled); }

    public function isDenyLogEnabled(): bool { return $this->getBool('deny_log_enabled', false); }
    public function setDenyLogEnabled(bool $enabled): void { $this->setBool('deny_log_enabled', $enabled); }

    public function getMaxPayloadChars(): int { return max(500, min(30000, (int) $this->get('max_payload_chars', '10000'))); }
    public function setMaxPayloadChars(int $value): void { $this->set('max_payload_chars', (string) max(500, min(30000, $value))); }

    public function getRetentionDays(): int { return max(1, min(365, (int) $this->get('retention_days', '30'))); }
    public function setRetentionDays(int $days): void { $this->set('retention_days', (string) max(1, min(365, $days))); }

    public function getIliasBaseUrl(): string
    {
        $configured = trim($this->get('ilias_base_url', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $scheme = 'http';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        }
        if (isset($_SERVER['REQUEST_SCHEME']) && is_scalar($_SERVER['REQUEST_SCHEME'])) {
            $candidate = strtolower((string) $_SERVER['REQUEST_SCHEME']);
            if ($candidate === 'http' || $candidate === 'https') {
                $scheme = $candidate;
            }
        }

        $host = 'ilias.local';
        if (isset($_SERVER['HTTP_HOST']) && is_scalar($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '') {
            $host = (string) $_SERVER['HTTP_HOST'];
        } elseif (isset($_SERVER['SERVER_NAME']) && is_scalar($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== '') {
            $host = (string) $_SERVER['SERVER_NAME'];
        }

        return rtrim($scheme . '://' . $host, '/');
    }

    public function setIliasBaseUrl(string $url): void { $this->set('ilias_base_url', rtrim(trim($url), '/')); }
    public function getActorHomePage(): string { return $this->getIliasBaseUrl(); }

    public function getTraxEndpoint(): string { return rtrim(trim($this->get('trax_endpoint', '')), '/'); }
    public function setTraxEndpoint(string $endpoint): void { $this->set('trax_endpoint', rtrim(trim($endpoint), '/')); }

    public function getTraxUsername(): string { return trim($this->get('trax_username', '')); }
    public function setTraxUsername(string $username): void { $this->set('trax_username', trim($username)); }

    public function getTraxPassword(): string { return (string) $this->get('trax_password', ''); }
    public function setTraxPassword(string $password): void { $this->set('trax_password', $password); }

    public function getXapiVersion(): string
    {
        $version = trim($this->get('xapi_version', '1.0.3'));
        return $version !== '' ? $version : '1.0.3';
    }
    public function setXapiVersion(string $version): void { $this->set('xapi_version', trim($version) !== '' ? trim($version) : '1.0.3'); }

    public function getHttpTimeout(): int { return max(2, min(120, (int) $this->get('http_timeout', '15'))); }
    public function setHttpTimeout(int $timeout): void { $this->set('http_timeout', (string) max(2, min(120, $timeout))); }

    public function getBatchSize(): int { return max(1, min(100, (int) $this->get('batch_size', '25'))); }
    public function setBatchSize(int $batchSize): void { $this->set('batch_size', (string) max(1, min(100, $batchSize))); }

    public function getMaxRetry(): int { return max(0, min(50, (int) $this->get('max_retry', '5'))); }
    public function setMaxRetry(int $maxRetry): void { $this->set('max_retry', (string) max(0, min(50, $maxRetry))); }

    public function isCronEnabled(): bool { return $this->getBool('cron_enabled', false); }
    public function setCronEnabled(bool $enabled): void { $this->setBool('cron_enabled', $enabled); }

    public function isTraxConfigured(): bool
    {
        return $this->getTraxEndpoint() !== '' && $this->getTraxUsername() !== '' && $this->getTraxPassword() !== '';
    }

    public function getStatementsEndpoint(): string
    {
        $endpoint = $this->getTraxEndpoint();
        if ($endpoint === '') { return ''; }
        if (preg_match('~/statements$~', $endpoint)) { return $endpoint; }
        return rtrim($endpoint, '/') . '/statements';
    }

    public function setLastAiTestResult(bool $success, int $httpStatus, string $message): void
    {
        $this->set('last_ai_test_at', date('Y-m-d H:i:s'));
        $this->set('last_ai_test_success', $success ? '1' : '0');
        $this->set('last_ai_test_http_status', (string) $httpStatus);
        $this->set('last_ai_test_message', substr($message, 0, 2000));
    }
    public function getLastAiTestAt(): string { return $this->get('last_ai_test_at', ''); }
    public function getLastAiTestSuccess(): string { return $this->yesNo('last_ai_test_success'); }
    public function getLastAiTestHttpStatus(): string { return $this->get('last_ai_test_http_status', ''); }
    public function getLastAiTestMessage(): string { return $this->get('last_ai_test_message', ''); }
    public function setLastTraxTestResult(bool $success, int $httpStatus, string $message): void
    {
        $this->set('last_trax_test_at', date('Y-m-d H:i:s'));
        $this->set('last_trax_test_success', $success ? '1' : '0');
        $this->set('last_trax_test_http_status', (string) $httpStatus);
        $this->set('last_trax_test_message', substr($message, 0, 2000));
    }
    public function getLastTraxTestAt(): string { return $this->get('last_trax_test_at', ''); }
    public function getLastTraxTestSuccess(): string { return $this->yesNo('last_trax_test_success'); }
    public function getLastTraxTestHttpStatus(): string { return $this->get('last_trax_test_http_status', ''); }
    public function getLastTraxTestMessage(): string { return $this->get('last_trax_test_message', ''); }

    public function setLastLrsReadResult(bool $success, int $httpStatus, string $message): void
    {
        $this->set('last_lrs_read_at', date('Y-m-d H:i:s'));
        $this->set('last_lrs_read_success', $success ? '1' : '0');
        $this->set('last_lrs_read_http_status', (string) $httpStatus);
        $this->set('last_lrs_read_message', substr($message, 0, 2000));
    }
    public function getLastLrsReadAt(): string { return $this->get('last_lrs_read_at', ''); }
    public function getLastLrsReadSuccess(): string { return $this->yesNo('last_lrs_read_success'); }
    public function getLastLrsReadHttpStatus(): string { return $this->get('last_lrs_read_http_status', ''); }
    public function getLastLrsReadMessage(): string { return $this->get('last_lrs_read_message', ''); }

    public function setLastLrsWriteResult(bool $success, int $httpStatus, string $message): void
    {
        $this->set('last_lrs_write_at', date('Y-m-d H:i:s'));
        $this->set('last_lrs_write_success', $success ? '1' : '0');
        $this->set('last_lrs_write_http_status', (string) $httpStatus);
        $this->set('last_lrs_write_message', substr($message, 0, 2000));
    }
    public function getLastLrsWriteAt(): string { return $this->get('last_lrs_write_at', ''); }
    public function getLastLrsWriteSuccess(): string { return $this->yesNo('last_lrs_write_success'); }
    public function getLastLrsWriteHttpStatus(): string { return $this->get('last_lrs_write_http_status', ''); }
    public function getLastLrsWriteMessage(): string { return $this->get('last_lrs_write_message', ''); }

    public function setLastTraxSendResult(bool $success, int $httpStatus, string $message): void
    {
        $this->set('last_trax_send_at', date('Y-m-d H:i:s'));
        $this->set('last_trax_send_success', $success ? '1' : '0');
        $this->set('last_trax_send_http_status', (string) $httpStatus);
        $this->set('last_trax_send_message', substr($message, 0, 2000));
    }
    public function getLastTraxSendAt(): string { return $this->get('last_trax_send_at', ''); }
    public function getLastTraxSendSuccess(): string { return $this->yesNo('last_trax_send_success'); }
    public function getLastTraxSendHttpStatus(): string { return $this->get('last_trax_send_http_status', ''); }
    public function getLastTraxSendMessage(): string { return $this->get('last_trax_send_message', ''); }

    public function setLastCronResult(bool $success, int $httpStatus, string $message): void
    {
        $this->set('last_cron_at', date('Y-m-d H:i:s'));
        $this->set('last_cron_success', $success ? '1' : '0');
        $this->set('last_cron_http_status', (string) $httpStatus);
        $this->set('last_cron_message', substr($message, 0, 2000));
    }
    public function getLastCronAt(): string { return $this->get('last_cron_at', ''); }
    public function getLastCronSuccess(): string { return $this->yesNo('last_cron_success'); }
    public function getLastCronHttpStatus(): string { return $this->get('last_cron_http_status', ''); }
    public function getLastCronMessage(): string { return $this->get('last_cron_message', ''); }

    public function isAiEnabled(): bool { return $this->getBool('ai_enabled', false); }
    public function setAiEnabled(bool $enabled): void { $this->setBool('ai_enabled', $enabled); }

    public function getAiProvider(): string { return trim($this->get('ai_provider', 'vibe')); }
    public function setAiProvider(string $provider): void { $this->set('ai_provider', trim($provider) !== '' ? trim($provider) : 'vibe'); }

    public function getAiApiUrl(): string { return rtrim(trim($this->get('ai_api_url', '')), '/'); }
    public function setAiApiUrl(string $url): void { $this->set('ai_api_url', rtrim(trim($url), '/')); }

    public function getAiModel(): string { return trim($this->get('ai_model', '')); }
    public function setAiModel(string $model): void { $this->set('ai_model', trim($model)); }

    public function getAiTimeout(): int { return max(2, min(120, (int) $this->get('ai_timeout', '20'))); }
    public function setAiTimeout(int $timeout): void { $this->set('ai_timeout', (string) max(2, min(120, $timeout))); }

    public function getAiAnonymizationMode(): string
    {
        $mode = strtolower(trim($this->get('ai_anonymization_mode', 'strict')));
        return in_array($mode, ['strict', 'pseudonymized', 'none'], true) ? $mode : 'strict';
    }
    public function setAiAnonymizationMode(string $mode): void
    {
        $mode = strtolower(trim($mode));
        $this->set('ai_anonymization_mode', in_array($mode, ['strict', 'pseudonymized', 'none'], true) ? $mode : 'strict');
    }

    public function getAiTraceLimit(): int { return max(1, min(1000, (int) $this->get('ai_trace_limit', '200'))); }
    public function setAiTraceLimit(int $limit): void { $this->set('ai_trace_limit', (string) max(1, min(1000, $limit))); }

    public function isAiLogEnabled(): bool { return $this->getBool('ai_log_enabled', false); }
    public function setAiLogEnabled(bool $enabled): void { $this->setBool('ai_log_enabled', $enabled); }

    public function getAiApiKey(): string
    {
        $value = getenv('ITXEB_AI_API_KEY');
        if (is_string($value) && trim($value) !== '') { return trim($value); }
        if (isset($_ENV['ITXEB_AI_API_KEY']) && is_scalar($_ENV['ITXEB_AI_API_KEY']) && trim((string)$_ENV['ITXEB_AI_API_KEY']) !== '') { return trim((string)$_ENV['ITXEB_AI_API_KEY']); }
        if (isset($_SERVER['ITXEB_AI_API_KEY']) && is_scalar($_SERVER['ITXEB_AI_API_KEY']) && trim((string)$_SERVER['ITXEB_AI_API_KEY']) !== '') { return trim((string)$_SERVER['ITXEB_AI_API_KEY']); }
        return '';
    }

    public function hasAiApiKey(): bool
    {
        return $this->getAiApiKey() !== '';
    }

    public function getAiApiKeyStatus(): string
    {
        return $this->hasAiApiKey() ? 'presente cote serveur' : 'absente cote serveur';
    }
    private function yesNo(string $key): string
    {
        $value = $this->get($key, '');
        if ($value === '') { return ''; }
        return $value === '1' ? 'oui' : 'non';
    }

    private function get(string $key, string $default): string
    {
        if ($this->settings === null) { return $default; }
        return (string) $this->settings->get($key, $default);
    }

    private function set(string $key, string $value): void
    {
        if ($this->settings === null) { return; }
        $this->settings->set($key, $value);
    }

    private function getBool(string $key, bool $default): bool
    {
        $value = $this->get($key, $default ? '1' : '0');
        return $value === '1' || strtolower($value) === 'true' || strtolower($value) === 'yes';
    }

    private function setBool(string $key, bool $value): void
    {
        $this->set($key, $value ? '1' : '0');
    }
}
