<?php
/**
 * Correctif V0.13 - fallback stable getHTML pour l'onglet cours ILIAS 10.
 *
 * Diagnostic validé sur ILIAS 10 :
 * - la classe UIHook est bien chargée ;
 * - getHTML() est appelé sur le rendu final du cours ;
 * - modifyGUI(..., "tabs", ...) n'est pas appelé pour les onglets du cours sur
 *   cette installation ;
 * - l'onglet doit donc être géré par getHTML(), mais uniquement sur la page
 *   finale complète, pas sur chaque fragment de template.
 *
 * Ce patch :
 * - ne modifie que le HTML final contenant il_center_col + mainspacekeeper ;
 * - supprime tout ancien onglet Suivi xAPI déjà injecté, puis réinjecte un lien
 *   propre vers ilias.php?...&itxeb_cui_cmd=showCourseDashboard ;
 * - remplace le contenu central seulement si itxeb_cui_cmd est présent ;
 * - conserve les onglets natifs ILIAS et active l'onglet Suivi xAPI sur les
 *   écrans xAPI.
 *
 * À lancer depuis la racine du plugin EventHook IliasTraxEventBridge :
 * php scripts/patch_v013_stable_gethtml_course_tab.php
 */

function itxeb_stable_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: " . $message . PHP_EOL);
    exit(1);
}

function itxeb_stable_replace_method(string $content, string $methodName, string $replacement, string $file): string
{
    $pattern = '~    (public|private|protected) function ' . preg_quote($methodName, '~') . '\\b~';
    if (!preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
        itxeb_stable_fail("méthode {$methodName} introuvable dans {$file}");
    }

    $start = (int) $m[0][1];
    $brace = strpos($content, '{', $start);
    if ($brace === false) {
        itxeb_stable_fail("accolade ouvrante {$methodName} introuvable dans {$file}");
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

    itxeb_stable_fail("fin méthode {$methodName} introuvable dans {$file}");
}

function itxeb_stable_patch_file(string $file): bool
{
    if (!is_file($file)) {
        echo "IGNORE: fichier absent: {$file}" . PHP_EOL;
        return false;
    }

    $content = file_get_contents($file);
    if (!is_string($content)) {
        itxeb_stable_fail("lecture impossible: {$file}");
    }

    $original = $content;

    // Supprime les traces manuelles de chargement ajoutées pendant le debug.
    $content = preg_replace('/^file_put_contents\([^\n]*itxeb_uihook_loaded\.log[^\n]*\);\R?/m', '', $content) ?? $content;

    $getHtml = <<<'PHP'
    public function getHTML($a_comp, $a_part, $a_par = []): array
    {
        if (!isset($a_par['html']) || !is_string($a_par['html'])) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $html = $a_par['html'];
        $isFinalCoursePage = strpos($html, 'il_center_col') !== false
            && strpos($html, 'mainspacekeeper') !== false;

        if (!$isFinalCoursePage) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $courseRefId = $this->bridge->detectCourseRefId();
        if ($courseRefId <= 0 || !$this->bridge->isCourseRefId($courseRefId)) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }
        if (!$this->bridge->canManageCourse($courseRefId)) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }
        if (!$this->bridge->isMainPluginAvailable() || !$this->bridge->loadCourseTrackingClasses()) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        if (!$this->isCourseUiCommandRequest()) {
            $withTab = $this->injectCourseMainTabIntoHtml($html);
            return $withTab !== $html
                ? ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $withTab]
                : ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $screen = new ilIliasTraxEventBridgeCourseUIScreen($this->bridge);
        $withScreen = $this->replaceCenterColumnContent($html, $screen->handle());
        $withTab = $this->activateInjectedMainTab($this->injectCourseMainTabIntoHtml($withScreen));

        return [
            'mode' => ilUIHookPluginGUI::REPLACE,
            'html' => $withTab,
        ];
    }
PHP;

    $inject = <<<'PHP'
    private function injectCourseMainTabIntoHtml(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        $context = $this->getCurrentCourseContext();
        $url = $this->esc((string) ($context['configuration_url'] ?? ''));
        if ($url === '') {
            return $html;
        }

        $active = $this->isCourseUiCommandRequest();
        $li = $this->mainTabLi($url, $active);
        $anchor = $this->mainTabAnchor($url, $active);

        // Toujours retirer les anciennes variantes de l'onglet : cela évite de
        // conserver un href ILIAS natif ou un lien injecté par une ancienne version.
        $html = preg_replace('/<li\b[^>]*id=("|\')tab_itxeb_course_xapi_main\1[^>]*>.*?<\/li>/isu', '', $html) ?? $html;
        $html = preg_replace('/<a\b[^>]*id=("|\')itxeb_course_xapi_main_tab\1[^>]*>.*?<\/a>/isu', '', $html) ?? $html;
        $html = preg_replace('/<li\b[^>]*>\s*<a\b[^>]*>\s*Suivi\s+xAPI\s*<\/a>\s*<\/li>/isu', '', $html) ?? $html;

        // Position souhaitée : après Info si présent, sinon après Contenu.
        foreach ([
            '/(<li\b[^>]*>\s*<a\b[^>]*>\s*(?:Info|Informations)\s*<\/a>\s*<\/li>)/isu',
            '/(<li\b[^>]*>\s*<a\b[^>]*>\s*(?:Contenu|Content|Inhalt)\s*<\/a>\s*<\/li>)/isu',
            '/(<li\b[^>]*>\s*<a\b[^>]*>\s*(?:Membres|Members|Participants)\s*<\/a>\s*<\/li>)/isu',
        ] as $pattern) {
            $newHtml = preg_replace($pattern, '$1' . $li, $html, 1, $count);
            if (is_string($newHtml) && $count > 0) {
                return $newHtml;
            }
        }

        // Variante avec balises internes dans le lien d'onglet.
        foreach ([
            '/(<li\b[^>]*>\s*<a\b[^>]*>.*?(?:Info|Informations).*?<\/a>\s*<\/li>)/isu',
            '/(<li\b[^>]*>\s*<a\b[^>]*>.*?(?:Contenu|Content|Inhalt).*?<\/a>\s*<\/li>)/isu',
            '/(<li\b[^>]*>\s*<a\b[^>]*>.*?(?:Membres|Members|Participants).*?<\/a>\s*<\/li>)/isu',
        ] as $pattern) {
            $newHtml = preg_replace($pattern, '$1' . $li, $html, 1, $count);
            if (is_string($newHtml) && $count > 0) {
                return $newHtml;
            }
        }

        // Fallback : lien visible au début de la colonne centrale.
        $fallback = '<div class="ilStartupSection" style="margin:8px 0 12px 0;">' . $anchor . '</div>';
        $newHtml = preg_replace('/(<[^>]+id=("|\')il_center_col\2[^>]*>)/isu', '$1' . $fallback, $html, 1, $count);
        return is_string($newHtml) && $count > 0 ? $newHtml : $html . $fallback;
    }
PHP;

    $content = itxeb_stable_replace_method($content, 'getHTML', $getHtml, $file);
    $content = itxeb_stable_replace_method($content, 'injectCourseMainTabIntoHtml', $inject, $file);

    if (strpos($content, "'generateCourseAiAnalysis'") === false) {
        $needle = "            'exportCourseDashboardPdf',\n";
        $insert = "            'exportCourseDashboardPdf',\n            'generateCourseAiAnalysis',\n";
        if (strpos($content, $needle) === false) {
            itxeb_stable_fail("point insertion generateCourseAiAnalysis introuvable dans {$file}");
        }
        $content = str_replace($needle, $insert, $content);
    }

    if ($content === $original) {
        echo "OK: déjà corrigé: {$file}" . PHP_EOL;
        return false;
    }

    if (file_put_contents($file, $content) === false) {
        itxeb_stable_fail("écriture impossible: {$file}");
    }

    echo "PATCH: {$file}" . PHP_EOL;
    return true;
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_stable_fail('lance ce script depuis la racine du plugin EventHook IliasTraxEventBridge.');
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
    $changed = itxeb_stable_patch_file($file) || $changed;
}

echo $changed ? "Fallback stable getHTML onglet Suivi xAPI appliqué." . PHP_EOL : "Aucune modification nécessaire." . PHP_EOL;
