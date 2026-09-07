<?php
/**
 * V0.24.16-dev
 * Correctif ciblé du layout Tableau de bord.
 *
 * Objectif :
 * - ne pas toucher à renderDashboard ;
 * - conserver l'appel existant renderDashboardActivityTopLayout(...);
 * - remplacer uniquement la méthode renderDashboardActivityTopLayout ;
 * - placer intro + boutons + KPI sur une première ligne ;
 * - placer Progression de l'activité et Top ressources dans la même ligne visuelle.
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

function itxeb_insert_before_method(string $content, string $targetMethod, string $insert): string
{
    $pattern = '/\n\s*(?:\/\*\*.*?\*\/\s*)?private function ' . preg_quote($targetMethod, '/') . '\s*\(/s';
    if (!preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
        fwrite(STDERR, "ERREUR: méthode cible introuvable pour insertion: $targetMethod\n");
        exit(1);
    }
    $pos = (int) $m[0][1];
    return substr($content, 0, $pos) . $insert . substr($content, $pos);
}

function itxeb_patch_screen(string $screen): string
{
    if (strpos($screen, 'class ilIliasTraxEventBridgeCourseUIScreen') === false) {
        fwrite(STDERR, "ERREUR: classe ilIliasTraxEventBridgeCourseUIScreen absente.\n");
        exit(1);
    }
    if (strpos($screen, 'renderDashboardActivityTopLayout($dashboard') === false) {
        fwrite(STDERR, "ERREUR: appel renderDashboardActivityTopLayout absent. Repartir de V0.24.10/V0.24.11 avant ce correctif.\n");
        exit(1);
    }

    // Nettoyer uniquement la méthode de layout et d'éventuels anciens helpers expérimentaux.
    foreach ([
        'renderDashboardActivityTopLayout',
        'renderActivityTimelineDashboardContent',
        'renderTopResourcesDashboardPanel',
        'renderDashboardChartsRow',
    ] as $methodName) {
        $screen = itxeb_remove_all_methods($screen, $methodName);
    }

    // Nettoyer les vieux wrappers visuels s'ils restent dans le code.
    $screen = preg_replace('/\n\s*\$html\s*\.=[^;]*renderDashboardChartsRow\([^;]*\);/s', "\n", $screen) ?? $screen;

    $method = <<<'PHP'

    /** @param array<string,mixed> $dashboard */
    private function renderDashboardActivityTopLayout(array $dashboard, bool $showActivity, bool $showTopResources): string
    {
        // ITXEB V0.24.16 chart row method only
        if (!$showActivity && !$showTopResources) {
            return '';
        }
        if ($showActivity && !$showTopResources) {
            return $this->renderActivityByDay($dashboard);
        }
        if (!$showTopResources) {
            return $this->renderActivityByDay($dashboard);
        }
        if (!$showActivity) {
            return $this->renderTopResources($dashboard);
        }

        $byDay = is_array($dashboard['by_day'] ?? null) ? $dashboard['by_day'] : [];
        $periodDays = max(1, min(365, $this->getPeriodDays()));
        $mode = $this->getActivityTimelineMode($periodDays);
        $daily = $this->normalizeActivityDays($byDay, $periodDays);
        $total = array_sum(array_map('intval', array_values($daily)));

        $activityHead = '<p>Vue compacte de l’activité du cours. Le détail complet reste disponible sans occuper toute la page.</p>'
            . $this->renderActivityTimelineSelector($mode);
        $activityChart = '';

        if ($total <= 0) {
            $activityChart = '<div class="itxeb-line-chart-card"><p><em>Aucune activité enregistrée sur la période sélectionnée.</em></p></div>';
        } elseif ($mode === 'week') {
            $items = $this->aggregateActivityByWeek($daily);
            $activityHead .= $this->renderActivityTimelineSummary($items, 'semaine(s)');
            $activityChart = $this->renderActivityTimelineLineChart($items);
        } elseif ($mode === 'all') {
            $items = $daily;
            $summaryItems = $periodDays > 30 ? $this->aggregateActivityByWeek($daily) : $daily;
            $activityHead .= $this->renderActivityTimelineSummary($summaryItems, $periodDays > 30 ? 'semaine(s)' : 'jour(s)');
            $activityChart = '<details class="itxeb-activity-details" open="open"><summary>Détail complet par jour (' . $this->esc((string) count($items)) . ' jour(s))</summary>'
                . $this->renderActivityTimelineLineChart($items)
                . '</details>';
        } else {
            $limit = (int) $mode;
            if ($limit <= 0) {
                $limit = min(14, $periodDays);
            }
            $limit = min($limit, $periodDays);
            $items = array_slice($daily, -$limit, null, true);
            $activityHead .= $this->renderActivityTimelineSummary($items, 'jour(s)');
            $activityChart = $this->renderActivityTimelineLineChart($items);
        }

        $resources = [];
        foreach ((array) ($dashboard['by_resource'] ?? []) as $stats) {
            if ((int) ($stats['traces'] ?? 0) > 0) {
                $resources[(string) ($stats['title'] ?? ('ref_id ' . ($stats['ref_id'] ?? '')))] = (int) ($stats['traces'] ?? 0);
            }
        }
        $resources = array_slice($resources, 0, 10, true);

        $topResources = '<div class="itxeb-dashboard-top-card"><div class="itxeb-dashboard-top-card-head"><strong>Top ressources</strong><br><small>Ressources les plus consultées sur la période affichée</small></div>';
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
        $topResources .= '</div>';

        return '<style>'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-final{display:grid!important;grid-template-columns:260px minmax(0,1fr)!important;column-gap:24px!important;row-gap:8px!important;border-top:1px solid #d9d9d9!important;padding:14px 0!important;margin:0!important;background:#fff!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-final>h3{grid-column:1!important;margin:0!important;padding:5px 0 0!important;border:0!important;font-size:16px!important;line-height:1.35!important;color:#333!important;font-weight:700!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-content{grid-column:2!important;display:block!important;min-width:0!important;margin:0!important;padding:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-head{display:block!important;margin:0 0 12px 0!important;padding:0!important;min-width:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-head p{margin-top:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-head .itxeb-kpi-grid{margin:.6rem 0 0!important;grid-template-columns:repeat(auto-fit,minmax(150px,1fr))!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-chart-row{display:grid!important;grid-template-columns:minmax(0,1.05fr) minmax(420px,.95fr)!important;gap:24px!important;align-items:start!important;margin:0!important;padding:0!important;min-width:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-chart,#itxeb-course-ui-screen .itxeb-dashboard-activity-top{min-width:0!important;margin:0!important;padding:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-chart .itxeb-line-chart-card{margin:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-top-card{border:1px solid #d9e2ec;border-radius:10px;background:#fff;padding:14px 16px;margin:0!important;box-shadow:0 1px 4px rgba(0,0,0,.05)}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-top-card-head{margin:0 0 8px!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-top-card .itxeb-bar-list{margin:0!important}'
            . '@media (max-width:1400px){#itxeb-course-ui-screen .itxeb-dashboard-activity-chart-row{grid-template-columns:1fr!important}}'
            . '@media (max-width:900px){#itxeb-course-ui-screen .itxeb-dashboard-activity-final{grid-template-columns:1fr!important}#itxeb-course-ui-screen .itxeb-dashboard-activity-final>h3,#itxeb-course-ui-screen .itxeb-dashboard-activity-content{grid-column:1!important}}'
            . '</style>'
            . '<section class="itxeb-cui-section itxeb-dashboard-activity-final"><h3>Activité dans le temps</h3>'
            . '<div class="itxeb-dashboard-activity-content">'
            . '<div class="itxeb-dashboard-activity-head">' . $activityHead . '</div>'
            . '<div class="itxeb-dashboard-activity-chart-row">'
            . '<div class="itxeb-dashboard-activity-chart">' . $activityChart . '</div>'
            . '<div class="itxeb-dashboard-activity-top">' . $topResources . '</div>'
            . '</div></div></section>';
    }
PHP;

    return itxeb_insert_before_method($screen, 'renderLrsDirectSummary', $method);
}

$screen = itxeb_read($screenTemplate);
$screen = itxeb_patch_screen($screen);
itxeb_write($screenTemplate, $screen);
if (is_file($liveScreen)) {
    itxeb_write($liveScreen, $screen);
}

$plugin = itxeb_read($mainPlugin);
$plugin = preg_replace('/\$version\s*=\s*[\'\"][^\'\"]+[\'\"];/', "\$version = '0.24.16-dev';", $plugin) ?? $plugin;
itxeb_write($mainPlugin, $plugin);

$companion = itxeb_read($companionPlugin);
$companion = preg_replace('/\$version\s*=\s*[\'\"][^\'\"]+[\'\"];/', "\$version = '0.8.36';", $companion) ?? $companion;
itxeb_write($companionPlugin, $companion);
if (is_file($livePlugin)) {
    $liveCompanion = itxeb_read($livePlugin);
    $liveCompanion = preg_replace('/\$version\s*=\s*[\'\"][^\'\"]+[\'\"];/', "\$version = '0.8.36';", $liveCompanion) ?? $liveCompanion;
    itxeb_write($livePlugin, $liveCompanion);
}

$filesToLint = [$mainPlugin, $companionPlugin, $screenTemplate];
if (is_file($livePlugin)) {
    $filesToLint[] = $livePlugin;
}
if (is_file($liveScreen)) {
    $filesToLint[] = $liveScreen;
}
foreach ($filesToLint as $file) {
    passthru('php -l ' . escapeshellarg($file), $code);
    if ($code !== 0) {
        fwrite(STDERR, "ERREUR: syntaxe PHP invalide: $file\n");
        exit(1);
    }
}

echo "V0.24.16 dashboard chart row method-only alignment applied.\n";
