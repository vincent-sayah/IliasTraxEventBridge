<?php

/**
 * Patch generated UIHook class to handle Suivi xAPI as a real top-level course tab.
 *
 * This complements the HTML fallback injection by using the native ILIAS tabs
 * object when available. On xAPI requests it also clears native sub tabs so the
 * only visible navigation is the xAPI inner navigation.
 */

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_native_tabs.php /path/to/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php\n");
    exit(1);
}

$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Unable to read target file: {$file}\n");
    exit(1);
}

if (strpos($code, 'native xAPI top-level tab patch') !== false) {
    echo "Native xAPI tab patch already present in {$file}\n";
    exit(0);
}

$old = <<<'PHP'
    public function modifyGUI($a_comp, $a_part, $a_par = []): void
    {
        // Intentionally empty: do not add Suivi xAPI as a Parameters subtab.
    }
PHP;

$new = <<<'PHP'
    public function modifyGUI($a_comp, $a_part, $a_par = []): void
    {
        // native xAPI top-level tab patch
        if (!isset($a_par['tabs']) || !is_object($a_par['tabs']) || !$this->isReadyForCourseContext()) {
            return;
        }

        $tabs = $a_par['tabs'];
        $url = $this->getContextualConfigurationUrl();
        if ($url === '') {
            return;
        }

        if ($a_part === 'tabs') {
            if (method_exists($tabs, 'addTab')) {
                $tabs->addTab('itxeb_course_xapi_main_tab', 'Suivi xAPI', $url);
            } elseif (method_exists($tabs, 'addTarget')) {
                $tabs->addTarget('Suivi xAPI', $url, '', '', '', 'itxeb_course_xapi_main_tab');
            } elseif (method_exists($tabs, 'addTargetTab')) {
                $tabs->addTargetTab('itxeb_course_xapi_main_tab', 'Suivi xAPI', $url);
            }

            if ($this->isCourseUiCommandRequest()) {
                if (method_exists($tabs, 'setTabActive')) {
                    $tabs->setTabActive('itxeb_course_xapi_main_tab');
                } elseif (method_exists($tabs, 'activateTab')) {
                    $tabs->activateTab('itxeb_course_xapi_main_tab');
                }
            }
            return;
        }

        if ($a_part === 'sub_tabs' && $this->isCourseUiCommandRequest()) {
            foreach (['clearSubTabs', 'clearSubTabTargets', 'clearTargets'] as $method) {
                if (method_exists($tabs, $method)) {
                    $tabs->{$method}();
                    return;
                }
            }
        }
    }
PHP;

$updated = str_replace($old, $new, $code);
if ($updated === $code) {
    fwrite(STDERR, "Patch failed: unable to replace modifyGUI in {$file}\n");
    exit(1);
}

if (file_put_contents($file, $updated) === false) {
    fwrite(STDERR, "Unable to write target file: {$file}\n");
    exit(1);
}

echo "Native xAPI top-level tab patch applied to {$file}\n";
