<?php

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_pdf_wkhtmltopdf_paths.php target.php\n");
    exit(1);
}
$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Cannot read {$file}\n");
    exit(1);
}
if (strpos($code, "'/opt/wkhtmltopdf/bin/wkhtmltopdf'") !== false) {
    echo "wkhtmltopdf opt path already present in {$file}\n";
    exit(0);
}
$updated = str_replace(
    "foreach (['/usr/local/bin/wkhtmltopdf', '/usr/bin/wkhtmltopdf', '/bin/wkhtmltopdf'] as \$candidate)",
    "foreach (['/usr/local/bin/wkhtmltopdf', '/usr/bin/wkhtmltopdf', '/bin/wkhtmltopdf', '/opt/wkhtmltopdf/bin/wkhtmltopdf'] as \$candidate)",
    $code
);
if ($updated === $code) {
    fwrite(STDERR, "Unable to add wkhtmltopdf opt path in {$file}\n");
    exit(1);
}
if (file_put_contents($file, $updated) === false) {
    fwrite(STDERR, "Cannot write {$file}\n");
    exit(1);
}
echo "wkhtmltopdf opt path patch applied to {$file}\n";
