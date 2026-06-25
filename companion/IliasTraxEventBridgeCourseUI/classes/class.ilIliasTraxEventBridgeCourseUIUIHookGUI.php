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
        if (self::$entryInjected) {
            return [
                'mode' => ilUIHookPluginGUI::KEEP,
                'html' => '',
            ];
        }

        if ($this->isCourseUiCommandRequest()) {
            self::$entryInjected = true;
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
        $this->injectCourseSettingsSubtab();
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

    private function injectCourseSettingsSubtab(): void
    {
        if (self::$subtabInjected || !$this->isReadyForCourseContext()) {
            return;
        }

        $url = $this->getContextualConfigurationUrl();
        if ($url === '') {
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
                return;
            }

            if (method_exists($tabs, 'addSubTab')) {
                $tabs->addSubTab('itxeb_course_xapi_settings', 'Suivi xAPI', $url);
                if ($this->isCourseUiCommandRequest() && method_exists($tabs, 'activateSubTab')) {
                    $tabs->activateSubTab('itxeb_course_xapi_settings');
                }
                self::$subtabInjected = true;
            }
        } catch (Throwable $ignored) {
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
}
