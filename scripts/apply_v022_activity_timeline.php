<?php
$root = getcwd();
$screen = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$plugin = $root . '/plugin.php';
$companionPlugin = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$servicesRoot = dirname(dirname(dirname($root)));
$liveRoot = $servicesRoot . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$liveScreen = $liveRoot . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = $liveRoot . '/plugin.php';

function rf(string $file): string
{
    $s = file_get_contents($file);
    if (!is_string($s)) {
        fwrite(STDERR, "Lecture impossible: $file\n");
        exit(1);
    }
    return $s;
}

function wf(string $file, string $old, string $new): void
{
    if ($old !== $new) {
        file_put_contents($file, $new);
        echo "WRITE: $file\n";
    } else {
        echo "OK: aucun changement $file\n";
    }
}

function set_version(string $file, string $version): void
{
    if (!is_file($file)) {
        return;
    }
    $old = rf($file);
    $replacement = '$version = \'' . $version . '\';';
    $new = preg_replace('/\$version = \'[^\']*\';/', $replacement, $old, 1);
    if (!is_string($new)) {
        fwrite(STDERR, "Version impossible: $file\n");
        exit(1);
    }
    wf($file, $old, $new);
}

function patch_once(string &$s, string $old, string $new, string $label): void
{
    if (strpos($s, $new) !== false) {
        echo "OK: $label\n";
        return;
    }
    $pos = strpos($s, $old);
    if ($pos === false) {
        fwrite(STDERR, "BLOC INTROUVABLE: $label\n");
        exit(1);
    }
    $s = substr($s, 0, $pos) . $new . substr($s, $pos + strlen($old));
    echo "PATCH: $label\n";
}

foreach ([$screen, $plugin, $companionPlugin] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Fichier absent: $file\n");
        exit(1);
    }
}

$old = rf($screen);
$s = $old;
$oldBlock = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderActivityByDay(array $dashboard): string
    {
        return $this->renderBarSection('Activité par jour', is_array($dashboard['by_day'] ?? null) ? $dashboard['by_day'] : []);
    }

PHP;

$newBlock = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderActivityByDay(array $dashboard): string
    {
        return $this->renderActivityTimeline(is_array($dashboard['by_day'] ?? null) ? $dashboard['by_day'] : []);
    }

    /** @param array<string,int|float|string> $byDay */
    private function renderActivityTimeline(array $byDay): string
    {
        $periodDays = max(1, min(365, $this->getPeriodDays()));
        $mode = $this->getActivityTimelineMode($periodDays);
        $daily = $this->normalizeActivityDays($byDay, $periodDays);
        $total = array_sum(array_map('intval', array_values($daily)));

        $html = '<section class="itxeb-cui-section itxeb-activity-timeline"><h3>Activité dans le temps</h3>'
            . '<p>Vue compacte de l’activité du cours. Le détail complet reste disponible sans occuper toute la page.</p>'
            . $this->renderActivityTimelineSelector($mode);

        if ($total <= 0) {
            return $html . '<p><em>Aucune activité enregistrée sur la période sélectionnée.</em></p></section>';
        }

        if ($mode === 'week') {
            $items = $this->aggregateActivityByWeek($daily);
            return $html . $this->renderActivityTimelineSummary($items, 'semaine(s)') . $this->renderActivityTimelineBars($items) . '</section>';
        }

        if ($mode === 'all') {
            $items = $daily;
            $summaryItems = $periodDays > 30 ? $this->aggregateActivityByWeek($daily) : $daily;
            return $html
                . $this->renderActivityTimelineSummary($summaryItems, $periodDays > 30 ? 'semaine(s)' : 'jour(s)')
                . '<details class="itxeb-activity-details"><summary>Afficher le détail complet par jour (' . $this->esc((string) count($items)) . ' jour(s))</summary>'
                . $this->renderActivityTimelineBars($items)
                . '</details></section>';
        }

        $limit = (int) $mode;
        if ($limit <= 0) { $limit = min(14, $periodDays); }
        $limit = min($limit, $periodDays);
        $items = array_slice($daily, -$limit, null, true);
        return $html . $this->renderActivityTimelineSummary($items, 'jour(s)') . $this->renderActivityTimelineBars($items) . '</section>';
    }

    private function getActivityTimelineMode(int $periodDays): string
    {
        $raw = strtolower(trim($this->requestValue($_GET, 'itxeb_activity_view')));
        if ($raw === '') {
            $raw = strtolower(trim($this->requestValue($_POST, 'itxeb_activity_view')));
        }
        $allowed = ['7' => true, '14' => true, '30' => true, 'week' => true, 'all' => true];
        if (isset($allowed[$raw])) {
            return $raw;
        }
        return $periodDays > 30 ? 'week' : '14';
    }

    private function renderActivityTimelineSelector(string $active): string
    {
        $links = ['7' => '7 jours', '14' => '14 jours', '30' => '30 jours', 'week' => 'Par semaine', 'all' => 'Détail complet'];
        $html = '<div class="itxeb-period-selector"><strong>Affichage activité :</strong> ';
        foreach ($links as $mode => $label) {
            $html .= '<a class="itxeb-period-link' . ($active === $mode ? ' itxeb-active' : '') . '" href="'
                . $this->esc($this->currentUrlWith(['itxeb_cui_cmd' => 'showCourseDashboard', 'itxeb_activity_view' => $mode]))
                . '">' . $this->esc($label) . '</a> ';
        }
        return $html . '</div>';
    }

    /** @param array<string,int|float|string> $byDay @return array<string,int> */
    private function normalizeActivityDays(array $byDay, int $periodDays): array
    {
        $periodDays = max(1, min(365, $periodDays));
        $today = strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC');
        if ($today === false) { $today = time(); }
        $days = [];
        for ($i = $periodDays - 1; $i >= 0; $i--) {
            $days[gmdate('Y-m-d', $today - ($i * 86400))] = 0;
        }
        foreach ($byDay as $day => $count) {
            $key = substr((string) $day, 0, 10);
            if (isset($days[$key])) {
                $days[$key] += (int) $count;
            }
        }
        return $days;
    }

    /** @param array<string,int> $daily @return array<string,int> */
    private function aggregateActivityByWeek(array $daily): array
    {
        $weeks = [];
        foreach ($daily as $day => $count) {
            $ts = strtotime($day . ' 00:00:00 UTC');
            if ($ts === false) { continue; }
            $key = 'Semaine ' . gmdate('o-W', $ts);
            if (!isset($weeks[$key])) { $weeks[$key] = 0; }
            $weeks[$key] += (int) $count;
        }
        return $weeks;
    }

    /** @param array<string,int> $items */
    private function renderActivityTimelineSummary(array $items, string $unitLabel): string
    {
        $total = 0;
        $active = 0;
        $empty = 0;
        $peakLabel = '-';
        $peakCount = 0;
        foreach ($items as $label => $count) {
            $count = (int) $count;
            $total += $count;
            if ($count > 0) { $active++; } else { $empty++; }
            if ($count > $peakCount) {
                $peakCount = $count;
                $peakLabel = (string) $label;
            }
        }
        $average = count($items) > 0 ? round($total / count($items), 1) : 0;
        return '<div class="itxeb-kpi-grid">'
            . $this->metricCard('Activité affichée', (string) $total, 'données d’apprentissage')
            . $this->metricCard('Périodes actives', (string) $active . ' / ' . (string) count($items), $unitLabel)
            . $this->metricCard('Sans activité', (string) $empty, $unitLabel)
            . $this->metricCard('Pic activité', $this->formatActivityTimelineLabel($peakLabel), (string) $peakCount . ' donnée(s)')
            . $this->metricCard('Moyenne', (string) $average, 'par période affichée')
            . '</div>';
    }

    /** @param array<string,int> $items */
    private function renderActivityTimelineBars(array $items): string
    {
        if (count($items) === 0) { return '<p><em>Aucune donnée.</em></p>'; }
        $max = max(array_map('intval', array_values($items)));
        $html = '<div class="itxeb-bar-list itxeb-activity-bars">';
        foreach ($items as $label => $count) {
            $html .= $this->barRow($this->formatActivityTimelineLabel((string) $label), (int) $count, $max);
        }
        return $html . '</div>';
    }

    private function formatActivityTimelineLabel(string $label): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $label) === 1) {
            $ts = strtotime($label . ' 00:00:00 UTC');
            if ($ts !== false) {
                return gmdate('d/m', $ts);
            }
        }
        return $label;
    }

PHP;

patch_once($s, $oldBlock, $newBlock, 'activity timeline compact dashboard');
wf($screen, $old, $s);

set_version($plugin, '0.22.0-dev');
set_version($companionPlugin, '0.8.6');

if (is_file($liveScreen)) {
    copy($screen, $liveScreen);
    echo "COPY: $screen -> $liveScreen\n";
}
if (is_file($livePlugin)) {
    copy($companionPlugin, $livePlugin);
    echo "COPY: $companionPlugin -> $livePlugin\n";
}

$lintFiles = [$screen, $plugin, $companionPlugin];
if (is_file($liveScreen)) { $lintFiles[] = $liveScreen; }
if (is_file($livePlugin)) { $lintFiles[] = $livePlugin; }
foreach ($lintFiles as $file) {
    $cmd = 'php -l ' . escapeshellarg($file);
    passthru($cmd, $code);
    if ($code !== 0) {
        fwrite(STDERR, "PHP lint KO: $file\n");
        exit(1);
    }
}

echo "V0.22 activity timeline applied.\n";
