<?php

/**
 * V0.15 local history for course AI analyses.
 *
 * Storage is file based to avoid a risky SQL migration in this iteration.
 * Records contain only aggregate IA outputs and technical metadata. No nominal
 * learner identity must be stored here.
 */
class ilIliasTraxEventBridgeAiAnalysisHistory
{
    /** @var string */
    private $dir;

    public function __construct(string $mainPluginPath)
    {
        $base = rtrim($mainPluginPath, '/');
        $this->dir = $base . '/var/ai_analysis_history';
        $this->ensureDir();
    }

    /** @param array<string,mixed> $course @param array<string,mixed> $result @return array<string,mixed> */
    public function save(array $course, int $periodDays, array $result, int $userId = 0): array
    {
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        if ($courseRefId <= 0 || trim((string) ($result['analysis'] ?? '')) === '') {
            return [];
        }

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $id = gmdate('YmdHis') . '-' . substr(sha1($courseRefId . '|' . $periodDays . '|' . $now . '|' . (string) ($result['analysis'] ?? '')), 0, 10);
        $record = [
            'id' => $id,
            'created_at_utc' => $now,
            'course_ref_id' => $courseRefId,
            'course_obj_id' => (int) ($course['course_obj_id'] ?? 0),
            'course_title' => (string) ($course['course_title'] ?? ''),
            'period_days' => max(1, min(365, $periodDays)),
            'created_by_user_id' => max(0, $userId),
            'success' => !empty($result['success']),
            'http_status' => (int) ($result['http_status'] ?? 0),
            'message' => (string) ($result['message'] ?? ''),
            'payload_summary' => (string) ($result['payload_summary'] ?? ''),
            'analysis' => (string) ($result['analysis'] ?? ''),
        ];

        $file = $this->recordFile($courseRefId, $id);
        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (!is_string($json) || file_put_contents($file, $json) === false) {
            return [];
        }
        @chmod($file, 0640);
        $this->prune($courseRefId, 20);
        return $record;
    }

    /** @return array<string,mixed> */
    public function latest(int $courseRefId, int $periodDays = 0): array
    {
        $records = $this->list($courseRefId, 20);
        foreach ($records as $record) {
            if ($periodDays <= 0 || (int) ($record['period_days'] ?? 0) === $periodDays) {
                return $record;
            }
        }
        return [];
    }

    /** @return array<int,array<string,mixed>> */
    public function list(int $courseRefId, int $limit = 10): array
    {
        if ($courseRefId <= 0 || !is_dir($this->dir)) {
            return [];
        }
        $files = glob($this->dir . '/course_' . $courseRefId . '_*.json');
        if (!is_array($files)) {
            return [];
        }
        rsort($files, SORT_STRING);
        $records = [];
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $json = file_get_contents($file);
            $record = is_string($json) ? json_decode($json, true) : null;
            if (is_array($record)) {
                $records[] = $record;
            }
            if (count($records) >= $limit) {
                break;
            }
        }
        return $records;
    }

    public function archive(int $courseRefId, string $id): bool
    {
        if ($courseRefId <= 0 || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $id)) {
            return false;
        }
        $file = $this->recordFile($courseRefId, $id);
        if (!is_file($file)) {
            return false;
        }
        $archiveDir = dirname($this->dir) . '/ai_analysis_history_deleted';
        if (!is_dir($archiveDir)) {
            @mkdir($archiveDir, 0750, true);
        }
        if (!is_dir($archiveDir)) {
            return false;
        }
        @chmod($archiveDir, 0750);
        $target = $archiveDir . '/' . basename($file) . '.archived-' . gmdate('YmdHis');
        return @rename($file, $target);
    }
    private function ensureDir(): void
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0750, true);
        }
        if (is_dir($this->dir)) {
            @chmod($this->dir, 0750);
        }
    }

    private function recordFile(int $courseRefId, string $id): string
    {
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
        if (!is_string($safeId) || $safeId === '') {
            $safeId = gmdate('YmdHis') . '-' . substr(sha1($id), 0, 10);
        }
        return $this->dir . '/course_' . $courseRefId . '_' . $safeId . '.json';
    }

    private function prune(int $courseRefId, int $keep): void
    {
        $files = glob($this->dir . '/course_' . $courseRefId . '_*.json');
        if (!is_array($files)) {
            return;
        }
        rsort($files, SORT_STRING);
        foreach (array_slice($files, max(1, $keep)) as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
