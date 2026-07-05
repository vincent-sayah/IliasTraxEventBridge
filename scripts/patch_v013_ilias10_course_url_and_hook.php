<?php
/**
 * Correctif V0.13 - URL et déclenchement de l'onglet cours ILIAS 10.
 *
 * Corrige deux points :
 * - l'URL de l'onglet Suivi xAPI doit pointer vers ilias.php, pas vers goto.php
 *   ni une route native instable ;
 * - getHTML() doit traiter une requête itxeb_cui_cmd dès qu'un HTML de page
 *   avec il_center_col est fourni, sans dépendre strictement d'un nom de part.
 *
 * À lancer depuis la racine du plugin EventHook IliasTraxEventBridge :
 * php scripts/patch_v013_ilias10_course_url_and_hook.php
 */

function itxeb_fix_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: " . $message . PHP_EOL);
    exit(1);
}

function itxeb_replace_method_any_visibility(string $content, string $methodName, string $replacement, string $file): string
{
    $pattern = '~    (public|private|protected) function ' . preg_quote($methodName, '~') . '\b~';
    if (!preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
        itxeb_fix_fail("méthode {$methodName} introuvable dans {$file}");
    }
    $start = (int) $m[0][1];
    $brace = strpos($content, '{', $start);
    if ($brace === false) {
        itxeb_fix_fail("accolade ouvrante {$methodName} introuvable dans {$file}");
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
                return substr($content, 0, $start) . rtrim($replacement) . substr($content, $i + 1);
            }
        }
    }
    itxeb_fix_fail("fin méthode {$methodName} introuvable dans {$file}");
}

function itxeb_patch_bridge(string $file): bool
{
    if (!is_file($file)) {
        echo "IGNORE: fichier absent: {$file}" . PHP_EOL;
        return false;
    }
    $content = file_get_contents($file);
    if (!is_string($content)) {
        itxeb_fix_fail("lecture impossible: {$file}");
    }
    $original = $content;

    $method = <<<'PHP'
    public function buildContextualConfigurationUrl(int $courseRefId): string
    {
        if ($courseRefId <= 0) {
            return '';
        }

        $script = $this->buildIliasPhpEntrypoint();
        if ($script === '') {
            return '';
        }

        $params = [
            'baseClass' => 'ilRepositoryGUI',
            'ref_id' => (string) $courseRefId,
            'itxeb_cui_cmd' => 'showCourseDashboard',
            'itxeb_course_ref_id' => (string) $courseRefId,
        ];

        return $script . '?' . http_build_query($params, '', '&');
    }

    private function buildIliasPhpEntrypoint(): string
    {
        foreach (['SCRIPT_NAME', 'PHP_SELF'] as $key) {
            $value = isset($_SERVER[$key]) && is_scalar($_SERVER[$key]) ? (string) $_SERVER[$key] : '';
            if ($value === '') {
                continue;
            }
            $path = (string) (parse_url($value, PHP_URL_PATH) ?: $value);
            $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
            if (basename($path) === 'ilias.php') {
                return $path;
            }
            if ($dir === '' || $dir === '.') {
                return '/ilias.php';
            }
            return $dir . '/ilias.php';
        }

        return '/ilias.php';
    }
PHP;

    $content = itxeb_replace_method_any_visibility($content, 'buildContextualConfigurationUrl', $method, $file);

    if ($content === $original) {
        echo "OK: bridge déjà corrigé: {$file}" . PHP_EOL;
        return false;
    }
    if (file_put_contents($file, $content) === false) {
        itxeb_fix_fail("écriture impossible: {$file}");
    }
    echo "PATCH: {$file}" . PHP_EOL;
    return true;
}

function itxeb_patch_uihook(string $file): bool
{
    if (!is_file($file)) {
        echo "IGNORE: fichier absent: {$file}" . PHP_EOL;
        return false;
    }
    $content = file_get_contents($file);
    if (!is_string($content)) {
        itxeb_fix_fail("lecture impossible: {$file}");
    }
    $original = $content;

    $getHtml = <<<'PHP'
    public function getHTML($a_comp, $a_part, $a_par = []): array
    {
        if (!isset($a_par['html']) || !is_string($a_par['html'])) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        if (!$this->isCourseUiCommandRequest()) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        if (strpos($a_par['html'], 'il_center_col') === false) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $screen = new ilIliasTraxEventBridgeCourseUIScreen($this->bridge);
        $html = $this->replaceCenterColumnContent($a_par['html'], $screen->handle());
        if ($html === $a_par['html']) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        return [
            'mode' => ilUIHookPluginGUI::REPLACE,
            'html' => $html,
        ];
    }
PHP;

    $content = itxeb_replace_method_any_visibility($content, 'getHTML', $getHtml, $file);

    if (strpos($content, "'generateCourseAiAnalysis'") === false) {
        $needle = "            'exportCourseDashboardPdf',\n";
        $insert = "            'exportCourseDashboardPdf',\n            'generateCourseAiAnalysis',\n";
        if (strpos($content, $needle) === false) {
            itxeb_fix_fail("point insertion generateCourseAiAnalysis introuvable dans {$file}");
        }
        $content = str_replace($needle, $insert, $content);
    }

    if ($content === $original) {
        echo "OK: UIHook déjà corrigé: {$file}" . PHP_EOL;
        return false;
    }
    if (file_put_contents($file, $content) === false) {
        itxeb_fix_fail("écriture impossible: {$file}");
    }
    echo "PATCH: {$file}" . PHP_EOL;
    return true;
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_fix_fail('lance ce script depuis la racine du plugin EventHook IliasTraxEventBridge.');
}

$changed = false;
$changed = itxeb_patch_bridge($root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIBridge.php.tpl') || $changed;
$changed = itxeb_patch_uihook($root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl') || $changed;

$eventHookSuffix = '/Services/EventHandling/EventHook/IliasTraxEventBridge';
$uiHookSuffix = '/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
if (substr($root, -strlen($eventHookSuffix)) === $eventHookSuffix) {
    $uiRoot = substr($root, 0, -strlen($eventHookSuffix)) . $uiHookSuffix;
    $changed = itxeb_patch_bridge($uiRoot . '/classes/class.ilIliasTraxEventBridgeCourseUIBridge.php') || $changed;
    $changed = itxeb_patch_uihook($uiRoot . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php') || $changed;
}

echo $changed ? "Correctif URL/hook onglet Suivi xAPI appliqué." . PHP_EOL : "Aucune modification nécessaire." . PHP_EOL;
