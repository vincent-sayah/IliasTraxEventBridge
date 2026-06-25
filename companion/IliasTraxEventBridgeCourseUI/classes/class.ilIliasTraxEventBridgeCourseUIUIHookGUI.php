<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIBridge.php';

/**
 * UIHook GUI skeleton for exposing course-level TRAX/xAPI configuration.
 *
 * Lot 2 is intentionally non-invasive: no HTML is injected yet. The class only
 * proves that the companion plugin can be loaded safely and can discover the
 * current course context.
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
        // Lot 2 skeleton only. Course-tab injection is handled in a later lot.
    }

    public function isReadyForCourseContext(): bool
    {
        $courseRefId = $this->bridge->detectCourseRefId();

        return $courseRefId > 0
            && $this->bridge->isMainPluginAvailable()
            && $this->bridge->canManageCourse($courseRefId);
    }
}
