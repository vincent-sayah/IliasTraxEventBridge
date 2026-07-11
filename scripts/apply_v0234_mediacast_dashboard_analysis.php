<?php

declare(strict_types=1);

/**
 * V0.23.4 MediaCast dashboard/analysis integration.
 *
 * Adds dedicated MediaCast media metrics to the course dashboard and trainer
 * analysis views, based on xAPI statements already read from TRAX/LRS.
 */

$root = dirname(__DIR__);

function itxeb234_read(string $path): string
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

function itxeb234_write(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: écriture impossible: $path\n");
        exit(1);
    }
    echo "WRITE: $path\n";
}

function itxeb234_replace_once(string $content, string $needle, string $replacement, string $label): string
{
    $pos = strpos($content, $needle);
    if ($pos === false) {
        fwrite(STDERR, "ERREUR: bloc introuvable: $label\n");
        exit(1);
    }
    return substr($content, 0, $pos) . $replacement . substr($content, $pos + strlen($needle));
}

function itxeb234_insert_before(string $content, string $needle, string $insert, string $label): string
{
    $pos = strpos($content, $needle);
    if ($pos === false) {
        fwrite(STDERR, "ERREUR: point d'insertion introuvable: $label\n");
        exit(1);
    }
    return substr($content, 0, $pos) . $insert . substr($content, $pos);
}

function itxeb234_set_version(string $path, string $version): void
{
    $content = itxeb234_read($path);
    $new = preg_replace('/\$version\s*=\s*\'[^\']*\';/', '$version = \'' . $version . '\';', $content, 1);
    if (!is_string($new) || $new === $content) {
        fwrite(STDERR, "ERREUR: version introuvable: $path\n");
        exit(1);
    }
    itxeb234_write($path, $new);
}

function itxeb234_lint(string $path): void
{
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $out, $code);
    echo implode("\n", $out) . "\n";
    if ($code !== 0) {
        fwrite(STDERR, "ERREUR: lint PHP en échec: $path\n");
        exit(1);
    }
}

$lrsPath = $root . '/classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php';
$screenTplPath = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$mainPluginPath = $root . '/plugin.php';
$companionPluginTplPath = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';

$lrs = itxeb234_read($lrsPath);
if (strpos($lrs, "by_mediacast_media") === false) {
    $needle = "                'tests_failed' => 0,\n            ],\n";
    $replacement = "                'tests_failed' => 0,\n                'mediacast_internal_played' => 0,\n                'mediacast_external_opened' => 0,\n                'mediacast_media_total' => 0,\n                'mediacast_media_unique' => 0,\n                'mediacast_media_learners' => 0,\n            ],\n";
    $lrs = itxeb234_replace_once($lrs, $needle, $replacement, 'LRS summary MediaCast counters');

    $needle = "            'by_resource' => [],\n            'expert_rows' => [],\n        ];\n";
    $replacement = "            'by_resource' => [],\n            'by_mediacast_media' => [],\n            'mediacast_media_learners' => [],\n            'expert_rows' => [],\n        ];\n";
    $lrs = itxeb234_replace_once($lrs, $needle, $replacement, 'LRS by_mediacast_media container');

    $needle = "        \$summary['by_verb'][\$verbId]['count']++;\n\n        \$key = (string) (\$resource['key'] ?? 'unknown');\n";
    $replacement = "        \$summary['by_verb'][\$verbId]['count']++;\n\n        \$this->addMediaCastMediaStatement(\$summary, \$statement, \$resource, \$actorKey, \$timestamp, \$verbId);\n\n        \$key = (string) (\$resource['key'] ?? 'unknown');\n";
    $lrs = itxeb234_replace_once($lrs, $needle, $replacement, 'LRS MediaCast aggregation call');

    $methodBlock = <<<'PHP'
    /** @param array<string,mixed> $summary @param array<string,mixed> $statement @param array<string,mixed> $resource */
    private function addMediaCastMediaStatement(array &$summary, array $statement, array $resource, string $actorKey, string $timestamp, string $verbId): void
    {
        $resultExtensions = is_array($statement['result']['extensions'] ?? null) ? $statement['result']['extensions'] : [];
        $mediaEvent = (string) $this->extensionValue($resultExtensions, '/media_client_event');
        $mediaTitle = trim((string) $this->extensionValue($resultExtensions, '/media_title'));
        $mediaProvider = trim((string) $this->extensionValue($resultExtensions, '/media_provider'));
        $mediaMime = trim((string) $this->extensionValue($resultExtensions, '/media_mime'));
        $mediaUrl = trim((string) $this->extensionValue($resultExtensions, '/media_url'));
        $mediaId = trim((string) $this->extensionValue($resultExtensions, '/media_id'));

        $isInternal = $mediaEvent === 'media_played' || stripos($verbId, '/played-media') !== false;
        $isExternal = $mediaEvent === 'external_media_opened' || stripos($verbId, '/opened-external-media') !== false;
        if (!$isInternal && !$isExternal) {
            return;
        }

        if ($mediaTitle === '') {
            $mediaTitle = $this->resourceTitle($statement, 'Média MediaCast');
        }
        if ($mediaProvider === '') {
            $mediaProvider = $isExternal ? 'external' : 'ilias';
        }

        $parentRefId = (int) ($resource['ref_id'] ?? 0);
        $parentTitle = trim((string) ($resource['title'] ?? ''));
        if ($parentTitle === '') {
            $parentTitle = $parentRefId > 0 ? 'MediaCast ref_id ' . $parentRefId : 'MediaCast';
        }

        $identity = $mediaId !== '' ? $mediaId : ($mediaUrl !== '' ? $mediaUrl : $mediaTitle);
        $type = $isExternal ? 'external' : 'internal';
        $key = 'mcst:' . $parentRefId . ':' . $type . ':' . sha1($identity);

        if (!isset($summary['by_mediacast_media']) || !is_array($summary['by_mediacast_media'])) {
            $summary['by_mediacast_media'] = [];
        }
        if (!isset($summary['mediacast_media_learners']) || !is_array($summary['mediacast_media_learners'])) {
            $summary['mediacast_media_learners'] = [];
        }
        if (!isset($summary['by_mediacast_media'][$key])) {
            $summary['by_mediacast_media'][$key] = [
                'key' => $key,
                'media_id' => $mediaId,
                'media_title' => $mediaTitle,
                'media_type' => $type,
                'media_provider' => $mediaProvider,
                'media_mime' => $mediaMime,
                'media_url' => $mediaUrl,
                'parent_ref_id' => $parentRefId,
                'parent_obj_id' => (int) ($resource['obj_id'] ?? 0),
                'parent_title' => $parentTitle,
                'total' => 0,
                'played_internal' => 0,
                'opened_external' => 0,
                'learners' => [],
                'learners_count' => 0,
                'last_at' => '',
            ];
        }

        $summary['by_mediacast_media'][$key]['total']++;
        if ($isExternal) {
            $summary['by_mediacast_media'][$key]['opened_external']++;
            $summary['summary']['mediacast_external_opened'] = (int) ($summary['summary']['mediacast_external_opened'] ?? 0) + 1;
        } else {
            $summary['by_mediacast_media'][$key]['played_internal']++;
            $summary['summary']['mediacast_internal_played'] = (int) ($summary['summary']['mediacast_internal_played'] ?? 0) + 1;
        }
        $summary['summary']['mediacast_media_total'] = (int) ($summary['summary']['mediacast_media_total'] ?? 0) + 1;

        if ($actorKey !== '') {
            $summary['by_mediacast_media'][$key]['learners'][$actorKey] = true;
            $summary['mediacast_media_learners'][$actorKey] = true;
        }
        if ($timestamp !== '' && ((string) ($summary['by_mediacast_media'][$key]['last_at'] ?? '') === '' || strcmp($timestamp, (string) $summary['by_mediacast_media'][$key]['last_at']) > 0)) {
            $summary['by_mediacast_media'][$key]['last_at'] = $timestamp;
        }
    }

PHP;
    $insertBefore = "    /** @param array<string,mixed> \$summary @return array<string,mixed> */\n    private function finalize(array \$summary): array";
    $lrs = itxeb234_insert_before($lrs, $insertBefore, $methodBlock, 'LRS MediaCast aggregation method');

    $needle = "        \$summary['pedagogy'] = \$this->finalizePedagogy(\$pedagogy, \$summary);\n\n        uasort(\$summary['by_resource'], static function (array \$a, array \$b): int {\n";
    $replacement = "        \$summary['pedagogy'] = \$this->finalizePedagogy(\$pedagogy, \$summary);\n\n        if (isset(\$summary['by_mediacast_media']) && is_array(\$summary['by_mediacast_media'])) {\n            foreach (\$summary['by_mediacast_media'] as &\$media) {\n                \$media['learners_count'] = count((array) (\$media['learners'] ?? []));\n                unset(\$media['learners']);\n            }\n            unset(\$media);\n            uasort(\$summary['by_mediacast_media'], static function (array \$a, array \$b): int {\n                \$total = (int) (\$b['total'] ?? 0) <=> (int) (\$a['total'] ?? 0);\n                if (\$total !== 0) { return \$total; }\n                return strcmp((string) (\$a['media_title'] ?? ''), (string) (\$b['media_title'] ?? ''));\n            });\n            \$summary['summary']['mediacast_media_unique'] = count(\$summary['by_mediacast_media']);\n        }\n        \$summary['summary']['mediacast_media_learners'] = count((array) (\$summary['mediacast_media_learners'] ?? []));\n        unset(\$summary['mediacast_media_learners']);\n\n        uasort(\$summary['by_resource'], static function (array \$a, array \$b): int {\n";
    $lrs = itxeb234_replace_once($lrs, $needle, $replacement, 'LRS MediaCast finalize');

    $needle = "        if ((int) \$pedagogy['resources_without_trace'] > 0) {\n            \$lines[] = \$pedagogy['resources_without_trace'] . ' ressource(s) activée(s) ne présentent aucune trace TRAX sur la période.';\n        }\n\n";
    $replacement = "        if ((int) \$pedagogy['resources_without_trace'] > 0) {\n            \$lines[] = \$pedagogy['resources_without_trace'] . ' ressource(s) activée(s) ne présentent aucune trace TRAX sur la période.';\n        }\n\n        \$mediaTotal = (int) (\$summary['summary']['mediacast_media_total'] ?? 0);\n        if (\$mediaTotal > 0) {\n            \$lines[] = \$mediaTotal . ' action(s) MediaCast détectée(s) sur des vidéos ou médias externes.';\n        }\n\n";
    $lrs = itxeb234_replace_once($lrs, $needle, $replacement, 'LRS MediaCast pedagogy line');

    itxeb234_write($lrsPath, $lrs);
} else {
    echo "SKIP: LrsCourseSummary déjà patché V0.23.4\n";
}

$screen = itxeb234_read($screenTplPath);
if (strpos($screen, 'renderMediaCastMediaDashboard(') === false) {
    $needle = "            . \$this->metricCard('Score moyen', \$summary['avg_score_raw'] === null ? '-' : (string) \$summary['avg_score_raw'] . ' %', 'Tests')\n            . '</div>';\n";
    $replacement = "            . \$this->metricCard('Score moyen', \$summary['avg_score_raw'] === null ? '-' : (string) \$summary['avg_score_raw'] . ' %', 'Tests')\n            . \$this->metricCard('Vidéos lues', (string) (\$summary['mediacast_internal_played'] ?? 0), 'MediaCast')\n            . \$this->metricCard('Médias externes', (string) (\$summary['mediacast_external_opened'] ?? 0), 'MediaCast')\n            . '</div>'\n            . \$this->renderMediaCastMediaDashboard(\$dashboard);\n";
    $screen = itxeb234_replace_once($screen, $needle, $replacement, 'Screen dashboard MediaCast KPIs');

    $needle = " . \$this->renderTrainerActionSummary(\$dashboard) . \$this->renderPedagogicalSynthesis(\$dashboard) . \$this->renderQuestionFailureHotspots(\$dashboard, \$course);\n";
    $replacement = " . \$this->renderTrainerActionSummary(\$dashboard) . \$this->renderPedagogicalSynthesis(\$dashboard) . \$this->renderQuestionFailureHotspots(\$dashboard, \$course) . \$this->renderMediaCastMediaAnalysis(\$dashboard);\n";
    $screen = itxeb234_replace_once($screen, $needle, $replacement, 'Screen analysis MediaCast section');

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

    /** @param array<string,mixed> $dashboard @return array<int,array<string,mixed>> */
    private function mediaCastMediaRows(array $dashboard): array
    {
        $rows = [];
        foreach ((array) ($dashboard['by_mediacast_media'] ?? []) as $media) {
            if (is_array($media)) { $rows[] = $media; }
        }
        usort($rows, static function (array $a, array $b): int {
            $total = (int) ($b['total'] ?? 0) <=> (int) ($a['total'] ?? 0);
            if ($total !== 0) { return $total; }
            return strcmp((string) ($a['media_title'] ?? ''), (string) ($b['media_title'] ?? ''));
        });
        return $rows;
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
    $insertBefore = "    /**\n     * @param array<string,mixed> \$course\n     */\n    /** @param array<string,mixed> \$dashboard */\n    private function renderStrugglingLearners(array \$dashboard): string";
    $screen = itxeb234_insert_before($screen, $insertBefore, $methodBlock, 'Screen MediaCast dashboard/analysis methods');

    itxeb234_write($screenTplPath, $screen);
} else {
    echo "SKIP: CourseUIScreen déjà patché V0.23.4\n";
}

itxeb234_set_version($mainPluginPath, '0.23.4-dev');
itxeb234_set_version($companionPluginTplPath, '0.8.15');

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

foreach ([$lrsPath, $screenTplPath, $mainPluginPath, $companionPluginTplPath] as $path) {
    itxeb234_lint($path);
}
if (is_file($liveScreen)) { itxeb234_lint($liveScreen); }
if (is_file($livePlugin)) { itxeb234_lint($livePlugin); }

echo "V0.23.4 MediaCast dashboard/analysis integration applied.\n";
