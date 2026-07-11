<?php
/**
 * V0.24.8-dev
 * Répare le tableau de bord après les essais V0.24.3 à V0.24.7 :
 * - supprime les anciens wrappers et appels dupliqués ;
 * - conserve un seul rendu Activité dans le temps + Top ressources ;
 * - place les deux blocs sur la même ligne, alignés en haut ;
 * - ne modifie pas les données, uniquement l'affichage.
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

function itxeb_remove_method(string $content, string $methodName): string
{
    $pattern = '/\n\s*(?:\/\*\*.*?\*\/\s*)?private function ' . preg_quote($methodName, '/') . '\s*\([^)]*\)\s*:\s*string\s*\{/s';
    if (!preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
        return $content;
    }

    $start = (int) $m[0][1];
    $open = strpos($content, '{', $start);
    if ($open === false) {
        return $content;
    }

    $len = strlen($content);
    $depth = 0;
    $inSingle = false;
    $inDouble = false;
    $escape = false;
    for ($i = $open; $i < $len; $i++) {
        $ch = $content[$i];
        if ($escape) {
            $escape = false;
            continue;
        }
        if (($inSingle || $inDouble) && $ch === '\\') {
            $escape = true;
            continue;
        }
        if (!$inDouble && $ch === "'") {
            $inSingle = !$inSingle;
            continue;
        }
        if (!$inSingle && $ch === '"') {
            $inDouble = !$inDouble;
            continue;
        }
        if ($inSingle || $inDouble) {
            continue;
        }
        if ($ch === '{') {
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                $end = $i + 1;
                while ($end < $len && ($content[$end] === "\n" || $content[$end] === "\r")) {
                    $end++;
                }
                return substr($content, 0, $start) . "\n" . substr($content, $end);
            }
        }
    }

    return $content;
}

function itxeb_patch_screen(string $screen): string
{
    if (strpos($screen, 'class ilIliasTraxEventBridgeCourseUIScreen') === false) {
        fwrite(STDERR, "ERREUR: classe ilIliasTraxEventBridgeCourseUIScreen absente. Restaure d'abord le fichier depuis git puis relance ce script.\n");
        exit(1);
    }

    // 1) Nettoyer les anciennes tentatives et les wrappers obsolètes.
    foreach ([
        'renderDashboardChartsRow',
        'renderDashboardActivityTopLayout',
    ] as $method) {
        $screen = itxeb_remove_method($screen, $method);
    }

    // Supprime tout appel restant aux anciens wrappers ou appels séparés dans renderDashboard.
    $patterns = [
        '/\n\s*\$html\s*\.=[^;]*renderDashboardChartsRow\([^;]*\);/s',
        '/\n\s*if \(!empty\(\$widgets\[\'activity_by_day\'\]\)\s*\|\|\s*!empty\(\$widgets\[\'top_resources\'\]\)\)\s*\{\s*\$html\s*\.=[^;]*renderDashboardActivityTopLayout\([^;]*\);\s*\}/s',
        '/\n\s*if \(!empty\(\$widgets\[\'activity_by_day\'\]\)\)\s*\{\s*\$html\s*\.=[^;]*renderActivityByDay\(\$dashboard\);\s*\}/s',
        '/\n\s*if \(!empty\(\$widgets\[\'top_resources\'\]\)\)\s*\{\s*\$html\s*\.=[^;]*renderTopResources\(\$dashboard\);\s*\}/s',
    ];
    foreach ($patterns as $pattern) {
        $screen = preg_replace($pattern, "\n", $screen) ?? $screen;
    }

    // 2) Insérer un seul appel, avant la distribution des actions.
    $singleCall = <<<'PHP'
        if (!empty($widgets['activity_by_day']) || !empty($widgets['top_resources'])) {
            $html .= $this->renderDashboardActivityTopLayout($dashboard, !empty($widgets['activity_by_day']), !empty($widgets['top_resources']));
        }
PHP;
    if (strpos($screen, 'renderDashboardActivityTopLayout($dashboard, !empty($widgets[\'activity_by_day\'])') === false) {
        $screen = itxeb_replace_once(
            $screen,
            "        if (!empty(\$widgets['verb_distribution'])) {\n",
            $singleCall . "\n        if (!empty(\$widgets['verb_distribution'])) {\n",
            'insertion appel unique activité/top ressources'
        );
    }

    // 3) Ajouter une méthode propre : section unique, deux colonnes internes.
    $method = <<<'PHP'

    /** @param array<string,mixed> $dashboard */
    private function renderDashboardActivityTopLayout(array $dashboard, bool $showActivity, bool $showTopResources): string
    {
        // ITXEB V0.24.8 clean activity/top resources layout
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
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-row{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(420px,.95fr);gap:24px;align-items:start;margin:16px 0}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-row .itxeb-dashboard-activity-left,#itxeb-course-ui-screen .itxeb-dashboard-activity-row .itxeb-dashboard-activity-right{min-width:0}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-row .itxeb-cui-section{margin-top:0;margin-bottom:0}'
            . '@media (max-width:1400px){#itxeb-course-ui-screen .itxeb-dashboard-activity-row{grid-template-columns:1fr}}'
            . '</style>'
            . '<div class="itxeb-dashboard-activity-row">'
            . '<div class="itxeb-dashboard-activity-left">' . $this->renderActivityByDay($dashboard) . '</div>'
            . '<div class="itxeb-dashboard-activity-right">' . $this->renderTopResources($dashboard) . '</div>'
            . '</div>';
    }
PHP;

    $screen = itxeb_replace_once(
        $screen,
        "\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n",
        $method . "\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n",
        'insertion méthode unique activité/top ressources'
    );

    return $screen;
}

$screen = itxeb_read($screenTemplate);
$screen = itxeb_patch_screen($screen);
itxeb_write($screenTemplate, $screen);
if (is_file($liveScreen)) {
    itxeb_write($liveScreen, $screen);
}

$plugin = itxeb_read($mainPlugin);
$plugin = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.24.8-dev';", $plugin) ?? $plugin;
itxeb_write($mainPlugin, $plugin);

$companion = itxeb_read($companionPlugin);
$companion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.28';", $companion) ?? $companion;
itxeb_write($companionPlugin, $companion);
if (is_file($livePlugin)) {
    $liveCompanion = itxeb_read($livePlugin);
    $liveCompanion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.28';", $liveCompanion) ?? $liveCompanion;
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

echo "V0.24.8 clean dashboard activity/top resources layout applied.\n";
