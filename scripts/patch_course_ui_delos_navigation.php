<?php

/**
 * Delos/ILIAS 10.8 navigation patch for the generated UIHook GUI.
 *
 * This patch deliberately avoids the experimental native_tabs and standalone
 * patches. It keeps a simple HTML injection of the top-level Suivi xAPI tab,
 * which is the behavior that works with the Delos course tab bar.
 */

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_delos_navigation.php /path/to/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php\n");
    exit(1);
}

$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Unable to read target file: {$file}\n");
    exit(1);
}

if (strpos($code, 'Delos stable xAPI navigation patch') !== false) {
    echo "Delos navigation patch already present in {$file}\n";
    exit(0);
}

$oldBlock = <<<'PHP'
        if ($this->isCourseUiCommandRequest()) {
            $screen = new ilIliasTraxEventBridgeCourseUIScreen($this->bridge);
            $html = $this->replaceCenterColumnContent($a_par['html'], $screen->handle());
            $html = $this->removeNativeCourseChrome($html);
            $html = $this->injectCourseMainTabIntoHtml($html);
            $html = $this->activateInjectedMainTab($html);
            return [
                'mode' => ilUIHookPluginGUI::REPLACE,
                'html' => $html,
            ];
        }
PHP;

$newBlock = <<<'PHP'
        if ($this->isCourseUiCommandRequest()) {
            // Delos stable xAPI navigation patch: keep the normal Delos course
            // tab bar and inject Suivi xAPI into it. Do not remove the tab bar.
            $screen = new ilIliasTraxEventBridgeCourseUIScreen($this->bridge);
            $html = $this->replaceCenterColumnContent($a_par['html'], $screen->handle());
            $html = $this->injectCourseMainTabIntoHtml($html);
            $html = $this->activateInjectedMainTab($html);
            return [
                'mode' => ilUIHookPluginGUI::REPLACE,
                'html' => $html,
            ];
        }
PHP;

$updated = str_replace($oldBlock, $newBlock, $code);
if ($updated === $code) {
    fwrite(STDERR, "Patch failed: unable to replace xAPI getHTML block in {$file}\n");
    exit(1);
}

$oldReady = <<<'PHP'
        return (int) ($context['course_ref_id'] ?? 0) > 0
            && (bool) ($context['main_plugin_available'] ?? false)
            && (bool) ($context['course_tracking_classes_available'] ?? false)
            && (bool) ($context['can_manage'] ?? false)
            && (string) ($context['configuration_url'] ?? '') !== '';
PHP;

$newReady = <<<'PHP'
        return (int) ($context['course_ref_id'] ?? 0) > 0
            && (bool) ($context['main_plugin_available'] ?? false)
            && (bool) ($context['course_tracking_classes_available'] ?? false)
            && (string) ($context['configuration_url'] ?? '') !== '';
PHP;

$updated2 = str_replace($oldReady, $newReady, $updated);
if ($updated2 === $updated) {
    fwrite(STDERR, "Patch failed: unable to relax course context readiness in {$file}\n");
    exit(1);
}

// Keep the helper methods in the file; they are harmless. The important point is
// that getHTML no longer calls removeNativeCourseChrome().
if (file_put_contents($file, $updated2) === false) {
    fwrite(STDERR, "Unable to write target file: {$file}\n");
    exit(1);
}

echo "Delos stable xAPI navigation patch applied to {$file}\n";
