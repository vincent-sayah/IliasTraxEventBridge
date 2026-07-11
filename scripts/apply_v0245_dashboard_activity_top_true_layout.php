<?php
/**
 * V0.24.5-dev
 * Tableau de bord : vrai layout du bloc Activité dans le temps + Top ressources.
 *
 * Objectif :
 * - ne plus mettre Top ressources à côté du sous-graphe uniquement ;
 * - créer une seule section "Activité dans le temps" ;
 * - placer dans le contenu de cette section deux colonnes :
 *   gauche = intro + boutons + KPI + graphique linéaire ;
 *   droite = Top ressources ;
 * - éviter les classes V0.24.3/V0.24.4 trop ambiguës.
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
    // Nettoyage des anciennes variantes V0.24.3/V0.24.4 si présentes.
    $screen = preg_replace(
        '/\n    \/\*\* @param array<string,mixed> \$dashboard \*\/\n    private function renderDashboardChartsRow\(array \$dashboard, bool \$showActivity, bool \$showTopResources\): string\n    \{.*?\n    \}\n/s',
        "\n",
        $screen
    ) ?? $screen;

    $screen = preg_replace(
        '/\n    \/\*\* @param array<string,mixed> \$dashboard \*\/\n    private function renderDashboardActivityTopLayout\(array \$dashboard, bool \$showActivity, bool \$showTopResources\): string\n    \{.*?\n    \}\n/s',
        "\n",
        $screen
    ) ?? $screen;

    $screen = preg_replace(
        '/\n    \/\*\* @param array<string,mixed> \$dashboard \*\/\n    private function renderActivityTimelineDashboardContent\(array \$dashboard\): string\n    \{.*?\n    \}\n/s',
        "\n",
        $screen
    ) ?? $screen;

    $screen = preg_replace(
        '/\n    \/\*\* @param array<string,mixed> \$dashboard \*\/\n    private function renderTopResourcesDashboardPanel\(array \$dashboard\): string\n    \{.*?\n    \}\n/s',
        "\n",
        $screen
    ) ?? $screen;

    // Retire les appels séparés ou l'ancien regroupement pour repartir proprement.
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

    $screen = itxeb_replace_once(
        $screen,
        "        if (!empty(\$widgets['verb_distribution'])) {\n",
        $newCall . "\n        if (!empty(\$widgets['verb_distribution'])) {\n",
        'insertion appel vrai layout activité/top ressources'
    );

    $methods = <<<'PHP'

    /** @param array<string,mixed> $dashboard */
    private function renderDashboardActivityTopLayout(array $dashboard, bool $showActivity, bool $showTopResources): string
    {
        // ITXEB V0.24.5 activity/top true layout
        if (!$showActivity && !$showTopResources) {
            return '';
        }
        if ($showActivity && !$showTopResources) {
            return $this->renderActivityByDay($dashboard);
        }
        if (!$showActivity && $showTopResources) {
            return $this->renderTopResources($dashboard);
        }

        return '<section class="itxeb-cui-section itxeb-dashboard-activity-top-section"><h3>Activité dans le temps</h3>'
            . '<style>'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-top-content{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(420px,.95fr);gap:24px;align-items:start}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-top-left,#itxeb-course-ui-screen .itxeb-dashboard-activity-top-side{min-width:0}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-top-side .itxeb-top-resources-panel{margin:0}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-top-side h3{margin-top:0}'
            . '#itxeb-course-ui-screen .itxeb-dashboard-activity-top-section .itxeb-line-chart{margin-top:14px}'
            . '@media (max-width:1400px){#itxeb-course-ui-screen .itxeb-dashboard-activity-top-content{grid-template-columns:1fr}}'
            . '</style>'
            . '<div class="itxeb-dashboard-activity-top-content">'
            . '<div class="itxeb-dashboard-activity-top-left">' . $this->renderActivityTimelineDashboardContent($dashboard) . '</div>'
            . '<div class="itxeb-dashboard-activity-top-side">' . $this->renderTopResourcesDashboardPanel($dashboard) . '</div>'
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
            return $html . $this->renderActivityTimelineSummary($items, 'semaine(s)') . $this->renderActivityTimelineLineChart($items);
        }

        if ($mode === 'all') {
            $items = $daily;
            $summaryItems = $periodDays > 30 ? $this->aggregateActivityByWeek($daily) : $daily;
            return $html
                . $this->renderActivityTimelineSummary($summaryItems, $periodDays > 30 ? 'semaine(s)' : 'jour(s)')
                . '<details class="itxeb-activity-details"><summary>Afficher le détail complet par jour (' . $this->esc((string) count($items)) . ' jour(s))</summary>'
                . $this->renderActivityTimelineLineChart($items)
                . '</details>';
        }

        $limit = (int) $mode;
        if ($limit <= 0) { $limit = min(14, $periodDays); }
        $limit = min($limit, $periodDays);
        $items = array_slice($daily, -$limit, null, true);
        return $html . $this->renderActivityTimelineSummary($items, 'jour(s)') . $this->renderActivityTimelineLineChart($items);
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
            return '<div class="itxeb-top-resources-panel"><h3>Top ressources</h3><p><em>Aucune donnée.</em></p></div>';
        }
        $max = max(array_map('intval', array_values($items)));
        $html = '<div class="itxeb-top-resources-panel"><h3>Top ressources</h3><div class="itxeb-bar-list">';
        foreach ($items as $label => $count) {
            $html .= $this->barRow((string) $label, (int) $count, $max);
        }
        return $html . '</div></div>';
    }
PHP;

    return itxeb_replace_once(
        $screen,
        "\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n",
        $methods . "\n    /** @param array<string,mixed> \$course */\n    private function renderLrsDirectSummary(array \$course): string\n",
        'insertion méthodes V0.24.5 vrai layout activité/top ressources'
    );
}

$screen = itxeb_read($screenTemplate);
$screen = itxeb_patch_screen($screen);
itxeb_write($screenTemplate, $screen);
if (is_file($liveScreen)) {
    itxeb_write($liveScreen, $screen);
}

$plugin = itxeb_read($mainPlugin);
$plugin = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.24.5-dev';", $plugin) ?? $plugin;
itxeb_write($mainPlugin, $plugin);

$companion = itxeb_read($companionPlugin);
$companion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.25';", $companion) ?? $companion;
itxeb_write($companionPlugin, $companion);
if (is_file($livePlugin)) {
    $liveCompanion = itxeb_read($livePlugin);
    $liveCompanion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.25';", $liveCompanion) ?? $liveCompanion;
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

echo "V0.24.5 dashboard true activity/top resources layout applied.\n";
