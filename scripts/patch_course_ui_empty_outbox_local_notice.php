<?php

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_empty_outbox_local_notice.php target.php\n");
    exit(1);
}
$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Cannot read {$file}\n");
    exit(1);
}
if (strpos($code, 'renderLocalOutboxEmptyNotice') !== false) {
    echo "Empty outbox local notice patch already present in {$file}\n";
    exit(0);
}

$needleDashboard = "        \$html = '<section class=\"itxeb-cui-section\"><h2>Tableau de bord du cours</h2><p>Vue synthétique des traces xAPI générées par les ressources du cours.</p>'\n            . \$this->renderPeriodSelector('showCourseDashboard') . \$this->renderResourceFilter(\$course, 'showCourseDashboard') . \$this->renderAnalyticsWarning()\n            . '<div class=\"itxeb-kpi-grid\">'";
$replaceDashboard = "        \$html = '<section class=\"itxeb-cui-section\"><h2>Tableau de bord du cours</h2><p>Vue synthétique des traces xAPI générées par les ressources du cours.</p>'\n            . \$this->renderPeriodSelector('showCourseDashboard') . \$this->renderResourceFilter(\$course, 'showCourseDashboard') . \$this->renderAnalyticsWarning();\n        if (\$this->isLocalDashboardEmpty(\$dashboard)) {\n            return \$html . \$this->renderLocalOutboxEmptyNotice('Tableau de bord local') . \$this->renderLrsDirectSummary(\$course) . '</section>';\n        }\n        \$html .= '<div class=\"itxeb-kpi-grid\">'";
$code2 = str_replace($needleDashboard, $replaceDashboard, $code);
if ($code2 === $code) {
    fwrite(STDERR, "Dashboard insertion point not found in {$file}\n");
    exit(1);
}

$needleAnalysis = "        \$resources = is_array(\$dashboard['by_resource'] ?? null) ? \$dashboard['by_resource'] : [];\n        \$html = '<section class=\"itxeb-cui-section\"><h2>Analyse des ressources</h2><p>Ressources utilisées, peu utilisées, activées sans trace ou associées à des erreurs.</p>' . \$this->renderPeriodSelector('showCourseAnalysis') . \$this->renderResourceFilter(\$course, 'showCourseAnalysis') . \$this->renderAnalyticsWarning();";
$replaceAnalysis = "        \$resources = is_array(\$dashboard['by_resource'] ?? null) ? \$dashboard['by_resource'] : [];\n        \$html = '<section class=\"itxeb-cui-section\"><h2>Analyse des ressources</h2><p>Analyse locale basée sur l’outbox xAPI du plugin.</p>' . \$this->renderPeriodSelector('showCourseAnalysis') . \$this->renderResourceFilter(\$course, 'showCourseAnalysis') . \$this->renderAnalyticsWarning();\n        if (\$this->isLocalDashboardEmpty(\$dashboard)) {\n            return \$html . \$this->renderLocalOutboxEmptyNotice('Analyse locale') . '</section>';\n        }";
$code3 = str_replace($needleAnalysis, $replaceAnalysis, $code2);
if ($code3 === $code2) {
    fwrite(STDERR, "Analysis insertion point not found in {$file}\n");
    exit(1);
}

$needleExpert = "        \$html = '<section class=\"itxeb-cui-section\"><h2>Traces détaillées</h2><p>Vue support des 200 dernières traces locales du cours. Les identités sont limitées au user_id ILIAS.</p>'\n            . \$this->renderPeriodSelector('showCourseExpert') . \$this->renderResourceFilter(\$course, 'showCourseExpert') . \$this->renderAnalyticsWarning()\n            . '<p><a class=\"btn btn-default itxeb-export-button\" href=\"' . \$this->esc(\$exportUrl) . '\">Exporter CSV</a></p>';";
$replaceExpert = "        \$html = '<section class=\"itxeb-cui-section\"><h2>Traces détaillées</h2><p>Vue support des 200 dernières traces locales du cours. Les identités sont limitées au user_id ILIAS.</p>'\n            . \$this->renderPeriodSelector('showCourseExpert') . \$this->renderResourceFilter(\$course, 'showCourseExpert') . \$this->renderAnalyticsWarning()\n            . '<p><a class=\"btn btn-default itxeb-export-button\" href=\"' . \$this->esc(\$exportUrl) . '\">Exporter CSV</a></p>';\n        if (\$this->isLocalDashboardEmpty(\$dashboard)) {\n            return \$html . \$this->renderLocalOutboxEmptyNotice('Vue Expert locale') . '</section>';\n        }";
$code4 = str_replace($needleExpert, $replaceExpert, $code3);
if ($code4 === $code3) {
    fwrite(STDERR, "Expert insertion point not found in {$file}\n");
    exit(1);
}

$helper = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function isLocalDashboardEmpty(array $dashboard): bool
    {
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        return (int) ($summary['total'] ?? 0) === 0
            && (int) ($summary['sent'] ?? 0) === 0
            && (int) ($summary['failed'] ?? 0) === 0;
    }

    private function renderLocalOutboxEmptyNotice(string $viewName): string
    {
        return '<div class="itxeb-cui-alert" style="margin:12px 0">'
            . '<strong>' . $this->esc($viewName) . ' non exploitable actuellement.</strong><br>'
            . 'Les indicateurs de cette vue sont basés sur l’outbox locale du plugin. Cette outbox est vide ou a été purgée. '
            . 'Pour éviter un faux signal, les compteurs et signaux locaux ne sont pas affichés. '
            . 'La lecture directe TRAX/LRS reste disponible dans le tableau de bord.'
            . '</div>';
    }

PHP;
$marker = "    /** @param array<string,mixed> \$course */\n    private function renderResourceFilter";
$code5 = str_replace($marker, $helper . $marker, $code4);
if ($code5 === $code4) {
    fwrite(STDERR, "Helper insertion point not found in {$file}\n");
    exit(1);
}

if (file_put_contents($file, $code5) === false) {
    fwrite(STDERR, "Cannot write {$file}\n");
    exit(1);
}
echo "Empty outbox local notice patch applied to {$file}\n";
