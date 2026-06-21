<?php

/**
 * Resolves whether an ILIAS repository object is effectively contained in a course.
 *
 * V0.5 keeps xAPI generation conservative: when no course parent can be confirmed,
 * the event remains in the raw debug log but no statement is added to the outbox.
 */
class ilIliasTraxEventBridgeCourseContextResolver
{
    /**
     * @param array<string,mixed> $record
     * @return array{is_in_course:bool,ref_id:int,course_ref_id:int,course_obj_id:int,reason:string}
     */
    public function resolve(array $record): array
    {
        $refId = (int) ($record['ref_id'] ?? 0);
        $objId = (int) ($record['obj_id'] ?? 0);

        // Creation/insertion events may carry the container ref_id instead of the
        // freshly created object's ref_id. Prefer object references when available,
        // then fall back to the event ref_id, which may itself be the course ref_id.
        $candidateRefIds = array_values(array_unique(array_filter(array_merge(
            $this->lookupRefIdsForObject($objId),
            $refId > 0 ? [$refId] : []
        ))));

        foreach ($candidateRefIds as $candidateRefId) {
            $courseRefId = $this->findCourseParentRefId((int) $candidateRefId);
            if ($courseRefId > 0) {
                return [
                    'is_in_course' => true,
                    'ref_id' => (int) $candidateRefId,
                    'course_ref_id' => $courseRefId,
                    'course_obj_id' => $this->lookupObjectId($courseRefId),
                    'reason' => $courseRefId === (int) $candidateRefId
                        ? 'matched course ref_id'
                        : 'matched course parent',
                ];
            }
        }

        return [
            'is_in_course' => false,
            'ref_id' => $refId,
            'course_ref_id' => 0,
            'course_obj_id' => 0,
            'reason' => $refId > 0 ? 'no course parent found' : 'no repository ref_id found',
        ];
    }

    /** @return array<int,int> */
    private function lookupRefIdsForObject(int $objId): array
    {
        if ($objId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_getAllReferences')) {
            return [];
        }

        try {
            $references = ilObject::_getAllReferences($objId);
        } catch (Throwable $ignored) {
            return [];
        }

        if (!is_array($references)) {
            return [];
        }

        $refIds = [];
        foreach ($references as $key => $value) {
            if (is_scalar($value) && (int) $value > 0) {
                $refIds[] = (int) $value;
            } elseif (is_scalar($key) && (int) $key > 0) {
                $refIds[] = (int) $key;
            }
        }

        return array_values(array_unique($refIds));
    }

    private function findCourseParentRefId(int $refId): int
    {
        if ($refId <= 0) {
            return 0;
        }

        // Some lifecycle events, especially object creation, pass the current
        // container ref_id. If this container is the course itself, accept it.
        if ($this->lookupTypeByRefId($refId) === 'crs') {
            return $refId;
        }

        $tree = $this->getRepositoryTree();
        if (!is_object($tree)) {
            return 0;
        }

        $fromPath = $this->findCourseInPath($tree, $refId);
        if ($fromPath > 0) {
            return $fromPath;
        }

        $fromParentWalk = $this->findCourseByWalkingParents($tree, $refId);
        if ($fromParentWalk > 0) {
            return $fromParentWalk;
        }

        return 0;
    }

    private function findCourseInPath(object $tree, int $refId): int
    {
        if (!method_exists($tree, 'getPathFull')) {
            return 0;
        }

        try {
            $path = $tree->getPathFull($refId);
        } catch (Throwable $ignored) {
            return 0;
        }

        if (!is_array($path)) {
            return 0;
        }

        $courseRefId = 0;
        foreach ($path as $node) {
            $nodeRefId = $this->extractNodeRefId($node);
            $type = $this->extractNodeType($node);
            if ($type === 'crs' && $nodeRefId > 0 && $nodeRefId !== $refId) {
                $courseRefId = $nodeRefId;
            }
        }

        return $courseRefId;
    }

    private function findCourseByWalkingParents(object $tree, int $refId): int
    {
        if (!method_exists($tree, 'getParentId')) {
            return 0;
        }

        $currentRefId = $refId;
        for ($i = 0; $i < 50; $i++) {
            try {
                $parentRefId = (int) $tree->getParentId($currentRefId);
            } catch (Throwable $ignored) {
                return 0;
            }

            if ($parentRefId <= 0 || $parentRefId === $currentRefId) {
                return 0;
            }

            if ($this->lookupTypeByRefId($parentRefId) === 'crs') {
                return $parentRefId;
            }

            $currentRefId = $parentRefId;
        }

        return 0;
    }

    private function getRepositoryTree()
    {
        if (isset($GLOBALS['DIC'])) {
            try {
                if (is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'repositoryTree')) {
                    return $GLOBALS['DIC']->repositoryTree();
                }
                if (is_array($GLOBALS['DIC']) || $GLOBALS['DIC'] instanceof ArrayAccess) {
                    if (isset($GLOBALS['DIC']['tree'])) {
                        return $GLOBALS['DIC']['tree'];
                    }
                }
            } catch (Throwable $ignored) {
                // Fallback below.
            }
        }

        return isset($GLOBALS['tree']) && is_object($GLOBALS['tree']) ? $GLOBALS['tree'] : null;
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
                        // Try the next accessor.
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

    private function lookupTypeByRefId(int $refId): string
    {
        if ($refId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupType')) {
            return '';
        }

        try {
            $type = ilObject::_lookupType($refId, true);
            return is_scalar($type) ? (string) $type : '';
        } catch (Throwable $ignored) {
            // Try the obj_id based fallback below.
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
}
