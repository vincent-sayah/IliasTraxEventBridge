<?php
/**
 * V0.24.7-dev
 * Tableau de bord : nettoyage forcé du rendu Activité / Top ressources.
 *
 * Objectif :
 * - supprimer l'ancien wrapper renderDashboardChartsRow ;
 * - supprimer les appels dupliqués à renderDashboardActivityTopLayout ;
 * - conserver un seul rendu : Activité dans le temps à gauche + Top ressources à droite ;
 * - écrire le template et le fichier live utilisés par ILIAS.
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

/** @return array{0:int,1:int} */
function itxeb_method_bounds(string $content, string $methodName): array
{
    $signaturePattern = '/\n[ \t]*(?:\/\*\*.*?\*\/\s*)?private function ' . preg_quote($methodName, '/') . '\s*\(/s';
    if (!preg_match($signaturePattern, $content, $m, PREG_OFFSET_CAPTURE)) {
        $signaturePattern = '/\n[ \t]*private function ' . preg_quote($methodName, '/') . '\s*\(/s';
        if (!preg_match($signaturePattern, $content, $m, PREG_OFFSET_CAPTURE)) {
            return [-1, -1];
        }
    }

    $start = (int) $m[0][1];
    $brace = strpos($content, '{', $start);
    if ($brace === false) {
        return [-1, -1];
    }

    $depth = 0;
    $len = strlen($content);
    for ($i = $brace; $i < $len; $i++) {
        $ch = $content[$i];
        if ($ch === '{') { $depth++; }
        if ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                return [$start, $i + 1];
            }
        }
    }

    return [-1, -1];
}

function itxeb_remove_method(string $content, string $methodName): string
{
    [$start, $end] = itxeb_method_bounds($content, $methodName);
    if ($start < 0 || $end <= $start) {
        return $content;
    }
    echo "REMOVE METHOD: $methodName\n";
    return substr($content, 0, $start) . "\n" . substr($content, $end);
}

function itxeb_patch_render_dashboard(string $content): string
{
    [$start, $end] = itxeb_method_bounds($content, 'renderDashboard');
    if ($start < 0 || $end <= $start) {
        fwrite(STDERR, "ERREUR: méthode renderDashboard introuvable\n");
        exit(1);
    }

    $method = substr($content, $start, $end - $start);

    // Supprime l'ancien appel V0.24.3/ancien wrapper.
    $method = preg_replace(
        "/\n[ \t]*\$html\s*\.=\s*\$this->renderDashboardChartsRow\(\$dashboard,\s*\$widgets\);[ \t]*/",
        "\n",
        $method
    ) ?? $method;

    // Supprime tous les blocs conditionnels qui ajoutent déjà ActivityTopLayout.
    $method = preg_replace(
        "/\n[ \t]*if \(!empty\(\$widgets\['activity_by_day'\]\) \|\| !empty\(\$widgets\['top_resources'\]\)\) \{\s*\n[ \t]*\$html\s*\.=\s*\$this->renderDashboardActivityTopLayout\([^;]+\);\s*\n[ \t]*\}\s*/s",
        "\n",
        $method
    ) ?? $method;

    // Supprime aussi les appels simples éventuels sans bloc.
    $method = preg_replace(
        "/\n[ \t]*\$html\s*\.=\s*\$this->renderDashboardActivityTopLayout\([^;]+\);[ \t]*/",
        "\n",
        $method
    ) ?? $method;

    $call = <<<'PHP'
        if (!empty($widgets['activity_by_day']) || !empty($widgets['top_resources'])) {
            $html .= $this->renderDashboardActivityTopLayout($dashboard, !empty($widgets['activity_by_day']), !empty($widgets['top_resources']));
        }
PHP;

    $needle = "        if (!empty(\$widgets['verb_distribution'])) {\n";
    if (strpos($method, $needle) === false) {
        fwrite(STDERR, "ERREUR: point d'insertion avant verb_distribution introuvable\n");
        exit(1);
    }
    $method = str_replace($needle, $call . "\n" . $needle, $method);

    return substr($content, 0, $start) . $method . substr($content, $end);
}

function itxeb_patch_screen(string $screen): string
{
    // Nettoyage de l'ancien wrapper qui créait un troisième rendu.
    $screen = itxeb_remove_method($screen, 'renderDashboardChartsRow');

    // Nettoyage et insertion d'un seul appel dans renderDashboard.
    $screen = itxeb_patch_render_dashboard($screen);

    // Marqueur V0.24.7 pour contrôle.
    if (strpos($screen, 'ITXEB V0.24.7 single dashboard chart layout') === false) {
        $screen = str_replace(
            'ITXEB V0.24.5 activity/top true layout',
            'ITXEB V0.24.7 single dashboard chart layout',
            $screen
        );
        if (strpos($screen, 'ITXEB V0.24.7 single dashboard chart layout') === false) {
            $screen = str_replace(
                'private function renderDashboardActivityTopLayout(array $dashboard, bool $showActivity, bool $showTopResources): string',
                "private function renderDashboardActivityTopLayout(array \$dashboard, bool \$showActivity, bool \$showTopResources): string\n    {\n        // ITXEB V0.24.7 single dashboard chart layout",
                $screen
            );
            $screen = str_replace("\n    {\n        // ITXEB V0.24.7 single dashboard chart layout\n    {", "\n    {\n        // ITXEB V0.24.7 single dashboard chart layout", $screen);
        }
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
$plugin = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.24.7-dev';", $plugin) ?? $plugin;
itxeb_write($mainPlugin, $plugin);

$companion = itxeb_read($companionPlugin);
$companion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.27';", $companion) ?? $companion;
itxeb_write($companionPlugin, $companion);
if (is_file($livePlugin)) {
    $liveCompanion = itxeb_read($livePlugin);
    $liveCompanion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.27';", $liveCompanion) ?? $liveCompanion;
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

echo "V0.24.7 dashboard duplicate chart hard cleanup applied.\n";
