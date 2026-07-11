<?php
/**
 * V0.24.2-dev
 * Tableau de bord : remplace la représentation en barres de l'activité dans le temps
 * par un graphique linéaire SVG, sans dépendance JavaScript externe.
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

function itxeb_patch_screen(string $screen): string
{
    if (strpos($screen, 'ITXEB V0.24.2 activity line chart') !== false) {
        echo "SKIP: écran déjà patché V0.24.2\n";
        return $screen;
    }

    $screen = str_replace(
        '$this->renderActivityTimelineBars($items)',
        '$this->renderActivityTimelineLineChart($items)',
        $screen
    );

    $newMethod = <<<'PHP'
    /** @param array<string,int> $items */
    private function renderActivityTimelineLineChart(array $items): string
    {
        // ITXEB V0.24.2 activity line chart
        if (count($items) === 0) { return '<p><em>Aucune donnée.</em></p>'; }

        $values = [];
        foreach ($items as $label => $count) {
            $values[(string) $label] = max(0, (int) $count);
        }
        if (count($values) === 0) { return '<p><em>Aucune donnée.</em></p>'; }

        $max = max(1, max(array_values($values)));
        $width = 920;
        $height = 280;
        $padLeft = 54;
        $padRight = 28;
        $padTop = 34;
        $padBottom = 48;
        $chartWidth = $width - $padLeft - $padRight;
        $chartHeight = $height - $padTop - $padBottom;
        $count = count($values);
        $index = 0;
        $points = [];
        $dots = '';
        $xLabels = '';
        $stepLabel = max(1, (int) ceil($count / 6));

        foreach ($values as $label => $value) {
            $x = $padLeft + ($count === 1 ? ($chartWidth / 2) : (($index * $chartWidth) / max(1, $count - 1)));
            $y = $padTop + $chartHeight - (($value / $max) * $chartHeight);
            $points[] = round($x, 2) . ',' . round($y, 2);
            $dots .= '<circle cx="' . $this->esc((string) round($x, 2)) . '" cy="' . $this->esc((string) round($y, 2)) . '" r="4.5"><title>' . $this->esc($this->formatActivityTimelineLabel((string) $label) . ' : ' . (string) $value) . '</title></circle>';
            if ($index === 0 || $index === ($count - 1) || ($index % $stepLabel) === 0) {
                $xLabels .= '<text x="' . $this->esc((string) round($x, 2)) . '" y="' . $this->esc((string) ($height - 16)) . '" text-anchor="middle">' . $this->esc($this->formatActivityTimelineLabel((string) $label)) . '</text>';
            }
            $index++;
        }

        $grid = '';
        for ($i = 0; $i <= 4; $i++) {
            $ratio = $i / 4;
            $value = (int) round($max * (1 - $ratio));
            $y = $padTop + ($chartHeight * $ratio);
            $grid .= '<line x1="' . $padLeft . '" y1="' . $this->esc((string) round($y, 2)) . '" x2="' . ($width - $padRight) . '" y2="' . $this->esc((string) round($y, 2)) . '"></line>'
                . '<text x="' . ($padLeft - 12) . '" y="' . $this->esc((string) (round($y, 2) + 4)) . '" text-anchor="end">' . $this->esc((string) $value) . '</text>';
        }

        $polyline = implode(' ', $points);
        $total = array_sum($values);
        $average = round($total / max(1, count($values)), 1);

        return '<div class="itxeb-line-chart-card">'
            . '<div class="itxeb-line-chart-head"><div><strong>Progression de l’activité</strong><br><small>Données d’apprentissage par période affichée</small></div>'
            . '<div class="itxeb-line-chart-legend"><span class="itxeb-line-dot"></span> Activité</div></div>'
            . '<svg class="itxeb-line-chart" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Activité dans le temps">'
            . '<style>.itxeb-line-chart-card{border:1px solid #d9e2ec;border-radius:10px;background:#fff;padding:14px 16px;margin:12px 0;box-shadow:0 1px 4px rgba(0,0,0,.05)}.itxeb-line-chart-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:8px}.itxeb-line-chart-legend{font-size:13px;color:#444;white-space:nowrap}.itxeb-line-dot{display:inline-block;width:10px;height:10px;border-radius:50%;background:#1f8fc2;margin-right:6px}.itxeb-line-chart{width:100%;height:auto;display:block}.itxeb-line-chart .grid line{stroke:#e6ebf1;stroke-width:1}.itxeb-line-chart text{font-size:12px;fill:#667085}.itxeb-line-chart .axis{stroke:#d0d7de;stroke-width:1.2}.itxeb-line-chart .series{fill:none;stroke:#1f8fc2;stroke-width:4;stroke-linecap:round;stroke-linejoin:round}.itxeb-line-chart .dots circle{fill:#1f8fc2;stroke:#fff;stroke-width:2}</style>'
            . '<g class="grid">' . $grid . '</g>'
            . '<line class="axis" x1="' . $padLeft . '" y1="' . ($height - $padBottom) . '" x2="' . ($width - $padRight) . '" y2="' . ($height - $padBottom) . '"></line>'
            . '<line class="axis" x1="' . $padLeft . '" y1="' . $padTop . '" x2="' . $padLeft . '" y2="' . ($height - $padBottom) . '"></line>'
            . '<polyline class="series" points="' . $this->esc($polyline) . '"></polyline>'
            . '<g class="dots">' . $dots . '</g>'
            . '<g class="x-labels">' . $xLabels . '</g>'
            . '</svg>'
            . '<p style="margin:8px 0 0;color:#555"><small>Total : ' . $this->esc((string) $total) . ' donnée(s) — moyenne : ' . $this->esc((string) $average) . ' par période affichée.</small></p>'
            . '</div>';
    }
PHP;

    $pattern = <<<'REGEX'
/    \/\*\* @param array<string,int> \$items \*\/\n    private function renderActivityTimelineBars\(array \$items\): string\n    \{.*?    \}\n\n    private function formatActivityTimelineLabel/s
REGEX;
    $replacement = $newMethod . "\n\n    private function formatActivityTimelineLabel";
    $patched = preg_replace($pattern, $replacement, $screen, 1, $count);
    if (!is_string($patched) || $count !== 1) {
        fwrite(STDERR, "ERREUR: méthode renderActivityTimelineBars introuvable\n");
        exit(1);
    }

    return $patched;
}

$screen = itxeb_read($screenTemplate);
$screen = itxeb_patch_screen($screen);
itxeb_write($screenTemplate, $screen);
if (is_file($liveScreen)) {
    itxeb_write($liveScreen, $screen);
}

$plugin = itxeb_read($mainPlugin);
$plugin = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.24.2-dev';", $plugin) ?? $plugin;
itxeb_write($mainPlugin, $plugin);

$companion = itxeb_read($companionPlugin);
$companion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.22';", $companion) ?? $companion;
itxeb_write($companionPlugin, $companion);
if (is_file($livePlugin)) {
    $liveCompanion = itxeb_read($livePlugin);
    $liveCompanion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.22';", $liveCompanion) ?? $liveCompanion;
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

echo "V0.24.2 dashboard activity line chart applied.\n";
