<?php
$root = getcwd();
$screen = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$plugin = $root . '/plugin.php';
$companionPlugin = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$servicesRoot = dirname(dirname(dirname($root)));
$liveRoot = $servicesRoot . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$liveScreen = $liveRoot . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = $liveRoot . '/plugin.php';

function v0224_read(string $file): string
{
    $s = file_get_contents($file);
    if (!is_string($s)) {
        fwrite(STDERR, "Lecture impossible: $file\n");
        exit(1);
    }
    return $s;
}

function v0224_write(string $file, string $old, string $new): void
{
    if ($old !== $new) {
        file_put_contents($file, $new);
        echo "WRITE: $file\n";
    } else {
        echo "OK: aucun changement $file\n";
    }
}

function v0224_set_version(string $file, string $version): void
{
    if (!is_file($file)) {
        return;
    }
    $old = v0224_read($file);
    $replacement = '$version = ' . "'" . $version . "'" . ';';
    $new = preg_replace('/\$version = \'[^\']*\';/', $replacement, $old, 1);
    if (!is_string($new)) {
        fwrite(STDERR, "Version impossible: $file\n");
        exit(1);
    }
    v0224_write($file, $old, $new);
}

function v0224_remove_css_block(string $s, string $start, string $end): string
{
    $p1 = strpos($s, $start);
    $p2 = strpos($s, $end);
    if ($p1 !== false && $p2 !== false && $p2 > $p1) {
        return substr($s, 0, $p1) . substr($s, $p2 + strlen($end));
    }
    return $s;
}

function v0224_replace_first(string $s, string $old, string $new, string $label, bool $required = false): string
{
    if (strpos($s, $new) !== false) {
        echo "OK: $label\n";
        return $s;
    }
    $pos = strpos($s, $old);
    if ($pos === false) {
        $msg = "SKIP: bloc introuvable $label\n";
        if ($required) {
            fwrite(STDERR, $msg);
            exit(1);
        }
        echo $msg;
        return $s;
    }
    echo "PATCH: $label\n";
    return substr($s, 0, $pos) . $new . substr($s, $pos + strlen($old));
}

foreach ([$screen, $plugin, $companionPlugin] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Fichier absent: $file\n");
        exit(1);
    }
}

$old = v0224_read($screen);
$s = $old;

// Remove previous experimental CSS blocks, then apply one consolidated layout block.
$s = v0224_remove_css_block($s, '/* V0.22.2 ILIAS-like full layout */', '/* END V0.22.2 ILIAS-like full layout */');
$s = v0224_remove_css_block($s, '/* V0.22.3 layout fixes */', '/* END V0.22.3 layout fixes */');
$s = v0224_remove_css_block($s, '/* V0.22.4 alignment and AI tab fixes */', '/* END V0.22.4 alignment and AI tab fixes */');

$css = '/* V0.22.4 alignment and AI tab fixes */'
    . '#itxeb-course-ui-screen .itxeb-cui-section{display:grid;grid-template-columns:260px minmax(0,1fr);column-gap:24px;row-gap:8px;border-top:1px solid #d9d9d9;padding:14px 0;margin:0;background:#fff}'
    . '#itxeb-course-ui-screen .itxeb-cui-section>h2,#itxeb-course-ui-screen .itxeb-cui-section>h3,#itxeb-course-ui-screen .itxeb-cui-section>h4{grid-column:1;margin:0!important;padding:5px 0 0!important;border:0!important;font-size:16px!important;line-height:1.35;color:#333}'
    . '#itxeb-course-ui-screen .itxeb-cui-section>:not(h2):not(h3):not(h4){grid-column:2;min-width:0;margin-top:0}'
    . '#itxeb-course-ui-screen .itxeb-cui-section>p{color:#555}'
    . '#itxeb-course-ui-screen .itxeb-cui-section .itxeb-cui-section{grid-column:1 / -1}'
    . '#itxeb-course-ui-screen .itxeb-period-selector,#itxeb-course-ui-screen .itxeb-resource-filter{border:0;background:transparent;padding:0;margin:0 0 8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center}'
    . '#itxeb-course-ui-screen .itxeb-period-selector strong,#itxeb-course-ui-screen .itxeb-resource-filter strong{min-width:96px;color:#333}'
    . '#itxeb-course-ui-screen .itxeb-kpi-grid{margin:.2rem 0 1rem}'
    . '#itxeb-course-ui-screen .itxeb-cui-table-wrapper{margin:.2rem 0 1rem}'
    . '#itxeb-course-ui-screen .itxeb-pedagogy-summary{grid-column:1 / -1!important;display:grid!important;grid-template-columns:260px minmax(0,1fr)!important;column-gap:24px!important;row-gap:8px!important;border-top:1px solid #d9d9d9!important;border-left:0!important;border-right:0!important;border-bottom:0!important;box-shadow:none!important;border-radius:0!important;padding:14px 0!important;margin:0!important;background:#fff!important}'
    . '#itxeb-course-ui-screen .itxeb-pedagogy-summary>h3{grid-column:1!important;margin:0!important;padding:5px 0 0!important;border:0!important;font-size:16px!important;line-height:1.35!important;color:#333!important}'
    . '#itxeb-course-ui-screen .itxeb-pedagogy-summary>.itxeb-pedagogy-kpis{grid-column:2!important;margin:0!important;min-width:0}'
    . '#itxeb-course-ui-screen .itxeb-pedagogy-summary>.itxeb-pedagogy-lines{grid-column:2!important;margin:10px 0 0 22px!important;min-width:0}'
    . '#itxeb-course-ui-screen .itxeb-trainer-summary{grid-column:2;margin-top:0}'
    . '#itxeb-course-ui-screen .itxeb-ai-markdown,#itxeb-course-ui-screen .itxeb-ai-history,#itxeb-course-ui-screen .itxeb-ai-compare{min-width:0}'
    . '#itxeb-course-ui-screen .itxeb-cui-section form{min-width:0}'
    . '#itxeb-course-ui-screen .itxeb-widget-grid{margin-top:0}'
    . '@media(max-width:900px){#itxeb-course-ui-screen .itxeb-cui-section,#itxeb-course-ui-screen .itxeb-pedagogy-summary{grid-template-columns:1fr!important;column-gap:0!important}#itxeb-course-ui-screen .itxeb-cui-section>h2,#itxeb-course-ui-screen .itxeb-cui-section>h3,#itxeb-course-ui-screen .itxeb-cui-section>h4,#itxeb-course-ui-screen .itxeb-cui-section>:not(h2):not(h3):not(h4),#itxeb-course-ui-screen .itxeb-pedagogy-summary>h3,#itxeb-course-ui-screen .itxeb-pedagogy-summary>.itxeb-pedagogy-kpis,#itxeb-course-ui-screen .itxeb-pedagogy-summary>.itxeb-pedagogy-lines{grid-column:1!important}}'
    . '/* END V0.22.4 alignment and AI tab fixes */';

$needle = "</style>';";
$pos = strrpos($s, $needle);
if ($pos === false) {
    fwrite(STDERR, "Point insertion CSS introuvable\n");
    exit(1);
}
$s = substr($s, 0, $pos) . $css . substr($s, $pos);
echo "PATCH: CSS V0.22.4\n";

$oldNormalize = <<<'PHP'
    private function normalizeCommand(string $cmd): string
    {
        return in_array($cmd, ['showCourseDashboard', 'showCourseAnalysis', 'showCourseAiAnalysis', 'showCourseExpert', 'exportCourseExpertCsv', 'exportCourseDashboardPdf'], true)
            ? ($cmd === 'exportCourseExpertCsv' ? 'showCourseExpert' : ($cmd === 'exportCourseDashboardPdf' ? 'showCourseDashboard' : $cmd))
            : 'showCourseTracking';
    }
PHP;
$newNormalize = <<<'PHP'
    private function normalizeCommand(string $cmd): string
    {
        $aliases = [
            'exportCourseExpertCsv' => 'showCourseExpert',
            'exportCourseDashboardPdf' => 'showCourseDashboard',
            'generateCourseAiAnalysis' => 'showCourseAiAnalysis',
            'archiveCourseAiHistory' => 'showCourseAiAnalysis',
        ];
        if (isset($aliases[$cmd])) {
            return $aliases[$cmd];
        }
        return in_array($cmd, ['showCourseDashboard', 'showCourseAnalysis', 'showCourseAiAnalysis', 'showCourseExpert'], true)
            ? $cmd
            : 'showCourseTracking';
    }
PHP;
$s = v0224_replace_first($s, $oldNormalize, $newNormalize, 'normalisation des commandes IA', false);

$oldArchiveUnset = "                unset(\$_POST['itxeb_ai_history_id'], \$_GET['itxeb_ai_history_id']);";
$newArchiveUnset = "                unset(\$_POST['itxeb_ai_history_id'], \$_GET['itxeb_ai_history_id']);\n                \$_GET['itxeb_cui_cmd'] = 'showCourseAiAnalysis';\n                \$_POST['itxeb_cui_cmd'] = 'showCourseAiAnalysis';";
$s = v0224_replace_first($s, $oldArchiveUnset, $newArchiveUnset, 'commande active après retrait IA', false);

$oldArchiveUrl = <<<'PHP'
            $archiveUrl = $this->currentUrlWith([
                'itxeb_cui_cmd' => 'archiveCourseAiHistory',
PHP;
$newArchiveUrl = <<<'PHP'
            $archiveUrl = $this->currentUrlWith([
                'itxeb_cui_cmd' => 'showCourseAiAnalysis',
PHP;
$s = v0224_replace_first($s, $oldArchiveUrl, $newArchiveUrl, 'URL de retrait IA sur onglet Analyse IA', false);

v0224_write($screen, $old, $s);
v0224_set_version($plugin, '0.22.4-dev');
v0224_set_version($companionPlugin, '0.8.10');

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

echo "V0.22.4 alignment and AI archive tab fixes applied.\n";
