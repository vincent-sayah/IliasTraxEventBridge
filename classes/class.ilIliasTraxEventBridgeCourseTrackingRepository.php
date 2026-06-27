<?php

/**
 * V0.7 course/resource xAPI tracking configuration repository.
 *
 * This repository stores the explicit course/resource decisions and the V0.9
 * course dashboard display preferences.
 */
class ilIliasTraxEventBridgeCourseTrackingRepository
{
    public const COURSE_TABLE = 'evnt_evhk_itxeb_ccfg';
    public const RESOURCE_TABLE = 'evnt_evhk_itxeb_rcfg';

    /** @var ilDBInterface|mixed */
    private $db;

    public function __construct()
    {
        if (isset($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'database')) {
            $this->db = $GLOBALS['DIC']->database();
        } elseif (isset($GLOBALS['ilDB'])) {
            $this->db = $GLOBALS['ilDB'];
        } else {
            throw new RuntimeException('ILIAS database object not available.');
        }
    }

    public function courseTableExists(): bool
    {
        return method_exists($this->db, 'tableExists') && $this->db->tableExists(self::COURSE_TABLE);
    }

    public function resourceTableExists(): bool
    {
        return method_exists($this->db, 'tableExists') && $this->db->tableExists(self::RESOURCE_TABLE);
    }

    public function tablesExist(): bool
    {
        return $this->courseTableExists() && $this->resourceTableExists();
    }

    public function dashboardPreferencesAvailable(): bool
    {
        return $this->courseTableExists()
            && method_exists($this->db, 'tableColumnExists')
            && $this->db->tableColumnExists(self::COURSE_TABLE, 'dashboard_widgets_json');
    }

    /** @return array<string,mixed> */
    public function getCourseConfig(int $courseRefId): array
    {
        if ($courseRefId <= 0 || !$this->courseTableExists()) {
            return [];
        }

        $columns = 'course_ref_id, course_obj_id, enabled, created_at, updated_at, updated_by';
        if ($this->dashboardPreferencesAvailable()) {
            $columns .= ', dashboard_widgets_json, dashboard_updated_at, dashboard_updated_by';
        }

        $set = $this->db->query(
            'SELECT ' . $columns
            . ' FROM ' . self::COURSE_TABLE
            . ' WHERE course_ref_id = ' . $courseRefId
        );
        $row = $this->db->fetchAssoc($set);
        return is_array($row) ? $row : [];
    }

    public function isCourseConfigured(int $courseRefId): bool
    {
        return $this->getCourseConfig($courseRefId) !== [];
    }

    public function isCourseEnabled(int $courseRefId): bool
    {
        $row = $this->getCourseConfig($courseRefId);
        return $row !== [] && (int) ($row['enabled'] ?? 0) === 1;
    }

    public function setCourseEnabled(int $courseRefId, int $courseObjId, bool $enabled, int $updatedBy = 0): void
    {
        if ($courseRefId <= 0 || !$this->courseTableExists()) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $enabledInt = $enabled ? 1 : 0;
        $courseObjId = max(0, $courseObjId);
        $updatedBy = max(0, $updatedBy);

        if ($this->isCourseConfigured($courseRefId)) {
            $this->db->manipulate(
                'UPDATE ' . self::COURSE_TABLE
                . ' SET course_obj_id = ' . $courseObjId
                . ', enabled = ' . $enabledInt
                . ', updated_at = ' . $this->db->quote($now, 'text')
                . ', updated_by = ' . $updatedBy
                . ' WHERE course_ref_id = ' . $courseRefId
            );
            return;
        }

        $this->db->insert(self::COURSE_TABLE, [
            'course_ref_id' => ['integer', $courseRefId],
            'course_obj_id' => ['integer', $courseObjId],
            'enabled' => ['integer', $enabledInt],
            'created_at' => ['text', $now],
            'updated_at' => ['text', $now],
            'updated_by' => ['integer', $updatedBy],
        ]);
    }

    /** @return array<string,bool> */
    public function getDashboardWidgets(int $courseRefId): array
    {
        if ($courseRefId <= 0 || !$this->dashboardPreferencesAvailable()) {
            return [];
        }
        $config = $this->getCourseConfig($courseRefId);
        $json = (string) ($config['dashboard_widgets_json'] ?? '');
        if (trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $result = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $result[$key] = (bool) $value;
            }
        }
        return $result;
    }

    /** @param array<string,bool> $widgets */
    public function setDashboardWidgets(int $courseRefId, int $courseObjId, array $widgets, int $updatedBy = 0): void
    {
        if ($courseRefId <= 0 || !$this->courseTableExists()) {
            return;
        }

        if (!$this->isCourseConfigured($courseRefId)) {
            $this->setCourseEnabled($courseRefId, $courseObjId, false, $updatedBy);
        }

        if (!$this->dashboardPreferencesAvailable()) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $json = json_encode($widgets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            $json = '{}';
        }

        $this->db->manipulate(
            'UPDATE ' . self::COURSE_TABLE
            . ' SET dashboard_widgets_json = ' . $this->db->quote($json, 'text')
            . ', dashboard_updated_at = ' . $this->db->quote($now, 'text')
            . ', dashboard_updated_by = ' . max(0, $updatedBy)
            . ' WHERE course_ref_id = ' . $courseRefId
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function findResourceConfigs(int $courseRefId): array
    {
        $rows = [];
        if ($courseRefId <= 0 || !$this->resourceTableExists()) {
            return $rows;
        }

        $set = $this->db->query(
            'SELECT course_ref_id, ref_id, obj_id, obj_type, enabled, created_at, updated_at, updated_by '
            . 'FROM ' . self::RESOURCE_TABLE
            . ' WHERE course_ref_id = ' . $courseRefId
            . ' ORDER BY obj_type ASC, ref_id ASC'
        );
        while ($row = $this->db->fetchAssoc($set)) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /** @return array<string,mixed> */
    public function getResourceConfig(int $courseRefId, int $refId): array
    {
        if ($courseRefId <= 0 || $refId <= 0 || !$this->resourceTableExists()) {
            return [];
        }

        $set = $this->db->query(
            'SELECT course_ref_id, ref_id, obj_id, obj_type, enabled, created_at, updated_at, updated_by '
            . 'FROM ' . self::RESOURCE_TABLE
            . ' WHERE course_ref_id = ' . $courseRefId
            . ' AND ref_id = ' . $refId
        );
        $row = $this->db->fetchAssoc($set);
        return is_array($row) ? $row : [];
    }

    public function isResourceConfigured(int $courseRefId, int $refId): bool
    {
        return $this->getResourceConfig($courseRefId, $refId) !== [];
    }

    public function isResourceEnabled(int $courseRefId, int $refId): bool
    {
        $row = $this->getResourceConfig($courseRefId, $refId);
        return $row !== [] && (int) ($row['enabled'] ?? 0) === 1;
    }

    public function setResourceEnabled(int $courseRefId, int $refId, int $objId, string $objType, bool $enabled, int $updatedBy = 0): void
    {
        if ($courseRefId <= 0 || $refId <= 0 || !$this->resourceTableExists()) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $enabledInt = $enabled ? 1 : 0;
        $objId = max(0, $objId);
        $updatedBy = max(0, $updatedBy);
        $objType = substr(trim($objType), 0, 64);

        if ($this->isResourceConfigured($courseRefId, $refId)) {
            $this->db->manipulate(
                'UPDATE ' . self::RESOURCE_TABLE
                . ' SET obj_id = ' . $objId
                . ', obj_type = ' . $this->db->quote($objType, 'text')
                . ', enabled = ' . $enabledInt
                . ', updated_at = ' . $this->db->quote($now, 'text')
                . ', updated_by = ' . $updatedBy
                . ' WHERE course_ref_id = ' . $courseRefId
                . ' AND ref_id = ' . $refId
            );
            return;
        }

        $this->db->insert(self::RESOURCE_TABLE, [
            'course_ref_id' => ['integer', $courseRefId],
            'ref_id' => ['integer', $refId],
            'obj_id' => ['integer', $objId],
            'obj_type' => ['text', $objType],
            'enabled' => ['integer', $enabledInt],
            'created_at' => ['text', $now],
            'updated_at' => ['text', $now],
            'updated_by' => ['integer', $updatedBy],
        ]);
    }

    /**
     * @param array<int,array<string,mixed>> $resources
     */
    public function setResourcesEnabled(int $courseRefId, array $resources, int $updatedBy = 0): void
    {
        foreach ($resources as $resource) {
            $this->setResourceEnabled(
                $courseRefId,
                (int) ($resource['ref_id'] ?? 0),
                (int) ($resource['obj_id'] ?? 0),
                (string) ($resource['obj_type'] ?? ''),
                (bool) ($resource['enabled'] ?? false),
                $updatedBy
            );
        }
    }

    /** @return array<int,int> */
    public function findEnabledResourceRefIds(int $courseRefId): array
    {
        $ids = [];
        if ($courseRefId <= 0 || !$this->resourceTableExists()) {
            return $ids;
        }

        $set = $this->db->query(
            'SELECT ref_id FROM ' . self::RESOURCE_TABLE
            . ' WHERE course_ref_id = ' . $courseRefId
            . ' AND enabled = 1'
        );
        while ($row = $this->db->fetchAssoc($set)) {
            if (is_array($row)) {
                $ids[] = (int) ($row['ref_id'] ?? 0);
            }
        }
        return $ids;
    }

    public function deleteCourseConfig(int $courseRefId): void
    {
        if ($courseRefId <= 0) {
            return;
        }
        if ($this->resourceTableExists()) {
            $this->db->manipulate('DELETE FROM ' . self::RESOURCE_TABLE . ' WHERE course_ref_id = ' . $courseRefId);
        }
        if ($this->courseTableExists()) {
            $this->db->manipulate('DELETE FROM ' . self::COURSE_TABLE . ' WHERE course_ref_id = ' . $courseRefId);
        }
    }
}
