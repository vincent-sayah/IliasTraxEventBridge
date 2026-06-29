<?php

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_pdf_export.php target.php\n");
    exit(1);
}
$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Cannot read {$file}\n");
    exit(1);
}
if (strpos($code, 'sendDashboardPdf') !== false) {
    echo "PDF export patch already present in {$file}\n";
    exit(0);
}
$updated = $code;

$updated = str_replace(
    "if (\$cmd === 'exportCourseExpertCsv') {\n            \$this->sendExpertCsv(\$course);\n        }",
    "if (\$cmd === 'exportCourseDashboardPdf') {\n            \$this->sendDashboardPdf(\$course);\n        }\n        if (\$cmd === 'exportCourseExpertCsv') {\n            \$this->sendExpertCsv(\$course);\n        }",
    $updated
);

$updated = str_replace(
    "in_array(\$cmd, ['showCourseDashboard', 'showCourseAnalysis', 'showCourseExpert', 'exportCourseExpertCsv'], true)",
    "in_array(\$cmd, ['showCourseDashboard', 'showCourseAnalysis', 'showCourseExpert', 'exportCourseExpertCsv', 'exportCourseDashboardPdf'], true)",
    $updated
);
$updated = str_replace(
    "? (\$cmd === 'exportCourseExpertCsv' ? 'showCourseExpert' : \$cmd)",
    "? (\$cmd === 'exportCourseExpertCsv' ? 'showCourseExpert' : (\$cmd === 'exportCourseDashboardPdf' ? 'showCourseDashboard' : \$cmd))",
    $updated
);

$needle = "            . \$this->renderPeriodSelector('showCourseDashboard') . \$this->renderResourceFilter(\$course, 'showCourseDashboard') . \$this->renderAnalyticsWarning()";
$replace = "            . \$this->renderPeriodSelector('showCourseDashboard') . \$this->renderResourceFilter(\$course, 'showCourseDashboard') . \$this->renderDashboardPdfButton(\$course) . \$this->renderAnalyticsWarning()";
$updated = str_replace($needle, $replace, $updated);

$method = <<<'PHP'
    /** @param array<string,mixed> $course */
    private function renderDashboardPdfButton(array $course): string
    {
        $url = $this->currentUrlWith([
            'itxeb_cui_cmd' => 'exportCourseDashboardPdf',
            'itxeb_course_ref_id' => (string) ($course['course_ref_id'] ?? 0),
            'itxeb_period_days' => (string) $this->getPeriodDays(),
            'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
            'itxeb_filter_type' => $this->getSelectedObjectType(),
        ]);
        return '<p class="itxeb-export-button"><a class="btn btn-default" href="' . $this->esc($url) . '">Export PDF</a></p>';
    }

    /** @param array<string,mixed> $course */
    private function sendDashboardPdf(array $course): void
    {
        $dashboard = $this->loadDashboard($course);
        $html = $this->buildDashboardPdfHtml($course, $dashboard);
        $filename = 'suivi-xapi-cours-' . (string) ((int) ($course['course_ref_id'] ?? 0)) . '-' . gmdate('Ymd-His') . '.pdf';

        if (class_exists('Dompdf\\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $this->sendPdfBytes((string) $dompdf->output(), $filename);
        }

        $wkhtmltopdf = $this->findWkhtmltopdfBinary();
        if ($wkhtmltopdf !== '') {
            $pdf = $this->renderPdfWithWkhtmltopdf($wkhtmltopdf, $html);
            if ($pdf !== '') {
                $this->sendPdfBytes($pdf, $filename);
            }
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Export PDF indisponible</title></head><body>'
            . '<h1>Export PDF indisponible</h1>'
            . '<p>Aucun moteur PDF serveur disponible : Dompdf absent et wkhtmltopdf introuvable ou en erreur.</p>'
            . '<p>Le rapport HTML ci-dessous est généré depuis TRAX/LRS et peut être imprimé en PDF depuis le navigateur.</p>'
            . $html
            . '</body></html>';
        exit;
    }

    private function sendPdfBytes(string $pdf, string $filename): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private function findWkhtmltopdfBinary(): string
    {
        foreach (['/usr/local/bin/wkhtmltopdf', '/usr/bin/wkhtmltopdf', '/bin/wkhtmltopdf'] as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }
        if (function_exists('shell_exec')) {
            $found = trim((string) @shell_exec('command -v wkhtmltopdf 2>/dev/null'));
            if ($found !== '' && is_file($found) && is_executable($found)) {
                return $found;
            }
        }
        return '';
    }

    private function renderPdfWithWkhtmltopdf(string $binary, string $html): string
    {
        $input = tempnam(sys_get_temp_dir(), 'itxeb_pdf_');
        $output = tempnam(sys_get_temp_dir(), 'itxeb_pdf_');
        if (!is_string($input) || !is_string($output)) {
            return '';
        }
        $htmlFile = $input . '.html';
        $pdfFile = $output . '.pdf';
        @unlink($input);
        @unlink($output);
        if (file_put_contents($htmlFile, $html) === false) {
            return '';
        }
        $cmd = escapeshellarg($binary)
            . ' --encoding utf-8 --quiet --disable-local-file-access '
            . escapeshellarg($htmlFile) . ' ' . escapeshellarg($pdfFile) . ' 2>&1';
        $result = 1;
        $lines = [];
        @exec($cmd, $lines, $result);
        $pdf = '';
        if ($result === 0 && is_file($pdfFile)) {
            $bytes = file_get_contents($pdfFile);
            if (is_string($bytes)) {
                $pdf = $bytes;
            }
        }
        @unlink($htmlFile);
        @unlink($pdfFile);
        return $pdf;
    }

    /** @param array<string,mixed> $course @param array<string,mixed> $dashboard */
    private function buildDashboardPdfHtml(array $course, array $dashboard): string
    {
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $title = (string) ($course['course_title'] ?? 'Cours');
        $resourceFilter = $this->getSelectedResourceRefId() > 0 ? (string) $this->getSelectedResourceRefId() : 'toutes';
        $typeFilter = $this->getSelectedObjectType() !== '' ? $this->getSelectedObjectType() : 'tous';
        $html = '<html><head><meta charset="utf-8"><style>'
            . 'body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#222}h1{font-size:20px;margin:0 0 6px}h2{font-size:15px;margin:18px 0 6px;border-bottom:1px solid #999;padding-bottom:3px}table{width:100%;border-collapse:collapse;margin:6px 0 12px}th,td{border:1px solid #bbb;padding:4px 5px;vertical-align:top}th{background:#eee}.small{font-size:9px;color:#555}.kpi td{width:25%}'
            . '</style></head><body>';
        $html .= '<h1>Rapport Suivi xAPI — ' . $this->esc($title) . '</h1>';
        $html .= '<p class="small">Source fonctionnelle : TRAX/LRS direct. Généré le ' . $this->esc(gmdate('Y-m-d H:i:s') . ' UTC') . '.</p>';
        $html .= '<h2>Contexte</h2><table><tbody>'
            . $this->row('course_ref_id', (string) ($course['course_ref_id'] ?? 0))
            . $this->row('Période', (string) $this->getPeriodDays() . ' jours')
            . $this->row('Filtre ressource', $resourceFilter)
            . $this->row('Filtre type', $typeFilter)
            . '</tbody></table>';
        $html .= '<h2>Synthèse</h2><table class="kpi"><tbody><tr>'
            . '<td><strong>Statements TRAX</strong><br>' . $this->esc((string) ($summary['total'] ?? 0)) . '</td>'
            . '<td><strong>Apprenants actifs</strong><br>' . $this->esc((string) ($summary['active_learners'] ?? 0)) . '</td>'
            . '<td><strong>Ressources utilisées</strong><br>' . $this->esc((string) ($summary['resources_with_traces'] ?? 0)) . ' / ' . $this->esc((string) ($summary['resources_total'] ?? 0)) . '</td>'
            . '<td><strong>Score moyen</strong><br>' . $this->esc(($summary['avg_score_raw'] ?? null) === null ? '-' : (string) $summary['avg_score_raw'] . ' %') . '</td>'
            . '</tr><tr>'
            . '<td><strong>Sans statement TRAX</strong><br>' . $this->esc((string) $this->countEnabledWithoutTraceResources($dashboard)) . '</td>'
            . '<td><strong>Pages LRS</strong><br>' . $this->esc((string) ($dashboard['pages'] ?? 0)) . '</td>'
            . '<td><strong>Tests réussis</strong><br>' . $this->esc((string) ($summary['tests_passed'] ?? 0)) . '</td>'
            . '<td><strong>Tests échoués</strong><br>' . $this->esc((string) ($summary['tests_failed'] ?? 0)) . '</td>'
            . '</tr></tbody></table>';
        $html .= $this->pdfSimpleCountTable('Activité par jour', is_array($dashboard['by_day'] ?? null) ? $dashboard['by_day'] : []);
        $verbItems = [];
        foreach ((array) ($dashboard['by_verb'] ?? []) as $verb) {
            $verbItems[(string) ($verb['label'] ?? '')] = (int) ($verb['count'] ?? 0);
        }
        $html .= $this->pdfSimpleCountTable('Actions xAPI', array_slice($verbItems, 0, 12, true));
        $html .= $this->pdfResourcesTable($dashboard);
        return $html . '</body></html>';
    }

    /** @param array<string,int> $items */
    private function pdfSimpleCountTable(string $title, array $items): string
    {
        $html = '<h2>' . $this->esc($title) . '</h2>';
        if (count($items) === 0) {
            return $html . '<p><em>Aucune donnée.</em></p>';
        }
        $html .= '<table><thead><tr><th>Libellé</th><th style="width:90px">Nombre</th></tr></thead><tbody>';
        foreach ($items as $label => $count) {
            $html .= '<tr><td>' . $this->esc((string) $label) . '</td><td>' . $this->esc((string) $count) . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }

    /** @param array<string,mixed> $dashboard */
    private function pdfResourcesTable(array $dashboard): string
    {
        $resources = is_array($dashboard['by_resource'] ?? null) ? $dashboard['by_resource'] : [];
        $html = '<h2>Ressources</h2>';
        if (count($resources) === 0) {
            return $html . '<p><em>Aucune ressource.</em></p>';
        }
        $html .= '<table><thead><tr><th>Ressource</th><th>Type</th><th>ref_id</th><th>Statements</th><th>Apprenants</th><th>Score moyen</th><th>Signal</th></tr></thead><tbody>';
        foreach (array_slice($resources, 0, 50) as $resource) {
            $score = ($resource['avg_score_raw'] ?? null) === null ? '-' : (string) $resource['avg_score_raw'] . ' %';
            $html .= '<tr><td>' . $this->esc((string) ($resource['title'] ?? '')) . '</td><td>' . $this->esc((string) ($resource['obj_type'] ?? '')) . '</td><td>' . $this->esc((string) ($resource['ref_id'] ?? 0)) . '</td><td>' . $this->esc((string) ($resource['traces'] ?? 0)) . '</td><td>' . $this->esc((string) ($resource['learners_count'] ?? 0)) . '</td><td>' . $this->esc($score) . '</td><td>' . $this->esc((string) ($resource['signal'] ?? '')) . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }

PHP;
$marker = "    /** @param array<string,mixed> \$course */\n    private function sendExpertCsv";
$updated2 = str_replace($marker, $method . $marker, $updated);
if ($updated2 === $updated) {
    fwrite(STDERR, "Unable to insert PDF export methods in {$file}\n");
    exit(1);
}
$updated = $updated2;

if (strpos($updated, 'exportCourseDashboardPdf') === false || strpos($updated, 'sendDashboardPdf') === false || strpos($updated, 'findWkhtmltopdfBinary') === false || strpos($updated, 'Export PDF') === false) {
    fwrite(STDERR, "PDF export patch incomplete in {$file}\n");
    exit(1);
}
if (file_put_contents($file, $updated) === false) {
    fwrite(STDERR, "Cannot write {$file}\n");
    exit(1);
}
echo "PDF export patch applied to {$file}\n";
