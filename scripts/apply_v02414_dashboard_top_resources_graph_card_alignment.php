<?php
/**
 * V0.24.14-dev
 * Corrige sans restauration complète l'alignement Dashboard :
 * - ne plante plus si l'appel renderDashboardActivityTopLayout existe déjà ;
 * - supprime les anciens wrappers expérimentaux ;
 * - conserve un seul appel Dashboard ;
 * - aligne Top ressources sur la carte du graphique Progression de l'activité.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$screenTemplate = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$mainPlugin = $root . '/plugin.php';
$companionPlugin = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$liveScreen = '/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = '/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php';

function itxeb_read_v02414(string $path): string
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

function itxeb_write_v02414(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: écriture impossible: $path\n");
        exit(1);
    }
    echo "WRITE: $path\n";
}

function itxeb_method_bounds_v02414(string $content, string $methodName): ?array
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

function itxeb_remove_all_methods_v02414(string $content, string $methodName): string
{
    while (($bounds = itxeb_method_bounds_v02414($content, $methodName)) !== null) {
        [$start, $end] = $bounds;
        $content = substr($content, 0, $start) . "\n" . substr($content, $end);
    }
    return $content;
}

function itxeb_patch_render_dashboard_calls_v02414(string $screen): string
{
    // Supprime les anciens appels, y compris les appels déjà présents de V0.24.10 à V0.24.13.
    $patterns = [
        '/\n\s*\$html\s*\.=?\s*\$this->renderDashboardChartsRow\([^;]*\);/s',
        '/\n\s*if\s*\(\s*!empty\(\$widgets\[\'activity_by_day\'\]\)\s*\|\|\s*!empty\(\$widgets\[\'top_resources\'\]\)\s*\)\s*\{\s*\$html\s*\.=?\s*\$this->renderDashboardActivityTopLayout\([^;]*\);\s*\}/s',
        '/\n\s*if\s*\(\s*!empty\(\$widgets\[\'activity_by_day\'\]\)\s*\)\s*\{\s*\$html\s*\.=?\s*\$this->renderActivityByDay\(\$dashboard\);\s*\}/s',
        '/\n\s*if\s*\(\s*!empty\(\$widgets\[\'top_resources\'\]\)\s*\)\s*\{\s*\$html\s*\.=?\s*\$this->renderTopResources\(\$dashboard\);\s*\}/s',
    ];
    foreach ($patterns as $pattern) {
        $screen = preg_replace($pattern, "\n", $screen) ?? $screen;
    }

    $singleCall = <<<'PHP'
        if (!empty($widgets['activity_by_day']) || !empty($widgets['top_resources'])) {
            $html .= $this->renderDashboardActivityTopLayout($dashboard, !empty($widgets['activity_by_day']), !empty($widgets['top_resources']));
        }
PHP;

    $needle = "        if (!empty(\$widgets['verb_distribution'])) {\n";
    if (strpos($screen, $needle) === false) {
        fwrite(STDERR, "ERREUR: point de remplacement introuvable: bloc verb_distribution dans renderDashboard\n");
        exit(1);
    }

    return str_replace($needle, $singleCall . "\n" . $needle, $screen);
}

function itxeb_patch_screen_v02414(string $screen): string
{
    if (strpos($screen, 'class ilIliasTraxEventBridgeCourseUIScreen') === false) {
        fwrite(STDERR, "ERREUR: classe ilIliasTraxEventBridgeCourseUIScreen absente. Restaure depuis origin/main puis relance.\n");
        exit(1);
    }

    foreach ([
        'renderDashboardChartsRow',
        'renderDashboardActivityTopLayout',
        'renderActivityTimelineDashboardContent',
        'renderTopResourcesDashboardPanel',
    ] as $methodName) {
        $screen = itxeb_remove_all_methods_v02414($screen, $methodName);
    }

    $screen = itxeb_patch_render_dashboard_calls_v02414($screen);

    $method = <<<'PHP'

    /** @param array<string,mixed> $dashboard */
    private function renderDashboardActivityTopLayout(array $dashboard, bool $showActivity, bool $showTopResources): string
    {
        // ITXEB V0.24.14 top resources aligned with graph card
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

        $intro = '<p>Vue compacte de l’activité du cours. Le détail complet reste disponible sans occuper toute la page.</p>'
            . $this->renderActivityTimelineSelector($mode);

        $summary = '';
        $chart = '';
        if ($total <= 0) {
            $summary = '<p><em>Aucune activité enregistrée sur la période sélectionnée.</em></p>';
        } elseif ($mode === 'week') {
            $items = $this->aggregateActivityByWeek($daily);
            $summary = $this->renderActivityTimelineSummary($items, 'semaine(s)');
            $chart = $this->renderActivityTimelineLineChart($items);
        } elseif ($mode === 'all') {
            $items = $daily;
            $summaryItems = $periodDays > 30 ? $this->aggregateActivityByWeek($daily) : $daily;
            $summary = $this->renderActivityTimelineSummary($summaryItems, $periodDays > 30 ? 'semaine(s)' : 'jour(s)');
            $chart = '<details class="itxeb-activity-details" open><summary>Détail complet par jour (' . $this->esc((string) count($items)) . ' jour(s))</summary>'
                . $this->renderActivityTimelineLineChart($items)
                . '</details>';
        } else {
            $limit = (int) $mode;
            if ($limit <= 0) { $limit = min(14, $periodDays); }
            $limit = min($limit, $periodDays);
            $items = array_slice($daily, -$limit, null, true);
            $summary = $this->renderActivityTimelineSummary($items, 'jour(s)');
            $chart = $this->renderActivityTimelineLineChart($items);
        }

        $resources = [];
        foreach ((array) ($dashboard['by_resource'] ?? []) as $stats) {
            $traces = (int) ($stats['traces'] ?? 0);
            if ($traces > 0) {
                $resources[(string) ($stats['title'] ?? ('ref_id ' . ($stats['ref_id'] ?? '')))] = $traces;
            }
        }
        $resources = array_slice($resources, 0, 10, true);
        $topResources = '<div class="itxeb-dashboard-top-card"><div class="itxeb-line-chart-head"><div><strong>Top ressources</strong><br><small>Ressources les plus actives sur la période</small></div></div>';
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
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-final{grid-template-columns:260px minmax(0,1fr)!important;column-gap:24px!important;row-gap:8px!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-final>h3{grid-column:1!important;margin:0!important;padding:5px 0 0!important;border:0!important;font-size:16px!important;line-height:1.35!important;color:#333!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-content{grid-column:2!important;min-width:0!important;display:block!important;margin:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-head{margin:0 0 12px!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-chart-row{display:grid!important;grid-template-columns:minmax(0,1.05fr) minmax(420px,.95fr)!important;gap:24px!important;align-items:start!important;margin-top:12px!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-chart-row>.itxeb-line-chart-card{margin:0!important}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-top-card{border:1px solid #d9e2ec;border-radius:10px;background:#fff;padding:14px 16px;margin:0!important;box-shadow:0 1px 4px rgba(0,0,0,.05);min-width:0}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-top-card .itxeb-bar-list{margin-top:8px}'
            . '@media (max-width:1400px){#itxeb-course-ui-screen .itxeb-dashboard-activity-chart-row{grid-template-columns:1fr!important}}'
            . '@media (max-width:900px){#itxeb-course-ui-screen .itxeb-dashboard-activity-final{grid-template-columns:1fr!important}#itxeb-course-ui-screen .itxeb-dashboard-activity-final>h3,#itxeb-course-ui-screen .itxeb-dashboard-activity-content{grid-column:1!important}}'
            . '</style>'
            . '<section class="itxeb-cui-section itxeb-dashboard-activity-final"><h3>Activité dans le temps</h3>'
            . '<div class="itxeb-dashboard-activity-content">'
            . '<div class="itxeb-dashboard-activity-head">' . $intro . $summary . '</div>'
            . '<div class="itxeb-dashboard-activity-chart-row">' . $chart . $topResources . '</div>'
            . '</div></section>';
    }
PHP;

    $anchor = "\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n";
    if (strpos($screen, $anchor) === false) {
        fwrite(STDERR, "ERREUR: point d'insertion introuvable: renderLrsDirectSummary\n");
        exit(1);
    }

    return str_replace($anchor, $method . $anchor, $screen);
}

$screen = itxeb_read_v02414($screenTemplate);
$screen = itxeb_patch_screen_v02414($screen);
itxeb_write_v02414($screenTemplate, $screen);
if (is_file($liveScreen)) {
    itxeb_write_v02414($liveScreen, $screen);
}

$plugin = itxeb_read_v02414($mainPlugin);
$plugin = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.24.14-dev';", $plugin) ?? $plugin;
itxeb_write_v02414($mainPlugin, $plugin);

$companion = itxeb_read_v02414($companionPlugin);
$companion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.34';", $companion) ?? $companion;
itxeb_write_v02414($companionPlugin, $companion);
if (is_file($livePlugin)) {
    $liveCompanion = itxeb_read_v02414($livePlugin);
    $liveCompanion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.34';", $liveCompanion) ?? $liveCompanion;
    itxeb_write_v02414($livePlugin, $liveCompanion);
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

echo "V0.24.14 dashboard top resources graph card alignment applied.\n";
