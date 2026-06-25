<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIBridge.php';

/**
 * UIHook GUI skeleton for exposing course-level TRAX/xAPI configuration.
 *
 * Lot 3 prepares course-context detection and contextual URL generation. The
 * next lots will use this information to inject a visible course entry.
 */
class ilIliasTraxEventBridgeCourseUIUIHookGUI extends ilUIHookPluginGUI
{
    /** @var ilIliasTraxEventBridgeCourseUIBridge */
    private $bridge;

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
        // Lot 3 only prepares context detection. Course entry injection is next.
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
}
