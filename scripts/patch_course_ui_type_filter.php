<?php

/**
 * Patch generated Course UI screen to add an object type filter.
 *
 * The source companion screen is generated from templates during installation.
 * This patch is applied after the template copy step and is intentionally
 * idempotent.
 */

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_type_filter.php /path/to/class.ilIliasTraxEventBridgeCourseUIScreen.php\n");
    exit(1);
}

$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Unable to read target file: {$file}\n");
    exit(1);
}

if (strpos($code, 'getSelectedObjectType(') !== false) {
    echo "Object type filter already present in {$file}\n";
    exit(0);
}

function requireReplacement(string $before, string $after, string $label): string
{
    if ($before === $after) {
        fwrite(STDERR, "Patch failed: {$label}\n");
        exit(1);
    }
    return $after;
}

$code = requireReplacement(
    $code,
    str_replace(
        "            'itxeb_filter_ref_id' => (string) \$this->getSelectedResourceRefId(),\n        ]);",
        "            'itxeb_filter_ref_id' => (string) \$this->getSelectedResourceRefId(),\n            'itxeb_filter_obj_type' => \$this->getSelectedObjectType(),\n        ]);",
        $code
    ),
    'add object type to expert export URL'
);

$code = requireReplacement(
    $code,
    str_replace(
        "        \$filterRefId = \$this->getSelectedResourceRefId();\n        \$filename = 'itxeb_course_' . \$courseRefId . (\$filterRefId > 0 ? '_ref_' . \$filterRefId : '') . '_expert_' . date('Ymd_His') . '.csv';",
        "        \$filterRefId = \$this->getSelectedResourceRefId();\n        \$filterObjType = \$this->getSelectedObjectType();\n        \$safeObjType = \$filterObjType !== '' ? preg_replace('/[^a-zA-Z0-9_-]+/', '_', \$filterObjType) : '';\n        \$filename = 'itxeb_course_' . \$courseRefId . (\$filterRefId > 0 ? '_ref_' . \$filterRefId : '') . (\$safeObjType !== '' ? '_type_' . \$safeObjType : '') . '_expert_' . date('Ymd_His') . '.csv';",
        $code
    ),
    'add object type to CSV filename'
);

$code = requireReplacement(
    $code,
    str_replace(
        "            fputcsv(\$out, ['date', 'course_ref_id', 'filter_ref_id', 'user_id', 'verb_label', 'verb_id', 'resource_title', 'ref_id', 'obj_id', 'obj_type', 'score_raw', 'completion', 'success', 'status', 'outbox_id', 'statement_uuid', 'last_error'], ';');",
        "            fputcsv(\$out, ['date', 'course_ref_id', 'filter_ref_id', 'filter_obj_type', 'user_id', 'verb_label', 'verb_id', 'resource_title', 'ref_id', 'obj_id', 'obj_type', 'score_raw', 'completion', 'success', 'status', 'outbox_id', 'statement_uuid', 'last_error'], ';');",
        $code
    ),
    'add object type to CSV header'
);

$code = requireReplacement(
    $code,
    str_replace(
        "                    \$filterRefId > 0 ? (string) \$filterRefId : '',\n                    (string) (\$row['user_id'] ?? 0),",
        "                    \$filterRefId > 0 ? (string) \$filterRefId : '',\n                    \$filterObjType,\n                    (string) (\$row['user_id'] ?? 0),",
        $code
    ),
    'add object type to CSV rows'
);

$newFilterCourseResources = <<<'PHP'
private function filterCourseResources(array $course): array
    {
        $selectedRefId = $this->getSelectedResourceRefId();
        $selectedObjectType = $selectedRefId > 0 ? '' : $this->getSelectedObjectType();
        $resources = is_array($course['resources'] ?? null) ? $course['resources'] : [];

        if ($selectedRefId <= 0 && $selectedObjectType === '') {
            return $course;
        }

        $filtered = [];
        foreach ($resources as $resource) {
            if ($selectedRefId > 0 && (int) ($resource['ref_id'] ?? 0) !== $selectedRefId) {
                continue;
            }
            if ($selectedObjectType !== '' && (string) ($resource['obj_type'] ?? '') !== $selectedObjectType) {
                continue;
            }
            $filtered[] = $resource;
        }
        $course['resources'] = $filtered;
        return $course;
    }

    private function renderAnalyticsWarning
PHP;

$code = requireReplacement(
    $code,
    preg_replace(
        '~private function filterCourseResources\(array \$course\): array\n    \{.*?\n    \}\n\n    private function renderAnalyticsWarning~s',
        $newFilterCourseResources,
        $code,
        1
    ) ?? $code,
    'replace course resource filter method'
);

$newRenderFilter = <<<'PHP'
private function renderResourceFilter(array $course, string $cmd): string
    {
        $resources = is_array($course['resources'] ?? null) ? $course['resources'] : [];
        if (count($resources) === 0) {
            return '';
        }
        $selected = $this->getSelectedResourceRefId();
        $selectedObjectType = $selected > 0 ? '' : $this->getSelectedObjectType();
        $objectTypes = $this->availableObjectTypes($course);
        $typeDisabled = $selected > 0;
        $typeDisabledAttr = $typeDisabled ? ' disabled="disabled"' : '';
        $html = '<form class="itxeb-resource-filter" method="get" action="' . $this->esc($this->currentPath()) . '">'
            . $this->hiddenCurrentQuery(['itxeb_cui_cmd', 'itxeb_course_ref_id', 'itxeb_period_days', 'itxeb_filter_ref_id', 'itxeb_filter_obj_type'])
            . '<input type="hidden" name="itxeb_cui_cmd" value="' . $this->esc($cmd) . '">'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) ($course['course_ref_id'] ?? 0)) . '">'
            . '<input type="hidden" name="itxeb_period_days" value="' . $this->esc((string) $this->getPeriodDays()) . '">'
            . '<label><strong>Ressource :</strong> <select name="itxeb_filter_ref_id">'
            . '<option value="0"' . ($selected <= 0 ? ' selected="selected"' : '') . '>Toutes les ressources</option>';
        foreach ($resources as $resource) {
            $refId = (int) ($resource['ref_id'] ?? 0);
            if ($refId <= 0) {
                continue;
            }
            $label = trim((string) ($resource['title'] ?? ''));
            if ($label === '') {
                $label = 'ref_id ' . $refId;
            }
            $label .= ' — ' . (string) ($resource['obj_type'] ?? '') . ' — ref_id ' . $refId;
            $html .= '<option value="' . $this->esc((string) $refId) . '"' . ($selected === $refId ? ' selected="selected"' : '') . '>' . $this->esc($label) . '</option>';
        }
        $html .= '</select></label> <label class="itxeb-type-filter"><strong>Type :</strong> <select name="itxeb_filter_obj_type"' . $typeDisabledAttr . '>'
            . '<option value=""' . ($selectedObjectType === '' ? ' selected="selected"' : '') . '>Tous les types</option>';
        foreach ($objectTypes as $type => $label) {
            $html .= '<option value="' . $this->esc($type) . '"' . ($selectedObjectType === $type ? ' selected="selected"' : '') . '>' . $this->esc($label) . '</option>';
        }
        $html .= '</select></label>';
        if ($typeDisabled) {
            $html .= ' <small class="itxeb-filter-help">Type ignoré : une ressource précise est sélectionnée.</small>';
        }
        return $html . ' <button class="btn btn-default" type="submit">Filtrer</button></form>';
    }

    /** @param array<int,string> $excludedKeys */
PHP;

$code = requireReplacement(
    $code,
    preg_replace(
        '~private function renderResourceFilter\(array \$course, string \$cmd\): string\n    \{.*?\n    \}\n\n    /\*\* @param array<int,string> \$excludedKeys \*/~s',
        $newRenderFilter,
        $code,
        1
    ) ?? $code,
    'replace resource filter renderer'
);

$newMethods = <<<'PHP'
private function getSelectedObjectType(): string
    {
        if ($this->getSelectedResourceRefId() > 0) {
            return '';
        }
        $value = trim($this->requestValue($_GET, 'itxeb_filter_obj_type'));
        if ($value === '') {
            $value = trim($this->requestValue($_POST, 'itxeb_filter_obj_type'));
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $value)) {
            return '';
        }
        return $value;
    }

    /** @param array<string,mixed> $course @return array<string,string> */
    private function availableObjectTypes(array $course): array
    {
        $types = [];
        foreach ((array) ($course['resources'] ?? []) as $resource) {
            $type = (string) ($resource['obj_type'] ?? '');
            if ($type === '') {
                continue;
            }
            $label = $this->objectTypeLabel($type);
            $types[$type] = $label;
        }
        ksort($types);
        return $types;
    }

    private function objectTypeLabel(string $type): string
    {
        $labels = [
            'blog' => 'Blog',
            'file' => 'Fichier',
            'frm' => 'Forum',
            'htlm' => 'Page HTML',
            'lm' => 'Module d’apprentissage',
            'mcst' => 'MediaCast',
            'sahs' => 'SCORM / module',
            'tst' => 'Test',
            'webr' => 'Lien web',
            'wiki' => 'Wiki',
        ];
        return ($labels[$type] ?? strtoupper($type)) . ' (' . $type . ')';
    }

    private function normalizeCommand
PHP;

$code = requireReplacement(
    $code,
    preg_replace(
        '~private function normalizeCommand~',
        $newMethods,
        $code,
        1
    ) ?? $code,
    'insert object type helpers'
);

$code = requireReplacement(
    $code,
    str_replace(
        '#itxeb-course-ui-screen .itxeb-resource-filter select{max-width:560px}',
        '#itxeb-course-ui-screen .itxeb-resource-filter select{max-width:560px}#itxeb-course-ui-screen .itxeb-type-filter{display:inline-block;margin-left:.75rem}#itxeb-course-ui-screen .itxeb-filter-help{color:#666;margin-left:.35rem}',
        $code
    ),
    'add type filter style'
);

if (file_put_contents($file, $code) === false) {
    fwrite(STDERR, "Unable to write target file: {$file}\n");
    exit(1);
}

echo "Object type filter patch applied to {$file}\n";
