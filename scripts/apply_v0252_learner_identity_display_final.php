<?php

declare(strict_types=1);

/**
 * V0.25.2 — Affichage nominatif des apprenants dans Analyse et Expert.
 *
 * Patch final plus robuste :
 * - ajoute learner_identity dans les lignes lues depuis TRAX/LRS ;
 * - affiche l'identité apprenant dans "Apprenants en difficulté" ;
 * - ajoute la colonne "Apprenant" dans Expert entre "User ID" et "Verbe" ;
 * - ajoute learner_identity dans l'export CSV Expert ;
 * - conserve user_id comme identifiant technique pseudonymisé.
 */

$root = dirname(__DIR__);
$iliasRoot = rtrim((string) (getenv('ILIAS_ROOT') ?: '/var/www/ilias'), '/');
$liveCompanion = $iliasRoot . '/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$stamp = date('YmdHis');
$backupDir = sys_get_temp_dir() . '/itxeb_v0252_backup_' . $stamp;

$summaryFile = $root . '/classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php';
$screenTemplate = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$liveScreen = $liveCompanion . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$mainPlugin = $root . '/plugin.php';
$companionPluginTemplate = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$liveCompanionPlugin = $liveCompanion . '/plugin.php';

function fail_v0252(string $message): void
{
    fwrite(STDERR, 'ERREUR: ' . $message . PHP_EOL);
    exit(1);
}

function read_file_v0252(string $path): string
{
    if (!is_file($path)) {
        fail_v0252('fichier introuvable: ' . $path);
    }
    $content = file_get_contents($path);
    if (!is_string($content)) {
        fail_v0252('lecture impossible: ' . $path);
    }
    return $content;
}

function backup_file_v0252(string $path, string $backupDir): void
{
    if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
        fail_v0252('creation dossier backup impossible: ' . $backupDir);
    }
    if (!is_file($path)) {
        return;
    }
    $target = $backupDir . '/' . str_replace('/', '__', ltrim($path, '/'));
    if (!copy($path, $target)) {
        fail_v0252('backup impossible: ' . $path);
    }
}

function write_file_v0252(string $path, string $content, string $backupDir): void
{
    backup_file_v0252($path, $backupDir);
    if (file_put_contents($path, $content) === false) {
        fail_v0252('ecriture impossible: ' . $path);
    }
}

function replace_once_v0252(string $content, string $search, string $replace, string $label): string
{
    if (strpos($content, $replace) !== false) {
        return $content;
    }
    $pos = strpos($content, $search);
    if ($pos === false) {
        fail_v0252('point de remplacement introuvable: ' . $label);
    }
    return substr($content, 0, $pos) . $replace . substr($content, $pos + strlen($search));
}

/** @return array{0:int,1:int} */
function method_bounds_v0252(string $content, string $methodName): array
{
    $needle = 'private function ' . $methodName . '(';
    $start = strpos($content, $needle);
    if ($start === false) {
        fail_v0252('methode introuvable: ' . $methodName);
    }
    if (strpos($content, $needle, $start + 1) !== false) {
        fail_v0252('methode en double: ' . $methodName);
    }
    $brace = strpos($content, '{', $start);
    if ($brace === false) {
        fail_v0252('accolade introuvable: ' . $methodName);
    }
    $depth = 0;
    $len = strlen($content);
    for ($i = $brace; $i < $len; $i++) {
        $ch = $content[$i];
        if ($ch === '{') {
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                return [$start, $i + 1];
            }
        }
    }
    fail_v0252('fin de methode introuvable: ' . $methodName);
}

function replace_method_v0252(string $content, string $methodName, string $newMethod): string
{
    [$start, $end] = method_bounds_v0252($content, $methodName);
    return substr($content, 0, $start) . rtrim($newMethod) . substr($content, $end);
}

function patch_lrs_summary_v0252(string $content): string
{
    $content = replace_once_v0252(
        $content,
        "            'user_id' => $actorKey === '' ? '' : substr(sha1($actorKey), 0, 10),\n            'verb_label' => $verbLabel,",
        "            'user_id' => $actorKey === '' ? '' : substr(sha1($actorKey), 0, 10),\n            'learner_identity' => $this->actorDisplayName($statement, $actorKey),\n            'verb_label' => $verbLabel,",
        'ajout learner_identity dans expert_rows'
    );

    if (strpos($content, 'private function actorDisplayName(') === false) {
        $marker = "    /** @param array<string,mixed> \$statement */\n    private function actorKey(array \$statement): string";
        $method = <<<'PHP'
    /** @param array<string,mixed> $statement */
    private function actorDisplayName(array $statement, string $actorKey = ''): string
    {
        $name = $statement['actor']['name'] ?? '';
        if (is_scalar($name) && trim((string) $name) !== '') {
            return trim((string) $name);
        }

        $accountName = $statement['actor']['account']['name'] ?? '';
        if (is_scalar($accountName) && trim((string) $accountName) !== '') {
            return trim((string) $accountName);
        }

        $mbox = $statement['actor']['mbox'] ?? '';
        if (is_scalar($mbox) && trim((string) $mbox) !== '') {
            $clean = preg_replace('/^mailto:/i', '', trim((string) $mbox));
            return is_string($clean) && $clean !== '' ? $clean : trim((string) $mbox);
        }

        if ($actorKey !== '') {
            if (strpos($actorKey, 'account:') === 0) {
                return substr($actorKey, 8);
            }
            if (strpos($actorKey, 'mbox:') === 0) {
                $value = substr($actorKey, 5);
                $clean = preg_replace('/^mailto:/i', '', $value);
                return is_string($clean) && $clean !== '' ? $clean : $value;
            }
            return $actorKey;
        }

        return '';
    }

PHP;
        $content = replace_once_v0252($content, $marker, $method . $marker, 'insertion actorDisplayName');
    }

    return $content;
}

function new_render_struggling_learners_v0252(): string
{
    return <<<'PHP'
    private function renderStrugglingLearners(array $dashboard): string
    {
        $rows = is_array($dashboard['expert_rows'] ?? null) ? $dashboard['expert_rows'] : [];
        $learners = [];

        foreach ($rows as $row) {
            if (!is_array($row) || (string) ($row['obj_type'] ?? '') !== 'tst') {
                continue;
            }

            $learnerIdentity = trim((string) ($row['learner_identity'] ?? ''));
            $userId = trim((string) ($row['user_id'] ?? ''));
            $learnerKey = $learnerIdentity !== '' ? $learnerIdentity : $userId;
            if ($learnerKey === '') {
                continue;
            }

            $score = is_numeric($row['score_raw'] ?? null) ? (float) $row['score_raw'] : null;
            $success = $row['success'] ?? null;
            $verbId = (string) ($row['verb_id'] ?? '');
            $failed = ($success === false) || stripos($verbId, 'failed') !== false;
            $lowScore = $score !== null && $score < 50.0;

            if (!$failed && !$lowScore) {
                continue;
            }

            if (!isset($learners[$learnerKey])) {
                $learners[$learnerKey] = [
                    'learner_identity' => $learnerIdentity !== '' ? $learnerIdentity : $userId,
                    'user_id' => $userId,
                    'alerts' => 0,
                    'failed' => 0,
                    'low_scores' => 0,
                    'scores_total' => 0.0,
                    'scores_count' => 0,
                    'last_at' => '',
                    'resources' => [],
                ];
            }

            $learners[$learnerKey]['alerts']++;
            if ($failed) {
                $learners[$learnerKey]['failed']++;
            }
            if ($lowScore) {
                $learners[$learnerKey]['low_scores']++;
            }
            if ($score !== null) {
                $learners[$learnerKey]['scores_total'] += $score;
                $learners[$learnerKey]['scores_count']++;
            }
            $createdAt = (string) ($row['created_at'] ?? '');
            if ($createdAt !== '' && $createdAt > (string) $learners[$learnerKey]['last_at']) {
                $learners[$learnerKey]['last_at'] = $createdAt;
            }
            $title = trim((string) ($row['object_title'] ?? ''));
            if ($title !== '') {
                $learners[$learnerKey]['resources'][$title] = true;
            }
        }

        $visible = [];
        foreach ($learners as $learner) {
            if ((int) $learner['failed'] >= 2 || (int) $learner['low_scores'] >= 2 || (int) $learner['alerts'] >= 3) {
                $learner['avg_score'] = (int) $learner['scores_count'] > 0
                    ? round(((float) $learner['scores_total']) / max(1, (int) $learner['scores_count']), 1)
                    : null;
                $visible[] = $learner;
            }
        }

        usort($visible, static function (array $a, array $b): int {
            return ((int) $b['alerts'] <=> (int) $a['alerts']) ?: ((int) $b['failed'] <=> (int) $a['failed']);
        });
        $visible = array_slice($visible, 0, 10);

        $html = '<section class="itxeb-cui-section"><h3>Apprenants en difficulté</h3>'
            . '<p>Vue nominative : les apprenants sont affichés avec l’identité transmise par TRAX/LRS afin de faciliter le suivi formateur.</p>';
        if (count($visible) === 0) {
            return $html . '<p><em>Aucun apprenant en difficulté détecté sur la période et le filtre sélectionnés.</em></p></section>';
        }

        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-struggling-table"><thead><tr><th>Apprenant</th><th>User ID</th><th>Alertes</th><th>Échecs</th><th>Scores faibles</th><th>Score moyen</th><th>Dernière alerte</th><th>Ressources concernées</th></tr></thead><tbody>';
        foreach ($visible as $learner) {
            $resources = array_keys((array) ($learner['resources'] ?? []));
            sort($resources);
            $resourceText = count($resources) === 0 ? '-' : implode(', ', array_slice($resources, 0, 3));
            if (count($resources) > 3) {
                $resourceText .= ' +' . (count($resources) - 3);
            }
            $scoreText = $learner['avg_score'] === null ? '-' : (string) $learner['avg_score'] . ' %';
            $html .= '<tr><td><span class="itxeb-signal itxeb-signal-warning">' . $this->esc((string) ($learner['learner_identity'] ?? '')) . '</span></td>'
                . '<td><small>' . $this->esc((string) ($learner['user_id'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc((string) ($learner['alerts'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($learner['failed'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($learner['low_scores'] ?? 0)) . '</td>'
                . '<td>' . $this->esc($scoreText) . '</td>'
                . '<td>' . $this->esc((string) ($learner['last_at'] ?? '')) . '</td>'
                . '<td>' . $this->esc($resourceText) . '</td></tr>';
        }

        return $html . '</tbody></table></div></section>';
    }

PHP;
}

function new_render_expert_v0252(): string
{
    return <<<'PHP'
    /** @param array<string,mixed> $course */
    private function renderExpert(array $course): string
    {
        $dashboard = $this->loadDashboard($course);
        $rows = is_array($dashboard['expert_rows'] ?? null) ? $dashboard['expert_rows'] : [];
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $exportUrl = $this->currentUrlWith([
            'itxeb_cui_cmd' => 'exportCourseExpertCsv',
            'itxeb_course_ref_id' => (string) $courseRefId,
            'itxeb_period_days' => (string) $this->getPeriodDays(),
            'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
            'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
        ]);
        $html = '<section class="itxeb-cui-section"><h2>Traces détaillées</h2><p>Vue support des 200 derniers statements retournés par TRAX pour ce cours.</p>'
            . $this->renderPeriodSelector('showCourseExpert') . $this->renderResourceFilter($course, 'showCourseExpert') . $this->renderAnalyticsWarning()
            . '<p><a class="btn btn-default itxeb-export-button" href="' . $this->esc($exportUrl) . '">Exporter CSV</a></p>';
        if (count($rows) === 0) {
            return $html . '<p><em>Aucun statement xAPI TRAX pour cette période ou cette ressource.</em></p></section>';
        }
        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-cui-expert-table"><thead><tr><th>Date</th><th>User ID</th><th>Apprenant</th><th>Verbe</th><th>Ressource</th><th>Type</th><th>Score</th><th>Completion</th><th>Success</th><th>Source</th><th>Statement ID</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr><td>' . $this->esc((string) ($row['created_at'] ?? '')) . '</td><td>' . $this->esc((string) ($row['user_id'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($row['learner_identity'] ?? '')) . '</td>'
                . '<td>' . $this->esc((string) ($row['verb_label'] ?? '')) . '<br><small>' . $this->esc((string) ($row['verb_id'] ?? '')) . '</small></td>'
                . '<td><strong>' . $this->esc((string) ($row['object_title'] ?? '')) . '</strong><br><small>ref_id ' . $this->esc((string) ($row['ref_id'] ?? 0)) . '</small></td>'
                . '<td>' . $this->esc((string) ($row['obj_type'] ?? '')) . '</td><td>' . $this->esc($row['score_raw'] === null ? '-' : (string) $row['score_raw'] . ' %') . '</td>'
                . '<td>' . $this->esc($this->nullableBoolLabel($row['completion'] ?? null)) . '</td><td>' . $this->esc($this->nullableBoolLabel($row['success'] ?? null)) . '</td><td>' . $this->esc((string) ($row['status'] ?? 'TRAX')) . '</td>'
                . '<td><small>' . $this->esc((string) ($row['statement_uuid'] ?? '')) . '</small></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

PHP;
}

function patch_screen_v0252(string $content): string
{
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= "itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class=itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);

    $content = replace_method_v0252($content, 'renderStrugglingLearners', new_render_struggling_learners_v0252());
    $content = replace_method_v0252($content, 'renderExpert', new_render_expert_v0252());

    $content = replace_once_v0252(
        $content,
        "                'date', 'course_ref_id', 'filter_ref_id', 'user_id',\n                'verb_label', 'verb_id', 'resource_title', 'ref_id', 'obj_id', 'obj_type',",
        "                'date', 'course_ref_id', 'filter_ref_id', 'user_id', 'learner_identity',\n                'verb_label', 'verb_id', 'resource_title', 'ref_id', 'obj_id', 'obj_type',",
        'colonne learner_identity CSV Expert'
    );

    $content = replace_once_v0252(
        $content,
        "                    (string) (\$row['user_id'] ?? 0),\n                    (string) (\$row['verb_label'] ?? ''),",
        "                    (string) (\$row['user_id'] ?? 0),\n                    (string) (\$row['learner_identity'] ?? ''),\n                    (string) (\$row['verb_label'] ?? ''),",
        'valeur learner_identity CSV Expert'
    );

    return $content;
}

function patch_version_v0252(string $content, string $newVersion, string $label): string
{
    $updated = preg_replace('/\$version\s*=\s*\'[^\']+\';/', '$version = \'' . $newVersion . '\';', $content, 1, $count);
    if (!is_string($updated) || $count !== 1) {
        fail_v0252('version introuvable: ' . $label);
    }
    return $updated;
}

function lint_v0252(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $out, $rc);
    echo implode(PHP_EOL, $out) . PHP_EOL;
    if ($rc !== 0) {
        fail_v0252('lint PHP KO: ' . $path);
    }
}

function final_check_v0252(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $content = read_file_v0252($path);
    if (strpos($content, 'learner_identity') === false) {
        fail_v0252('controle final KO learner_identity absent: ' . $path);
    }
    if (strpos($content, 'private function renderStrugglingLearners') !== false && strpos($content, 'anonymous_id') !== false) {
        fail_v0252('controle final KO anonymous_id encore present: ' . $path);
    }
    if (strpos($content, '<th>User ID</th><th>Apprenant</th><th>Verbe</th>') === false && strpos($content, 'renderExpert') !== false) {
        fail_v0252('controle final KO colonne Apprenant Expert absente: ' . $path);
    }
}

$summary = patch_lrs_summary_v0252(read_file_v0252($summaryFile));
write_file_v0252($summaryFile, $summary, $backupDir);

$screenTpl = patch_screen_v0252(read_file_v0252($screenTemplate));
write_file_v0252($screenTemplate, $screenTpl, $backupDir);

if (is_file($liveScreen)) {
    $screenLive = patch_screen_v0252(read_file_v0252($liveScreen));
    write_file_v0252($liveScreen, $screenLive, $backupDir);
}

write_file_v0252($mainPlugin, patch_version_v0252(read_file_v0252($mainPlugin), '0.25.2-dev', 'plugin principal'), $backupDir);
write_file_v0252($companionPluginTemplate, patch_version_v0252(read_file_v0252($companionPluginTemplate), '0.8.40', 'plugin compagnon template'), $backupDir);
if (is_file($liveCompanionPlugin)) {
    write_file_v0252($liveCompanionPlugin, patch_version_v0252(read_file_v0252($liveCompanionPlugin), '0.8.40', 'plugin compagnon live'), $backupDir);
}

foreach ([$summaryFile, $screenTemplate, $mainPlugin, $companionPluginTemplate, $liveScreen, $liveCompanionPlugin] as $file) {
    if (is_file($file)) {
        lint_v0252($file);
        final_check_v0252($file);
    }
}

echo 'V0.25.2 appliquee : identite apprenant non anonymisee dans Analyse et Expert.' . PHP_EOL;
echo 'Backups: ' . $backupDir . PHP_EOL;
