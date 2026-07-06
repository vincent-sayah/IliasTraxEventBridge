<?php
/**
 * V0.13 - ne pas réintercepter la page routée par le UIHook.
 *
 * Diagnostic : la page ilUIPluginRouterGUI fonctionne désormais, mais le HTML
 * du suivi xAPI est affiché comme texte. La cause est le UIHook compagnon qui
 * voit encore itxeb_cui_cmd posé par le router, réexécute replaceCenterColumnContent()
 * sur la page routée, puis transforme le HTML en TextNode lorsque DOMDocument
 * ne peut pas parser tout le fragment comme XML.
 *
 * Ce patch :
 * - passe itxebcui en 0.3.4 ;
 * - ajoute un garde-fou dans getHTML() : si baseClass=ilUIPluginRouterGUI ou
 *   cmdClass=ilIliasTraxEventBridgeCourseUIRouterGUI, le UIHook retourne KEEP ;
 * - conserve l'encart Suivi xAPI uniquement dans l'onglet Contenu du cours.
 *
 * À lancer depuis la racine du plugin EventHook IliasTraxEventBridge :
 * php scripts/patch_v013_skip_uihook_on_router.php
 */

function itxeb_skip_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: {$message}\n");
    exit(1);
}

function itxeb_skip_write(string $file, string $content): void
{
    if (file_put_contents($file, $content) === false) {
        itxeb_skip_fail("écriture impossible: {$file}");
    }
    echo "WRITE: {$file}\n";
}

function itxeb_skip_patch_plugin(string $file): void
{
    if (!is_file($file)) {
        echo "IGNORE: plugin.php absent: {$file}\n";
        return;
    }
    $content = file_get_contents($file);
    if (!is_string($content)) {
        itxeb_skip_fail("lecture impossible: {$file}");
    }
    $content = preg_replace('/\$version\s*=\s*\'[^\']+\';/', "\$version = '0.3.4';", $content, 1, $count);
    if (!is_string($content) || $count !== 1) {
        itxeb_skip_fail("version plugin introuvable: {$file}");
    }
    itxeb_skip_write($file, $content);
}

function itxeb_skip_patch_uihook(string $file): void
{
    if (!is_file($file)) {
        echo "IGNORE: UIHook absent: {$file}\n";
        return;
    }
    $content = file_get_contents($file);
    if (!is_string($content)) {
        itxeb_skip_fail("lecture impossible: {$file}");
    }
    $original = $content;

    if (strpos($content, '$this->isRoutedPluginRequest()') === false) {
        $needle = "        if (strpos(\$html, 'il_center_col') === false || strpos(\$html, 'mainspacekeeper') === false) {\n            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];\n        }\n\n";
        $insert = $needle
            . "        // Ne jamais réintercepter la page routée ilUIPluginRouterGUI :\n"
            . "        // sinon le HTML du screen est réinséré comme texte échappé.\n"
            . "        if (\$this->isRoutedPluginRequest()) {\n"
            . "            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];\n"
            . "        }\n\n";
        if (strpos($content, $needle) === false) {
            itxeb_skip_fail("point d'insertion garde-fou router introuvable: {$file}");
        }
        $content = str_replace($needle, $insert, $content);
    }

    if (strpos($content, 'private function isRoutedPluginRequest(): bool') === false) {
        $needle = "    /** @return array<string,mixed> */\n    public function getCurrentCourseContext(): array\n    {\n        return \$this->bridge->getCourseContext();\n    }\n\n";
        $method = $needle . <<<'PHP'
    private function isRoutedPluginRequest(): bool
    {
        $query = [];
        $uri = isset($_SERVER['REQUEST_URI']) && is_scalar($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $parts = parse_url($uri);
        if (is_array($parts) && isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        } elseif (!empty($_GET)) {
            $query = $_GET;
        }

        $baseClass = strtolower((string) ($query['baseClass'] ?? $query['baseclass'] ?? ''));
        $cmdClass = strtolower((string) ($query['cmdClass'] ?? $query['cmdclass'] ?? ''));

        return $baseClass === 'iluipluginroutergui'
            || $cmdClass === strtolower(ilIliasTraxEventBridgeCourseUIRouterGUI::class);
    }

PHP;
        if (strpos($content, $needle) === false) {
            itxeb_skip_fail("point d'insertion méthode isRoutedPluginRequest introuvable: {$file}");
        }
        $content = str_replace($needle, $method, $content);
    }

    if ($content === $original) {
        echo "OK: UIHook déjà corrigé: {$file}\n";
    } else {
        itxeb_skip_write($file, $content);
    }
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_skip_fail('lance ce script depuis la racine du plugin EventHook IliasTraxEventBridge.');
}
$eventHookSuffix = '/Services/EventHandling/EventHook/IliasTraxEventBridge';
if (substr($root, -strlen($eventHookSuffix)) !== $eventHookSuffix) {
    itxeb_skip_fail("chemin plugin principal inattendu: {$root}");
}

$customizingRoot = substr($root, 0, -strlen($eventHookSuffix));
$companionTemplate = $root . '/companion/IliasTraxEventBridgeCourseUI';
$companionInstalled = $customizingRoot . '/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';

itxeb_skip_patch_plugin($companionTemplate . '/plugin.php.tpl');
itxeb_skip_patch_uihook($companionTemplate . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl');

if (is_dir($companionInstalled)) {
    itxeb_skip_patch_plugin($companionInstalled . '/plugin.php');
    itxeb_skip_patch_uihook($companionInstalled . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php');
}

$files = [
    $companionTemplate . '/plugin.php.tpl',
    $companionTemplate . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl',
];
if (is_dir($companionInstalled)) {
    $files[] = $companionInstalled . '/plugin.php';
    $files[] = $companionInstalled . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php';
}
foreach ($files as $file) {
    if (!is_file($file)) {
        continue;
    }
    passthru('php -l ' . escapeshellarg($file), $code);
    if ($code !== 0) {
        itxeb_skip_fail("syntaxe PHP invalide: {$file}");
    }
}

echo "\nCorrectif appliqué : version itxebcui 0.3.4 + UIHook ignoré sur pages routées.\n";
