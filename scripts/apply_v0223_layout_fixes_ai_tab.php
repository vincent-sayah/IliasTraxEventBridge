<?php
$root = getcwd();
$screen = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$plugin = $root . '/plugin.php';
$companionPlugin = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$servicesRoot = dirname(dirname(dirname($root)));
$liveRoot = $servicesRoot . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$liveScreen = $liveRoot . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = $liveRoot . '/plugin.php';

function v0223_read(string $file): string
{
    $s = file_get_contents($file);
    if (!is_string($s)) {
        fwrite(STDERR, "Lecture impossible: $file\n");
        exit(1);
    }
    return $s;
}

function v0223_write(string $file, string $old, string $new): void
{
    if ($old !== $new) {
        file_put_contents($file, $new);
        echo "WRITE: $file\n";
    } else {
        echo "OK: aucun changement $file\n";
    }
}

function v0223_set_version(string $file, string $version): void
{
    if (!is_file($file)) {
        return;
    }
    $old = v0223_read($file);
    $replacement = '$version = ' . "'" . $version . "'" . ';';
    $new = preg_replace('/\$version = \'[^\']*\';/', $replacement, $old, 1);
    if (!is_string($new)) {
        fwrite(STDERR, "Version impossible: $file\n");
        exit(1);
    }
    v0223_write($file, $old, $new);
}

function v0223_replace_method(string $s, string $methodName, string $newMethod): string
{
    $needle = '    private function ' . $methodName . '(';
    $start = strpos($s, $needle);
    if ($start === false) {
        fwrite(STDERR, "Méthode introuvable: $methodName\n");
        exit(1);
    }
    $brace = strpos($s, '{', $start);
    if ($brace === false) {
        fwrite(STDERR, "Accolade introuvable: $methodName\n");
        exit(1);
    }
    $level = 0;
    $len = strlen($s);
    for ($i = $brace; $i < $len; $i++) {
        $ch = $s[$i];
        if ($ch === '{') { $level++; }
        if ($ch === '}') {
            $level--;
            if ($level === 0) {
                $end = $i + 1;
                while ($end < $len && ($s[$end] === "\r" || $s[$end] === "\n")) {
                    $end++;
                }
                return substr($s, 0, $start) . rtrim($newMethod) . "\n\n" . substr($s, $end);
            }
        }
    }
    fwrite(STDERR, "Fin méthode introuvable: $methodName\n");
    exit(1);
}

function v0223_upsert_css(string $s): string
{
    $start = '/* V0.22.3 layout fixes */';
    $end = '/* END V0.22.3 layout fixes */';
    $p1 = strpos($s, $start);
    $p2 = strpos($s, $end);
    if ($p1 !== false && $p2 !== false && $p2 > $p1) {
        $s = substr($s, 0, $p1) . substr($s, $p2 + strlen($end));
    }

    $css = $start
        . '#itxeb-course-ui-screen .itxeb-pedagogy-summary{grid-column:1 / -1!important;display:block!important;border:1px solid #d9e2ec!important;background:#fff!important;border-radius:0!important;box-shadow:none!important;padding:12px 14px!important;margin:10px 0 16px!important}'
        . '#itxeb-course-ui-screen .itxeb-pedagogy-summary>h3{display:block!important;margin:0 0 10px!important;padding:0 0 6px!important;border:0!important;border-bottom:1px solid #e5e5e5!important;font-size:16px!important;color:#333!important}'
        . '#itxeb-course-ui-screen .itxeb-pedagogy-summary .itxeb-kpi-grid{margin:.2rem 0 .6rem!important}'
        . '#itxeb-course-ui-screen .itxeb-pedagogy-summary .itxeb-pedagogy-lines{margin:.6rem 0 0 1.2rem!important}'
        . '#itxeb-course-ui-screen .itxeb-ai-history{grid-column:1 / -1!important}'
        . '#itxeb-course-ui-screen .itxeb-ai-history .itxeb-cui-table-wrapper{margin-top:8px!important}'
        . '#itxeb-course-ui-screen .itxeb-ai-history-actions{display:flex!important;gap:6px!important;align-items:center!important;flex-wrap:wrap!important}'
        . '#itxeb-course-ui-screen .itxeb-ai-history-archive-form{display:inline!important;margin:0!important}'
        . $end;

    $needle = "</style>';";
    $pos = strrpos($s, $needle);
    if ($pos === false) {
        fwrite(STDERR, "Point insertion CSS introuvable\n");
        exit(1);
    }
    return substr($s, 0, $pos) . $css . substr($s, $pos);
}

foreach ([$screen, $plugin, $companionPlugin] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Fichier absent: $file\n");
        exit(1);
    }
}

$old = v0223_read($screen);
$s = $old;
$s = v0223_upsert_css($s);

$newNormalize = <<<'PHP'
    private function normalizeCommand(string $cmd): string
    {
        $aliases = [
            'exportCourseExpertCsv' => 'showCourseExpert',
            'exportCourseDashboardPdf' => 'showCourseDashboard',
            'archiveCourseAiHistory' => 'showCourseAiAnalysis',
            'generateCourseAiAnalysis' => 'showCourseAiAnalysis',
        ];
        if (isset($aliases[$cmd])) {
            return $aliases[$cmd];
        }
        return in_array($cmd, ['showCourseDashboard', 'showCourseAnalysis', 'showCourseAiAnalysis', 'showCourseExpert'], true)
            ? $cmd
            : 'showCourseTracking';
    }
PHP;
$s = v0223_replace_method($s, 'normalizeCommand', $newNormalize);

v0223_write($screen, $old, $s);
v0223_set_version($plugin, '0.22.3-dev');
v0223_set_version($companionPlugin, '0.8.9');

if (is_file($liveScreen)) {
    copy($screen, $liveScreen);
    echo "COPY: $screen -> $liveScreen\n";
}
if (is_file($livePlugin)) {
    copy($companionPlugin, $livePlugin);
    echo "COPY: $companionPlugin -> $livePlugin\n";
}

$lintFiles = [$screen, $plugin, $companionPlugin];
if (is_file($liveScreen)) { $lintFiles[] = $liveScreen; }
if (is_file($livePlugin)) { $lintFiles[] = $livePlugin; }
foreach ($lintFiles as $file) {
    $cmd = 'php -l ' . escapeshellarg($file);
    passthru($cmd, $code);
    if ($code !== 0) {
        fwrite(STDERR, "PHP lint KO: $file\n");
        exit(1);
    }
}

echo "V0.22.3 layout fixes and AI tab normalization applied.\n";
