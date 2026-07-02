<?php

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_v012_layout.php <CourseUIScreen.php>\n");
    exit(1);
}

$s = file_get_contents($file);
if (!is_string($s) || $s === '') {
    fwrite(STDERR, "Unable to read target file.\n");
    exit(1);
}

if (strpos($s, 'itxeb-v012-layout') !== false) {
    echo "V0.12 layout patch already applied.\n";
    exit(0);
}

$s = str_replace(
    " . \$this->renderDashboardPdfButton(\$course) . \$this->renderAnalyticsWarning()",
    " . \$this->renderAnalyticsWarning()",
    $s
);

$start = strpos($s, '    private function renderShell(string $content, int $courseRefId, string $courseTitle, string $cmd): string');
$end = strpos($s, '    private function renderMessage(): string', $start);
if ($start === false || $end === false || $end <= $start) {
    fwrite(STDERR, "Unable to locate renderShell block.\n");
    exit(1);
}

$newShell = <<<'PHP'
    private function renderShell(string $content, int $courseRefId, string $courseTitle, string $cmd): string
    {
        $title = trim($courseTitle) !== '' ? 'Suivi xAPI — ' . $courseTitle : 'Suivi xAPI — configuration du cours';
        $normalizedCmd = $this->normalizeCommand($cmd);
        $subtitle = $normalizedCmd === 'showCourseTracking' ? 'Configuration xAPI depuis l’objet cours' : 'Feedback et analyse des traces xAPI du cours';
        $header = '<div class="itxeb-v012-header itxeb-v012-layout"><div class="itxeb-v012-header-title"><h1>' . $this->esc($title) . '</h1><p>' . $this->esc($subtitle) . ($courseRefId > 0 ? ' — course_ref_id ' . $this->esc((string) $courseRefId) : '') . '</p></div>';
        if ($normalizedCmd === 'showCourseDashboard' && $courseRefId > 0) {
            $pdfUrl = $this->currentUrlWith([
                'itxeb_cui_cmd' => 'exportCourseDashboardPdf',
                'itxeb_course_ref_id' => (string) $courseRefId,
                'itxeb_period_days' => (string) $this->getPeriodDays(),
                'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
            ]);
            $header .= '<div class="itxeb-v012-header-actions"><a class="btn btn-default itxeb-v012-pdf" href="' . $this->esc($pdfUrl) . '">Export PDF</a></div>';
        }
        $header .= '</div>';
        return $this->styles() . '<div id="itxeb-course-ui-screen">' . $header . $content . '</div>';
    }

PHP;

$s = substr($s, 0, $start) . $newShell . substr($s, $end);

$css = '#itxeb-course-ui-screen .itxeb-v012-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;border:2px solid #c8d6e5;background:#f8fbff;padding:12px 14px;margin:0 0 16px;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,.08)}#itxeb-course-ui-screen .itxeb-v012-header h1{font-size:28px;font-weight:700;margin:0 0 4px;line-height:1.2}#itxeb-course-ui-screen .itxeb-v012-header p{margin:0;color:#444}#itxeb-course-ui-screen .itxeb-v012-header-actions{white-space:nowrap;padding-top:3px}#itxeb-course-ui-screen .itxeb-v012-pdf{font-weight:700}#itxeb-course-ui-screen .itxeb-cui-section h2{font-size:24px;font-weight:700;border-bottom:2px solid #c8d6e5;padding-bottom:.4rem;margin-top:1.1rem}#itxeb-course-ui-screen .itxeb-cui-section h3{font-weight:700}#itxeb-course-ui-screen .itxeb-kpi-card,#itxeb-course-ui-screen .itxeb-pedagogy-summary{border:2px solid #c8d6e5;box-shadow:0 1px 4px rgba(0,0,0,.08)}#itxeb-course-ui-screen .itxeb-kpi-label{font-weight:700}#itxeb-course-ui-screen .itxeb-cui-table{border:2px solid #c8d6e5}#itxeb-course-ui-screen .itxeb-cui-table th{font-weight:700;border-bottom:2px solid #c8d6e5}#itxeb-course-ui-screen .itxeb-pedagogy-critical{border:2px solid #a94442;background:#f2dede;color:#8a1f11}#itxeb-course-ui-screen .itxeb-pedagogy-watch{border:2px solid #8a6d3b;background:#fcf8e3;color:#684f1d}';
$count = 0;
$s = str_replace('</style>', $css . '</style>', $s, $count);
if ($count < 1) {
    fwrite(STDERR, "Unable to inject V0.12 layout styles.\n");
    exit(1);
}

if (file_put_contents($file, $s) === false) {
    fwrite(STDERR, "Unable to write target file.\n");
    exit(1);
}

echo "V0.12 layout patch applied.\n";
