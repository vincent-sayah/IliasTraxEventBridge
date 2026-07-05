<?php
/**
 * Correctif V0.13 - intégration propre de l'onglet de cours ILIAS 10.
 *
 * Principe :
 * - l'onglet principal "Suivi xAPI" est ajouté via modifyGUI(..., "tabs", ...)
 *   et l'objet ilTabsGUI, conformément au mécanisme ILIAS prévu pour les tabs ;
 * - on ne crée plus l'onglet par remplacement HTML / regex ;
 * - getHTML() ne sert plus qu'à remplacer le contenu central quand la requête
 *   porte explicitement un itxeb_cui_cmd ;
 * - la commande generateCourseAiAnalysis est reconnue comme commande xAPI.
 *
 * À lancer depuis la racine du plugin EventHook IliasTraxEventBridge :
 * php scripts/patch_v013_ilias10_clean_course_tabs.php
 */

function itxeb_clean_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: " . $message . PHP_EOL);
    exit(1);
}

function itxeb_replace_method(string $content, string $methodName, string $replacement, string $file): string
{
    $needle = '    public function ' . $methodName;
    $start = strpos($content, $needle);
    if ($start === false) {
        itxeb_clean_fail("méthode {$methodName} introuvable dans {$file}");
    }

    $brace = strpos($content, '{', $start);
    if ($brace === false) {
        itxeb_clean_fail("accolade ouvrante {$methodName} introuvable dans {$file}");
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

    itxeb_clean_fail("fin méthode {$methodName} introuvable dans {$file}");
}

function itxeb_patch_uihook_file(string $file): bool
{
    if (!is_file($file)) {
        echo "IGNORE: fichier absent: {$file}" . PHP_EOL;
        return false;
    }

    $content = file_get_contents($file);
    if (!is_string($content)) {
        itxeb_clean_fail("lecture impossible: {$file}");
    }

    $original = $content;

    $getHtml = <<<'PHP'
    public function getHTML($a_comp, $a_part, $a_par = []): array
    {
        if ($a_part !== 'template_show' || !isset($a_par['html']) || !is_string($a_par['html'])) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        if (!$this->isCourseUiCommandRequest()) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $screen = new ilIliasTraxEventBridgeCourseUIScreen($this->bridge);
        $html = $this->replaceCenterColumnContent($a_par['html'], $screen->handle());

        return [
            'mode' => ilUIHookPluginGUI::REPLACE,
            'html' => $html,
        ];
    }
PHP;

    $modifyGui = <<<'PHP'
    public function modifyGUI($a_comp, $a_part, $a_par = []): void
    {
        if ($a_part !== 'tabs') {
            return;
        }

        $courseRefId = $this->bridge->detectCourseRefId();
        if ($courseRefId <= 0 || !$this->bridge->canManageCourse($courseRefId)) {
            return;
        }
        if (!$this->bridge->isMainPluginAvailable() || !$this->bridge->loadCourseTrackingClasses()) {
            return;
        }

        $tabs = is_array($a_par) ? ($a_par['tabs'] ?? null) : null;
        if (!is_object($tabs)) {
            return;
        }

        $url = $this->bridge->buildContextualConfigurationUrl($courseRefId);
        if ($url === '') {
            return;
        }

        if (method_exists($tabs, 'addTab')) {
            $tabs->addTab('itxeb_course_xapi_main', 'Suivi xAPI', $url);
        }

        if ($this->isCourseUiCommandRequest()) {
            if (method_exists($tabs, 'activateTab')) {
                $tabs->activateTab('itxeb_course_xapi_main');
            } elseif (method_exists($tabs, 'setTabActive')) {
                $tabs->setTabActive('itxeb_course_xapi_main');
            }
        }
    }
PHP;

    $content = itxeb_replace_method($content, 'getHTML', $getHtml, $file);
    $content = itxeb_replace_method($content, 'modifyGUI', $modifyGui, $file);

    if (strpos($content, "'generateCourseAiAnalysis'") === false) {
        $needle = "            'exportCourseDashboardPdf',\n";
        $insert = "            'exportCourseDashboardPdf',\n            'generateCourseAiAnalysis',\n";
        if (strpos($content, $needle) === false) {
            itxeb_clean_fail("point insertion generateCourseAiAnalysis introuvable dans {$file}");
        }
        $content = str_replace($needle, $insert, $content);
    }

    if ($content === $original) {
        echo "OK: déjà corrigé: {$file}" . PHP_EOL;
        return false;
    }

    if (file_put_contents($file, $content) === false) {
        itxeb_clean_fail("écriture impossible: {$file}");
    }

    echo "PATCH: {$file}" . PHP_EOL;
    return true;
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_clean_fail('lance ce script depuis la racine du plugin EventHook IliasTraxEventBridge.');
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
    $changed = itxeb_patch_uihook_file($file) || $changed;
}

echo $changed ? "Correctif onglets ILIAS 10 appliqué." . PHP_EOL : "Aucune modification nécessaire." . PHP_EOL;
