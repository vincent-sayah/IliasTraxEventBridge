<?php

/**
 * Bridge between the UIHook companion plugin and the main EventHook plugin.
 *
 * Lot 2 only prepares safe discovery/loading. Actual course UI integration is
 * introduced in later V0.7.1 lots.
 */
class ilIliasTraxEventBridgeCourseUIBridge
{
    /** @var string */
    private $mainPluginPath;

    public function __construct(?string $mainPluginPath = null)
    {
        $this->mainPluginPath = $mainPluginPath !== null
            ? rtrim($mainPluginPath, '/')
            : dirname(__DIR__, 3) . '/EventHandling/EventHook/IliasTraxEventBridge';
    }

    public function getMainPluginPath(): string
    {
        return $this->mainPluginPath;
    }

    public function isMainPluginAvailable(): bool
    {
        return is_dir($this->mainPluginPath)
            && is_file($this->mainPluginPath . '/plugin.php')
            && is_dir($this->mainPluginPath . '/classes');
    }

    public function loadCourseTrackingClasses(): bool
    {
        if (!$this->isMainPluginAvailable()) {
            return false;
        }

        $files = [
            'class.ilIliasTraxEventBridgeCourseTrackingRepository.php',
            'class.ilIliasTraxEventBridgeCourseResourceResolver.php',
            'class.ilIliasTraxEventBridgeCourseTrackingGUI.php',
        ];

        foreach ($files as $file) {
            $path = $this->mainPluginPath . '/classes/' . $file;
            if (!is_file($path)) {
                return false;
            }
            require_once $path;
        }

        return class_exists('ilIliasTraxEventBridgeCourseTrackingGUI');
    }

    public function detectCourseRefId(): int
    {
        foreach (['ref_id', 'course_ref_id', 'target_ref_id'] as $key) {
            if (isset($_GET[$key]) && is_scalar($_GET[$key]) && (int) $_GET[$key] > 0) {
                $refId = (int) $_GET[$key];
                if ($this->isCourseRefId($refId)) {
                    return $refId;
                }
            }
            if (isset($_POST[$key]) && is_scalar($_POST[$key]) && (int) $_POST[$key] > 0) {
                $refId = (int) $_POST[$key];
                if ($this->isCourseRefId($refId)) {
                    return $refId;
                }
            }
        }

        return 0;
    }

    public function isCourseRefId(int $refId): bool
    {
        if ($refId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupType')) {
            return false;
        }

        try {
            return (string) ilObject::_lookupType($refId, true) === 'crs';
        } catch (Throwable $ignored) {
            return false;
        }
    }

    public function canManageCourse(int $courseRefId): bool
    {
        if ($courseRefId <= 0) {
            return false;
        }

        foreach (['write', 'edit_permission', 'manage_members'] as $permission) {
            if ($this->checkAccess($permission, $courseRefId)) {
                return true;
            }
        }

        return false;
    }

    private function checkAccess(string $permission, int $refId): bool
    {
        try {
            if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'access')) {
                $access = $GLOBALS['DIC']->access();
                if (is_object($access) && method_exists($access, 'checkAccess')) {
                    return (bool) $access->checkAccess($permission, '', $refId);
                }
            }
        } catch (Throwable $ignored) {
            // Fallback below.
        }

        try {
            if (isset($GLOBALS['ilAccess']) && is_object($GLOBALS['ilAccess']) && method_exists($GLOBALS['ilAccess'], 'checkAccess')) {
                return (bool) $GLOBALS['ilAccess']->checkAccess($permission, '', $refId);
            }
        } catch (Throwable $ignored) {
            return false;
        }

        return false;
    }
}
