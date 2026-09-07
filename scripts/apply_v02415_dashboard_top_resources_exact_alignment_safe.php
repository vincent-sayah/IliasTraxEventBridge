<?php
/**
 * V0.24.15-dev
 * Correctif sûr du bloc Tableau de bord / Activité dans le temps.
 *
 * Objectif :
 * - ne plus dépendre d'un texte exact autour de verb_distribution ;
 * - conserver un seul appel renderDashboardActivityTopLayout si déjà présent ;
 * - remplacer uniquement la méthode de rendu du bloc activité/top ressources ;
 * - aligner le panneau "Top ressources" sur la même ligne que la carte "Progression de l'activité" ;
 * - ne pas modifier les données métier.
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

function itxeb_replace_once_or_fail(string $content, string $search, string $replace, string $label): string
{
    if (strpos($content, $search) === false) {
        fwrite(STDERR, "ERREUR: point de remplacement introuvable: $label\n");
        exit(1);
    }
    return str_replace($search, $replace, $content);
}

function itxeb_normalize_dashboard_call(string $screen): string
{
    $bounds = itxeb_method_bounds($screen, 'renderDashboard');
    if ($bounds === null) {
        fwrite(STDERR, "ERREUR: méthode renderDashboard introuvable\n");
        exit(1);
    }

    [$start, $end] = $bounds;
    $method = substr($screen, $start, $end - $start);
    $callNeedle = 'renderDashboardActivityTopLayout($dashboard';
    $callCount = substr_count($method, $callNeedle);

    if ($callCount === 1 && strpos($method, 'renderDashboardChartsRow(') === false) {
        return $screen;
    }

    $singleCall = <<<'PHP'
        if (!empty($widgets['activity_by_day']) || !empty($widgets['top_resources'])) {
            $html .= $this->renderDashboardActivityTopLayout($dashboard, !empty($widgets['activity_by_day']), !empty($widgets['top_resources']));
        }
PHP;

    $patterns = [
        '/\n\s*\$html\s*\.=[^;]*renderDashboardChartsRow\([^;]*\);/s',
        '/\n\s*if\s*\([^\n{}]*activity_by_day[^{}]*top_resources[^{}]*\)\s*\{\s*\$html\s*\.=[^;]*renderDashboardActivityTopLayout\([^;]*\);\s*\}/s',
        '/\n\s*if\s*\([^\n{}]*activity_by_day[^{}]*\)\s*\{\s*\$html\s*\.=[^;]*renderActivityByDay\([^;]*\);\s*\}/s',
        '/\n\s*if\s*\([^\n{}]*top_resources[^{}]*\)\s*\{\s*\$html\s*\.=[^;]*renderTopResources\([^;]*\);\s*\}/s',
    ];
    foreach ($patterns as $pattern) {
        $method = preg_replace($pattern, "\n", $method) ?? $method;
    }

    if (strpos($method, $callNeedle) === false) {
        if (preg_match('/\n\s*if\s*\(\s*!empty\s*\(\s*\$widgets\s*\[\s*\'verb_distribution\'\s*\]\s*\)\s*\)\s*\{/s', $method, $m, PREG_OFFSET_CAPTURE)) {
            $insertAt = (int) $m[0][1];
            $method = substr($method, 0, $insertAt) . "\n" . $singleCall . substr($method, $insertAt);
        } elseif (preg_match('/\n\s*\$html\s*\.=[^;]*renderVerbDistribution\([^;]*\);/s', $method, $m, PREG_OFFSET_CAPTURE)) {
            $insertAt = (int) $m[0][1];
            $method = substr($method, 0, $insertAt) . "\n" . $singleCall . substr($method, $insertAt);
        } else {
            fwrite(STDERR, "ERREUR: impossible de localiser le point d'insertion dans renderDashboard\n");
            exit(1);
        }
    }

    return substr($screen, 0, $start) . $method . substr($screen, $end);
}

function itxeb_patch_screen(string $screen): string
{
    if (strpos($screen, 'class ilIliasTraxEventBridgeCourseUIScreen') === false) {
        fwrite(STDERR, "ERREUR: classe ilIliasTraxEventBridgeCourseUIScreen absente\n");
        exit(1);
    }

    // Nettoyage des anciennes méthodes expérimentales ou dupliquées.
    foreach ([
        'renderDashboardChartsRow',
        'renderDashboardActivityTopLayout',
        'renderActivityTimelineDashboardContent',
        'renderTopResourcesDashboardPanel',
    ] as $methodName) {
        $screen = itxeb_remove_all_methods($screen, $methodName);
    }

    // Sécurise l'appel dans renderDashboard sans dépendre du libellé exact autour de verb_distribution.
    $screen = itxeb_normalize_dashboard_call($screen);

    $method = <<<'PHP'

    /** @param array<string,mixed> $dashboard */
    private function renderDashboardActivityTopLayout(array $dashboard, bool $showActivity, bool $showTopResources): string
    {
        // ITXEB V0.24.15 top resources exactly aligned with graph card
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

        $activityIntro = '<p>Vue compacte de l’activité du cours. Le détail complet reste disponible sans occuper toute la page.</p>'
            . $this->renderActivityTimelineSelector($mode);
        $activitySummary = '';
        $activityChart = '';

        if ($total <= 0) {
            $activityChart = '<div class="itxeb-line-chart-card"><p><em>Aucune activité enregistrée sur la période sélectionnée.</em></p></div>';
        } elseif ($mode === 'week') {
            $items = $this->aggregateActivityByWeek($daily);
            $activitySummary = $this->renderActivityTimelineSummary($items, 'semaine(s)');
            $activityChart = $this->renderActivityTimelineLineChart($items);
        } elseif ($mode === 'all') {
            $items = $daily;
            $summaryItems = $periodDays > 30 ? $this->aggregateActivityByWeek($daily) : $daily;
            $activitySummary = $this->renderActivityTimelineSummary($summaryItems, $periodDays > 30 ? 'semaine(s)' : 'jour(s)');
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
            $activitySummary = $this->renderActivityTimelineSummary($items, 'jour(s)');
            $activityChart = $this->renderActivityTimelineLineChart($items);
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
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-final{display:grid!important;grid-template-columns:260px minmax(0,1fr)!important;column-gap:24px!important;row-gap:8px!important;align-items:start!important;border-top:1px solid #d9d9d9!important;padding:14px 0!important;margin:0!important;background:#fff!important;border-left:0!important;border-right:0!important;border-bottom:0!important;box-shadow:none!important;border-radius:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-final>h3{grid-column:1!important;margin:0!important;padding:5px 0 0!important;border:0!important;font-size:16px!important;line-height:1.35!important;color:#333!important;font-weight:700!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-content{grid-column:2!important;min-width:0!important;display:block!important;margin:0!important;padding:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-intro{margin:0 0 10px!important;min-width:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-intro p{margin-top:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-summary{margin:0 0 12px!important;min-width:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-summary .itxeb-kpi-grid{grid-template-columns:repeat(auto-fit,minmax(150px,1fr))!important;margin:.6rem 0 0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-chart-row{display:grid!important;grid-template-columns:minmax(0,1.05fr) minmax(420px,.95fr)!important;gap:24px!important;align-items:start!important;margin-top:0!important;min-width:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-chart{min-width:0!important;margin:0!important;padding:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-chart>.itxeb-line-chart-card{margin-top:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-top-card{min-width:0!important;margin:0!important;border:1px solid #d9e2ec!important;border-radius:10px!important;background:#fff!important;padding:14px 16px!important;box-shadow:0 1px 4px rgba(0,0,0,.05)!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-top-card h3{margin:0 0 8px!important;padding:0!important;border:0!important;font-size:16px!important;font-weight:700!important;line-height:1.35!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-top-card .itxeb-bar-list{margin:0!important}'
            . '@media (max-width:1400px){#itxeb-course-ui-screen .itxeb-dashboard-activity-chart-row{grid-template-columns:1fr!important}}'
            . '@media (max-width:900px){#itxeb-course-ui-screen .itxeb-dashboard-activity-final{grid-template-columns:1fr!important}#itxeb-course-ui-screen .itxeb-dashboard-activity-final>h3,#itxeb-course-ui-screen .itxeb-dashboard-activity-content{grid-column:1!important}}'
            . '</style>'
            . '<section class="itxeb-cui-section itxeb-dashboard-activity-final"><h3>Activité dans le temps</h3>'
            . '<div class="itxeb-dashboard-activity-content">'
            . '<div class="itxeb-dashboard-activity-intro">' . $activityIntro . '</div>'
            . '<div class="itxeb-dashboard-activity-summary">' . $activitySummary . '</div>'
            . '<div class="itxeb-dashboard-activity-chart-row">'
            . '<div class="itxeb-dashboard-activity-chart">' . $activityChart . '</div>'
            . '<div class="itxeb-dashboard-top-card">' . $topResources . '</div>'
            . '</div></div></section>';
    }
PHP;

    $screen = itxeb_replace_once_or_fail(
        $screen,
        "\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n",
        $method . "\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n",
        'insertion méthode V0.24.15 activité/top ressources'
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
$plugin = preg_replace('/\$version\s*=\s*\'[^\']+\';/', "\$version = '0.24.15-dev';", $plugin) ?? $plugin;
itxeb_write($mainPlugin, $plugin);

$companion = itxeb_read($companionPlugin);
$companion = preg_replace('/\$version\s*=\s*\'[^\']+\';/', "\$version = '0.8.35';", $companion) ?? $companion;
itxeb_write($companionPlugin, $companion);
if (is_file($livePlugin)) {
    $liveCompanion = itxeb_read($livePlugin);
    $liveCompanion = preg_replace('/\$version\s*=\s*\'[^\']+\';/', "\$version = '0.8.35';", $liveCompanion) ?? $liveCompanion;
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

echo "V0.24.15 dashboard top resources exact alignment applied.\n";
