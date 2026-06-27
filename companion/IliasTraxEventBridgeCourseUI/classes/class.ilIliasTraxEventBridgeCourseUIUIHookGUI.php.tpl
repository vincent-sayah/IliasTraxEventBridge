<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIBridge.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIScreen.php';

/**
 * UIHook GUI for exposing course-level xAPI configuration and feedback.
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
            $html = $this->replaceCenterColumnContent($a_par['html'], $screen->handle());
            return [
                'mode' => ilUIHookPluginGUI::REPLACE,
                'html' => $this->injectCourseMainTabIntoHtml($html),
            ];
        }

        if ($this->isReadyForCourseContext()) {
            return [
                'mode' => ilUIHookPluginGUI::REPLACE,
                'html' => $this->injectCourseMainTabIntoHtml($a_par['html']),
            ];
        }

        return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
    }

    /**
     * Top-level tab injection is handled through getHTML(), because it is more
     * stable across ILIAS 10 screens than trying to hook different tab objects.
     *
     * @param string $a_comp
     * @param string $a_part
     * @param array<string,mixed> $a_par
     */
    public function modifyGUI($a_comp, $a_part, $a_par = []): void
    {
        // Intentionally empty: do not add Suivi xAPI as a Parameters subtab.
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

    private function injectCourseMainTabIntoHtml(string $html): string
    {
        if ($html === '' || strpos($html, 'itxeb_course_xapi_main_tab') !== false) {
            return $html;
        }

        $url = $this->esc($this->getContextualConfigurationUrl());
        if ($url === '') {
            return $html;
        }

        $active = $this->isCourseUiCommandRequest();
        $liClass = $active ? ' class="active"' : '';
        $aClass = $active ? ' class="active"' : '';
        $li = '<li id="tab_itxeb_course_xapi_main"' . $liClass . '><a id="itxeb_course_xapi_main_tab"' . $aClass . ' href="' . $url . '">Suivi xAPI</a></li>';
        $anchor = '<a id="itxeb_course_xapi_main_tab"' . $aClass . ' href="' . $url . '">Suivi xAPI</a>';

        // Preferred position: immediately after the main course tab "Membres".
        foreach ([
            '/(<li[^>]*>\s*<a[^>]*>\s*Membres\s*<\/a>\s*<\/li>)/iu',
            '/(<li[^>]*>\s*<a[^>]*>\s*Members\s*<\/a>\s*<\/li>)/iu',
            '/(<li[^>]*>\s*<a[^>]*>\s*Participants\s*<\/a>\s*<\/li>)/iu',
        ] as $pattern) {
            $newHtml = preg_replace($pattern, '$1' . $li, $html, 1, $count);
            if (is_string($newHtml) && $count > 0) {
                return $newHtml;
            }
        }

        // Fallback: place it just before Parameters/Settings if Members was not found.
        foreach ([
            '/(<li[^>]*>\s*<a[^>]*>\s*Paramètres\s*<\/a>\s*<\/li>)/iu',
            '/(<li[^>]*>\s*<a[^>]*>\s*Settings\s*<\/a>\s*<\/li>)/iu',
            '/(<li[^>]*>\s*<a[^>]*>\s*Réglages\s*<\/a>\s*<\/li>)/iu',
        ] as $pattern) {
            $newHtml = preg_replace($pattern, $li . '$1', $html, 1, $count);
            if (is_string($newHtml) && $count > 0) {
                return $newHtml;
            }
        }

        // Last fallback for variants without <li>: add the link after a Members anchor.
        foreach ([
            '/(<a[^>]*>\s*Membres\s*<\/a>)/iu',
            '/(<a[^>]*>\s*Members\s*<\/a>)/iu',
            '/(<a[^>]*>\s*Participants\s*<\/a>)/iu',
        ] as $pattern) {
            $newHtml = preg_replace($pattern, '$1 ' . $anchor, $html, 1, $count);
            if (is_string($newHtml) && $count > 0) {
                return $newHtml;
            }
        }

        // Last resort: visible link before the central form/content.
        $fallback = '<div class="ilStartupSection" style="margin:8px 0 12px 0;"><a id="itxeb_course_xapi_main_tab" href="' . $url . '">Suivi xAPI</a></div>';
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
            'showCourseDashboard',
            'showCourseAnalysis',
            'showCourseExpert',
            'exportCourseExpertCsv',
            'saveCourseTracking',
            'saveDashboardPreferences',
            'enableAllCourseTracking',
            'disableAllCourseTracking',
            'resetCourseTracking',
        ], true) || $this->requestValue($_POST, 'itxeb_dashboard_save') === '1';
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
