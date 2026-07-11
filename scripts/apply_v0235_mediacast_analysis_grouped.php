<?php

declare(strict_types=1);

/**
 * V0.23.5 MediaCast analysis grouped by parent MediaCast.
 *
 * V0.23.4 adds MediaCast media counters. This patch changes the trainer
 * analysis display so the videos/media are shown at the MediaCast level:
 * one block per MediaCast parent, then the videos read inside it.
 */

$root = dirname(__DIR__);

function itxeb235_read(string $path): string
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

function itxeb235_write(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: écriture impossible: $path\n");
        exit(1);
    }
    echo "WRITE: $path\n";
}

function itxeb235_replace_once(string $content, string $needle, string $replacement, string $label): string
{
    $pos = strpos($content, $needle);
    if ($pos === false) {
        fwrite(STDERR, "ERREUR: bloc introuvable: $label\n");
        exit(1);
    }
    return substr($content, 0, $pos) . $replacement . substr($content, $pos + strlen($needle));
}

function itxeb235_insert_before(string $content, string $needle, string $insert, string $label): string
{
    $pos = strpos($content, $needle);
    if ($pos === false) {
        fwrite(STDERR, "ERREUR: point d'insertion introuvable: $label\n");
        exit(1);
    }
    return substr($content, 0, $pos) . $insert . substr($content, $pos);
}

function itxeb235_set_version(string $path, string $version): void
{
    $content = itxeb235_read($path);
    $new = preg_replace('/\$version\s*=\s*\'[^\']*\';/', '$version = \'' . $version . '\';', $content, 1);
    if (!is_string($new) || $new === $content) {
        fwrite(STDERR, "ERREUR: version introuvable: $path\n");
        exit(1);
    }
    itxeb235_write($path, $new);
}

function itxeb235_lint(string $path): void
{
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $out, $code);
    echo implode("\n", $out) . "\n";
    if ($code !== 0) {
        fwrite(STDERR, "ERREUR: lint PHP en échec: $path\n");
        exit(1);
    }
}

$screenTplPath = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$mainPluginPath = $root . '/plugin.php';
$companionPluginTplPath = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';

$screen = itxeb235_read($screenTplPath);
if (strpos($screen, 'renderMediaCastMediaAnalysisGroupedByParent(') === false) {
    if (strpos($screen, 'renderMediaCastMediaAnalysis(') === false || strpos($screen, 'by_mediacast_media') === false) {
        fwrite(STDERR, "ERREUR: V0.23.4 doit être appliquée avant V0.23.5\n");
        exit(1);
    }

    $oldMethod = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderMediaCastMediaAnalysis(array $dashboard): string
    {
        $rows = $this->mediaCastMediaRows($dashboard);
        $html = '<section class="itxeb-cui-section itxeb-mediacast-media-analysis"><h3>Analyse des vidéos MediaCast</h3>'
            . '<p>Cette section permet au formateur d’identifier quels médias ont été réellement consultés.</p>';
        if (count($rows) === 0) {
            return $html . '<p><em>Aucun média MediaCast détecté sur la période et le filtre sélectionnés.</em></p></section>';
        }

        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr><th>Signal</th><th>Média</th><th>Type</th><th>Actions</th><th>Apprenants</th><th>Dernière trace</th><th>URL</th></tr></thead><tbody>';
        foreach ($rows as $media) {
            $total = (int) ($media['total'] ?? 0);
            $learners = (int) ($media['learners_count'] ?? 0);
            $signal = $total <= 0 ? 'Aucune activité' : ($learners <= 1 ? 'Faible diffusion' : 'Consulté');
            $html .= '<tr>'
                . '<td><span class="itxeb-pedagogy-badge ' . ($learners <= 1 ? 'itxeb-pedagogy-watch' : 'itxeb-pedagogy-ok') . '">' . $this->esc($signal) . '</span></td>'
                . '<td><strong>' . $this->esc((string) ($media['media_title'] ?? '')) . '</strong><br><small>' . $this->esc((string) ($media['parent_title'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc($this->mediaCastMediaTypeLabel($media)) . '</td>'
                . '<td>' . $this->esc($this->mediaCastMediaActionText($media)) . '</td>'
                . '<td>' . $this->esc((string) $learners) . '</td>'
                . '<td>' . $this->esc((string) ($media['last_at'] ?? '')) . '</td>'
                . '<td><small>' . $this->esc($this->shorten((string) ($media['media_url'] ?? ''), 120)) . '</small></td>'
                . '</tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

PHP;

    $newMethod = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderMediaCastMediaAnalysis(array $dashboard): string
    {
        return $this->renderMediaCastMediaAnalysisGroupedByParent($dashboard);
    }

    /** @param array<string,mixed> $dashboard */
    private function renderMediaCastMediaAnalysisGroupedByParent(array $dashboard): string
    {
        $groups = $this->mediaCastMediaRowsGroupedByParent($dashboard);
        $html = '<section class="itxeb-cui-section itxeb-mediacast-media-analysis"><h3>Analyse des vidéos MediaCast</h3>'
            . '<p>Vue par objet MediaCast : pour chaque MediaCast, le tableau indique les vidéos internes lancées et les médias externes ouverts.</p>';
        if (count($groups) === 0) {
            return $html . '<p><em>Aucun média MediaCast détecté sur la période et le filtre sélectionnés.</em></p></section>';
        }

        foreach ($groups as $group) {
            $parentTitle = (string) ($group['parent_title'] ?? 'MediaCast');
            $parentRefId = (int) ($group['parent_ref_id'] ?? 0);
            $total = (int) ($group['total'] ?? 0);
            $learners = (int) ($group['learners_count'] ?? 0);
            $mediaRows = is_array($group['media'] ?? null) ? $group['media'] : [];

            $html .= '<section class="itxeb-cui-section itxeb-mediacast-parent-analysis"><h4>MediaCast : ' . $this->esc($parentTitle) . '</h4>'
                . '<div class="itxeb-kpi-grid">'
                . $this->metricCard('Médias vus', (string) count($mediaRows), 'dans ce MediaCast')
                . $this->metricCard('Actions', (string) $total, 'lancements / ouvertures')
                . $this->metricCard('Apprenants', (string) $learners, 'ayant vu au moins un média')
                . $this->metricCard('ref_id', (string) $parentRefId, 'MediaCast')
                . '</div>';

            $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr>'
                . '<th>Vidéo / média lu</th><th>Type</th><th>Actions</th><th>Apprenants</th><th>Dernière trace</th><th>URL</th>'
                . '</tr></thead><tbody>';
            foreach ($mediaRows as $media) {
                if (!is_array($media)) { continue; }
                $html .= '<tr>'
                    . '<td><strong>' . $this->esc((string) ($media['media_title'] ?? '')) . '</strong><br><small>' . $this->esc((string) ($media['media_provider'] ?? '')) . '</small></td>'
                    . '<td>' . $this->esc($this->mediaCastMediaTypeLabel($media)) . '</td>'
                    . '<td>' . $this->esc($this->mediaCastMediaActionText($media)) . '</td>'
                    . '<td>' . $this->esc((string) ($media['learners_count'] ?? 0)) . '</td>'
                    . '<td>' . $this->esc((string) ($media['last_at'] ?? '')) . '</td>'
                    . '<td><small>' . $this->esc($this->shorten((string) ($media['media_url'] ?? ''), 120)) . '</small></td>'
                    . '</tr>';
            }
            $html .= '</tbody></table></div></section>';
        }

        return $html . '</section>';
    }

PHP;

    $screen = itxeb235_replace_once($screen, $oldMethod, $newMethod, 'Screen renderMediaCastMediaAnalysis grouped by parent');

    $groupMethod = <<<'PHP'
    /** @param array<string,mixed> $dashboard @return array<int,array<string,mixed>> */
    private function mediaCastMediaRowsGroupedByParent(array $dashboard): array
    {
        $groups = [];
        foreach ($this->mediaCastMediaRows($dashboard) as $media) {
            if (!is_array($media)) { continue; }
            $parentRefId = (int) ($media['parent_ref_id'] ?? 0);
            $parentKey = 'mcst:' . (string) $parentRefId;
            if (!isset($groups[$parentKey])) {
                $groups[$parentKey] = [
                    'parent_ref_id' => $parentRefId,
                    'parent_obj_id' => (int) ($media['parent_obj_id'] ?? 0),
                    'parent_title' => (string) ($media['parent_title'] ?? ($parentRefId > 0 ? 'MediaCast ref_id ' . $parentRefId : 'MediaCast')),
                    'total' => 0,
                    'learners' => [],
                    'learners_count' => 0,
                    'last_at' => '',
                    'media' => [],
                ];
            }
            $groups[$parentKey]['total'] += (int) ($media['total'] ?? 0);
            $groups[$parentKey]['media'][] = $media;
            $groups[$parentKey]['learners_count'] += (int) ($media['learners_count'] ?? 0);
            $lastAt = (string) ($media['last_at'] ?? '');
            if ($lastAt !== '' && ((string) ($groups[$parentKey]['last_at'] ?? '') === '' || strcmp($lastAt, (string) $groups[$parentKey]['last_at']) > 0)) {
                $groups[$parentKey]['last_at'] = $lastAt;
            }
        }

        $items = array_values($groups);
        usort($items, static function (array $a, array $b): int {
            $total = (int) ($b['total'] ?? 0) <=> (int) ($a['total'] ?? 0);
            if ($total !== 0) { return $total; }
            return strcmp((string) ($a['parent_title'] ?? ''), (string) ($b['parent_title'] ?? ''));
        });
        return $items;
    }

PHP;

    $insertBefore = "    /** @param array<string,mixed> \$dashboard @return array<int,array<string,mixed>> */\n    private function mediaCastMediaRows(array \$dashboard): array";
    $screen = itxeb235_insert_before($screen, $insertBefore, $groupMethod, 'Screen MediaCast grouping helper');

    itxeb235_write($screenTplPath, $screen);
} else {
    echo "SKIP: CourseUIScreen déjà patché V0.23.5\n";
}

itxeb235_set_version($mainPluginPath, '0.23.5-dev');
itxeb235_set_version($companionPluginTplPath, '0.8.16');

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
    itxeb235_lint($path);
}
if (is_file($liveScreen)) { itxeb235_lint($liveScreen); }
if (is_file($livePlugin)) { itxeb235_lint($livePlugin); }

echo "V0.23.5 MediaCast analysis grouped by parent applied.\n";
