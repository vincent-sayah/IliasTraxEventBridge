<?php

/**
 * Minimal configuration wrapper for V0.1.
 * Uses ilSetting to avoid a dedicated config table in the first debug version.
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
        // Default ON for V0.1 so event discovery works immediately after activation.
        return $this->getBool('enabled', true);
    }

    public function setEnabled(bool $enabled): void
    {
        $this->setBool('enabled', $enabled);
    }

    public function isDebugEnabled(): bool
    {
        // Default ON for V0.1; later versions should default this to false in production.
        return $this->getBool('debug_enabled', true);
    }

    public function setDebugEnabled(bool $enabled): void
    {
        $this->setBool('debug_enabled', $enabled);
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
