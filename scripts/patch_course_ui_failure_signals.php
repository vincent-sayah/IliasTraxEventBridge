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

if (strpos($code, 'itxeb-signal-danger') !== false && strpos($code, '$signalClass =') !== false) {
    echo "Failure signal color patch already present in {$file}\n";
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
            $signalClass = 'itxeb-signal';
            if ($signalText === 'échecs fréquents') {
                $signalClass .= ' itxeb-signal-danger';
            } elseif ($signalText === 'à surveiller') {
                $signalClass .= ' itxeb-signal-warning';
            }
            $html .= '<tr><td><span class="' . $this->esc($signalClass) . '">' . $this->esc($signalText) . '</span></td>'
PHP;

$updated = str_replace($old, $new, $code);
if ($updated === $code) {
    fwrite(STDERR, "Patch failed: unable to locate analysis signal line in {$file}\n");
    exit(1);
}

$styleOld = '#itxeb-course-ui-screen .itxeb-signal{display:inline-block;padding:.15rem .35rem;border:1px solid #ddd;border-radius:4px;background:#f7f7f7}';
$styleNew = '#itxeb-course-ui-screen .itxeb-signal{display:inline-block;padding:.15rem .35rem;border:1px solid #ddd;border-radius:4px;background:#f7f7f7}#itxeb-course-ui-screen .itxeb-signal-warning{border-color:#f0ad4e;background:#fcf8e3;color:#8a6d3b;font-weight:bold}#itxeb-course-ui-screen .itxeb-signal-danger{border-color:#d9534f;background:#f2dede;color:#a94442;font-weight:bold}';
$updatedWithStyle = str_replace($styleOld, $styleNew, $updated);
if ($updatedWithStyle === $updated) {
    fwrite(STDERR, "Patch failed: unable to locate signal style in {$file}\n");
    exit(1);
}

if (file_put_contents($file, $updatedWithStyle) === false) {
    fwrite(STDERR, "Unable to write target file: {$file}\n");
    exit(1);
}

echo "Failure signal color patch applied to {$file}\n";
