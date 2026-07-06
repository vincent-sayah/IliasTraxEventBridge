<?php
/**
 * Corrige le bug des périodes 7/30 jours dans le tableau de bord LRS.
 *
 * Symptôme observé : la comparaison voit 8 statements sur la période actuelle,
 * mais les KPIs principaux affichent 0. On lit donc une fenêtre élargie côté
 * LRS pour les périodes courtes, puis on filtre en PHP sur la vraie période
 * demandée. Cela rend tous les blocs cohérents : synthèse, activité, actions,
 * top ressources, analyse, expert, PDF et comparaison.
 */

function fail_period_patch(string $message): void
{
    fwrite(STDERR, "ERREUR: {$message}\n");
    exit(1);
}

function write_period_patch(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fail_period_patch("écriture impossible: {$path}");
    }
    echo "WRITE: {$path}\n";
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    fail_period_patch('lance ce script depuis la racine du plugin IliasTraxEventBridge.');
}

$file = $root . '/classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php';
if (!is_file($file)) {
    fail_period_patch("fichier introuvable: {$file}");
}

$c = file_get_contents($file);
if (!is_string($c)) {
    fail_period_patch("lecture impossible: {$file}");
}
$original = $c;

$old = <<<'PHP'
        $days = max(1, min(365, $days));
        $activity = $this->courseActivityId($courseRefId, $courseObjId);
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - ($days * 86400));
        $allowedResources = $this->allowedResources($course);
PHP;
$new = <<<'PHP'
        $days = max(1, min(365, $days));
        $periodStartTs = time() - ($days * 86400);
        // TRAX/LRS peut retourner une page vide sur une fenêtre courte alors que
        // les mêmes statements existent dans une fenêtre plus large. Pour éviter
        // des KPIs à 0 incohérents, on interroge plus large pour 7/30 jours puis
        // on post-filtre chaque statement sur la vraie période demandée.
        $queryDays = $days <= 30 ? min(365, max($days * 2, 14)) : $days;
        $activity = $this->courseActivityId($courseRefId, $courseObjId);
        $since = gmdate('Y-m-d\TH:i:s\Z', time() - ($queryDays * 86400));
        $allowedResources = $this->allowedResources($course);
PHP;
if (strpos($c, $old) === false) {
    fail_period_patch('bloc days/since introuvable');
}
$c = str_replace($old, $new, $c);

$old = <<<'PHP'
            'since' => $since,
            'returned' => 0,
PHP;
$new = <<<'PHP'
            'since' => gmdate('Y-m-d\TH:i:s\Z', $periodStartTs),
            'query_since' => $since,
            'period_days' => $days,
            'period_start_ts' => $periodStartTs,
            'returned' => 0,
PHP;
if (strpos($c, $old) === false) {
    fail_period_patch('bloc summary since introuvable');
}
$c = str_replace($old, $new, $c);

$old = <<<'PHP'
            $resource = $this->resourceInfo($statement);
            $refId = (int) ($resource['ref_id'] ?? 0);
PHP;
$new = <<<'PHP'
            if (!$this->isStatementInRequestedPeriod($summary, $statement)) {
                continue;
            }
            $resource = $this->resourceInfo($statement);
            $refId = (int) ($resource['ref_id'] ?? 0);
PHP;
if (strpos($c, $old) === false) {
    fail_period_patch('point insertion filtre période introuvable');
}
$c = str_replace($old, $new, $c);

if (strpos($c, 'private function isStatementInRequestedPeriod') === false) {
    $marker = "    /** @param array<string,mixed> \$summary */\n    private function addStatement";
    $method = <<<'PHP'
    /** @param array<string,mixed> $summary @param array<string,mixed> $statement */
    private function isStatementInRequestedPeriod(array $summary, array $statement): bool
    {
        $start = (int) ($summary['period_start_ts'] ?? 0);
        if ($start <= 0) {
            return true;
        }
        $timestamp = (string) ($statement['timestamp'] ?? ($statement['stored'] ?? ''));
        if ($timestamp === '') {
            return true;
        }
        $ts = strtotime($timestamp);
        if ($ts === false) {
            return true;
        }
        return $ts >= $start;
    }

PHP;
    if (strpos($c, $marker) === false) {
        fail_period_patch('marker addStatement introuvable');
    }
    $c = str_replace($marker, $method . $marker, $c);
}

if ($c === $original) {
    echo "OK: déjà corrigé\n";
} else {
    write_period_patch($file, $c);
}

passthru('php -l ' . escapeshellarg($file), $code);
if ($code !== 0) {
    fail_period_patch("syntaxe PHP invalide: {$file}");
}

echo "\nCorrectif appliqué : lecture LRS élargie + post-filtrage strict des périodes courtes.\n";
