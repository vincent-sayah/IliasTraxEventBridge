<?php

/**
 * V0.7 resolver for trackable course resources.
 *
 * This class prepares the data needed by the future course-level TRAX/xAPI UI.
 * It only reads the repository tree and the V0.7 config tables; it does not
 * write configuration and it does not filter xAPI generation yet.
 */
class ilIliasTraxEventBridgeCourseResourceResolver
{
    /** @var array<string,string> */
    private const TRACKABLE_TYPES = [
        'file' => 'file',
        'tst' => 'test',
        'blog' => 'blog',
        'wiki' => 'wiki',
        'webr' => 'web_link',
        'mcst' => 'media',
        'frm' => 'forum',
        'htlm' => 'html_module',
        'lm' => 'learning_module',
        'sahs' => 'scorm',
    ];

    /** @var ilIliasTraxEventBridgeCourseTrackingRepository|null */
    private $trackingRepository;

    /** @var ilDBInterface|mixed|null */
    private $db;

    public function __construct($trackingRepository = null)
    {
        $this->trackingRepository = $trackingRepository instanceof ilIliasTraxEventBridgeCourseTrackingRepository
            ? $trackingRepository
            : null;
        $this->db = $this->resolveDatabase();
    }

    /**
     * Returns a course summary with its current V0.7 xAPI configuration and
     * the list of trackable resources found below the course node.
     *
     * @return array<string,mixed>
     */
    public function resolveCourse(int $courseRefId): array
    {
        $courseRefId = max(0, $courseRefId);
        $courseObjId = $this->lookupObjectId($courseRefId);
        $courseConfig = $this->getCourseConfig($courseRefId);

        return [
            'course_ref_id' => $courseRefId,
            'course_obj_id' => $courseObjId,
            'course_title' => $this->lookupTitle($courseObjId),
            'course_configured' => $courseConfig !== [],
            'course_enabled' => $courseConfig !== [] && (int) ($courseConfig['enabled'] ?? 0) === 1,
            'course_config' => $courseConfig,
            'resources' => $this->findTrackableResources($courseRefId),
        ];
    }

    /**
     * Lists the trackable resources contained in a course and joins the current
     * per-resource configuration when it exists.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findTrackableResources(int $courseRefId): array
    {
        $courseRefId = max(0, $courseRefId);
        if ($courseRefId <= 0 || $this->lookupTypeByRefId($courseRefId) !== 'crs') {
            return [];
        }

        $configsByRefId = $this->getResourceConfigsByRefId($courseRefId);
        $seen = [];
        $resources = $this->collectTrackableChildren($courseRefId, $courseRefId, 0, $seen);

        foreach ($resources as $index => $resource) {
            $refId = (int) ($resource['ref_id'] ?? 0);
            $config = $configsByRefId[$refId] ?? [];

            $resources[$index]['configured'] = $config !== [];
            $resources[$index]['enabled'] = $config !== [] && (int) ($config['enabled'] ?? 0) === 1;
            $resources[$index]['config'] = $config;
        }

        usort($resources, static function (array $left, array $right): int {
            return strcmp((string) ($left['sort_key'] ?? ''), (string) ($right['sort_key'] ?? ''));
        });

        return $resources;
    }

    /** @return array<string,string> */
    public function getTrackableTypes(): array
    {
        return self::TRACKABLE_TYPES;
    }

    /** @return array<int,array<string,mixed>> */
    private function collectTrackableChildren(int $courseRefId, int $parentRefId, int $depth, array &$seen): array
    {
        if ($parentRefId <= 0 || $depth > 50) {
            return [];
        }

        $rows = [];
        foreach ($this->getChildren($parentRefId) as $node) {
            $refId = $this->extractNodeRefId($node);
            if ($refId <= 0 || isset($seen[$refId])) {
                continue;
            }
            $seen[$refId] = true;

            $objId = $this->extractNodeObjId($node);
            if ($objId <= 0) {
                $objId = $this->lookupObjectId($refId);
            }

            $objType = $this->extractNodeType($node);
            if ($objType === '') {
                $objType = $this->lookupTypeByRefId($refId);
            }

            $title = $this->extractNodeTitle($node);
            if ($title === '') {
                $title = $this->lookupTitle($objId);
            }

            if (isset(self::TRACKABLE_TYPES[$objType])) {
                $path = $this->buildPathLabel($courseRefId, $refId, $title);
                $rows[] = [
                    'course_ref_id' => $courseRefId,
                    'ref_id' => $refId,
                    'obj_id' => $objId,
                    'obj_type' => $objType,
                    'resource_family' => self::TRACKABLE_TYPES[$objType],
                    'title' => $title,
                    'path' => $path,
                    'sort_key' => strtolower($path !== '' ? $path : ($objType . ':' . $title . ':' . $refId)),
                ];
            }

            foreach ($this->collectTrackableChildren($courseRefId, $refId, $depth + 1, $seen) as $childRow) {
                $rows[] = $childRow;
            }
        }

        return $rows;
    }

    /** @return array<int,mixed> */
    private function getChildren(int $parentRefId): array
    {
        $tree = $this->getRepositoryTree();
        if (is_object($tree) && method_exists($tree, 'getChilds')) {
            try {
                $children = $tree->getChilds($parentRefId);
                if (is_array($children)) {
                    return $children;
                }
            } catch (Throwable $ignored) {
                // Try database fallback below.
            }
        }

        return $this->getChildrenFromDatabase($parentRefId);
    }

    /** @return array<int,array<string,int>> */
    private function getChildrenFromDatabase(int $parentRefId): array
    {
        if (!is_object($this->db) || !method_exists($this->db, 'tableExists') || !$this->db->tableExists('tree')) {
            return [];
        }

        try {
            $set = $this->db->query('SELECT child FROM tree WHERE parent = ' . $parentRefId . ' ORDER BY child ASC');
        } catch (Throwable $ignored) {
            return [];
        }

        $rows = [];
        while ($row = $this->db->fetchAssoc($set)) {
            if (is_array($row) && isset($row['child']) && (int) $row['child'] > 0) {
                $rows[] = ['ref_id' => (int) $row['child']];
            }
        }

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    private function getResourceConfigsByRefId(int $courseRefId): array
    {
        $repository = $this->getTrackingRepository();
        if (!$repository instanceof ilIliasTraxEventBridgeCourseTrackingRepository || !$repository->resourceTableExists()) {
            return [];
        }

        $configs = [];
        foreach ($repository->findResourceConfigs($courseRefId) as $config) {
            $refId = (int) ($config['ref_id'] ?? 0);
            if ($refId > 0) {
                $configs[$refId] = $config;
            }
        }

        return $configs;
    }

    /** @return array<string,mixed> */
    private function getCourseConfig(int $courseRefId): array
    {
        $repository = $this->getTrackingRepository();
        if (!$repository instanceof ilIliasTraxEventBridgeCourseTrackingRepository || !$repository->courseTableExists()) {
            return [];
        }

        return $repository->getCourseConfig($courseRefId);
    }

    private function getTrackingRepository()
    {
        if ($this->trackingRepository instanceof ilIliasTraxEventBridgeCourseTrackingRepository) {
            return $this->trackingRepository;
        }

        if (!class_exists('ilIliasTraxEventBridgeCourseTrackingRepository')) {
            return null;
        }

        try {
            $this->trackingRepository = new ilIliasTraxEventBridgeCourseTrackingRepository();
        } catch (Throwable $ignored) {
            $this->trackingRepository = null;
        }

        return $this->trackingRepository;
    }

    private function getRepositoryTree()
    {
        if (isset($GLOBALS['DIC'])) {
            try {
                if (is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'repositoryTree')) {
                    return $GLOBALS['DIC']->repositoryTree();
                }
                if ((is_array($GLOBALS['DIC']) || $GLOBALS['DIC'] instanceof ArrayAccess) && isset($GLOBALS['DIC']['tree'])) {
                    return $GLOBALS['DIC']['tree'];
                }
            } catch (Throwable $ignored) {
                // Fallback below.
            }
        }

        return isset($GLOBALS['tree']) && is_object($GLOBALS['tree']) ? $GLOBALS['tree'] : null;
    }

    private function resolveDatabase()
    {
        if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'database')) {
            try {
                return $GLOBALS['DIC']->database();
            } catch (Throwable $ignored) {
                // Fallback below.
            }
        }

        return isset($GLOBALS['ilDB']) ? $GLOBALS['ilDB'] : null;
    }

    private function extractNodeRefId($node): int
    {
        if (is_array($node)) {
            foreach (['ref_id', 'child', 'id'] as $key) {
                if (isset($node[$key]) && is_scalar($node[$key]) && (int) $node[$key] > 0) {
                    return (int) $node[$key];
                }
            }
        }

        if (is_object($node)) {
            foreach (['getRefId', 'getId'] as $method) {
                if (method_exists($node, $method)) {
                    try {
                        $value = $node->$method();
                        if (is_scalar($value) && (int) $value > 0) {
                            return (int) $value;
                        }
                    } catch (Throwable $ignored) {
                        // Try next accessor.
                    }
                }
            }
        }

        return 0;
    }

    private function extractNodeObjId($node): int
    {
        if (is_array($node)) {
            foreach (['obj_id', 'object_id'] as $key) {
                if (isset($node[$key]) && is_scalar($node[$key]) && (int) $node[$key] > 0) {
                    return (int) $node[$key];
                }
            }
        }

        if (is_object($node)) {
            foreach (['getObjId', 'getObjectId'] as $method) {
                if (method_exists($node, $method)) {
                    try {
                        $value = $node->$method();
                        if (is_scalar($value) && (int) $value > 0) {
                            return (int) $value;
                        }
                    } catch (Throwable $ignored) {
                        // Try next accessor.
                    }
                }
            }
        }

        return 0;
    }

    private function extractNodeType($node): string
    {
        if (is_array($node) && isset($node['type']) && is_scalar($node['type'])) {
            return (string) $node['type'];
        }

        if (is_object($node) && method_exists($node, 'getType')) {
            try {
                $type = $node->getType();
                return is_scalar($type) ? (string) $type : '';
            } catch (Throwable $ignored) {
                return '';
            }
        }

        return '';
    }

    private function extractNodeTitle($node): string
    {
        if (is_array($node)) {
            foreach (['title', 'name'] as $key) {
                if (isset($node[$key]) && is_scalar($node[$key])) {
                    return trim((string) $node[$key]);
                }
            }
        }

        if (is_object($node)) {
            foreach (['getTitle', 'getName'] as $method) {
                if (method_exists($node, $method)) {
                    try {
                        $value = $node->$method();
                        return is_scalar($value) ? trim((string) $value) : '';
                    } catch (Throwable $ignored) {
                        // Try next accessor.
                    }
                }
            }
        }

        return '';
    }

    private function lookupObjectId(int $refId): int
    {
        if ($refId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupObjectId')) {
            return 0;
        }

        try {
            return (int) ilObject::_lookupObjectId($refId);
        } catch (Throwable $ignored) {
            return 0;
        }
    }

    private function lookupTypeByRefId(int $refId): string
    {
        if ($refId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupType')) {
            return '';
        }

        try {
            $type = ilObject::_lookupType($refId, true);
            return is_scalar($type) ? (string) $type : '';
        } catch (Throwable $ignored) {
            // Try obj_id fallback below.
        }

        $objId = $this->lookupObjectId($refId);
        if ($objId <= 0) {
            return '';
        }

        try {
            $type = ilObject::_lookupType($objId);
            return is_scalar($type) ? (string) $type : '';
        } catch (Throwable $ignored) {
            return '';
        }
    }

    private function lookupTitle(int $objId): string
    {
        if ($objId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupTitle')) {
            return '';
        }

        try {
            $title = ilObject::_lookupTitle($objId);
            return is_scalar($title) ? trim((string) $title) : '';
        } catch (Throwable $ignored) {
            return '';
        }
    }

    private function buildPathLabel(int $courseRefId, int $resourceRefId, string $fallbackTitle): string
    {
        $tree = $this->getRepositoryTree();
        if (!is_object($tree) || !method_exists($tree, 'getPathFull')) {
            return $fallbackTitle;
        }

        try {
            $path = $tree->getPathFull($resourceRefId);
        } catch (Throwable $ignored) {
            return $fallbackTitle;
        }

        if (!is_array($path)) {
            return $fallbackTitle;
        }

        $parts = [];
        $insideCourse = false;
        foreach ($path as $node) {
            $nodeRefId = $this->extractNodeRefId($node);
            if ($nodeRefId === $courseRefId) {
                $insideCourse = true;
                continue;
            }

            if (!$insideCourse || $nodeRefId <= 0) {
                continue;
            }

            $title = $this->extractNodeTitle($node);
            if ($title === '') {
                $title = $this->lookupTitle($this->lookupObjectId($nodeRefId));
            }
            if ($title !== '') {
                $parts[] = $title;
            }
        }

        return $parts !== [] ? implode(' / ', $parts) : $fallbackTitle;
    }
}
