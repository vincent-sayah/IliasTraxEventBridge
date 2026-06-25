<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIBridge.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIScreen.php';

/**
 * UIHook GUI for exposing course-level xAPI configuration.
 *
 * The final UI entry is a generic course settings subtab named "Suivi xAPI".
 * The previous floating button is intentionally removed.
 */
class ilIliasTraxEventBridgeCourseUIUIHookGUI extends ilUIHookPluginGUI
{
    /** @var ilIliasTraxEventBridgeCourseUIBridge */
    private $bridge;

    /** @var bool */
    private static $entryInjected = false;

    /** @var bool */
    private static $subtabInjected = false;

    public function __construct()
    {
        $this->bridge = new ilIliasTraxEventBridgeCourseUIBridge();
    }

    /**
     * ILIAS UIHook HTML hook.
     *
     * @param string $a_comp
     * @param string $a_part
     * @param array<string,mixed> $a_par
     * @return array<string,mixed>
     */
    public function getHTML($a_comp, $a_part, $a_par = []): array
    {
        $this->debugHook('getHTML', $a_comp, $a_part, $a_par);
        $this->injectCourseSettingsSubtab('getHTML');

        if (self::$entryInjected) {
            return [
                'mode' => ilUIHookPluginGUI::KEEP,
                'html' => '',
            ];
        }

        if ($this->isCourseUiCommandRequest()) {
            self::$entryInjected = true;
            $this->injectCourseSettingsSubtab('getHTML:screen');
            $screen = new ilIliasTraxEventBridgeCourseUIScreen($this->bridge);
            return [
                'mode' => ilUIHookPluginGUI::APPEND,
                'html' => $screen->handle(),
            ];
        }

        return [
            'mode' => ilUIHookPluginGUI::KEEP,
            'html' => '',
        ];
    }

    /**
     * ILIAS UIHook GUI modification hook.
     *
     * @param string $a_comp
     * @param string $a_part
     * @param array<string,mixed> $a_par
     */
    public function modifyGUI($a_comp, $a_part, $a_par = []): void
    {
        $this->debugHook('modifyGUI', $a_comp, $a_part, $a_par);
        $this->injectCourseSettingsSubtab('modifyGUI');
    }

    /** @return array<string,mixed> */
    public function getCurrentCourseContext(): array
    {
        return $this->bridge->getCourseContext();
    }

    public function isReadyForCourseContext(): bool
    {
        $context = $this->getCurrentCourseContext();

        return (int) ($context['course_ref_id'] ?? 0) > 0
            && (bool) ($context['main_plugin_available'] ?? false)
            && (bool) ($context['course_tracking_classes_available'] ?? false)
            && (bool) ($context['can_manage'] ?? false)
            && (string) ($context['configuration_url'] ?? '') !== '';
    }

    public function getContextualConfigurationUrl(): string
    {
        $context = $this->getCurrentCourseContext();

        return (string) ($context['configuration_url'] ?? '');
    }

    private function injectCourseSettingsSubtab(string $source): void
    {
        if (self::$subtabInjected) {
            $this->debug('inject skip already source=' . $source);
            return;
        }

        if (!$this->isReadyForCourseContext()) {
            $this->debug('inject skip not-ready source=' . $source . ' uri=' . $this->currentUri());
            return;
        }

        $url = $this->getContextualConfigurationUrl();
        if ($url === '') {
            $this->debug('inject skip empty-url source=' . $source);
            return;
        }

        try {
            $tabs = null;
            if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'tabs')) {
                $tabs = $GLOBALS['DIC']->tabs();
            } elseif (isset($GLOBALS['ilTabs']) && is_object($GLOBALS['ilTabs'])) {
                $tabs = $GLOBALS['ilTabs'];
            }

            if (!is_object($tabs)) {
                $this->debug('inject skip no-tabs source=' . $source);
                return;
            }

            $this->debug('inject tabs source=' . $source
                . ' class=' . get_class($tabs)
                . ' addSubTab=' . (method_exists($tabs, 'addSubTab') ? '1' : '0')
                . ' addSubTabTarget=' . (method_exists($tabs, 'addSubTabTarget') ? '1' : '0')
                . ' activateSubTab=' . (method_exists($tabs, 'activateSubTab') ? '1' : '0')
                . ' url=' . $url
            );

            $injected = false;

            if (method_exists($tabs, 'addSubTab')) {
                $tabs->addSubTab('itxeb_course_xapi_settings', 'Suivi xAPI', $url);
                $injected = true;
            } elseif (method_exists($tabs, 'addSubTabTarget')) {
                $tabs->addSubTabTarget('Suivi xAPI', $url, '', '', '', 'itxeb_course_xapi_settings');
                $injected = true;
            }

            if ($injected && $this->isCourseUiCommandRequest() && method_exists($tabs, 'activateSubTab')) {
                $tabs->activateSubTab('itxeb_course_xapi_settings');
            }

            if ($injected) {
                self::$subtabInjected = true;
                $this->debug('inject success source=' . $source);
            } else {
                $this->debug('inject failed no-method source=' . $source);
            }
        } catch (Throwable $e) {
            $this->debug('inject exception source=' . $source . ' error=' . $e->getMessage());
            // UI hook must never break the course page.
        }
    }

    private function isCourseUiCommandRequest(): bool
    {
        $cmd = $this->requestValue($_POST, 'itxeb_cui_cmd');
        if ($cmd === '') {
            $cmd = $this->requestValue($_GET, 'itxeb_cui_cmd');
        }

        return in_array($cmd, [
            'showCourseTracking',
            'saveCourseTracking',
            'enableAllCourseTracking',
            'disableAllCourseTracking',
            'resetCourseTracking',
        ], true);
    }

    private function requestValue($source, string $key): string
    {
        try {
            if (is_array($source)) {
                return isset($source[$key]) && is_scalar($source[$key]) ? (string) $source[$key] : '';
            }
            if ($source instanceof ArrayAccess) {
                return isset($source[$key]) && is_scalar($source[$key]) ? (string) $source[$key] : '';
            }
            if (is_object($source)) {
                if (method_exists($source, 'offsetExists') && method_exists($source, 'offsetGet')) {
                    if (!$source->offsetExists($key)) {
                        return '';
                    }
                    $value = $source->offsetGet($key);
                    return is_scalar($value) ? (string) $value : '';
                }
                if (method_exists($source, 'retrieve')) {
                    $value = $source->retrieve($key, false);
                    return is_scalar($value) ? (string) $value : '';
                }
            }
        } catch (Throwable $ignored) {
            return '';
        }
        return '';
    }

    /** @param array<string,mixed> $a_par */
    private function debugHook(string $method, $a_comp, $a_part, array $a_par): void
    {
        if (!$this->debugEnabled()) {
            return;
        }

        $context = $this->getCurrentCourseContext();
        $this->debug($method
            . ' comp=' . (is_scalar($a_comp) ? (string) $a_comp : gettype($a_comp))
            . ' part=' . (is_scalar($a_part) ? (string) $a_part : gettype($a_part))
            . ' par_keys=' . implode(',', array_keys($a_par))
            . ' course_ref_id=' . (string) ((int) ($context['course_ref_id'] ?? 0))
            . ' can_manage=' . (!empty($context['can_manage']) ? '1' : '0')
            . ' uri=' . $this->currentUri()
        );
    }

    private function debug(string $line): void
    {
        if (!$this->debugEnabled()) {
            return;
        }

        @file_put_contents(
            $this->debugLogPath(),
            date('Y-m-d H:i:s') . ' ' . $line . PHP_EOL,
            FILE_APPEND
        );
    }

    private function debugEnabled(): bool
    {
        return is_file(dirname(__DIR__) . '/debug.tabs');
    }

    private function debugLogPath(): string
    {
        return dirname(__DIR__) . '/debug.tabs.log';
    }

    private function currentUri(): string
    {
        return isset($_SERVER['REQUEST_URI']) && is_scalar($_SERVER['REQUEST_URI'])
            ? (string) $_SERVER['REQUEST_URI']
            : '';
    }
}
