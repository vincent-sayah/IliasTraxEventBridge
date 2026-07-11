<?php
/**
 * V0.24.3-dev
 * Tableau de bord : affiche Activité dans le temps et Top ressources sur une même ligne large.
 *
 * Objectifs :
 * - conserver le graphique linéaire de progression d'activité ;
 * - déplacer Top ressources à droite du graphique en écran large ;
 * - conserver un affichage empilé en écran étroit via CSS grid auto-fit ;
 * - ne plus afficher Top ressources une seconde fois plus bas.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$screenTemplate = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$mainPlugin = $root . '/plugin.php';
$companionPlugin = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$liveScreen = '/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = '/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php';

function itxeb_read(string $path): string
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

function itxeb_write(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: écriture impossible: $path\n");
        exit(1);
    }
    echo "WRITE: $path\n";
}

function itxeb_patch_screen(string $screen): string
{
    if (strpos($screen, 'ITXEB V0.24.3 dual chart row') !== false) {
        echo "SKIP: écran déjà patché V0.24.3\n";
        return $screen;
    }

    $patterns = [
        <<<'PHP'
        if (!empty($widgets['activity_by_day'])) {
            $html .= $this->renderActivityByDay($dashboard);
        }
        if (!empty($widgets['verb_distribution'])) {
PHP,
        <<<'PHP'
        if (!empty($widgets['activity_by_day'])) {
            $html .= $this->renderActivityByDay($dashboard);
        }
        if (!empty($widgets['verb_distribution'])) {
PHP,
    ];

    $replacement = <<<'PHP'
        $html .= $this->renderDashboardChartsRow($dashboard, $widgets);
        if (!empty($widgets['verb_distribution'])) {
PHP;

    $patched = false;
    foreach ($patterns as $pattern) {
        if (strpos($screen, $pattern) !== false) {
            $screen = str_replace($pattern, $replacement, $screen);
            $patched = true;
            echo "PATCH: appel activité/top ressources regroupé\n";
            break;
        }
    }
    if (!$patched) {
        fwrite(STDERR, "ERREUR: point d'insertion introuvable: remplacement appel renderActivityByDay\n");
        exit(1);
    }

    $topPatterns = [
        <<<'PHP'
        if (!empty($widgets['top_resources'])) {
            $html .= $this->renderTopResources($dashboard);
        }
        if (!empty($widgets['enabled_without_trace'])) {
PHP,
        <<<'PHP'
        if (!empty($widgets['top_resources'])) {
            $html .= $this->renderTopResources($dashboard);
        }
        if (!empty($widgets['enabled_without_trace'])) {
PHP,
    ];
    $topReplacement = <<<'PHP'
        if (!empty($widgets['enabled_without_trace'])) {
PHP;

    $patchedTop = false;
    foreach ($topPatterns as $pattern) {
        if (strpos($screen, $pattern) !== false) {
            $screen = str_replace($pattern, $topReplacement, $screen);
            $patchedTop = true;
            echo "PATCH: affichage séparé Top ressources retiré\n";
            break;
        }
    }
    if (!$patchedTop) {
        fwrite(STDERR, "ERREUR: point d'insertion introuvable: suppression appel renderTopResources séparé\n");
        exit(1);
    }

    $anchor = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderActivityByDay(array $dashboard): string
    {
        return $this->renderActivityTimeline(is_array($dashboard['by_day'] ?? null) ? $dashboard['by_day'] : []);
    }
PHP;

    if (strpos($screen, $anchor) === false) {
        fwrite(STDERR, "ERREUR: point d'insertion introuvable: méthode renderActivityByDay\n");
        exit(1);
    }

    $addition = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderActivityByDay(array $dashboard): string
    {
        return $this->renderActivityTimeline(is_array($dashboard['by_day'] ?? null) ? $dashboard['by_day'] : []);
    }

    /** @param array<string,mixed> $dashboard @param array<string,bool> $widgets */
    private function renderDashboardChartsRow(array $dashboard, array $widgets): string
    {
        // ITXEB V0.24.3 dual chart row
        $showActivity = !empty($widgets['activity_by_day']);
        $showTopResources = !empty($widgets['top_resources']);

        if (!$showActivity && !$showTopResources) {
            return '';
        }
        if ($showActivity && !$showTopResources) {
            return $this->renderActivityByDay($dashboard);
        }
        if (!$showActivity && $showTopResources) {
            return $this->renderTopResources($dashboard);
        }

        return '<div class="itxeb-dashboard-charts-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(420px,1fr));gap:18px;align-items:start;margin:16px 0">'
            . '<div class="itxeb-dashboard-chart-panel itxeb-dashboard-activity-panel" style="min-width:0">' . $this->renderActivityByDay($dashboard) . '</div>'
            . '<div class="itxeb-dashboard-chart-panel itxeb-dashboard-top-resources-panel" style="min-width:0">' . $this->renderTopResources($dashboard) . '</div>'
            . '</div>';
    }
PHP;

    $screen = str_replace($anchor, $addition, $screen);
    echo "PATCH: méthode renderDashboardChartsRow ajoutée\n";

    return $screen;
}

$screen = itxeb_patch_screen(itxeb_read($screenTemplate));
itxeb_write($screenTemplate, $screen);
if (is_file($liveScreen)) {
    itxeb_write($liveScreen, $screen);
}

$plugin = itxeb_read($mainPlugin);
$plugin = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.24.3-dev';", $plugin) ?? $plugin;
itxeb_write($mainPlugin, $plugin);

$companion = itxeb_read($companionPlugin);
$companion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.23';", $companion) ?? $companion;
itxeb_write($companionPlugin, $companion);
if (is_file($livePlugin)) {
    $liveCompanion = itxeb_read($livePlugin);
    $liveCompanion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.23';", $liveCompanion) ?? $liveCompanion;
    itxeb_write($livePlugin, $liveCompanion);
}

$filesToLint = [$mainPlugin, $companionPlugin, $screenTemplate];
if (is_file($livePlugin)) { $filesToLint[] = $livePlugin; }
if (is_file($liveScreen)) { $filesToLint[] = $liveScreen; }
foreach ($filesToLint as $file) {
    passthru('php -l ' . escapeshellarg($file), $code);
    if ($code !== 0) {
        fwrite(STDERR, "ERREUR: syntaxe PHP invalide: $file\n");
        exit(1);
    }
}

echo "V0.24.3 dashboard dual chart row applied.\n";
