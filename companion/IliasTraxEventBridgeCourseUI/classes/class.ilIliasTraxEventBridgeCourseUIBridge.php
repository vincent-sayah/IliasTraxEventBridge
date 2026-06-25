<?php

/**
 * Bridge between the UIHook companion plugin and the main EventHook plugin.
 *
 * Lot 3 prepares robust course-context detection and a contextual configuration
 * URL. Actual visual injection is introduced in the next V0.7.1 lots.
 */
class ilIliasTraxEventBridgeCourseUIBridge
{
    /** @var string */
    private $mainPluginPath;

    public function __construct(?string $mainPluginPath = null)
    {
        $this->mainPluginPath = $mainPluginPath !== null
            ? rtrim($mainPluginPath, '/')
            : dirname(__DIR__, 4) . '/EventHandling/EventHook/IliasTraxEventBridge';
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

    /**
     * @return array<string,mixed>
     */
    public function getCourseContext(): array
    {
        $courseRefId = $this->detectCourseRefId();

        return [
            'course_ref_id' => $courseRefId,
            'course_obj_id' => $courseRefId > 0 ? $this->lookupObjectId($courseRefId) : 0,
            'course_title' => $courseRefId > 0 ? $this->lookupTitleByRefId($courseRefId) : '',
            'can_manage' => $courseRefId > 0 && $this->canManageCourse($courseRefId),
            'main_plugin_available' => $this->isMainPluginAvailable(),
            'course_tracking_classes_available' => $this->loadCourseTrackingClasses(),
            'configuration_url' => $courseRefId > 0 ? $this->buildContextualConfigurationUrl($courseRefId) : '',
            'detection_candidates' => $this->collectCourseRefIdCandidates(),
        ];
    }

    public function detectCourseRefId(): int
    {
        foreach ($this->collectCourseRefIdCandidates() as $candidate) {
            if ($candidate > 0 && $this->isCourseRefId($candidate)) {
                return $candidate;
            }
        }

        return 0;
    }

    /**
     * @return array<int,int>
     */
    public function collectCourseRefIdCandidates(): array
    {
        $candidates = [];

        foreach (['ref_id', 'course_ref_id', 'target_ref_id', 'itxeb_course_ref_id'] as $key) {
            $this->appendNumericRequestCandidate($candidates, $_GET, $key);
            $this->appendNumericRequestCandidate($candidates, $_POST, $key);
        }

        foreach (['target', 'ref_id', 'cmdClass', 'cmd'] as $key) {
            $this->appendStructuredRequestCandidate($candidates, $_GET, $key);
            $this->appendStructuredRequestCandidate($candidates, $_POST, $key);
        }

        $requestUri = isset($_SERVER['REQUEST_URI']) && is_scalar($_SERVER['REQUEST_URI'])
            ? (string) $_SERVER['REQUEST_URI']
            : '';
        $this->appendCandidatesFromText($candidates, $requestUri);

        return array_values(array_unique(array_filter($candidates, static function (int $value): bool {
            return $value > 0;
        })));
    }

    public function buildContextualConfigurationUrl(int $courseRefId): string
    {
        if ($courseRefId <= 0) {
            return '';
        }

        $script = $this->currentScriptUrl();
        if ($script === '') {
            return '';
        }

        $params = $_GET;
        $params['itxeb_cui_cmd'] = 'showCourseTracking';
        $params['itxeb_course_ref_id'] = (string) $courseRefId;
        unset($params['cmd']);

        return $script . '?' . http_build_query($params, '', '&');
    }

    public function isCourseUiRequest(): bool
    {
        $cmd = isset($_GET['itxeb_cui_cmd']) && is_scalar($_GET['itxeb_cui_cmd'])
            ? (string) $_GET['itxeb_cui_cmd']
            : '';

        return $cmd === 'showCourseTracking';
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

    public function lookupObjectId(int $refId): int
    {
        if ($refId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupObjId')) {
            return 0;
        }

        try {
            return (int) ilObject::_lookupObjId($refId);
        } catch (Throwable $ignored) {
            return 0;
        }
    }

    public function lookupTitleByRefId(int $refId): string
    {
        $objId = $this->lookupObjectId($refId);
        if ($objId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupTitle')) {
            return '';
        }

        try {
            return (string) ilObject::_lookupTitle($objId);
        } catch (Throwable $ignored) {
            return '';
        }
    }

    /**
     * @param array<int,int> $candidates
     * @param array<string,mixed> $source
     */
    private function appendNumericRequestCandidate(array &$candidates, array $source, string $key): void
    {
        if (!isset($source[$key]) || !is_scalar($source[$key])) {
            return;
        }

        $value = (int) $source[$key];
        if ($value > 0) {
            $candidates[] = $value;
        }
    }

    /**
     * @param array<int,int> $candidates
     * @param array<string,mixed> $source
     */
    private function appendStructuredRequestCandidate(array &$candidates, array $source, string $key): void
    {
        if (!isset($source[$key]) || !is_scalar($source[$key])) {
            return;
        }

        $this->appendCandidatesFromText($candidates, (string) $source[$key]);
    }

    /** @param array<int,int> $candidates */
    private function appendCandidatesFromText(array &$candidates, string $text): void
    {
        if ($text === '') {
            return;
        }

        $patterns = [
            '/(?:^|[^a-z0-9_])crs[_-](\d+)(?:[^0-9]|$)/i',
            '/(?:^|[^a-z0-9_])ref_id[=\/](\d+)(?:[^0-9]|$)/i',
            '/(?:^|[^a-z0-9_])course_ref_id[=\/](\d+)(?:[^0-9]|$)/i',
            '/\/goto\.php\/crs\/(\d+)(?:[^0-9]|$)/i',
            '/\/crs\/(\d+)(?:[^0-9]|$)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $match) {
                    $value = (int) $match;
                    if ($value > 0) {
                        $candidates[] = $value;
                    }
                }
            }
        }
    }

    private function currentScriptUrl(): string
    {
        $scriptName = isset($_SERVER['SCRIPT_NAME']) && is_scalar($_SERVER['SCRIPT_NAME'])
            ? (string) $_SERVER['SCRIPT_NAME']
            : '';

        if ($scriptName === '') {
            return '';
        }

        return $scriptName;
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
