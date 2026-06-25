<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIBridge.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIScreen.php';

/**
 * UIHook GUI for exposing course-level xAPI configuration.
 *
 * This follows the same ILIAS 10 pattern as AutoCourseReminder:
 * - modifyGUI(..., 'sub_tabs', ['tabs' => ...]) adds the subtab.
 * - getHTML() renders the configuration screen only on the plugin URL.
 */
class ilIliasTraxEventBridgeCourseUIUIHookGUI extends ilUIHookPluginGUI
{
    /** @var ilIliasTraxEventBridgeCourseUIBridge */
    private $bridge;

    /** @var bool */
    private static $entryInjected = false;

    public function __construct()
    {
        $this->bridge = new ilIliasTraxEventBridgeCourseUIBridge();
    }

    /**
     * @param string $a_comp
     * @param string $a_part
     * @param array<string,mixed> $a_par
     * @return array<string,mixed>
     */
    public function getHTML($a_comp, $a_part, $a_par = []): array
    {
        if (!$this->isCourseUiCommandRequest() || self::$entryInjected) {
            return [
                'mode' => ilUIHookPluginGUI::KEEP,
                'html' => '',
            ];
        }

        self::$entryInjected = true;
        $screen = new ilIliasTraxEventBridgeCourseUIScreen($this->bridge);

        return [
            'mode' => ilUIHookPluginGUI::APPEND,
            'html' => $screen->handle(),
        ];
    }

    /**
     * @param string $a_comp
     * @param string $a_part
     * @param array<string,mixed> $a_par
     */
    public function modifyGUI($a_comp, $a_part, $a_par = []): void
    {
        if ($a_part !== 'sub_tabs' || !isset($a_par['tabs']) || !is_object($a_par['tabs'])) {
            return;
        }

        $this->modifySubTabs($a_par['tabs']);
    }

    private function modifySubTabs($tabs): void
    {
        if (!$this->isReadyForCourseContext() || !$this->isSettingsAreaRequest()) {
            return;
        }

        $url = $this->getContextualConfigurationUrl();
        if ($url === '') {
            return;
        }

        if (method_exists($tabs, 'addSubTab')) {
            $tabs->addSubTab('itxeb_course_xapi_settings', 'Suivi xAPI', $url);
        } elseif (method_exists($tabs, 'addSubTabTarget')) {
            $tabs->addSubTabTarget('Suivi xAPI', $url, '', '', '', 'itxeb_course_xapi_settings');
        } else {
            return;
        }

        if ($this->isCourseUiCommandRequest()) {
            if (method_exists($tabs, 'setSubTabActive')) {
                $tabs->setSubTabActive('itxeb_course_xapi_settings');
            } elseif (method_exists($tabs, 'activateSubTab')) {
                $tabs->activateSubTab('itxeb_course_xapi_settings');
            }
        }
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

    private function isSettingsAreaRequest(): bool
    {
        if ($this->isCourseUiCommandRequest()) {
            return true;
        }

        $cmd = strtolower($this->requestValue($_GET, 'cmd'));
        return in_array($cmd, ['edit', 'update', 'editinfo', 'updateinfo'], true);
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
