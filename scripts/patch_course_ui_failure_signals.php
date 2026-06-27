<?php

/**
 * Patch generated Course UI screen to highlight resources/tests with frequent failures.
 *
 * The patch is applied after the companion templates are copied to the ILIAS
 * UIHook plugin directory. It is idempotent.
 */

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_failure_signals.php /path/to/class.ilIliasTraxEventBridgeCourseUIScreen.php\n");
    exit(1);
}

$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Unable to read target file: {$file}\n");
    exit(1);
}

if (strpos($code, 'échecs fréquents') !== false && strpos($code, '$signalText =') !== false) {
    echo "Failure signal patch already present in {$file}\n";
    exit(0);
}

$old = <<<'PHP'
            $html .= '<tr><td><span class="itxeb-signal">' . $this->esc((string) ($stats['signal'] ?? '')) . '</span></td>'
PHP;

$new = <<<'PHP'
            $signalText = (string) ($stats['signal'] ?? '');
            if (($stats['obj_type'] ?? '') === 'tst' && $testAttempts >= 3) {
                $failureSignalRate = round(($testFailed / max(1, $testAttempts)) * 100, 1);
                if ($failureSignalRate >= 50.0) {
                    $signalText = 'échecs fréquents';
                } elseif ($failureSignalRate >= 30.0 && $signalText === '') {
                    $signalText = 'à surveiller';
                }
            }
            $html .= '<tr><td><span class="itxeb-signal">' . $this->esc($signalText) . '</span></td>'
PHP;

$updated = str_replace($old, $new, $code);
if ($updated === $code) {
    fwrite(STDERR, "Patch failed: unable to locate analysis signal line in {$file}\n");
    exit(1);
}

if (file_put_contents($file, $updated) === false) {
    fwrite(STDERR, "Unable to write target file: {$file}\n");
    exit(1);
}

echo "Failure signal patch applied to {$file}\n";
