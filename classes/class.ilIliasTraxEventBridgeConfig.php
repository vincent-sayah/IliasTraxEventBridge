<?php

/**
 * Configuration wrapper.
 *
 * V0.2 keeps defaults enabled to simplify discovery and local xAPI generation.
 * Later production versions should expose a real admin form and default debug to OFF.
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

    public function isEnabled(): bool
    {
        return $this->getBool('enabled', true);
    }

    public function setEnabled(bool $enabled): void
    {
        $this->setBool('enabled', $enabled);
    }

    public function isDebugEnabled(): bool
    {
        return $this->getBool('debug_enabled', true);
    }

    public function setDebugEnabled(bool $enabled): void
    {
        $this->setBool('debug_enabled', $enabled);
    }

    public function isLocalXapiGenerationEnabled(): bool
    {
        return $this->getBool('local_xapi_generation_enabled', true);
    }

    public function setLocalXapiGenerationEnabled(bool $enabled): void
    {
        $this->setBool('local_xapi_generation_enabled', $enabled);
    }

    public function getMaxPayloadChars(): int
    {
        return max(500, min(30000, (int) $this->get('max_payload_chars', '10000')));
    }

    public function setMaxPayloadChars(int $value): void
    {
        $this->set('max_payload_chars', (string) max(500, min(30000, $value)));
    }

    public function getRetentionDays(): int
    {
        return max(1, min(365, (int) $this->get('retention_days', '30')));
    }

    public function setRetentionDays(int $days): void
    {
        $this->set('retention_days', (string) max(1, min(365, $days)));
    }

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

    public function getActorHomePage(): string
    {
        return $this->getIliasBaseUrl();
    }

    private function get(string $key, string $default): string
    {
        if ($this->settings === null) {
            return $default;
        }

        return (string) $this->settings->get($key, $default);
    }

    private function set(string $key, string $value): void
    {
        if ($this->settings !== null) {
            $this->settings->set($key, $value);
        }
    }

    private function getBool(string $key, bool $default): bool
    {
        return $this->get($key, $default ? '1' : '0') === '1';
    }

    private function setBool(string $key, bool $value): void
    {
        $this->set($key, $value ? '1' : '0');
    }
}
