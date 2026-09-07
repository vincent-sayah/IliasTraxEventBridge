<?php

declare(strict_types=1);

/**
 * V0.25.0 — Affichage nominatif des apprenants dans Analyse et Expert.
 *
 * Objectif :
 * - ajouter l'identité apprenant lue depuis l'acteur xAPI TRAX/LRS ;
 * - afficher cette identité dans "Apprenants en difficulté" ;
 * - ajouter une colonne "Apprenant" dans l'onglet Expert entre "User ID" et "Verbe" ;
 * - ajouter la même colonne dans l'export CSV Expert ;
 * - conserver User ID comme identifiant technique pseudonymisé.
 */

$root = dirname(__DIR__);
$iliasRoot = rtrim((string) (getenv('ILIAS_ROOT') ?: '/var/www/ilias'), '/');
$liveCompanion = $iliasRoot . '/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$stamp = date('YmdHis');
$backupDir = sys_get_temp_dir() . '/itxeb_v025_backup_' . $stamp;

$summaryFile = $root . '/classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php';
$screenTemplate = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$liveScreen = $liveCompanion . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$mainPlugin = $root . '/plugin.php';
$companionPluginTemplate = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$liveCompanionPlugin = $liveCompanion . '/plugin.php';

function fail_v025(string $message): void
{
    fwrite(STDERR, 'ERREUR: ' . $message . PHP_EOL);
    exit(1);
}

function read_file_v025(string $path): string
{
    if (!is_file($path)) {
        fail_v025('fichier introuvable: ' . $path);
    }
    $content = file_get_contents($path);
    if (!is_string($content)) {
        fail_v025('lecture impossible: ' . $path);
    }
    return $content;
}

function backup_file_v025(string $path, string $backupDir): void
{
    if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
        fail_v025('creation dossier backup impossible: ' . $backupDir);
    }
    if (is_file($path)) {
        $target = $backupDir . '/' . str_replace('/', '__', ltrim($path, '/'));
        if (!copy($path, $target)) {
            fail_v025('backup impossible: ' . $path);
        }
    }
}

function write_file_v025(string $path, string $content, string $backupDir): void
{
    backup_file_v025($path, $backupDir);
    if (file_put_contents($path, $content) === false) {
        fail_v025('ecriture impossible: ' . $path);
    }
}

function replace_once_v025(string $content, string $search, string $replace, string $label): string
{
    if (strpos($content, $replace) !== false) {
        return $content;
    }
    $pos = strpos($content, $search);
    if ($pos === false) {
        fail_v025('point de remplacement introuvable: ' . $label);
    }
    return substr($content, 0, $pos) . $replace . substr($content, $pos + strlen($search));
}

/** @return array{0:int,1:int} */
function method_bounds_v025(string $content, string $methodName): array
{
    $needle = 'private function ' . $methodName . '(';
    $start = strpos($content, $needle);
    if ($start === false) {
        fail_v025('methode introuvable: ' . $methodName);
    }
    if (strpos($content, $needle, $start + 1) !== false) {
        fail_v025('methode en double: ' . $methodName);
    }
    $brace = strpos($content, '{', $start);
    if ($brace === false) {
        fail_v025('accolade introuvable: ' . $methodName);
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
    fail_v025('fin de methode introuvable: ' . $methodName);
}

function replace_in_method_v025(string $content, string $methodName, callable $callback): string
{
    [$start, $end] = method_bounds_v025($content, $methodName);
    $method = substr($content, $start, $end - $start);
    $patched = $callback($method);
    if (!is_string($patched) || $patched === '') {
        fail_v025('patch methode invalide: ' . $methodName);
    }
    return substr($content, 0, $start) . $patched . substr($content, $end);
}

function patch_lrs_summary_v025(string $content): string
{
    $search = <<<'PHP'
            'user_id' => $actorKey === '' ? '' : substr(sha1($actorKey), 0, 10),
            'verb_label' => $verbLabel,
PHP;
    $replace = <<<'PHP'
            'user_id' => $actorKey === '' ? '' : substr(sha1($actorKey), 0, 10),
            'learner_identity' => $this->actorDisplayName($statement, $actorKey),
            'verb_label' => $verbLabel,
PHP;
    $content = replace_once_v025($content, $search, $replace, 'ajout learner_identity dans expert_rows');

    if (strpos($content, 'private function actorDisplayName(') === false) {
        $marker = <<<'PHP'
    /** @param array<string,mixed> $statement */
    private function actorKey(array $statement): string
PHP;
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
            return preg_replace('/^mailto:/i', '', trim((string) $mbox)) ?: trim((string) $mbox);
        }

        if ($actorKey !== '') {
            if (strpos($actorKey, 'account:') === 0) {
                return substr($actorKey, 8);
            }
            if (strpos($actorKey, 'mbox:') === 0) {
                $value = substr($actorKey, 5);
                return preg_replace('/^mailto:/i', '', $value) ?: $value;
            }
            return $actorKey;
        }

        return '';
    }

PHP;
        $content = replace_once_v025($content, $marker, $method . $marker, 'insertion actorDisplayName');
    }

    return $content;
}

function patch_screen_v025(string $content): string
{
    // Corrige au passage une mauvaise quote HTML déjà observée localement sur le template V0.24.17.
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);
    $content = str_replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"', $content);

    $content = replace_in_method_v025($content, 'renderStrugglingLearners', static function (string $method): string {
        $search = <<<'PHP'
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
PHP;
        $replace = <<<'PHP'
            $learnerIdentity = trim((string) ($row['learner_identity'] ?? ''));
            $userId = trim((string) ($row['user_id'] ?? ''));
            $learnerKey = $learnerIdentity !== '' ? $learnerIdentity : $userId;
            if ($learnerKey === '') {
                continue;
            }
PHP;
        $method = replace_once_v025($method, $search, $replace, 'renderStrugglingLearners identite apprenant');

        $search = <<<'PHP'
            if (!isset($learners[$userId])) {
                $learners[$userId] = [
                    'anonymous_id' => 'Apprenant ' . substr(sha1('itxeb:' . (string) $userId), 0, 8),
PHP;
        $replace = <<<'PHP'
            if (!isset($learners[$learnerKey])) {
                $learners[$learnerKey] = [
                    'learner_identity' => $learnerIdentity !== '' ? $learnerIdentity : $userId,
PHP;
        $method = replace_once_v025($method, $search, $replace, 'renderStrugglingLearners suppression pseudonyme');

        $method = str_replace('$learners[$userId]', '$learners[$learnerKey]', $method);
        $method = str_replace('Vue anonymisée : aucun nom ni courriel n’est affiché. Les identifiants sont des pseudonymes techniques.', 'Vue nominative : les apprenants sont affichés avec l’identité transmise par TRAX/LRS afin de faciliter le suivi formateur.', $method);
        $method = str_replace('$learner[\'anonymous_id\']', '$learner[\'learner_identity\']', $method);

        if (strpos($method, 'anonymous_id') !== false) {
            fail_v025('anonymous_id encore present dans renderStrugglingLearners');
        }
        if (strpos($method, 'learner_identity') === false) {
            fail_v025('learner_identity absent de renderStrugglingLearners');
        }
        return $method;
    });

    $content = replace_once_v025(
        $content,
        '<thead><tr><th>Date</th><th>User ID</th><th>Verbe</th><th>Ressource</th><th>Type</th><th>Score</th><th>Completion</th><th>Success</th><th>Source</th><th>Statement ID</th></tr></thead><tbody>',
        '<thead><tr><th>Date</th><th>User ID</th><th>Apprenant</th><th>Verbe</th><th>Ressource</th><th>Type</th><th>Score</th><th>Completion</th><th>Success</th><th>Source</th><th>Statement ID</th></tr></thead><tbody>',
        'colonne Apprenant tableau Expert'
    );

    $search = <<<'PHP'
            $html .= '<tr><td>' . $this->esc((string) ($row['created_at'] ?? '')) . '</td><td>' . $this->esc((string) ($row['user_id'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($row['verb_label'] ?? '')) . '<br><small>' . $this->esc((string) ($row['verb_id'] ?? '')) . '</small></td>';
PHP;
    $replace = <<<'PHP'
            $html .= '<tr><td>' . $this->esc((string) ($row['created_at'] ?? '')) . '</td><td>' . $this->esc((string) ($row['user_id'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($row['learner_identity'] ?? '')) . '</td>'
                . '<td>' . $this->esc((string) ($row['verb_label'] ?? '')) . '<br><small>' . $this->esc((string) ($row['verb_id'] ?? '')) . '</small></td>';
PHP;
    $content = replace_once_v025($content, $search, $replace, 'valeur colonne Apprenant tableau Expert');

    $search = <<<'PHP'
                'date', 'course_ref_id', 'filter_ref_id', 'user_id',
                'verb_label', 'verb_id', 'resource_title', 'ref_id', 'obj_id', 'obj_type',
PHP;
    $replace = <<<'PHP'
                'date', 'course_ref_id', 'filter_ref_id', 'user_id', 'learner_identity',
                'verb_label', 'verb_id', 'resource_title', 'ref_id', 'obj_id', 'obj_type',
PHP;
    $content = replace_once_v025($content, $search, $replace, 'colonne learner_identity CSV Expert');

    $search = <<<'PHP'
                    (string) ($row['user_id'] ?? 0),
                    (string) ($row['verb_label'] ?? ''),
PHP;
    $replace = <<<'PHP'
                    (string) ($row['user_id'] ?? 0),
                    (string) ($row['learner_identity'] ?? ''),
                    (string) ($row['verb_label'] ?? ''),
PHP;
    $content = replace_once_v025($content, $search, $replace, 'valeur learner_identity CSV Expert');

    return $content;
}

function patch_version_v025(string $content, string $newVersion, string $label): string
{
    $updated = preg_replace("/\\$version\\s*=\\s*'[^']+';/", "\$version = '" . $newVersion . "';", $content, 1, $count);
    if (!is_string($updated) || $count !== 1) {
        fail_v025('version introuvable: ' . $label);
    }
    return $updated;
}

function lint_v025(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $out, $rc);
    echo implode(PHP_EOL, $out) . PHP_EOL;
    if ($rc !== 0) {
        fail_v025('lint PHP KO: ' . $path);
    }
}

$filesToPatch = [$summaryFile, $screenTemplate, $mainPlugin, $companionPluginTemplate];
if (is_file($liveScreen)) { $filesToPatch[] = $liveScreen; }
if (is_file($liveCompanionPlugin)) { $filesToPatch[] = $liveCompanionPlugin; }
foreach ($filesToPatch as $file) {
    backup_file_v025($file, $backupDir);
}

$summary = patch_lrs_summary_v025(read_file_v025($summaryFile));
write_file_v025($summaryFile, $summary, $backupDir);

$screenTpl = patch_screen_v025(read_file_v025($screenTemplate));
write_file_v025($screenTemplate, $screenTpl, $backupDir);

if (is_file($liveScreen)) {
    $screenLive = patch_screen_v025(read_file_v025($liveScreen));
    write_file_v025($liveScreen, $screenLive, $backupDir);
}

write_file_v025($mainPlugin, patch_version_v025(read_file_v025($mainPlugin), '0.25.0-dev', 'plugin principal'), $backupDir);
write_file_v025($companionPluginTemplate, patch_version_v025(read_file_v025($companionPluginTemplate), '0.8.38', 'plugin compagnon template'), $backupDir);
if (is_file($liveCompanionPlugin)) {
    write_file_v025($liveCompanionPlugin, patch_version_v025(read_file_v025($liveCompanionPlugin), '0.8.38', 'plugin compagnon live'), $backupDir);
}

foreach ([$summaryFile, $screenTemplate, $mainPlugin, $companionPluginTemplate, $liveScreen, $liveCompanionPlugin] as $file) {
    if (is_file($file)) {
        lint_v025($file);
    }
}

foreach ([$summaryFile, $screenTemplate, $liveScreen] as $file) {
    if (!is_file($file)) { continue; }
    $content = read_file_v025($file);
    if (strpos($content, 'learner_identity') === false) {
        fail_v025('controle final KO learner_identity absent: ' . $file);
    }
    if (strpos($content, 'private function renderStrugglingLearners') !== false && strpos($content, 'anonymous_id') !== false) {
        fail_v025('controle final KO anonymous_id encore present: ' . $file);
    }
}

echo 'V0.25.0 appliquee : identite apprenant non anonymisee dans Analyse et Expert.' . PHP_EOL;
echo 'Backups: ' . $backupDir . PHP_EOL;
