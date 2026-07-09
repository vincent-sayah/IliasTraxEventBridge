<?php
$root = getcwd();
$template = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl';
$servicesRoot = dirname(dirname(dirname($root)));
$live = $servicesRoot . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php';

function rf(string $file): string {
    $s = file_get_contents($file);
    if (!is_string($s)) {
        fwrite(STDERR, "Lecture impossible: $file\n");
        exit(1);
    }
    return $s;
}

function wf(string $file, string $old, string $new): void {
    if ($old !== $new) {
        file_put_contents($file, $new);
        echo "WRITE: $file\n";
    } else {
        echo "OK: aucun changement $file\n";
    }
}

function patchHookFile(string $file): void {
    $old = rf($file);
    $h = $old;

    $needle = "        $" . "html = $" . "a_par['html'];\n";
    $insert = "        $" . "html = $" . "a_par['html'];\n"
        . "        $" . "cleanHtml = $" . "this->removeInjectedCourseEntryBlock($" . "html);\n"
        . "        if ($" . "cleanHtml !== $" . "html) {\n"
        . "            return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $" . "cleanHtml];\n"
        . "        }\n";
    if (strpos($h, 'removeInjectedCourseEntryBlock($html)') === false) {
        if (strpos($h, $needle) === false) {
            fwrite(STDERR, "Point insertion nettoyage introuvable: $file\n");
            exit(1);
        }
        $h = str_replace($needle, $insert, $h);
        echo "PATCH: nettoyage forcé en entrée getHTML ($file)\n";
    } else {
        echo "OK: nettoyage getHTML déjà présent ($file)\n";
    }

    if (strpos($h, 'private function removeInjectedCourseEntryBlock(string $html): string') === false) {
        $method = <<<'PHP'
    private function removeInjectedCourseEntryBlock(string $html): string
    {
        if (strpos($html, 'itxeb_course_xapi_entry') === false && strpos($html, 'itxeb-course-xapi-entry') === false && strpos($html, 'Ouvrir le suivi xAPI') === false) {
            return $html;
        }
        if (class_exists('DOMDocument')) {
            $internalErrors = libxml_use_internal_errors(true);
            $dom = new DOMDocument('1.0', 'UTF-8');
            $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            if ($loaded) {
                $node = $dom->getElementById('itxeb_course_xapi_entry');
                if ($node instanceof DOMNode && $node->parentNode instanceof DOMNode) {
                    $node->parentNode->removeChild($node);
                    $result = $dom->saveHTML();
                    $result = preg_replace('/^<\?xml[^>]+>\s*/', '', (string) $result) ?? (string) $result;
                    libxml_clear_errors();
                    libxml_use_internal_errors($internalErrors);
                    return $result;
                }
            }
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        }
        $clean = preg_replace('/<div\s+id=("|\')itxeb_course_xapi_entry\1\b.*?<\/div>/isu', '', $html, 1);
        return is_string($clean) ? $clean : $html;
    }

PHP;
        $marker = "    private function injectCourseEntryButton(string $" . "html, string $" . "url): string";
        $pos = strpos($h, $marker);
        if ($pos === false) {
            $marker = "    private function replaceCenterColumnContent(string $" . "html, string $" . "content): string";
            $pos = strpos($h, $marker);
        }
        if ($pos === false) {
            fwrite(STDERR, "Point insertion méthode nettoyage introuvable: $file\n");
            exit(1);
        }
        $h = substr($h, 0, $pos) . $method . substr($h, $pos);
        echo "PATCH: méthode removeInjectedCourseEntryBlock ($file)\n";
    } else {
        echo "OK: méthode nettoyage déjà présente ($file)\n";
    }

    $startMarker = "    private function injectCourseEntryButton(string $" . "html, string $" . "url): string\n";
    $start = strpos($h, $startMarker);
    $endMarker = "    private function replaceCenterColumnContent";
    $end = strpos($h, $endMarker, $start === false ? 0 : $start);
    if ($start !== false && $end !== false) {
        $newMethod = "    private function injectCourseEntryButton(string $" . "html, string $" . "url): string\n    {\n        return $" . "html;\n    }\n\n";
        $current = substr($h, $start, $end - $start);
        if ($current !== $newMethod) {
            $h = substr($h, 0, $start) . $newMethod . substr($h, $end);
            echo "PATCH: neutralisation injectCourseEntryButton ($file)\n";
        } else {
            echo "OK: injectCourseEntryButton déjà neutralisée ($file)\n";
        }
    } else {
        echo "INFO: injectCourseEntryButton absente ou déjà supprimée ($file)\n";
    }

    $pattern = "/\s*\$url = \$this->buildRouterUrl\(\$courseRefId, 'showDashboard'\);\s*\$newHtml = \$this->injectCourseEntryButton\(\$html, \$url\);\s*return \$newHtml !== \$html\s*\? \['mode' => ilUIHookPluginGUI::REPLACE, 'html' => \$newHtml\]\s*: \['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''\];/s";
    $replacement = "\n        return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];";
    $new = preg_replace($pattern, $replacement, $h, 1, $count);
    if (is_string($new)) {
        $h = $new;
        echo $count > 0 ? "PATCH: suppression appel injectCourseEntryButton ($file)\n" : "OK: appel injectCourseEntryButton absent ($file)\n";
    }

    wf($file, $old, $h);
}

patchHookFile($template);
if (is_file($live)) {
    copy($template, $live);
    echo "COPY: $template -> $live\n";
    patchHookFile($live);
}

echo "V0.20.1 suppression forcée du bloc Suivi xAPI terminée\n";
