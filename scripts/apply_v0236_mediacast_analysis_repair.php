<?php

declare(strict_types=1);

/**
 * V0.23.6 MediaCast analysis screen repair.
 *
 * Repairs the V0.23.4/V0.23.5 screen patch when LrsCourseSummary has already
 * been patched but the companion CourseUIScreen insertion point was not found.
 */

$root = dirname(__DIR__);

function itxeb236_read(string $path): string
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

function itxeb236_write(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: écriture impossible: $path\n");
        exit(1);
    }
    echo "WRITE: $path\n";
}

function itxeb236_set_version(string $path, string $version): void
{
    $content = itxeb236_read($path);
    $new = preg_replace('/\$version\s*=\s*\'[^\']*\';/', '$version = \'' . $version . '\';', $content, 1);
    if (!is_string($new) || $new === $content) {
        fwrite(STDERR, "ERREUR: version introuvable: $path\n");
        exit(1);
    }
    itxeb236_write($path, $new);
}

function itxeb236_lint(string $path): void
{
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $out, $code);
    echo implode("\n", $out) . "\n";
    if ($code !== 0) {
        fwrite(STDERR, "ERREUR: lint PHP en échec: $path\n");
        exit(1);
    }
}

function itxeb236_insert_before_function(string $content, string $functionNeedle, string $insert, string $label): string
{
    $pos = strpos($content, $functionNeedle);
    if ($pos === false) {
        fwrite(STDERR, "ERREUR: point d'insertion introuvable: $label\n");
        exit(1);
    }
    return substr($content, 0, $pos) . $insert . substr($content, $pos);
}

$screenTplPath = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$lrsPath = $root . '/classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php';
$mainPluginPath = $root . '/plugin.php';
$companionPluginTplPath = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';

$lrs = itxeb236_read($lrsPath);
if (strpos($lrs, 'by_mediacast_media') === false) {
    fwrite(STDERR, "ERREUR: agrégation LRS MediaCast absente. Appliquer d'abord une V0.23.4 corrigée ou vérifier class.ilIliasTraxEventBridgeLrsCourseSummary.php.\n");
    exit(1);
}
echo "OK: LrsCourseSummary contient by_mediacast_media\n";

$screen = itxeb236_read($screenTplPath);

if (strpos($screen, 'renderMediaCastMediaDashboard($dashboard)') === false) {
    $needle = "            . \$this->metricCard('Score moyen', \$summary['avg_score_raw'] === null ? '-' : (string) \$summary['avg_score_raw'] . ' %', 'Tests')\n            . '</div>';";
    $replacement = "            . \$this->metricCard('Score moyen', \$summary['avg_score_raw'] === null ? '-' : (string) \$summary['avg_score_raw'] . ' %', 'Tests')\n            . \$this->metricCard('Vidéos lues', (string) (\$summary['mediacast_internal_played'] ?? 0), 'MediaCast')\n            . \$this->metricCard('Médias externes', (string) (\$summary['mediacast_external_opened'] ?? 0), 'MediaCast')\n            . '</div>'\n            . \$this->renderMediaCastMediaDashboard(\$dashboard);";
    if (strpos($screen, $needle) !== false) {
        $screen = str_replace($needle, $replacement, $screen);
        echo "PATCH: dashboard MediaCast KPIs + bloc médias\n";
    } else {
        echo "WARN: bloc KPI tableau de bord introuvable, le patch Analyse continue.\n";
    }
} else {
    echo "SKIP: appel renderMediaCastMediaDashboard déjà présent\n";
}

if (strpos($screen, 'renderMediaCastMediaAnalysisGroupedByParent($dashboard)') === false) {
    if (strpos($screen, 'renderMediaCastMediaAnalysis($dashboard)') !== false) {
        $screen = str_replace('renderMediaCastMediaAnalysis($dashboard)', 'renderMediaCastMediaAnalysisGroupedByParent($dashboard)', $screen);
        echo "PATCH: appel Analyse MediaCast remplacé par version groupée\n";
    } else {
        $needle = ". \$this->renderQuestionFailureHotspots(\$dashboard, \$course);";
        $replacement = ". \$this->renderQuestionFailureHotspots(\$dashboard, \$course) . \$this->renderMediaCastMediaAnalysisGroupedByParent(\$dashboard);";
        if (strpos($screen, $needle) === false) {
            fwrite(STDERR, "ERREUR: appel renderQuestionFailureHotspots introuvable dans renderAnalysis.\n");
            exit(1);
        }
        $screen = str_replace($needle, $replacement, $screen);
        echo "PATCH: appel Analyse MediaCast groupée ajouté\n";
    }
} else {
    echo "SKIP: appel Analyse MediaCast groupée déjà présent\n";
}

$methodBlock = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderMediaCastMediaDashboard(array $dashboard): string
    {
        $rows = $this->mediaCastMediaRows($dashboard);
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $html = '<section class="itxeb-cui-section itxeb-mediacast-media-dashboard"><h3>Médias MediaCast vus</h3>'
            . '<p>Vue des vidéos internes lancées et des médias externes sélectionnés dans les objets MediaCast.</p>'
            . '<div class="itxeb-kpi-grid">'
            . $this->metricCard('Vidéos internes', (string) ($summary['mediacast_internal_played'] ?? 0), 'lancements')
            . $this->metricCard('Médias externes', (string) ($summary['mediacast_external_opened'] ?? 0), 'ouvertures')
            . $this->metricCard('Médias différents', (string) ($summary['mediacast_media_unique'] ?? 0), 'MediaCast')
            . $this->metricCard('Apprenants', (string) ($summary['mediacast_media_learners'] ?? 0), 'ayant vu un média')
            . '</div>';
        if (count($rows) === 0) {
            return $html . '<p><em>Aucune vidéo ou média externe MediaCast détecté sur la période sélectionnée.</em></p></section>';
        }

        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr><th>Média</th><th>Type</th><th>Actions</th><th>Apprenants</th><th>MediaCast</th><th>Dernière trace</th></tr></thead><tbody>';
        foreach (array_slice($rows, 0, 8) as $media) {
            $html .= '<tr>'
                . '<td><strong>' . $this->esc((string) ($media['media_title'] ?? '')) . '</strong><br><small>' . $this->esc((string) ($media['media_provider'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc($this->mediaCastMediaTypeLabel($media)) . '</td>'
                . '<td>' . $this->esc($this->mediaCastMediaActionText($media)) . '</td>'
                . '<td>' . $this->esc((string) ($media['learners_count'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($media['parent_title'] ?? '')) . '<br><small>ref_id ' . $this->esc((string) ($media['parent_ref_id'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc((string) ($media['last_at'] ?? '')) . '</td>'
                . '</tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    /** @param array<string,mixed> $dashboard */
    private function renderMediaCastMediaAnalysisGroupedByParent(array $dashboard): string
    {
        $groups = $this->mediaCastMediaRowsGroupedByParent($dashboard);
        $html = '<section class="itxeb-cui-section itxeb-mediacast-media-analysis"><h3>Analyse des vidéos MediaCast</h3>'
            . '<p>Les vidéos et médias externes sont regroupés par objet MediaCast afin de voir précisément ce qui a été lu dans chaque MediaCast.</p>';
        if (count($groups) === 0) {
            return $html . '<p><em>Aucun média MediaCast détecté sur la période et le filtre sélectionnés.</em></p></section>';
        }

        foreach ($groups as $group) {
            $rows = is_array($group['rows'] ?? null) ? $group['rows'] : [];
            $html .= '<div class="itxeb-cui-section" style="margin-top:12px">'
                . '<h4>MediaCast : ' . $this->esc((string) ($group['parent_title'] ?? '')) . '</h4>'
                . '<p><small>ref_id ' . $this->esc((string) ($group['parent_ref_id'] ?? '')) . ' — ' . $this->esc((string) count($rows)) . ' média(s) détecté(s)</small></p>'
                . '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr>'
                . '<th>Vidéo / média lu</th><th>Type</th><th>Actions</th><th>Apprenants</th><th>Dernière trace</th><th>URL</th>'
                . '</tr></thead><tbody>';
            foreach ($rows as $media) {
                $html .= '<tr>'
                    . '<td><strong>' . $this->esc((string) ($media['media_title'] ?? '')) . '</strong><br><small>' . $this->esc((string) ($media['media_provider'] ?? '')) . '</small></td>'
                    . '<td>' . $this->esc($this->mediaCastMediaTypeLabel($media)) . '</td>'
                    . '<td>' . $this->esc($this->mediaCastMediaActionText($media)) . '</td>'
                    . '<td>' . $this->esc((string) ($media['learners_count'] ?? 0)) . '</td>'
                    . '<td>' . $this->esc((string) ($media['last_at'] ?? '')) . '</td>'
                    . '<td><small>' . $this->esc($this->shorten((string) ($media['media_url'] ?? ''), 120)) . '</small></td>'
                    . '</tr>';
            }
            $html .= '</tbody></table></div></div>';
        }

        return $html . '</section>';
    }

    /** @param array<string,mixed> $dashboard @return array<int,array<string,mixed>> */
    private function mediaCastMediaRows(array $dashboard): array
    {
        $rows = [];
        foreach ((array) ($dashboard['by_mediacast_media'] ?? []) as $media) {
            if (is_array($media)) {
                $rows[] = $media;
            }
        }
        usort($rows, static function (array $a, array $b): int {
            $total = (int) ($b['total'] ?? 0) <=> (int) ($a['total'] ?? 0);
            if ($total !== 0) { return $total; }
            return strcmp((string) ($a['media_title'] ?? ''), (string) ($b['media_title'] ?? ''));
        });
        return $rows;
    }

    /** @param array<string,mixed> $dashboard @return array<int,array<string,mixed>> */
    private function mediaCastMediaRowsGroupedByParent(array $dashboard): array
    {
        $groups = [];
        foreach ($this->mediaCastMediaRows($dashboard) as $media) {
            $parentRefId = (int) ($media['parent_ref_id'] ?? 0);
            $parentTitle = trim((string) ($media['parent_title'] ?? ''));
            if ($parentTitle === '') {
                $parentTitle = $parentRefId > 0 ? 'MediaCast ref_id ' . $parentRefId : 'MediaCast';
            }
            $key = 'mcst:' . $parentRefId . ':' . $parentTitle;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'parent_ref_id' => $parentRefId,
                    'parent_title' => $parentTitle,
                    'rows' => [],
                ];
            }
            $groups[$key]['rows'][] = $media;
        }
        uasort($groups, static function (array $a, array $b): int {
            return strcmp((string) ($a['parent_title'] ?? ''), (string) ($b['parent_title'] ?? ''));
        });
        return array_values($groups);
    }

    /** @param array<string,mixed> $media */
    private function mediaCastMediaTypeLabel(array $media): string
    {
        return (string) ($media['media_type'] ?? '') === 'external' ? 'Média externe' : 'Vidéo interne';
    }

    /** @param array<string,mixed> $media */
    private function mediaCastMediaActionText(array $media): string
    {
        $internal = (int) ($media['played_internal'] ?? 0);
        $external = (int) ($media['opened_external'] ?? 0);
        $parts = [];
        if ($internal > 0) { $parts[] = $internal . ' lancement(s)'; }
        if ($external > 0) { $parts[] = $external . ' ouverture(s) externe(s)'; }
        return $parts === [] ? '0' : implode(' / ', $parts);
    }

PHP;

if (strpos($screen, 'private function mediaCastMediaRowsGroupedByParent(') === false) {
    if (strpos($screen, 'private function renderMediaCastMediaDashboard(') !== false) {
        fwrite(STDERR, "ERREUR: méthodes MediaCast partielles déjà présentes. Vérifier manuellement CourseUIScreen avant V0.23.6.\n");
        exit(1);
    }
    $screen = itxeb236_insert_before_function(
        $screen,
        '    private function renderStrugglingLearners(array $dashboard): string',
        $methodBlock,
        'avant renderStrugglingLearners'
    );
    echo "PATCH: méthodes MediaCast dashboard/analyse insérées\n";
} else {
    echo "SKIP: méthodes MediaCast groupées déjà présentes\n";
}

itxeb236_write($screenTplPath, $screen);
itxeb236_set_version($mainPluginPath, '0.23.6-dev');
itxeb236_set_version($companionPluginTplPath, '0.8.17');

$liveBase = dirname($root) . '/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$liveScreen = $liveBase . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = $liveBase . '/plugin.php';
if (is_file($liveScreen)) {
    copy($screenTplPath, $liveScreen);
    echo "COPY: $screenTplPath -> $liveScreen\n";
}
if (is_file($livePlugin)) {
    copy($companionPluginTplPath, $livePlugin);
    echo "COPY: $companionPluginTplPath -> $livePlugin\n";
}

foreach ([$screenTplPath, $mainPluginPath, $companionPluginTplPath] as $path) {
    itxeb236_lint($path);
}
if (is_file($liveScreen)) { itxeb236_lint($liveScreen); }
if (is_file($livePlugin)) { itxeb236_lint($livePlugin); }

echo "V0.23.6 MediaCast analysis screen repair applied.\n";
