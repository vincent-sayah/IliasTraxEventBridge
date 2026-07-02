<?php

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_expert_csv_pedagogy.php <CourseUIScreen.php>\n");
    exit(1);
}

$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Unable to read target file.\n");
    exit(1);
}

if (strpos($code, 'pedagogical_status') !== false && strpos($code, 'resource_failure_rate') !== false) {
    echo "V0.12 expert CSV pedagogy patch already applied.\n";
    exit(0);
}

$start = strpos($code, "    /** @param array<string,mixed> \$course */\n    private function sendExpertCsv(array \$course): void");
$end = strpos($code, "    /** @param array<string,mixed> \$course */\n    private function loadDashboard(array \$course): array", $start);
if ($start === false || $end === false || $end <= $start) {
    fwrite(STDERR, "Unable to locate sendExpertCsv block.\n");
    exit(1);
}

$newMethod = <<<'PHP'
    /** @param array<string,mixed> $course */
    private function sendExpertCsv(array $course): void
    {
        $dashboard = $this->loadDashboard($course);
        $rows = is_array($dashboard['expert_rows'] ?? null) ? $dashboard['expert_rows'] : [];
        $resources = is_array($dashboard['by_resource'] ?? null) ? $dashboard['by_resource'] : [];
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $filterRefId = $this->getSelectedResourceRefId();
        $filename = 'itxeb_course_' . $courseRefId . ($filterRefId > 0 ? '_ref_' . $filterRefId : '') . '_expert_' . date('Ymd_His') . '.csv';

        $resourceByRefId = [];
        $resourceByTitle = [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                continue;
            }
            $refId = (int) ($resource['ref_id'] ?? 0);
            if ($refId > 0) {
                $resourceByRefId[$refId] = $resource;
            }
            $title = trim((string) ($resource['title'] ?? ''));
            if ($title !== '') {
                $resourceByTitle[$title] = $resource;
            }
        }

        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        if ($out !== false) {
            fputcsv($out, [
                'date', 'course_ref_id', 'filter_ref_id', 'user_id',
                'verb_label', 'verb_id', 'resource_title', 'ref_id', 'obj_id', 'obj_type',
                'score_raw', 'completion', 'success', 'status', 'outbox_id', 'statement_uuid', 'last_error',
                'pedagogical_status', 'pedagogical_label', 'pedagogical_reason',
                'resource_failure_rate', 'resource_avg_score_raw', 'resource_traces', 'resource_learners_count',
                'resource_is_critical', 'resource_is_watch'
            ], ';');
            foreach ($rows as $row) {
                $refId = (int) ($row['ref_id'] ?? 0);
                $resourceTitle = (string) ($row['object_title'] ?? '');
                $resource = $refId > 0 && isset($resourceByRefId[$refId]) ? $resourceByRefId[$refId] : ($resourceByTitle[$resourceTitle] ?? []);
                $pedagogicalStatus = (string) ($resource['pedagogical_status'] ?? '');
                fputcsv($out, [
                    (string) ($row['created_at'] ?? ''),
                    (string) $courseRefId,
                    $filterRefId > 0 ? (string) $filterRefId : '',
                    (string) ($row['user_id'] ?? 0),
                    (string) ($row['verb_label'] ?? ''),
                    (string) ($row['verb_id'] ?? ''),
                    $resourceTitle,
                    (string) ($row['ref_id'] ?? 0),
                    (string) ($row['obj_id'] ?? 0),
                    (string) ($row['obj_type'] ?? ''),
                    $row['score_raw'] === null ? '' : (string) $row['score_raw'],
                    $this->nullableBoolLabel($row['completion'] ?? null),
                    $this->nullableBoolLabel($row['success'] ?? null),
                    (string) ($row['status'] ?? ''),
                    (string) ($row['outbox_id'] ?? 0),
                    (string) ($row['statement_uuid'] ?? ''),
                    (string) ($row['last_error'] ?? ''),
                    $pedagogicalStatus,
                    (string) ($resource['pedagogical_label'] ?? ''),
                    (string) ($resource['pedagogical_reason'] ?? ''),
                    is_numeric($resource['failure_rate'] ?? null) ? (string) $resource['failure_rate'] : '',
                    is_numeric($resource['avg_score_raw'] ?? null) ? (string) $resource['avg_score_raw'] : '',
                    (string) ($resource['traces'] ?? ''),
                    (string) ($resource['learners_count'] ?? ''),
                    $pedagogicalStatus === 'critical' ? 'oui' : 'non',
                    $pedagogicalStatus === 'watch' ? 'oui' : 'non',
                ], ';');
            }
            fclose($out);
        }
        exit;
    }

PHP;

$updated = substr($code, 0, $start) . $newMethod . substr($code, $end);
if (file_put_contents($file, $updated) === false) {
    fwrite(STDERR, "Unable to write target file.\n");
    exit(1);
}

echo "V0.12 expert CSV pedagogy patch applied.\n";
