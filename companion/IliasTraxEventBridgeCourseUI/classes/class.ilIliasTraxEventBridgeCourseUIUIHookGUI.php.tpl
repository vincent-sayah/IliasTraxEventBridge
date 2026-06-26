<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIBridge.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIScreen.php';

/**
 * UIHook GUI for exposing course-level xAPI configuration.
 */
class ilIliasTraxEventBridgeCourseUIUIHookGUI extends ilUIHookPluginGUI
{
    /** @var ilIliasTraxEventBridgeCourseUIBridge */
    private $bridge;

    public function __construct()
    {
        $this->bridge = new ilIliasTraxEventBridgeCourseUIBridge();
    }

    /**
     * @param string $a_comp
     * @param string $a_part
     * @param array<string,mixed> $a_par
     * @return array<string,mixed>
     */
    public function getHTML($a_comp, $a_part, $a_par = []): array
    {
        if ($a_part !== 'template_show' || !isset($a_par['html']) || !is_string($a_par['html'])) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        if ($this->isCourseUiCommandRequest()) {
            $screen = new ilIliasTraxEventBridgeCourseUIScreen($this->bridge);
            return [
                'mode' => ilUIHookPluginGUI::REPLACE,
                'html' => $this->replaceCenterColumnContent($a_par['html'], $screen->handle()),
            ];
        }

        if ($this->isReadyForCourseContext()) {
            return [
                'mode' => ilUIHookPluginGUI::REPLACE,
                'html' => $this->injectSubtabIntoHtml($a_par['html']),
            ];
        }

        return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
    }

    /**
     * @param string $a_comp
     * @param string $a_part
     * @param array<string,mixed> $a_par
     */
    public function modifyGUI($a_comp, $a_part, $a_par = []): void
    {
        if ($a_part !== 'sub_tabs' || !isset($a_par['tabs']) || !is_object($a_par['tabs'])) {
            return;
        }

        $this->modifySubTabs($a_par['tabs']);
    }

    private function modifySubTabs($tabs): void
    {
        if (!$this->isReadyForCourseContext()) {
            return;
        }

        $url = $this->getContextualConfigurationUrl();
        if ($url === '') {
            return;
        }

        if (method_exists($tabs, 'addSubTab')) {
            $tabs->addSubTab('itxeb_course_xapi_settings', 'Suivi xAPI', $url);
        } elseif (method_exists($tabs, 'addSubTabTarget')) {
            $tabs->addSubTabTarget('Suivi xAPI', $url, '', '', '', 'itxeb_course_xapi_settings');
        } else {
            return;
        }

        if ($this->isCourseUiCommandRequest()) {
            if (method_exists($tabs, 'setSubTabActive')) {
                $tabs->setSubTabActive('itxeb_course_xapi_settings');
            } elseif (method_exists($tabs, 'activateSubTab')) {
                $tabs->activateSubTab('itxeb_course_xapi_settings');
            }
        }
    }

    /** @return array<string,mixed> */
    public function getCurrentCourseContext(): array
    {
        return $this->bridge->getCourseContext();
    }

    public function isReadyForCourseContext(): bool
    {
        $context = $this->getCurrentCourseContext();

        return (int) ($context['course_ref_id'] ?? 0) > 0
            && (bool) ($context['main_plugin_available'] ?? false)
            && (bool) ($context['course_tracking_classes_available'] ?? false)
            && (bool) ($context['can_manage'] ?? false)
            && (string) ($context['configuration_url'] ?? '') !== '';
    }

    public function getContextualConfigurationUrl(): string
    {
        $context = $this->getCurrentCourseContext();
        return (string) ($context['configuration_url'] ?? '');
    }

    private function injectSubtabIntoHtml(string $html): string
    {
        if ($html === '' || strpos($html, 'itxeb_course_xapi_settings') !== false || strpos($html, 'Suivi xAPI') !== false) {
            return $html;
        }

        $url = $this->esc($this->getContextualConfigurationUrl());
        if ($url === '') {
            return $html;
        }

        $li = '<li id="subtab_itxeb_course_xapi_settings"><a id="itxeb_course_xapi_settings" href="' . $url . '">Suivi xAPI</a></li>';
        $anchor = '<a id="itxeb_course_xapi_settings" href="' . $url . '">Suivi xAPI</a>';

        foreach ([
            '/(<li[^>]*>\s*<a[^>]*>\s*Multi-Linguisme\s*<\/a>\s*<\/li>)/iu',
            '/(<li[^>]*>\s*<a[^>]*>\s*Multilinguisme\s*<\/a>\s*<\/li>)/iu',
            '/(<li[^>]*>\s*<a[^>]*>\s*Multilingualism\s*<\/a>\s*<\/li>)/iu',
        ] as $pattern) {
            $newHtml = preg_replace($pattern, '$1' . $li, $html, 1, $count);
            if (is_string($newHtml) && $count > 0) {
                return $newHtml;
            }
        }

        foreach ([
            '/(<a[^>]*>\s*Multi-Linguisme\s*<\/a>)/iu',
            '/(<a[^>]*>\s*Multilinguisme\s*<\/a>)/iu',
            '/(<a[^>]*>\s*Multilingualism\s*<\/a>)/iu',
        ] as $pattern) {
            $newHtml = preg_replace($pattern, '$1 ' . $anchor, $html, 1, $count);
            if (is_string($newHtml) && $count > 0) {
                return $newHtml;
            }
        }

        $fallback = '<div class="ilStartupSection" style="margin:8px 0 12px 0;"><a id="itxeb_course_xapi_settings" href="' . $url . '">Suivi xAPI</a></div>';
        $newHtml = preg_replace('/(<form\b)/i', $fallback . '$1', $html, 1, $count);
        return is_string($newHtml) && $count > 0 ? $newHtml : $html . $fallback;
    }

    private function replaceCenterColumnContent(string $pageHtml, string $contentHtml): string
    {
        if ($pageHtml === '' || !class_exists('DOMDocument')) {
            return $pageHtml;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $pageHtml);
        if (!$loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $pageHtml;
        }

        $xpath = new DOMXPath($dom);
        $centerColumn = $xpath->query('//*[@id="il_center_col"]')->item(0);
        if (!$centerColumn instanceof DOMElement) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $pageHtml;
        }

        while ($centerColumn->firstChild !== null) {
            $centerColumn->removeChild($centerColumn->firstChild);
        }

        $fragmentDocument = new DOMDocument('1.0', 'UTF-8');
        $fragmentLoaded = $fragmentDocument->loadHTML('<?xml encoding="utf-8" ?><div id="itxeb_cui_wrapper">' . $contentHtml . '</div>');
        if ($fragmentLoaded) {
            $wrapper = $fragmentDocument->getElementById('itxeb_cui_wrapper');
            if ($wrapper instanceof DOMElement) {
                while ($wrapper->firstChild !== null) {
                    $centerColumn->appendChild($dom->importNode($wrapper->firstChild, true));
                    $wrapper->removeChild($wrapper->firstChild);
                }
            }
        }

        $html = $dom->saveHTML();
        $html = preg_replace('/^<\?xml[^>]+>\s*/', '', (string) $html) ?? $pageHtml;
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $html;
    }

    private function isCourseUiCommandRequest(): bool
    {
        $cmd = $this->requestValue($_POST, 'itxeb_cui_cmd');
        if ($cmd === '') {
            $cmd = $this->requestValue($_GET, 'itxeb_cui_cmd');
        }

        return in_array($cmd, [
            'showCourseTracking',
            'saveCourseTracking',
            'enableAllCourseTracking',
            'disableAllCourseTracking',
            'resetCourseTracking',
        ], true);
    }

    private function requestValue($source, string $key): string
    {
        try {
            if (is_array($source)) {
                return isset($source[$key]) && is_scalar($source[$key]) ? (string) $source[$key] : '';
            }
            if ($source instanceof ArrayAccess) {
                return isset($source[$key]) && is_scalar($source[$key]) ? (string) $source[$key] : '';
            }
            if (is_object($source) && method_exists($source, 'offsetExists') && method_exists($source, 'offsetGet')) {
                if (!$source->offsetExists($key)) {
                    return '';
                }
                $value = $source->offsetGet($key);
                return is_scalar($value) ? (string) $value : '';
            }
        } catch (Throwable $ignored) {
            return '';
        }
        return '';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
