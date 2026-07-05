<?php
/**
 * Correctif V0.13 - onglet de cours Suivi xAPI par DOMDocument.
 *
 * Constat ILIAS 10 validé sur l'instance :
 * - la classe UIHook est bien chargée ;
 * - modifyGUI(..., "tabs", ...) n'est pas appelé pour la page cours ;
 * - getHTML() est appelé sur la page finale complète.
 *
 * Ce script remplace l'ancienne injection par expressions régulières par une
 * manipulation DOM limitée à la page finale du cours :
 * - suppression robuste de tous les anciens onglets/liens Suivi xAPI ;
 * - insertion d'un vrai <li><a> dans la liste d'onglets existante ;
 * - activation/désactivation des classes active sur les onglets frères ;
 * - remplacement du contenu central uniquement lorsque itxeb_cui_cmd est présent.
 *
 * À lancer depuis la racine du plugin EventHook IliasTraxEventBridge :
 * php scripts/patch_v013_dom_course_tabs.php
 */

function itxeb_dom_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: " . $message . PHP_EOL);
    exit(1);
}

function itxeb_dom_replace_method(string $content, string $methodName, string $replacement, string $file): string
{
    $pattern = '~    (public|private|protected) function ' . preg_quote($methodName, '~') . '\\b~';
    if (!preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
        itxeb_dom_fail("méthode {$methodName} introuvable dans {$file}");
    }

    $start = (int) $m[0][1];
    $brace = strpos($content, '{', $start);
    if ($brace === false) {
        itxeb_dom_fail("accolade ouvrante {$methodName} introuvable dans {$file}");
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

    itxeb_dom_fail("fin méthode {$methodName} introuvable dans {$file}");
}

function itxeb_dom_patch_file(string $file): bool
{
    if (!is_file($file)) {
        echo "IGNORE: fichier absent: {$file}" . PHP_EOL;
        return false;
    }

    $content = file_get_contents($file);
    if (!is_string($content)) {
        itxeb_dom_fail("lecture impossible: {$file}");
    }

    $original = $content;

    // Nettoyage des probes de debug ajoutés manuellement.
    $content = preg_replace('/^file_put_contents\([^\n]*itxeb_uihook_loaded\.log[^\n]*\);\R?/m', '', $content) ?? $content;

    $getHtml = <<<'PHP'
    public function getHTML($a_comp, $a_part, $a_par = []): array
    {
        if (!isset($a_par['html']) || !is_string($a_par['html'])) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $html = $a_par['html'];
        if (strpos($html, 'il_center_col') === false || strpos($html, 'mainspacekeeper') === false) {
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

        if ($this->isCourseUiCommandRequest()) {
            $screen = new ilIliasTraxEventBridgeCourseUIScreen($this->bridge);
            $html = $this->replaceCenterColumnContent($html, $screen->handle());
            $html = $this->injectCourseMainTabIntoHtml($html);
            $html = $this->activateInjectedMainTab($html);
            return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $html];
        }

        $htmlWithTab = $this->injectCourseMainTabIntoHtml($html);
        return $htmlWithTab !== $html
            ? ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $htmlWithTab]
            : ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
    }
PHP;

    $modifyGui = <<<'PHP'
    public function modifyGUI($a_comp, $a_part, $a_par = []): void
    {
        // ILIAS 10 garde ce hook pour certains écrans seulement. Sur la page
        // cours testée, il n'est pas appelé avec $a_part="tabs". L'onglet cours
        // est donc posé dans getHTML(), uniquement sur le rendu final complet.
    }
PHP;

    $inject = <<<'PHP'
    private function injectCourseMainTabIntoHtml(string $html): string
    {
        if ($html === '' || !class_exists('DOMDocument')) {
            return $html;
        }

        $context = $this->getCurrentCourseContext();
        $url = (string) ($context['configuration_url'] ?? '');
        if ($url === '') {
            return $html;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        if (!$loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $html;
        }

        $this->removeExistingCourseXapiTabs($dom);
        $targetLi = $this->findCourseTabInsertionPoint($dom);
        if (!$targetLi instanceof DOMElement || !$targetLi->parentNode instanceof DOMNode) {
            $html = $this->insertCourseXapiFallbackLink($dom, $url);
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $html;
        }

        $active = $this->isCourseUiCommandRequest();
        $newLi = $this->createCourseXapiTabNode($dom, $targetLi, $url, $active);
        if ($targetLi->nextSibling instanceof DOMNode) {
            $targetLi->parentNode->insertBefore($newLi, $targetLi->nextSibling);
        } else {
            $targetLi->parentNode->appendChild($newLi);
        }

        if ($active) {
            $this->deactivateSiblingTabs($newLi);
            $this->addClass($newLi, 'active');
            foreach ($newLi->getElementsByTagName('a') as $a) {
                if ($a instanceof DOMElement) {
                    $this->addClass($a, 'active');
                    $a->setAttribute('aria-selected', 'true');
                }
            }
        }

        $result = $dom->saveHTML();
        $result = preg_replace('/^<\?xml[^>]+>\s*/', '', (string) $result) ?? (string) $result;
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
        return $result;
    }
PHP;

    $activate = <<<'PHP'
    private function activateInjectedMainTab(string $html): string
    {
        if ($html === '' || !class_exists('DOMDocument')) {
            return $html;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        if (!$loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $html;
        }

        $tab = $dom->getElementById('tab_itxeb_course_xapi_main');
        if ($tab instanceof DOMElement) {
            $this->deactivateSiblingTabs($tab);
            $this->addClass($tab, 'active');
            foreach ($tab->getElementsByTagName('a') as $a) {
                if ($a instanceof DOMElement) {
                    $this->addClass($a, 'active');
                    $a->setAttribute('aria-selected', 'true');
                }
            }
        }

        $result = $dom->saveHTML();
        $result = preg_replace('/^<\?xml[^>]+>\s*/', '', (string) $result) ?? (string) $result;
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
        return $result;
    }
PHP;

    $content = itxeb_dom_replace_method($content, 'getHTML', $getHtml, $file);
    $content = itxeb_dom_replace_method($content, 'modifyGUI', $modifyGui, $file);
    $content = itxeb_dom_replace_method($content, 'injectCourseMainTabIntoHtml', $inject, $file);
    $content = itxeb_dom_replace_method($content, 'activateInjectedMainTab', $activate, $file);

    if (strpos($content, 'function removeExistingCourseXapiTabs(') === false) {
        $helpers = <<<'PHP'

    private function removeExistingCourseXapiTabs(DOMDocument $dom): void
    {
        $toRemove = [];
        foreach ($dom->getElementsByTagName('a') as $a) {
            if (!$a instanceof DOMElement) {
                continue;
            }
            $text = trim(preg_replace('/\s+/u', ' ', $a->textContent) ?? '');
            if ((string) $a->getAttribute('id') === 'itxeb_course_xapi_main_tab' || stripos($text, 'Suivi xAPI') !== false) {
                $li = $this->findAncestorByTag($a, 'li');
                $toRemove[] = $li instanceof DOMElement ? $li : $a;
            }
        }

        foreach ($toRemove as $node) {
            if ($node instanceof DOMNode && $node->parentNode instanceof DOMNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }

    private function findCourseTabInsertionPoint(DOMDocument $dom): ?DOMElement
    {
        $preferred = [
            ['Info', 'Informations'],
            ['Contenu', 'Content', 'Inhalt'],
            ['Membres', 'Members', 'Participants'],
        ];

        foreach ($preferred as $labels) {
            foreach ($dom->getElementsByTagName('a') as $a) {
                if (!$a instanceof DOMElement) {
                    continue;
                }
                $text = trim(preg_replace('/\s+/u', ' ', $a->textContent) ?? '');
                foreach ($labels as $label) {
                    if (strcasecmp($text, $label) === 0 || stripos($text, $label) !== false) {
                        $li = $this->findAncestorByTag($a, 'li');
                        if ($li instanceof DOMElement && $li->parentNode instanceof DOMElement && strtolower($li->parentNode->tagName) === 'ul') {
                            return $li;
                        }
                    }
                }
            }
        }

        return null;
    }

    private function createCourseXapiTabNode(DOMDocument $dom, DOMElement $templateLi, string $url, bool $active): DOMElement
    {
        $li = $dom->createElement('li');
        $li->setAttribute('id', 'tab_itxeb_course_xapi_main');

        $liClass = trim($templateLi->getAttribute('class'));
        if ($liClass !== '') {
            $li->setAttribute('class', $liClass);
            $this->removeClass($li, 'active');
        }
        if ($active) {
            $this->addClass($li, 'active');
        }

        $templateA = null;
        foreach ($templateLi->getElementsByTagName('a') as $a) {
            if ($a instanceof DOMElement) {
                $templateA = $a;
                break;
            }
        }

        $a = $dom->createElement('a');
        $a->setAttribute('id', 'itxeb_course_xapi_main_tab');
        $a->setAttribute('href', $url);
        $a->appendChild($dom->createTextNode('Suivi xAPI'));
        if ($templateA instanceof DOMElement) {
            foreach (['class', 'role', 'data-toggle', 'aria-controls'] as $attr) {
                if ($templateA->hasAttribute($attr)) {
                    $a->setAttribute($attr, $templateA->getAttribute($attr));
                }
            }
        }
        $this->removeClass($a, 'active');
        $a->setAttribute('aria-selected', $active ? 'true' : 'false');
        if ($active) {
            $this->addClass($a, 'active');
        }

        $li->appendChild($a);
        return $li;
    }

    private function deactivateSiblingTabs(DOMElement $tab): void
    {
        $parent = $tab->parentNode;
        if (!$parent instanceof DOMNode) {
            return;
        }

        foreach ($parent->childNodes as $sibling) {
            if (!$sibling instanceof DOMElement || $sibling->isSameNode($tab)) {
                continue;
            }
            $this->removeClass($sibling, 'active');
            foreach ($sibling->getElementsByTagName('a') as $a) {
                if ($a instanceof DOMElement) {
                    $this->removeClass($a, 'active');
                    if ($a->hasAttribute('aria-selected')) {
                        $a->setAttribute('aria-selected', 'false');
                    }
                }
            }
        }
    }

    private function insertCourseXapiFallbackLink(DOMDocument $dom, string $url): string
    {
        $center = $dom->getElementById('il_center_col');
        if (!$center instanceof DOMElement) {
            $result = $dom->saveHTML();
            return preg_replace('/^<\?xml[^>]+>\s*/', '', (string) $result) ?? (string) $result;
        }

        $div = $dom->createElement('div');
        $div->setAttribute('class', 'ilStartupSection itxeb-course-xapi-fallback');
        $div->setAttribute('style', 'margin:8px 0 12px 0;');
        $a = $dom->createElement('a');
        $a->setAttribute('id', 'itxeb_course_xapi_main_tab');
        $a->setAttribute('href', $url);
        $a->appendChild($dom->createTextNode('Suivi xAPI'));
        $div->appendChild($a);

        if ($center->firstChild instanceof DOMNode) {
            $center->insertBefore($div, $center->firstChild);
        } else {
            $center->appendChild($div);
        }

        $result = $dom->saveHTML();
        return preg_replace('/^<\?xml[^>]+>\s*/', '', (string) $result) ?? (string) $result;
    }

    private function findAncestorByTag(DOMElement $node, string $tag): ?DOMElement
    {
        $tag = strtolower($tag);
        $current = $node->parentNode;
        while ($current instanceof DOMElement) {
            if (strtolower($current->tagName) === $tag) {
                return $current;
            }
            if (in_array(strtolower($current->tagName), ['body', 'html'], true)) {
                return null;
            }
            $current = $current->parentNode;
        }
        return null;
    }
PHP;

        $marker = "\n    /** @param array<int,string> $labels */\n    private function findMainTabHref";
        if (strpos($content, $marker) === false) {
            itxeb_dom_fail("point insertion helpers DOM introuvable dans {$file}");
        }
        $content = str_replace($marker, $helpers . $marker, $content);
    }

    if (strpos($content, "'generateCourseAiAnalysis'") === false) {
        $needle = "            'exportCourseDashboardPdf',\n";
        $insert = "            'exportCourseDashboardPdf',\n            'generateCourseAiAnalysis',\n";
        if (strpos($content, $needle) === false) {
            itxeb_dom_fail("point insertion generateCourseAiAnalysis introuvable dans {$file}");
        }
        $content = str_replace($needle, $insert, $content);
    }

    if ($content === $original) {
        echo "OK: déjà corrigé: {$file}" . PHP_EOL;
        return false;
    }

    if (file_put_contents($file, $content) === false) {
        itxeb_dom_fail("écriture impossible: {$file}");
    }

    echo "PATCH: {$file}" . PHP_EOL;
    return true;
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_dom_fail('lance ce script depuis la racine du plugin EventHook IliasTraxEventBridge.');
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
    $changed = itxeb_dom_patch_file($file) || $changed;
}

echo $changed ? "Correctif DOM onglet Suivi xAPI appliqué." . PHP_EOL : "Aucune modification nécessaire." . PHP_EOL;
