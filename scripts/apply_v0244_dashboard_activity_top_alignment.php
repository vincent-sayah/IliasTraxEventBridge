<?php
/**
 * V0.24.4-dev
 * Tableau de bord : aligne le bloc Activité dans le temps et Top ressources sur une même ligne.
 *
 * Objectif :
 * - conserver le bloc complet Activité dans le temps à gauche : titre, description, boutons, KPI, graphique ;
 * - placer Top ressources à droite, aligné dès le haut du bloc Activité ;
 * - éviter le décalage où Top ressources était aligné seulement avec le graphique.
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

function itxeb_replace_once(string $content, string $search, string $replace, string $label): string
{
    if (strpos($content, $search) === false) {
        fwrite(STDERR, "ERREUR: point de remplacement introuvable: $label\n");
        exit(1);
    }
    return str_replace($search, $replace, $content);
}

function itxeb_patch_screen(string $screen): string
{
    $marker = 'ITXEB V0.24.4 activity/top aligned row';

    // Supprime les appels séparés ou l'ancien regroupement V0.24.3 pour les remplacer par un vrai layout 2 colonnes.
    $patterns = [
        "/\n        if \(!empty\(\$widgets\['activity_by_day'\]\)\) \{\n            \$html \.= \$this->renderActivityByDay\(\$dashboard\);\n        \}\n/s",
        "/\n        if \(!empty\(\$widgets\['top_resources'\]\)\) \{\n            \$html \.= \$this->renderTopResources\(\$dashboard\);\n        \}\n/s",
        "/\n        if \(!empty\(\$widgets\['activity_by_day'\]\) \|\| !empty\(\$widgets\['top_resources'\]\)\) \{\n\s*\$html \.= \$this->renderDashboardChartsRow\([^;]*\);\n\s*\}\n/s",
        "/\n        if \(!empty\(\$widgets\['activity_by_day'\]\) \|\| !empty\(\$widgets\['top_resources'\]\)\) \{\n\s*\$html \.= \$this->renderDashboardActivityTopLayout\([^;]*\);\n\s*\}\n/s",
    ];
    foreach ($patterns as $pattern) {
        $screen = preg_replace($pattern, "\n", $screen) ?? $screen;
    }

    $newCall = <<<'PHP'
        if (!empty($widgets['activity_by_day']) || !empty($widgets['top_resources'])) {
            $html .= $this->renderDashboardActivityTopLayout($dashboard, !empty($widgets['activity_by_day']), !empty($widgets['top_resources']));
        }
PHP;

    if (strpos($screen, 'renderDashboardActivityTopLayout($dashboard') === false) {
        $screen = itxeb_replace_once(
            $screen,
            "        if (!empty(\$widgets['verb_distribution'])) {\n",
            $newCall . "\n        if (!empty(\$widgets['verb_distribution'])) {\n",
            'insertion appel layout activité/top ressources'
        );
    }

    if (strpos($screen, $marker) === false) {
        $method = <<<'PHP'

    /** @param array<string,mixed> $dashboard */
    private function renderDashboardActivityTopLayout(array $dashboard, bool $showActivity, bool $showTopResources): string
    {
        // ITXEB V0.24.4 activity/top aligned row
        if (!$showActivity && !$showTopResources) {
            return '';
        }
        if ($showActivity && !$showTopResources) {
            return $this->renderActivityByDay($dashboard);
        }
        if (!$showActivity && $showTopResources) {
            return $this->renderTopResources($dashboard);
        }

        return '<style>'
            . '#itxeb-course-ui-screen .itxeb-dashboard-charts-row{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(420px,.85fr);gap:24px;align-items:start;margin-top:16px}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-charts-row>.itxeb-dashboard-charts-main,#itxeb-course-ui-screen .itxeb-dashboard-charts-row>.itxeb-dashboard-charts-side{min-width:0}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-charts-row .itxeb-cui-section{margin-top:0}'
            . '@media (max-width:1400px){#itxeb-course-ui-screen .itxeb-dashboard-charts-row{grid-template-columns:1fr}}'
            . '</style>'
            . '<div class="itxeb-dashboard-charts-row">'
            . '<div class="itxeb-dashboard-charts-main">' . $this->renderActivityByDay($dashboard) . '</div>'
            . '<div class="itxeb-dashboard-charts-side">' . $this->renderTopResources($dashboard) . '</div>'
            . '</div>';
    }
PHP;
        $screen = itxeb_replace_once(
            $screen,
            "\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n",
            $method . "\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n",
            'insertion méthode layout activité/top ressources'
        );
    }

    return $screen;
}

$screen = itxeb_read($screenTemplate);
$screen = itxeb_patch_screen($screen);
itxeb_write($screenTemplate, $screen);
if (is_file($liveScreen)) {
    itxeb_write($liveScreen, $screen);
}

$plugin = itxeb_read($mainPlugin);
$plugin = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.24.4-dev';", $plugin) ?? $plugin;
itxeb_write($mainPlugin, $plugin);

$companion = itxeb_read($companionPlugin);
$companion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.24';", $companion) ?? $companion;
itxeb_write($companionPlugin, $companion);
if (is_file($livePlugin)) {
    $liveCompanion = itxeb_read($livePlugin);
    $liveCompanion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.24';", $liveCompanion) ?? $liveCompanion;
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

echo "V0.24.4 dashboard activity/top aligned row applied.\n";
