<?php
$root = getcwd();
$screen = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$plugin = $root . '/plugin.php';
$companionPlugin = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$servicesRoot = dirname(dirname(dirname($root)));
$liveRoot = $servicesRoot . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$liveScreen = $liveRoot . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = $liveRoot . '/plugin.php';

function v0221_read(string $file): string
{
    $s = file_get_contents($file);
    if (!is_string($s)) {
        fwrite(STDERR, "Lecture impossible: $file\n");
        exit(1);
    }
    return $s;
}

function v0221_write(string $file, string $old, string $new): void
{
    if ($old !== $new) {
        file_put_contents($file, $new);
        echo "WRITE: $file\n";
    } else {
        echo "OK: aucun changement $file\n";
    }
}

function v0221_set_version(string $file, string $version): void
{
    if (!is_file($file)) { return; }
    $old = v0221_read($file);
    $replacement = '$version = ' . "'" . $version . "'" . ';';
    $new = preg_replace('/\$version = \'[^\']*\';/', $replacement, $old, 1);
    if (!is_string($new)) {
        fwrite(STDERR, "Version impossible: $file\n");
        exit(1);
    }
    v0221_write($file, $old, $new);
}

function v0221_replace_regex(string &$s, string $pattern, string $replacement, string $label, string $marker): void
{
    if ($marker !== '' && strpos($s, $marker) !== false) {
        echo "OK: $label\n";
        return;
    }
    $new = preg_replace($pattern, $replacement, $s, 1, $count);
    if (!is_string($new)) {
        fwrite(STDERR, "REGEX invalide: $label\n");
        exit(1);
    }
    if ($count !== 1) {
        echo "SKIP: bloc introuvable $label\n";
        return;
    }
    $s = $new;
    echo "PATCH: $label\n";
}

function v0221_replace_string(string &$s, string $old, string $new, string $label, string $marker = ''): void
{
    if ($marker !== '' && strpos($s, $marker) !== false) {
        echo "OK: $label\n";
        return;
    }
    if (strpos($s, $new) !== false) {
        echo "OK: $label\n";
        return;
    }
    $pos = strpos($s, $old);
    if ($pos === false) {
        echo "SKIP: bloc introuvable $label\n";
        return;
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

$old = v0221_read($screen);
$s = $old;

if (strpos($s, 'itxeb-ilias-row') === false) {
    $css = '#itxeb-course-ui-screen .itxeb-ilias-like-section{border-top:1px solid #ddd;padding-top:8px}'
        . '#itxeb-course-ui-screen .itxeb-ilias-row{display:grid;grid-template-columns:260px minmax(0,1fr);gap:18px;border-top:1px solid #e5e5e5;padding:12px 0;align-items:start}'
        . '#itxeb-course-ui-screen .itxeb-ilias-row:first-of-type{border-top:0}'
        . '#itxeb-course-ui-screen .itxeb-ilias-label{font-weight:600;color:#333;line-height:1.35;padding-top:6px}'
        . '#itxeb-course-ui-screen .itxeb-ilias-value{min-width:0}'
        . '#itxeb-course-ui-screen .itxeb-ilias-value>p:first-child{margin-top:0}'
        . '#itxeb-course-ui-screen .itxeb-ilias-value .itxeb-kpi-grid{margin-top:0}'
        . '#itxeb-course-ui-screen .itxeb-ilias-value .itxeb-bar-list{margin-top:0}'
        . '#itxeb-course-ui-screen .itxeb-ilias-value .itxeb-cui-table-wrapper{margin-top:0}'
        . '#itxeb-course-ui-screen .itxeb-ilias-hint{display:block;font-weight:400;color:#777;font-size:12px;margin-top:3px}'
        . '@media(max-width:900px){#itxeb-course-ui-screen .itxeb-ilias-row{grid-template-columns:1fr;gap:6px}#itxeb-course-ui-screen .itxeb-ilias-label{padding-top:0}}';
    $needle = "</style>';";
    $pos = strrpos($s, $needle);
    if ($pos === false) {
        fwrite(STDERR, "Point insertion CSS introuvable\n");
        exit(1);
    }
    $s = substr($s, 0, $pos) . $css . substr($s, $pos);
    echo "PATCH: CSS ILIAS-like\n";
} else {
    echo "OK: CSS ILIAS-like\n";
}

if (strpos($s, 'private function renderIliasLikeRow(') === false) {
    $needle = <<<'PHP_CODE'
    private function row(string $label, string $value): string
    {
        return '<tr><td><strong>' . $this->esc($label) . '</strong></td><td>' . $this->esc($value) . '</td></tr>';
    }

PHP_CODE;
    $helper = <<<'PHP_CODE'
    private function renderIliasLikeRow(string $label, string $content, string $hint = ''): string
    {
        $labelHtml = $this->esc($label);
        if ($hint !== '') {
            $labelHtml .= '<span class="itxeb-ilias-hint">' . $this->esc($hint) . '</span>';
        }
        return '<div class="itxeb-ilias-row"><div class="itxeb-ilias-label">' . $labelHtml . '</div><div class="itxeb-ilias-value">' . $content . '</div></div>';
    }

PHP_CODE;
    v0221_replace_string($s, $needle, $helper . $needle, 'helper renderIliasLikeRow', 'private function renderIliasLikeRow(');
}

$kpiReplacement = <<<'PHP_CODE'
            . $this->renderIliasLikeRow('Indicateurs clés', '<div class="itxeb-kpi-grid">'
                . $this->metricCard('Statements TRAX', (string) ($summary['total'] ?? 0), 'Lecture LRS')
                . $this->metricCard('Apprenants actifs', (string) ($summary['active_learners'] ?? 0), 'Comptage anonyme')
                . $this->metricCard('Ressources utilisées', (string) ($summary['resources_with_traces'] ?? 0) . ' / ' . (string) ($summary['resources_total'] ?? 0), 'Au moins une trace')
                . $this->metricCard('Sans statement TRAX', (string) $this->countEnabledWithoutTraceResources($dashboard), 'À surveiller')
                . $this->metricCard('Pages LRS', (string) ($dashboard['pages'] ?? 0), 'pagination')
                . $this->metricCard('Critiques', (string) ($dashboard['pedagogy']['critical_count'] ?? 0), 'Priorité')
                . $this->metricCard('À surveiller', (string) ($dashboard['pedagogy']['watch_count'] ?? 0), 'Signal pédagogique')
                . $this->metricCard('Score moyen', $summary['avg_score_raw'] === null ? '-' : (string) $summary['avg_score_raw'] . ' %', 'Tests')
                . '</div>', 'Résumé de la période sélectionnée');
PHP_CODE;
v0221_replace_regex(
    $s,
    "~\n\s+\. '<div class=\"itxeb-kpi-grid\">'\n\s+\. \$this->metricCard\('Statements TRAX'.*?\n\s+\. '</div>';~s",
    "\n" . $kpiReplacement,
    'dashboard KPI ILIAS-like',
    "renderIliasLikeRow('Indicateurs clés'"
);

$barReplacement = <<<'PHP_CODE'
    /** @param array<string,int> $items */
    private function renderBarSection(string $title, array $items): string
    {
        $section = '<section class="itxeb-cui-section itxeb-ilias-like-section"><h3>' . $this->esc($title) . '</h3>';
        if (count($items) === 0) {
            return $section . $this->renderIliasLikeRow('Données', '<p><em>Aucune donnée.</em></p>') . '</section>';
        }
        $max = max(array_map('intval', array_values($items)));
        $content = '<div class="itxeb-bar-list">';
        foreach ($items as $label => $count) {
            $content .= $this->barRow((string) $label, (int) $count, $max);
        }
        return $section . $this->renderIliasLikeRow('Données', $content . '</div>') . '</section>';
    }

    private function renderInnerTabs
PHP_CODE;
v0221_replace_regex(
    $s,
    "~\n    /\*\* @param array<string,int> \\$items \*/\n    private function renderBarSection\(string \\$title, array \\$items\): string\n    \{.*?\n    \}\n\n    private function renderInnerTabs~s",
    "\n" . $barReplacement,
    'bar sections ILIAS-like',
    'itxeb-ilias-like-section"><h3>'
);

$pedagoReplacement = <<<'PHP_CODE'
    /** @param array<string,mixed> $dashboard */
    private function renderPedagogicalSynthesis(array $dashboard): string
    {
        $pedagogy = is_array($dashboard['pedagogy'] ?? null) ? $dashboard['pedagogy'] : [];
        $lines = is_array($pedagogy['synthesis_lines'] ?? null) ? $pedagogy['synthesis_lines'] : [];
        $content = '<div class="itxeb-pedagogy-summary"><div class="itxeb-pedagogy-kpis">'
            . $this->metricCard('OK', (string) ($pedagogy['ok_count'] ?? 0), 'Ressources sans signal')
            . $this->metricCard('À surveiller', (string) ($pedagogy['watch_count'] ?? 0), 'Signal faible')
            . $this->metricCard('Critiques', (string) ($pedagogy['critical_count'] ?? 0), 'Priorité')
            . $this->metricCard('Sans trace', (string) ($pedagogy['resources_without_trace'] ?? 0), 'Sans statement TRAX')
            . '</div>';
        if (count($lines) > 0) {
            $content .= '<ul class="itxeb-pedagogy-lines">';
            foreach ($lines as $line) {
                if (is_scalar($line) && trim((string) $line) !== '') {
                    $content .= '<li>' . $this->esc((string) $line) . '</li>';
                }
            }
            $content .= '</ul>';
        }
        $content .= '</div>';
        return '<section class="itxeb-cui-section itxeb-ilias-like-section"><h3>Synthèse pédagogique</h3>'
            . $this->renderIliasLikeRow('Résumé', $content, 'Indicateurs et signaux de la période')
            . '</section>';
    }
    /** @param array<string,mixed> $dashboard @param array<string,mixed> $course */
PHP_CODE;
v0221_replace_regex(
    $s,
    "~\n    /\*\* @param array<string,mixed> \\$dashboard \*/\n    private function renderPedagogicalSynthesis\(array \\$dashboard\): string\n    \{.*?\n    \}\n    /\*\* @param array<string,mixed> \\$dashboard @param array<string,mixed> \\$course \*/~s",
    "\n" . $pedagoReplacement,
    'pedagogical synthesis ILIAS-like',
    "renderIliasLikeRow('Résumé'"
);

$activityPatches = [
    ["return \$html . '<p><em>Aucune activité enregistrée sur la période sélectionnée.</em></p></section>';", "return \$html . \$this->renderIliasLikeRow('Vue', '<p><em>Aucune activité enregistrée sur la période sélectionnée.</em></p>') . '</section>';", 'activity empty', "renderIliasLikeRow('Vue', '<p><em>Aucune activité enregistrée"],
    ["return \$html . \$this->renderActivityTimelineSummary(\$items, 'semaine(s)') . \$this->renderActivityTimelineBars(\$items) . '</section>';", "return \$html . \$this->renderIliasLikeRow('Vue hebdomadaire', \$this->renderActivityTimelineSummary(\$items, 'semaine(s)') . \$this->renderActivityTimelineBars(\$items)) . '</section>';", 'activity week', "renderIliasLikeRow('Vue hebdomadaire'"],
    ["return \$html . \$this->renderActivityTimelineSummary(\$items, 'jour(s)') . \$this->renderActivityTimelineBars(\$items) . '</section>';", "return \$html . \$this->renderIliasLikeRow('Vue quotidienne', \$this->renderActivityTimelineSummary(\$items, 'jour(s)') . \$this->renderActivityTimelineBars(\$items)) . '</section>';", 'activity day', "renderIliasLikeRow('Vue quotidienne'"],
];
foreach ($activityPatches as $p) {
    v0221_replace_string($s, $p[0], $p[1], $p[2], $p[3]);
}

v0221_write($screen, $old, $s);
v0221_set_version($plugin, '0.22.1-dev');
v0221_set_version($companionPlugin, '0.8.7');

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

echo "V0.22.1 ILIAS-like dashboard layout applied.\n";
