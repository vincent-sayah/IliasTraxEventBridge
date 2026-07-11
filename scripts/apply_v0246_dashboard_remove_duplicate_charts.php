<?php
/**
 * V0.24.6-dev
 * Tableau de bord : nettoyage strict des anciens rendus d'activité/top ressources.
 *
 * Objectifs :
 * - supprimer l'ancien appel renderDashboardChartsRow() encore présent ;
 * - supprimer les appels doublons renderDashboardActivityTopLayout() ;
 * - conserver un seul bloc : Activité dans le temps à gauche + Top ressources à droite ;
 * - supprimer l'ancienne méthode renderDashboardChartsRow() qui créait le troisième graphique.
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

function itxeb_replace_between(string $content, string $startNeedle, string $endNeedle, callable $callback, string $label): string
{
    $start = strpos($content, $startNeedle);
    if ($start === false) {
        fwrite(STDERR, "ERREUR: début introuvable: $label\n");
        exit(1);
    }
    $end = strpos($content, $endNeedle, $start);
    if ($end === false) {
        fwrite(STDERR, "ERREUR: fin introuvable: $label\n");
        exit(1);
    }
    $before = substr($content, 0, $start);
    $middle = substr($content, $start, $end - $start);
    $after = substr($content, $end);
    return $before . $callback($middle) . $after;
}

function itxeb_remove_method(string $content, string $methodName, string $nextMethodName): string
{
    $methodPos = strpos($content, 'private function ' . $methodName . '(');
    if ($methodPos === false) {
        return $content;
    }

    $start = strrpos(substr($content, 0, $methodPos), "\n    /**");
    if ($start === false) {
        $start = strrpos(substr($content, 0, $methodPos), "\n    private function");
    }
    if ($start === false) {
        fwrite(STDERR, "ERREUR: début méthode introuvable: $methodName\n");
        exit(1);
    }

    $nextPos = strpos($content, 'private function ' . $nextMethodName . '(', $methodPos + 1);
    if ($nextPos === false) {
        fwrite(STDERR, "ERREUR: méthode suivante introuvable: $nextMethodName\n");
        exit(1);
    }
    $end = strrpos(substr($content, 0, $nextPos), "\n    /**");
    if ($end === false || $end <= $start) {
        $end = strrpos(substr($content, 0, $nextPos), "\n    private function");
    }
    if ($end === false || $end <= $start) {
        fwrite(STDERR, "ERREUR: fin méthode introuvable: $methodName\n");
        exit(1);
    }

    return substr($content, 0, $start) . substr($content, $end);
}

function itxeb_patch_screen(string $screen): string
{
    $screen = itxeb_replace_between(
        $screen,
        "    private function renderDashboard(array \$course): string\n    {\n",
        "\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n",
        static function (string $method): string {
            $method = str_replace("\n        \$html .= \$this->renderDashboardChartsRow(\$dashboard, \$widgets);", '', $method);

            $patterns = [
                "/\n        if \(!empty\(\$widgets\['activity_by_day'\]\) \|\| !empty\(\$widgets\['top_resources'\]\)\) \{\n\s*\$html \.= \$this->renderDashboardActivityTopLayout\(\$dashboard, !empty\(\$widgets\['activity_by_day'\]\), !empty\(\$widgets\['top_resources'\]\)\);\n\s*\}/s",
                "/\n        if \(!empty\(\$widgets\['activity_by_day'\]\)\) \{\n\s*\$html \.= \$this->renderActivityByDay\(\$dashboard\);\n\s*\}/s",
                "/\n        if \(!empty\(\$widgets\['top_resources'\]\)\) \{\n\s*\$html \.= \$this->renderTopResources\(\$dashboard\);\n\s*\}/s",
            ];
            foreach ($patterns as $pattern) {
                $method = preg_replace($pattern, '', $method) ?? $method;
            }

            $newCall = <<<'PHP'
        if (!empty($widgets['activity_by_day']) || !empty($widgets['top_resources'])) {
            $html .= $this->renderDashboardActivityTopLayout($dashboard, !empty($widgets['activity_by_day']), !empty($widgets['top_resources']));
        }
PHP;
            if (strpos($method, "if (!empty(\$widgets['verb_distribution'])) {") === false) {
                fwrite(STDERR, "ERREUR: point insertion avant verb_distribution introuvable\n");
                exit(1);
            }
            $method = str_replace(
                "        if (!empty(\$widgets['verb_distribution'])) {\n",
                $newCall . "\n        if (!empty(\$widgets['verb_distribution'])) {\n",
                $method
            );

            return $method;
        },
        'renderDashboard cleanup'
    );

    $screen = itxeb_remove_method($screen, 'renderDashboardChartsRow', 'renderActivityByDay');

    $screen = str_replace('ITXEB V0.24.5 activity/top true layout', 'ITXEB V0.24.6 activity/top single layout cleanup', $screen);

    return $screen;
}

$screen = itxeb_read($screenTemplate);
$screen = itxeb_patch_screen($screen);
itxeb_write($screenTemplate, $screen);
if (is_file($liveScreen)) {
    itxeb_write($liveScreen, $screen);
}

$plugin = itxeb_read($mainPlugin);
$plugin = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.24.6-dev';", $plugin) ?? $plugin;
itxeb_write($mainPlugin, $plugin);

$companion = itxeb_read($companionPlugin);
$companion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.26';", $companion) ?? $companion;
itxeb_write($companionPlugin, $companion);
if (is_file($livePlugin)) {
    $liveCompanion = itxeb_read($livePlugin);
    $liveCompanion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.26';", $liveCompanion) ?? $liveCompanion;
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

echo "V0.24.6 dashboard duplicate chart cleanup applied.\n";
