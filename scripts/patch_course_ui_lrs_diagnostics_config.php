<?php

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_lrs_diagnostics_config.php target.php\n");
    exit(1);
}
$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Cannot read {$file}\n");
    exit(1);
}
$updated = $code;

$dashboardCall = "        \$html .= \$this->renderLrsDirectSummary(\$course);\n        return \$html . '</section>';";
$dashboardReturn = "        return \$html . '</section>';";
$updated = str_replace($dashboardCall, $dashboardReturn, $updated);

$configOld = "\$this->renderOutboxTechnicalSupervision(\$course) . \$this->renderBulkActions";
$configNew = "\$this->renderOutboxTechnicalSupervision(\$course) . \$this->renderLrsDirectSummary(\$course) . \$this->renderBulkActions";
if (strpos($updated, $configNew) === false) {
    $updated2 = str_replace($configOld, $configNew, $updated);
    if ($updated2 === $updated) {
        fwrite(STDERR, "Configuration insertion point not found in {$file}\n");
        exit(1);
    }
    $updated = $updated2;
}

$updated = str_replace(
    '<p>Lecture directe de TRAX/LRS. Ce bloc ne compare plus avec l\'outbox locale, car cette table peut être purgée en exploitation.</p>',
    '<p>Diagnostic de lecture directe TRAX/LRS. Ce bloc vérifie que les statements du cours sont lisibles depuis TRAX.</p>',
    $updated
);

if (strpos($updated, 'renderLrsDirectSummary($course) . $this->renderBulkActions') === false) {
    fwrite(STDERR, "LRS diagnostics block was not moved to Configuration in {$file}\n");
    exit(1);
}

if (file_put_contents($file, $updated) === false) {
    fwrite(STDERR, "Cannot write {$file}\n");
    exit(1);
}
echo "LRS diagnostics moved to Configuration in {$file}\n";
