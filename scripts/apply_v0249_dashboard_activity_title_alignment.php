<?php
/**
 * V0.24.9-dev
 * Tableau de bord : aligne le titre "Activité dans le temps" sur la colonne des autres titres.
 *
 * Correction :
 * - ne plus encapsuler un <section> Activité complet dans une colonne interne ;
 * - créer une seule section "Activité dans le temps" au niveau du layout ILIAS ;
 * - placer le contenu activité à gauche et Top ressources à droite dans la zone de contenu ;
 * - conserver un seul rendu, sans renderDashboardChartsRow ni appels dupliqués.
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

    // Nettoyage total des anciennes tentatives.
    foreach ([
        'renderDashboardChartsRow',
        'renderDashboardActivityTopLayout',
        'renderActivityTimelineDashboardContent',
        'renderTopResourcesDashboardPanel',
    ] as $method) {
        $screen = itxeb_remove_method($screen, $method);
    }

    // Supprime tous les appels Dashboard liés aux anciens rendus.
    $patterns = [
        '/\n\s*\$html\s*\.=[^;]*renderDashboardChartsRow\([^;]*\);/s',
        '/\n\s*if \(!empty\(\$widgets\[\'activity_by_day\'\]\)\s*\|\|\s*!empty\(\$widgets\[\'top_resources\'\]\)\)\s*\{\s*\$html\s*\.=[^;]*renderDashboardActivityTopLayout\([^;]*\);\s*\}/s',
        '/\n\s*if \(!empty\(\$widgets\[\'activity_by_day\'\]\)\)\s*\{\s*\$html\s*\.=[^;]*renderActivityByDay\(\$dashboard\);\s*\}/s',
        '/\n\s*if \(!empty\(\$widgets\[\'top_resources\'\]\)\)\s*\{\s*\$html\s*\.=[^;]*renderTopResources\(\$dashboard\);\s*\}/s',
    ];
    foreach ($patterns as $pattern) {
        $screen = preg_replace($pattern, "\n", $screen) ?? $screen;
    }

    // Insère un seul appel avant la distribution des actions.
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

    // Méthodes propres : section unique au niveau du layout ILIAS.
    $methods = <<<'PHP'

    /** @param array<string,mixed> $dashboard */
    private function renderDashboardActivityTopLayout(array $dashboard, bool $showActivity, bool $showTopResources): string
    {
        // ITXEB V0.24.9 activity title aligned with dashboard sections
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
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-shell{align-items:start}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-shell>h3{margin-top:0}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-content{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(420px,.95fr);gap:24px;align-items:start;min-width:0}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-left,#itxeb-course-ui-screen .itxeb-dashboard-activity-right{min-width:0}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-right>h3{margin-top:0}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-content .itxeb-kpi-grid{margin-top:12px}'
            . '@media (max-width:1400px){#itxeb-course-ui-screen .itxeb-dashboard-activity-content{grid-template-columns:1fr}}'
            . '</style>'
            . '<section class="itxeb-cui-section itxeb-dashboard-activity-shell"><h3>Activité dans le temps</h3>'
            . '<div class="itxeb-dashboard-activity-content">'
            . '<div class="itxeb-dashboard-activity-left">' . $this->renderActivityTimelineDashboardContent($dashboard) . '</div>'
            . '<div class="itxeb-dashboard-activity-right">' . $this->renderTopResourcesDashboardPanel($dashboard) . '</div>'
            . '</div></section>';
    }

    /** @param array<string,mixed> $dashboard */
    private function renderActivityTimelineDashboardContent(array $dashboard): string
    {
        $byDay = is_array($dashboard['by_day'] ?? null) ? $dashboard['by_day'] : [];
        $periodDays = max(1, min(365, $this->getPeriodDays()));
        $mode = $this->getActivityTimelineMode($periodDays);
        $daily = $this->normalizeActivityDays($byDay, $periodDays);
        $total = array_sum(array_map('intval', array_values($daily)));

        $html = '<p>Vue compacte de l’activité du cours. Le détail complet reste disponible sans occuper toute la page.</p>'
            . $this->renderActivityTimelineSelector($mode);

        if ($total <= 0) {
            return $html . '<p><em>Aucune activité enregistrée sur la période sélectionnée.</em></p>';
        }

        if ($mode === 'week') {
            $items = $this->aggregateActivityByWeek($daily);
            return $html . $this->renderActivityTimelineSummary($items, 'semaine(s)') . $this->renderActivityTimelineVisual($items);
        }

        if ($mode === 'all') {
            $items = $daily;
            $summaryItems = $periodDays > 30 ? $this->aggregateActivityByWeek($daily) : $daily;
            return $html
                . $this->renderActivityTimelineSummary($summaryItems, $periodDays > 30 ? 'semaine(s)' : 'jour(s)')
                . '<details class="itxeb-activity-details"><summary>Afficher le détail complet par jour (' . $this->esc((string) count($items)) . ' jour(s))</summary>'
                . $this->renderActivityTimelineVisual($items)
                . '</details>';
        }

        $limit = (int) $mode;
        if ($limit <= 0) { $limit = min(14, $periodDays); }
        $limit = min($limit, $periodDays);
        $items = array_slice($daily, -$limit, null, true);
        return $html . $this->renderActivityTimelineSummary($items, 'jour(s)') . $this->renderActivityTimelineVisual($items);
    }

    /** @param array<string,int> $items */
    private function renderActivityTimelineVisual(array $items): string
    {
        if (method_exists($this, 'renderActivityTimelineLineChart')) {
            return $this->renderActivityTimelineLineChart($items);
        }
        return $this->renderActivityTimelineBars($items);
    }

    /** @param array<string,mixed> $dashboard */
    private function renderTopResourcesDashboardPanel(array $dashboard): string
    {
        $items = [];
        foreach ((array) ($dashboard['by_resource'] ?? []) as $stats) {
            if ((int) ($stats['traces'] ?? 0) > 0) {
                $items[(string) ($stats['title'] ?? ('ref_id ' . ($stats['ref_id'] ?? '')))] = (int) ($stats['traces'] ?? 0);
            }
        }
        $items = array_slice($items, 0, 10, true);
        if (count($items) === 0) {
            return '<h3>Top ressources</h3><p><em>Aucune donnée.</em></p>';
        }
        $max = max(array_map('intval', array_values($items)));
        $html = '<h3>Top ressources</h3><div class="itxeb-bar-list itxeb-top-resources-dashboard">';
        foreach ($items as $label => $count) {
            $html .= $this->barRow((string) $label, (int) $count, $max);
        }
        return $html . '</div>';
    }
PHP;

    $screen = itxeb_replace_once(
        $screen,
        "\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n",
        $methods . "\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n",
        'insertion méthodes activité/top ressources alignées'
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
$plugin = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.24.9-dev';", $plugin) ?? $plugin;
itxeb_write($mainPlugin, $plugin);

$companion = itxeb_read($companionPlugin);
$companion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.29';", $companion) ?? $companion;
itxeb_write($companionPlugin, $companion);
if (is_file($livePlugin)) {
    $liveCompanion = itxeb_read($livePlugin);
    $liveCompanion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.29';", $liveCompanion) ?? $liveCompanion;
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

echo "V0.24.9 dashboard activity title alignment applied.\n";
