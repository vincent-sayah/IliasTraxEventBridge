<?php

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_pdf_route.php target-uihook.php\n");
    exit(1);
}
$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Cannot read {$file}\n");
    exit(1);
}
if (strpos($code, "'exportCourseDashboardPdf'") !== false) {
    echo "PDF route patch already present in {$file}\n";
    exit(0);
}
$updated = str_replace(
    "            'exportCourseExpertCsv',\n",
    "            'exportCourseExpertCsv',\n            'exportCourseDashboardPdf',\n",
    $code
);
if ($updated === $code) {
    fwrite(STDERR, "Unable to patch PDF route in {$file}\n");
    exit(1);
}
if (file_put_contents($file, $updated) === false) {
    fwrite(STDERR, "Cannot write {$file}\n");
    exit(1);
}
echo "PDF route patch applied to {$file}\n";
