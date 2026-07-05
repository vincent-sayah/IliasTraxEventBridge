<?php
/**
 * Debug temporaire V0.13 - trace les appels UIHook de l'onglet de cours.
 *
 * À lancer depuis la racine du plugin EventHook IliasTraxEventBridge :
 * php scripts/patch_v013_uihook_debug_log.php
 *
 * Log généré : /tmp/itxeb_uihook_debug.log
 */

function itxeb_dbg_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: " . $message . PHP_EOL);
    exit(1);
}

function itxeb_dbg_replace_method(string $content, string $methodName, string $replacement, string $file): string
{
    $pattern = '~    (public|private|protected) function ' . preg_quote($methodName, '~') . '\b~';
    if (!preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
        itxeb_dbg_fail("méthode {$methodName} introuvable dans {$file}");
    }
    $start = (int) $m[0][1];
    $brace = strpos($content, '{', $start);
    if ($brace === false) {
        itxeb_dbg_fail("accolade ouvrante {$methodName} introuvable dans {$file}");
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
    itxeb_dbg_fail("fin méthode {$methodName} introuvable dans {$file}");
}

function itxeb_dbg_patch(string $file): bool
{
    if (!is_file($file)) {
        echo "IGNORE: fichier absent: {$file}" . PHP_EOL;
        return false;
    }
    $content = file_get_contents($file);
    if (!is_string($content)) {
        itxeb_dbg_fail("lecture impossible: {$file}");
    }
    $original = $content;

    $getHtml = <<<'PHP'
    public function getHTML($a_comp, $a_part, $a_par = []): array
    {
        $html = isset($a_par['html']) && is_string($a_par['html']) ? $a_par['html'] : '';
        $this->itxebDebug('getHTML.enter', [
            'comp' => (string) $a_comp,
            'part' => (string) $a_part,
            'cmd_get' => isset($_GET['itxeb_cui_cmd']) ? (string) $_GET['itxeb_cui_cmd'] : '',
            'cmd_post' => isset($_POST['itxeb_cui_cmd']) ? (string) $_POST['itxeb_cui_cmd'] : '',
            'ref_get' => isset($_GET['ref_id']) ? (string) $_GET['ref_id'] : '',
            'course_get' => isset($_GET['itxeb_course_ref_id']) ? (string) $_GET['itxeb_course_ref_id'] : '',
            'html_len' => strlen($html),
            'has_center_col' => strpos($html, 'il_center_col') !== false ? '1' : '0',
            'has_mainspacekeeper' => strpos($html, 'mainspacekeeper') !== false ? '1' : '0',
            'has_itxeb' => strpos($html, 'itxeb') !== false ? '1' : '0',
            'uri' => isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '',
        ]);

        if ($html === '') {
            $this->itxebDebug('getHTML.keep.empty_html', []);
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        if (!$this->isCourseUiCommandRequest()) {
            $this->itxebDebug('getHTML.keep.not_itxeb_request', []);
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $screen = new ilIliasTraxEventBridgeCourseUIScreen($this->bridge);
        $screenHtml = $screen->handle();
        $replaced = $this->replaceCenterColumnContent($html, $screenHtml);
        if ($replaced !== $html) {
            $this->itxebDebug('getHTML.replace.center_column', [
                'mode' => 'REPLACE',
                'result_len' => strlen($replaced),
            ]);
            return [
                'mode' => ilUIHookPluginGUI::REPLACE,
                'html' => $replaced,
            ];
        }

        if (in_array((string) $a_part, ['template_show', 'template_get', 'content', 'center_column', 'main_content', 'standard'], true) || strlen($html) > 1000) {
            $this->itxebDebug('getHTML.replace.fallback_screen', [
                'mode' => 'REPLACE',
                'screen_len' => strlen($screenHtml),
            ]);
            return [
                'mode' => ilUIHookPluginGUI::REPLACE,
                'html' => $screenHtml,
            ];
        }

        $this->itxebDebug('getHTML.keep.no_matching_container', []);
        return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
    }
PHP;

    $modifyGui = <<<'PHP'
    public function modifyGUI($a_comp, $a_part, $a_par = []): void
    {
        $this->itxebDebug('modifyGUI.enter', [
            'comp' => (string) $a_comp,
            'part' => (string) $a_part,
            'cmd_get' => isset($_GET['itxeb_cui_cmd']) ? (string) $_GET['itxeb_cui_cmd'] : '',
            'ref_get' => isset($_GET['ref_id']) ? (string) $_GET['ref_id'] : '',
            'has_tabs_key' => is_array($a_par) && isset($a_par['tabs']) ? '1' : '0',
            'has_tabs_gui_key' => is_array($a_par) && isset($a_par['tabs_gui']) ? '1' : '0',
            'keys' => is_array($a_par) ? implode(',', array_keys($a_par)) : '',
            'uri' => isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '',
        ]);

        if ($a_part !== 'tabs') {
            return;
        }

        $courseRefId = $this->bridge->detectCourseRefId();
        if ($courseRefId <= 0 || !$this->bridge->canManageCourse($courseRefId)) {
            $this->itxebDebug('modifyGUI.skip.context', ['course_ref_id' => (string) $courseRefId]);
            return;
        }
        if (!$this->bridge->isMainPluginAvailable() || !$this->bridge->loadCourseTrackingClasses()) {
            $this->itxebDebug('modifyGUI.skip.main_unavailable', []);
            return;
        }

        $tabs = is_array($a_par) ? ($a_par['tabs'] ?? ($a_par['tabs_gui'] ?? null)) : null;
        if (!is_object($tabs)) {
            $this->itxebDebug('modifyGUI.skip.no_tabs_object', []);
            return;
        }

        $url = $this->bridge->buildContextualConfigurationUrl($courseRefId);
        if ($url === '') {
            $this->itxebDebug('modifyGUI.skip.empty_url', []);
            return;
        }

        $this->itxebDebug('modifyGUI.tabs.object', [
            'class' => get_class($tabs),
            'url' => $url,
            'has_addTab' => method_exists($tabs, 'addTab') ? '1' : '0',
            'has_activateTab' => method_exists($tabs, 'activateTab') ? '1' : '0',
            'has_setTabActive' => method_exists($tabs, 'setTabActive') ? '1' : '0',
        ]);

        if (method_exists($tabs, 'addTab')) {
            $tabs->addTab('itxeb_course_xapi_main', 'Suivi xAPI', $url);
        }

        if ($this->isCourseUiCommandRequest()) {
            if (method_exists($tabs, 'activateTab')) {
                $tabs->activateTab('itxeb_course_xapi_main');
                $this->itxebDebug('modifyGUI.tabs.activateTab', []);
            } elseif (method_exists($tabs, 'setTabActive')) {
                $tabs->setTabActive('itxeb_course_xapi_main');
                $this->itxebDebug('modifyGUI.tabs.setTabActive', []);
            } else {
                $this->itxebDebug('modifyGUI.tabs.no_activate_method', []);
            }
        }
    }
PHP;

    $content = itxeb_dbg_replace_method($content, 'getHTML', $getHtml, $file);
    $content = itxeb_dbg_replace_method($content, 'modifyGUI', $modifyGui, $file);

    if (strpos($content, 'function itxebDebug(') === false) {
        $marker = "    /** @return array<string,mixed> */\n    public function getCurrentCourseContext(): array\n";
        $debugMethod = <<<'PHP'
    /** @param array<string,string> $data */
    private function itxebDebug(string $event, array $data): void
    {
        try {
            $line = '[' . date('Y-m-d H:i:s') . '] ' . $event . ' ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            @file_put_contents('/tmp/itxeb_uihook_debug.log', $line, FILE_APPEND);
        } catch (Throwable $ignored) {
        }
    }

PHP;
        if (strpos($content, $marker) === false) {
            itxeb_dbg_fail("point insertion itxebDebug introuvable dans {$file}");
        }
        $content = str_replace($marker, $debugMethod . $marker, $content);
    }

    if ($content === $original) {
        echo "OK: debug déjà présent: {$file}" . PHP_EOL;
        return false;
    }
    if (file_put_contents($file, $content) === false) {
        itxeb_dbg_fail("écriture impossible: {$file}");
    }
    echo "PATCH: {$file}" . PHP_EOL;
    return true;
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_dbg_fail('lance ce script depuis la racine du plugin EventHook IliasTraxEventBridge.');
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
    $changed = itxeb_dbg_patch($file) || $changed;
}

echo $changed ? "Debug UIHook Suivi xAPI appliqué." . PHP_EOL : "Aucune modification nécessaire." . PHP_EOL;
