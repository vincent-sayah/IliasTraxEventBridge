<?php
/**
 * V0.24.10-dev
 * Correction finale du bloc Tableau de bord / Activité dans le temps.
 *
 * Objectif :
 * - nettoyer les tentatives V0.24.3 à V0.24.9 ;
 * - supprimer les méthodes et appels dupliqués ;
 * - rendre UNE seule section ILIAS avec le titre "Activité dans le temps" aligné comme les autres titres ;
 * - placer, dans la colonne contenu de cette section, le graphique d'activité à gauche et Top ressources à droite.
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

function itxeb_method_bounds(string $content, string $methodName): ?array
{
    $pattern = '/\n\s*(?:\/\*\*.*?\*\/\s*)?private function ' . preg_quote($methodName, '/') . '\s*\([^)]*\)\s*:\s*string\s*\{/s';
    if (!preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    $start = (int) $m[0][1];
    $open = strpos($content, '{', $start);
    if ($open === false) {
        return null;
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
                return [$start, $end];
            }
        }
    }

    return null;
}

function itxeb_remove_all_methods(string $content, string $methodName): string
{
    while (($bounds = itxeb_method_bounds($content, $methodName)) !== null) {
        [$start, $end] = $bounds;
        $content = substr($content, 0, $start) . "\n" . substr($content, $end);
    }
    return $content;
}

function itxeb_patch_screen(string $screen): string
{
    if (strpos($screen, 'class ilIliasTraxEventBridgeCourseUIScreen') === false) {
        fwrite(STDERR, "ERREUR: classe ilIliasTraxEventBridgeCourseUIScreen absente. Restaure le fichier depuis origin/main puis relance.\n");
        exit(1);
    }

    // 1) Supprimer toutes les anciennes méthodes expérimentales, même si elles ont été dupliquées.
    foreach ([
        'renderDashboardChartsRow',
        'renderDashboardActivityTopLayout',
        'renderActivityTimelineDashboardContent',
        'renderTopResourcesDashboardPanel',
    ] as $methodName) {
        $screen = itxeb_remove_all_methods($screen, $methodName);
    }

    // 2) Supprimer tous les appels anciens / dupliqués dans renderDashboard.
    $patterns = [
        '/\n\s*\$html\s*\.=\s*\$this->renderDashboardChartsRow\([^;]*\);/s',
        '/\n\s*if \(!empty\(\$widgets\[\'activity_by_day\'\]\) \|\| !empty\(\$widgets\[\'top_resources\'\]\)\) \{\s*\$html\s*\.=\s*\$this->renderDashboardActivityTopLayout\([^;]*\);\s*\}/s',
        '/\n\s*if \(!empty\(\$widgets\[\'activity_by_day\'\]\)\) \{\s*\$html\s*\.=\s*\$this->renderActivityByDay\(\$dashboard\);\s*\}/s',
        '/\n\s*if \(!empty\(\$widgets\[\'top_resources\'\]\)\) \{\s*\$html\s*\.=\s*\$this->renderTopResources\(\$dashboard\);\s*\}/s',
    ];
    foreach ($patterns as $pattern) {
        $screen = preg_replace($pattern, "\n", $screen) ?? $screen;
    }

    // 3) Insérer un seul appel, avant la distribution des actions xAPI.
    $singleCall = <<<'PHP'
        if (!empty($widgets['activity_by_day']) || !empty($widgets['top_resources'])) {
            $html .= $this->renderDashboardActivityTopLayout($dashboard, !empty($widgets['activity_by_day']), !empty($widgets['top_resources']));
        }
PHP;

    $screen = itxeb_replace_once(
        $screen,
        "        if (!empty(\$widgets['verb_distribution'])) {\n",
        $singleCall . "\n        if (!empty(\$widgets['verb_distribution'])) {\n",
        'insertion appel unique activité/top ressources'
    );

    // 4) Ajouter une méthode propre : section ILIAS avec titre à gauche et contenu à droite.
    $method = <<<'PHP'

    /** @param array<string,mixed> $dashboard */
    private function renderDashboardActivityTopLayout(array $dashboard, bool $showActivity, bool $showTopResources): string
    {
        // ITXEB V0.24.10 final activity title alignment
        if (!$showActivity && !$showTopResources) {
            return '';
        }
        if ($showActivity && !$showTopResources) {
            return $this->renderActivityByDay($dashboard);
        }
        if (!$showActivity && $showTopResources) {
            return $this->renderTopResources($dashboard);
        }

        $byDay = is_array($dashboard['by_day'] ?? null) ? $dashboard['by_day'] : [];
        $periodDays = max(1, min(365, $this->getPeriodDays()));
        $mode = $this->getActivityTimelineMode($periodDays);
        $daily = $this->normalizeActivityDays($byDay, $periodDays);
        $total = array_sum(array_map('intval', array_values($daily)));

        $activity = '<p>Vue compacte de l’activité du cours. Le détail complet reste disponible sans occuper toute la page.</p>'
            . $this->renderActivityTimelineSelector($mode);
        if ($total <= 0) {
            $activity .= '<p><em>Aucune activité enregistrée sur la période sélectionnée.</em></p>';
        } elseif ($mode === 'week') {
            $items = $this->aggregateActivityByWeek($daily);
            $activity .= $this->renderActivityTimelineSummary($items, 'semaine(s)') . $this->renderActivityTimelineLineChart($items);
        } elseif ($mode === 'all') {
            $items = $daily;
            $summaryItems = $periodDays > 30 ? $this->aggregateActivityByWeek($daily) : $daily;
            $activity .= $this->renderActivityTimelineSummary($summaryItems, $periodDays > 30 ? 'semaine(s)' : 'jour(s)')
                . '<details class="itxeb-activity-details"><summary>Afficher le détail complet par jour (' . $this->esc((string) count($items)) . ' jour(s))</summary>'
                . $this->renderActivityTimelineLineChart($items)
                . '</details>';
        } else {
            $limit = (int) $mode;
            if ($limit <= 0) { $limit = min(14, $periodDays); }
            $limit = min($limit, $periodDays);
            $items = array_slice($daily, -$limit, null, true);
            $activity .= $this->renderActivityTimelineSummary($items, 'jour(s)') . $this->renderActivityTimelineLineChart($items);
        }

        $resources = [];
        foreach ((array) ($dashboard['by_resource'] ?? []) as $stats) {
            if ((int) ($stats['traces'] ?? 0) > 0) {
                $resources[(string) ($stats['title'] ?? ('ref_id ' . ($stats['ref_id'] ?? '')))] = (int) ($stats['traces'] ?? 0);
            }
        }
        $resources = array_slice($resources, 0, 10, true);
        $topResources = '<h3>Top ressources</h3>';
        if (count($resources) === 0) {
            $topResources .= '<p><em>Aucune donnée.</em></p>';
        } else {
            $max = max(array_map('intval', array_values($resources)));
            $topResources .= '<div class="itxeb-bar-list">';
            foreach ($resources as $label => $count) {
                $topResources .= $this->barRow((string) $label, (int) $count, $max);
            }
            $topResources .= '</div>';
        }

        return '<style>'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-final{display:grid;grid-template-columns:minmax(190px,260px) minmax(0,1fr);gap:18px;align-items:start;border-top:1px solid #ddd;padding-top:16px;margin-top:16px}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-final>h3{margin:0;font-size:16px;font-weight:700}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-content{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(420px,.95fr);gap:24px;align-items:start}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-left,#itxeb-course-ui-screen .itxeb-dashboard-activity-right{min-width:0}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-right h3{margin-top:0;font-size:16px;font-weight:700}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-left .itxeb-kpi-grid{grid-template-columns:repeat(auto-fit,minmax(150px,1fr))}'
            . '@media (max-width:1400px){#itxeb-course-ui-screen .itxeb-dashboard-activity-content{grid-template-columns:1fr}}'
            . '@media (max-width:900px){#itxeb-course-ui-screen .itxeb-dashboard-activity-final{grid-template-columns:1fr}}'
            . '</style>'
            . '<section class="itxeb-dashboard-activity-final"><h3>Activité dans le temps</h3>'
            . '<div class="itxeb-dashboard-activity-content">'
            . '<div class="itxeb-dashboard-activity-left">' . $activity . '</div>'
            . '<div class="itxeb-dashboard-activity-right">' . $topResources . '</div>'
            . '</div></section>';
    }
PHP;

    $screen = itxeb_replace_once(
        $screen,
        "\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n",
        $method . "\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n",
        'insertion méthode finale activité/top ressources'
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
$plugin = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.24.10-dev';", $plugin) ?? $plugin;
itxeb_write($mainPlugin, $plugin);

$companion = itxeb_read($companionPlugin);
$companion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.30';", $companion) ?? $companion;
itxeb_write($companionPlugin, $companion);
if (is_file($livePlugin)) {
    $liveCompanion = itxeb_read($livePlugin);
    $liveCompanion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.30';", $liveCompanion) ?? $liveCompanion;
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

echo "V0.24.10 final dashboard activity alignment applied.\n";
