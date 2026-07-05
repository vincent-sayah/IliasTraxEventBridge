<?php
/**
 * Correctif debug V0.13 - écrit les logs UIHook dans /var/www/logs.
 *
 * php-fpm et httpd utilisent PrivateTmp=yes sur la VM, donc les fichiers écrits
 * dans /tmp par le processus web ne sont pas visibles depuis le shell root.
 * Ce script remplace /tmp/itxeb_uihook_debug.log par
 * /var/www/logs/itxeb_uihook_debug.log dans le plugin UIHook installé et dans
 * le template compagnon.
 *
 * À lancer depuis la racine du plugin EventHook IliasTraxEventBridge :
 * php scripts/patch_v013_uihook_debug_log_varwww.php
 */

function itxeb_log_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: " . $message . PHP_EOL);
    exit(1);
}

function itxeb_log_patch_file(string $file): bool
{
    if (!is_file($file)) {
        echo "IGNORE: fichier absent: {$file}" . PHP_EOL;
        return false;
    }

    $content = file_get_contents($file);
    if (!is_string($content)) {
        itxeb_log_fail("lecture impossible: {$file}");
    }

    $new = str_replace('/tmp/itxeb_uihook_debug.log', '/var/www/logs/itxeb_uihook_debug.log', $content);
    $new = str_replace('/tmp/itxeb_uihook_loaded.log', '/var/www/logs/itxeb_uihook_loaded.log', $new);

    if ($new === $content) {
        echo "OK: déjà corrigé: {$file}" . PHP_EOL;
        return false;
    }

    if (file_put_contents($file, $new) === false) {
        itxeb_log_fail("écriture impossible: {$file}");
    }

    echo "PATCH: {$file}" . PHP_EOL;
    return true;
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_log_fail('lance ce script depuis la racine du plugin EventHook IliasTraxEventBridge.');
}

$candidates = [];
$candidates[] = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl';

$eventHookSuffix = '/Services/EventHandling/EventHook/IliasTraxEventBridge';
$uiHookSuffix = '/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
if (substr($root, -strlen($eventHookSuffix)) === $eventHookSuffix) {
    $candidates[] = substr($root, 0, -strlen($eventHookSuffix)) . $uiHookSuffix . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php';
}

$changed = false;
foreach (array_unique($candidates) as $file) {
    $changed = itxeb_log_patch_file($file) || $changed;
}

echo $changed ? "Destination debug UIHook corrigée vers /var/www/logs." . PHP_EOL : "Aucune modification nécessaire." . PHP_EOL;
