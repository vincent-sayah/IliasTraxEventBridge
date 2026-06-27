<?php

/**
 * Patch generated Course UI screen to display success/failure rates in Analysis.
 *
 * The patch is applied after the companion templates are copied to the ILIAS
 * UIHook plugin directory. It is idempotent.
 */

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_success_rates.php /path/to/class.ilIliasTraxEventBridgeCourseUIScreen.php\n");
    exit(1);
}

$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Unable to read target file: {$file}\n");
    exit(1);
}

if (strpos($code, 'Réussite ') !== false && strpos($code, 'Échec ') !== false) {
    echo "Success/failure rates already present in {$file}\n";
    exit(0);
}

$old = <<<'PHP'
            $testText = (int) ($stats['test_attempts'] ?? 0) > 0 ? (string) ($stats['test_passed'] ?? 0) . ' réussis / ' . (string) ($stats['test_failed'] ?? 0) . ' échoués' : '-';
            $score = $stats['avg_score_raw'] === null ? '-' : (string) $stats['avg_score_raw'] . ' %';
PHP;

$new = <<<'PHP'
            $testAttempts = max(0, (int) ($stats['test_attempts'] ?? 0));
            $testPassed = max(0, (int) ($stats['test_passed'] ?? 0));
            $testFailed = max(0, (int) ($stats['test_failed'] ?? 0));
            if ($testAttempts > 0) {
                $successRate = round(($testPassed / max(1, $testAttempts)) * 100, 1);
                $failureRate = round(($testFailed / max(1, $testAttempts)) * 100, 1);
                $testText = (string) $testPassed . ' réussis / ' . (string) $testFailed . ' échoués'
                    . ' — Réussite ' . (string) $successRate . ' % / Échec ' . (string) $failureRate . ' %';
            } else {
                $testText = '-';
            }
            $score = $stats['avg_score_raw'] === null ? '-' : (string) $stats['avg_score_raw'] . ' %';
PHP;

$updated = str_replace($old, $new, $code);
if ($updated === $code) {
    fwrite(STDERR, "Patch failed: unable to locate test summary line in {$file}\n");
    exit(1);
}

if (file_put_contents($file, $updated) === false) {
    fwrite(STDERR, "Unable to write target file: {$file}\n");
    exit(1);
}

echo "Success/failure rates patch applied to {$file}\n";
