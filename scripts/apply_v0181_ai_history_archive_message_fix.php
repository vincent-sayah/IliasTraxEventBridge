<?php
/**
 * V0.18.1 - Ne pas afficher d'erreur si l'identifiant d'historique IA
 * restant dans l'URL correspond a une analyse qui vient d'etre retiree.
 */

function fail_v0181(string $message): void
{
    fwrite(STDERR, "ERREUR: {$message}\n");
    exit(1);
}

function read_v0181(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content)) {
        fail_v0181("lecture impossible: {$path}");
    }
    return $content;
}

function write_v0181(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fail_v0181("ecriture impossible: {$path}");
    }
    echo "WRITE: {$path}\n";
}

function lint_v0181(string $path): void
{
    passthru('php -l ' . escapeshellarg($path), $code);
    if ($code !== 0) {
        fail_v0181("syntaxe invalide: {$path}");
    }
}

function patch_screen_v0181(string $path): void
{
    echo "\n== Patch ecran: {$path} ==\n";
    $content = read_v0181($path);
    $original = $content;

    $old = <<<'PHP'
            if ($selectedRecord === []) {
                $html .= '<div class="itxeb-cui-alert itxeb-cui-error">Analyse IA historisée introuvable pour cet identifiant.</div>';
            } else {
PHP;

    $new = <<<'PHP'
            if ($selectedRecord === []) {
                // V0.18.1 : l'identifiant peut rester dans l'URL juste après le retrait
                // d'une analyse. Dans ce cas on n'affiche pas d'erreur : le tableau mis
                // à jour suffit à confirmer que l'analyse n'est plus visible.
            } else {
PHP;

    if (strpos($content, $new) !== false) {
        echo "OK: correctif V0.18.1 deja present\n";
    } elseif (strpos($content, $old) !== false) {
        $content = str_replace($old, $new, $content);
        echo "PATCH: message historique introuvable neutralise\n";
    } else {
        fail_v0181('bloc message introuvable');
    }

    if ($content !== $original) {
        write_v0181($path, $content);
    } else {
        echo "OK: ecran inchange\n";
    }
    lint_v0181($path);
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    fail_v0181('lance ce script depuis la racine du plugin principal IliasTraxEventBridge.');
}

$template = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$servicesDir = dirname(dirname(dirname($root)));
$liveScreen = $servicesDir . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';

patch_screen_v0181($template);

if (is_file($liveScreen)) {
    if (!copy($template, $liveScreen)) {
        fail_v0181("copie template vers live impossible: {$liveScreen}");
    }
    echo "COPY: template V0.18.1 vers companion live\n";
    lint_v0181($liveScreen);
} else {
    echo "WARN: companion live absent: {$liveScreen}\n";
}

echo "\nV0.18.1 appliquee : plus de message introuvable apres retrait.\n";
