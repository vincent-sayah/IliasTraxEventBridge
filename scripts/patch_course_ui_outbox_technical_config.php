<?php

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_outbox_technical_config.php target.php\n");
    exit(1);
}
$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Cannot read {$file}\n");
    exit(1);
}
if (strpos($code, 'renderOutboxTechnicalSupervision') !== false) {
    echo "Outbox technical configuration patch already present in {$file}\n";
    exit(0);
}

$oldView = "        return \$this->renderCourseSummary(\$course) . \$this->renderConfigForm(\$course) . \$this->renderDashboardPreferencesForm(\$course) . \$this->renderBulkActions((int) (\$course['course_ref_id'] ?? 0));";
$newView = "        return \$this->renderCourseSummary(\$course) . \$this->renderConfigForm(\$course) . \$this->renderDashboardPreferencesForm(\$course) . \$this->renderOutboxTechnicalSupervision(\$course) . \$this->renderBulkActions((int) (\$course['course_ref_id'] ?? 0));";
$updated = str_replace($oldView, $newView, $code);
if ($updated === $code) {
    fwrite(STDERR, "Configuration view return block not found in {$file}\n");
    exit(1);
}

$method = <<<'PHP'
    /** @param array<string,mixed> $course */
    private function renderOutboxTechnicalSupervision(array $course): string
    {
        $html = '<section class="itxeb-cui-section"><h2>Supervision technique de l’envoi xAPI</h2>'
            . '<p>Cette section concerne uniquement la file locale d’envoi vers TRAX. Elle ne sert pas de source au suivi pédagogique xAPI, qui est lu directement dans TRAX/LRS.</p>';

        if (!$this->analytics || !method_exists($this->analytics, 'tableExists') || !$this->analytics->tableExists()) {
            return $html . '<div class="itxeb-cui-alert itxeb-cui-error">Outbox locale indisponible : table evnt_evhk_itxeb_out absente.</div></section>';
        }

        $dashboard = $this->analytics->buildForCourse($this->filterCourseResources($course), 365);
        $status = is_array($dashboard['by_status'] ?? null) ? $dashboard['by_status'] : [];
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $failed = (int) ($status['failed'] ?? 0);

        $html .= '<div class="itxeb-kpi-grid">'
            . $this->metricCard('À générer', (string) ($status['generated'] ?? 0), 'status generated')
            . $this->metricCard('En envoi', (string) ($status['sending'] ?? 0), 'status sending')
            . $this->metricCard('Envoyées', (string) ($status['sent'] ?? 0), 'status sent')
            . $this->metricCard('En erreur', (string) $failed, 'status failed')
            . $this->metricCard('Autres', (string) ($status['other'] ?? 0), 'autres statuts')
            . '</div>';

        if ($failed > 0) {
            $html .= '<div class="itxeb-cui-alert itxeb-cui-error"><strong>Attention :</strong> des envois xAPI sont en erreur dans l’outbox locale. Vérifier la configuration TRAX et les logs d’envoi.</div>';
        }

        $html .= '<table class="itxeb-cui-table"><tbody>'
            . $this->row('Périmètre', 'outbox locale technique sur 365 jours')
            . $this->row('Total outbox', (string) ($summary['total'] ?? 0))
            . $this->row('Rôle de cette section', 'supervision de l’envoi uniquement')
            . $this->row('Source du suivi xAPI', 'TRAX/LRS direct')
            . '</tbody></table></section>';
        return $html;
    }

PHP;
$marker = "    private function renderBulkActions(int \$courseRefId): string\n";
$updated2 = str_replace($marker, $method . $marker, $updated);
if ($updated2 === $updated) {
    fwrite(STDERR, "Method insertion point not found in {$file}\n");
    exit(1);
}

if (file_put_contents($file, $updated2) === false) {
    fwrite(STDERR, "Cannot write {$file}\n");
    exit(1);
}
echo "Outbox technical configuration patch applied to {$file}\n";
