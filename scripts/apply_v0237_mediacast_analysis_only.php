<?php

declare(strict_types=1);

/**
 * V0.23.7 MediaCast analysis-only view.
 *
 * Moves the MediaCast media table out of the Dashboard tab and keeps the
 * preferred compact “Médias MediaCast vus” view only in the Analysis tab.
 */

$root = dirname(__DIR__);

function itxeb237_read(string $path): string
{
    if (!is_file($path)) {
        fwrite(STDERR, "ERREUR: fichier introuvable: $path\n");
        exit(1);
    }
    $content = file_get_contents($path);
    if (!is_string($content)) {
        fwrite(STDERR, "ERREUR: lecture impossible: $path\n");
        exit(1);
    }
    return $content;
}

function itxeb237_write(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: écriture impossible: $path\n");
        exit(1);
    }
    echo "WRITE: $path\n";
}

function itxeb237_set_version(string $path, string $version): void
{
    $content = itxeb237_read($path);
    $new = preg_replace('/\$version\s*=\s*\'[^\']*\';/', '$version = \'' . $version . '\';', $content, 1);
    if (!is_string($new) || $new === $content) {
        fwrite(STDERR, "ERREUR: version introuvable: $path\n");
        exit(1);
    }
    itxeb237_write($path, $new);
}

function itxeb237_lint(string $path): void
{
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $out, $code);
    echo implode("\n", $out) . "\n";
    if ($code !== 0) {
        fwrite(STDERR, "ERREUR: lint PHP en échec: $path\n");
        exit(1);
    }
}

$screenTplPath = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$mainPluginPath = $root . '/plugin.php';
$companionPluginTplPath = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';

$screen = itxeb237_read($screenTplPath);
$original = $screen;

// 1) Remove MediaCast KPIs from the Dashboard main KPI grid.
$screen = str_replace(
    "            . \$this->metricCard('Vidéos lues', (string) (\$summary['mediacast_internal_played'] ?? 0), 'MediaCast')\n"
    . "            . \$this->metricCard('Médias externes', (string) (\$summary['mediacast_external_opened'] ?? 0), 'MediaCast')\n",
    '',
    $screen
);

// 2) Remove the MediaCast media block from the Dashboard tab.
$screen = str_replace(
    "            . '</div>'\n            . \$this->renderMediaCastMediaDashboard(\$dashboard);",
    "            . '</div>';",
    $screen
);

// 3) In Analysis, replace the grouped view by the preferred compact Dashboard-like view.
$screen = str_replace(
    " . \$this->renderQuestionFailureHotspots(\$dashboard, \$course) . \$this->renderMediaCastMediaAnalysisGroupedByParent(\$dashboard);",
    " . \$this->renderQuestionFailureHotspots(\$dashboard, \$course) . \$this->renderMediaCastMediaDashboard(\$dashboard);",
    $screen
);

// Also handle a previous non-grouped V0.23.4 insertion, for idempotence.
$screen = str_replace(
    " . \$this->renderQuestionFailureHotspots(\$dashboard, \$course) . \$this->renderMediaCastMediaAnalysis(\$dashboard);",
    " . \$this->renderQuestionFailureHotspots(\$dashboard, \$course) . \$this->renderMediaCastMediaDashboard(\$dashboard);",
    $screen
);

// 4) Ensure the Analysis call is present if the previous scripts only partially patched the file.
if (strpos($screen, 'renderMediaCastMediaDashboard($dashboard);') === false) {
    $needle = " . \$this->renderTrainerActionSummary(\$dashboard) . \$this->renderPedagogicalSynthesis(\$dashboard) . \$this->renderQuestionFailureHotspots(\$dashboard, \$course);\n";
    $replacement = " . \$this->renderTrainerActionSummary(\$dashboard) . \$this->renderPedagogicalSynthesis(\$dashboard) . \$this->renderQuestionFailureHotspots(\$dashboard, \$course) . \$this->renderMediaCastMediaDashboard(\$dashboard);\n";
    if (strpos($screen, $needle) === false) {
        fwrite(STDERR, "ERREUR: appel Analyse introuvable pour déplacer la vue MediaCast.\n");
        exit(1);
    }
    $screen = str_replace($needle, $replacement, $screen);
}

// 5) The preferred compact method must exist. If not, stop cleanly.
if (strpos($screen, 'private function renderMediaCastMediaDashboard(array $dashboard): string') === false) {
    fwrite(STDERR, "ERREUR: méthode renderMediaCastMediaDashboard absente. Appliquer/réparer V0.23.6 avant V0.23.7.\n");
    exit(1);
}

if ($screen !== $original) {
    itxeb237_write($screenTplPath, $screen);
    echo "PATCH: vue MediaCast retirée du Tableau de bord et déplacée dans Analyse\n";
} else {
    echo "SKIP: écran déjà conforme V0.23.7\n";
}

itxeb237_set_version($mainPluginPath, '0.23.7-dev');
itxeb237_set_version($companionPluginTplPath, '0.8.18');

$liveBase = dirname($root) . '/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$liveScreen = $liveBase . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = $liveBase . '/plugin.php';
if (is_file($liveScreen)) {
    copy($screenTplPath, $liveScreen);
    echo "COPY: $screenTplPath -> $liveScreen\n";
}
if (is_file($livePlugin)) {
    copy($companionPluginTplPath, $livePlugin);
    echo "COPY: $companionPluginTplPath -> $livePlugin\n";
}

foreach ([$screenTplPath, $mainPluginPath, $companionPluginTplPath] as $path) {
    itxeb237_lint($path);
}
if (is_file($liveScreen)) { itxeb237_lint($liveScreen); }
if (is_file($livePlugin)) { itxeb237_lint($livePlugin); }

echo "V0.23.7 MediaCast analysis-only view applied.\n";
