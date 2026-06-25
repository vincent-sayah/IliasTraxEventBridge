<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIBridge.php';

/**
 * UIHook GUI for exposing course-level TRAX/xAPI configuration.
 *
 * Lot 4 injects a visible, non-destructive course entry for users allowed to
 * manage the course. The link target is prepared here and will be wired to the
 * full configuration screen in the next lot.
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
     * ILIAS UIHook HTML hook.
     *
     * @param string $a_comp
     * @param string $a_part
     * @param array<string,mixed> $a_par
     * @return array<string,mixed>
     */
    public function getHTML($a_comp, $a_part, $a_par = []): array
    {
        if (self::$entryInjected || !$this->isReadyForCourseContext()) {
            return [
                'mode' => ilUIHookPluginGUI::KEEP,
                'html' => '',
            ];
        }

        self::$entryInjected = true;

        return [
            'mode' => ilUIHookPluginGUI::APPEND,
            'html' => $this->renderCourseEntry($this->getCurrentCourseContext()),
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
        // getHTML() injects the visible placeholder. Full screen routing is next.
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

    /** @param array<string,mixed> $context */
    private function renderCourseEntry(array $context): string
    {
        $url = $this->esc((string) ($context['configuration_url'] ?? ''));
        $courseRefId = $this->esc((string) ((int) ($context['course_ref_id'] ?? 0)));
        $courseTitle = trim((string) ($context['course_title'] ?? ''));
        $label = $courseTitle !== ''
            ? 'TRAX / xAPI — ' . $courseTitle
            : 'TRAX / xAPI';

        return '<style>'
            . '#itxeb-course-ui-entry{position:fixed;right:24px;bottom:24px;z-index:9999;background:#ffffff;border:1px solid #b8c7d9;border-radius:6px;box-shadow:0 2px 10px rgba(0,0,0,.16);padding:10px 12px;font-family:Arial,sans-serif;max-width:320px}'
            . '#itxeb-course-ui-entry .itxeb-course-ui-button{display:inline-block;background:#336699;color:#fff;text-decoration:none;border-radius:4px;padding:7px 12px;font-weight:bold}'
            . '#itxeb-course-ui-entry .itxeb-course-ui-button:hover{background:#244f78;color:#fff;text-decoration:none}'
            . '#itxeb-course-ui-entry .itxeb-course-ui-note{display:block;margin-top:6px;color:#555;font-size:12px}'
            . '</style>'
            . '<div id="itxeb-course-ui-entry" class="itxeb-course-ui-entry" data-course-ref-id="' . $courseRefId . '">'
            . '<a class="itxeb-course-ui-button" href="' . $url . '" title="Configuration TRAX / xAPI du cours">'
            . $this->esc($label)
            . '</a>'
            . '<span class="itxeb-course-ui-note">Configuration xAPI du cours</span>'
            . '</div>';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
