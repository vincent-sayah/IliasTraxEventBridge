<?php

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_delos_info_route.php /path/to/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php\n");
    exit(1);
}

$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Cannot read target file: {$file}\n");
    exit(1);
}

if (strpos($code, 'Delos Info/showSummary route for xAPI') !== false) {
    echo "Delos Info route patch already present in {$file}\n";
    exit(0);
}

$oldBase = <<<'PHP'
        $baseHref = $this->findMainTabHref($html, ['Contenu', 'Content', 'Inhalt'])
            ?: $this->findMainTabHref($html, ['Membres', 'Members', 'Participants'])
            ?: $this->findMainTabHref($html, ['Paramètres', 'Settings', 'Réglages'])
            ?: $this->getContextualConfigurationUrl();
PHP;

$newBase = <<<'PHP'
        // Delos Info/showSummary route for xAPI: use Info as support page.
        $baseHref = $this->findMainTabHref($html, ['Info', 'Information'])
            ?: $this->findMainTabHref($html, ['Contenu', 'Content', 'Inhalt'])
            ?: $this->findMainTabHref($html, ['Membres', 'Members', 'Participants'])
            ?: $this->findMainTabHref($html, ['Paramètres', 'Settings', 'Réglages'])
            ?: $this->getContextualConfigurationUrl();
PHP;

$updated = str_replace($oldBase, $newBase, $code);
if ($updated === $code) {
    fwrite(STDERR, "Base href block not found in {$file}\n");
    exit(1);
}

$oldCmd = <<<'PHP'
        // Avoid invalid commands such as ilObjCourseGUI::showObject(). Keep the
        // valid ILIAS routing parameters already present in the selected tab URL.
        unset($query['cmd']);
        $query['itxeb_cui_cmd'] = 'showCourseDashboard';
PHP;

$newCmd = <<<'PHP'
        // Keep the support tab route, normally Info/showSummary on Delos.
        $query['itxeb_cui_cmd'] = 'showCourseDashboard';
PHP;

$updated2 = str_replace($oldCmd, $newCmd, $updated);
if ($updated2 === $updated) {
    fwrite(STDERR, "Command block not found in {$file}\n");
    exit(1);
}

if (file_put_contents($file, $updated2) === false) {
    fwrite(STDERR, "Cannot write target file: {$file}\n");
    exit(1);
}

echo "Delos Info route patch applied to {$file}\n";
