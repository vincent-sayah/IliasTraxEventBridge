<?php

/**
 * Patch generated Course UI screen navigation after moving Suivi xAPI to a main course tab.
 *
 * - default view: Tableau de bord
 * - inner tabs order: Tableau de bord, Analyse, Expert, Configuration
 * - generated inner URLs do not preserve ILIAS cmdClass/cmdNode/cmd from the current screen
 */

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_clean_navigation.php /path/to/class.ilIliasTraxEventBridgeCourseUIScreen.php\n");
    exit(1);
}

$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Unable to read target file: {$file}\n");
    exit(1);
}

if (strpos($code, 'Clean xAPI navigation route') !== false) {
    echo "Clean navigation patch already present in {$file}\n";
    exit(0);
}

$oldTabs = "        \$tabs = ['showCourseTracking' => 'Configuration', 'showCourseDashboard' => 'Tableau de bord', 'showCourseAnalysis' => 'Analyse', 'showCourseExpert' => 'Expert'];";
$newTabs = "        \$tabs = ['showCourseDashboard' => 'Tableau de bord', 'showCourseAnalysis' => 'Analyse', 'showCourseExpert' => 'Expert', 'showCourseTracking' => 'Configuration'];";
$updated = str_replace($oldTabs, $newTabs, $code);
if ($updated === $code) {
    fwrite(STDERR, "Patch failed: unable to replace inner tab order in {$file}\n");
    exit(1);
}

$oldDefault = "        return \$cmd !== '' ? \$cmd : 'showCourseTracking';";
$newDefault = "        return \$cmd !== '' ? \$cmd : 'showCourseDashboard';";
$updated2 = str_replace($oldDefault, $newDefault, $updated);
if ($updated2 === $updated) {
    fwrite(STDERR, "Patch failed: unable to replace default command in {$file}\n");
    exit(1);
}

$oldCurrentUrlWith = <<<'PHP'
    /** @param array<string,string> $params */
    private function currentUrlWith(array $params): string
    {
        $current = $this->currentQueryArray();
        foreach ($params as $key => $value) {
            $current[$key] = $value;
        }
        return $this->currentPath() . '?' . http_build_query($current, '', '&');
    }
PHP;

$newCurrentUrlWith = <<<'PHP'
    /** @param array<string,string> $params */
    private function currentUrlWith(array $params): string
    {
        // Clean xAPI navigation route: never preserve the current ILIAS command
        // stack, otherwise links can inherit ilInfoScreenGUI/edit or Parameters/edit.
        $current = [];
        $current['baseClass'] = 'ilrepositorygui';

        $courseRefId = (int) ($params['itxeb_course_ref_id'] ?? 0);
        if ($courseRefId <= 0) {
            $courseRefId = $this->getCourseRefId();
        }
        if ($courseRefId > 0) {
            $current['ref_id'] = (string) $courseRefId;
            $current['itxeb_course_ref_id'] = (string) $courseRefId;
        }

        $current['cmd'] = 'show';
        foreach ($params as $key => $value) {
            $current[$key] = $value;
        }
        unset($current['cmdClass'], $current['cmdNode']);

        return $this->currentPath() . '?' . http_build_query($current, '', '&');
    }
PHP;

$updated3 = str_replace($oldCurrentUrlWith, $newCurrentUrlWith, $updated2);
if ($updated3 === $updated2) {
    fwrite(STDERR, "Patch failed: unable to replace currentUrlWith in {$file}\n");
    exit(1);
}

if (file_put_contents($file, $updated3) === false) {
    fwrite(STDERR, "Unable to write target file: {$file}\n");
    exit(1);
}

echo "Clean course UI navigation patch applied to {$file}\n";
