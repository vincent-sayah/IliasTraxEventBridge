<?php

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_lrs_analysis_details.php target.php\n");
    exit(1);
}
$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Cannot read {$file}\n");
    exit(1);
}
if (strpos($code, 'renderLrsAnalysisDetails') !== false) {
    echo "LRS analysis details patch already present in {$file}\n";
    exit(0);
}
$updated = $code;

$pattern = '/\n        \$verbs = is_array\(\$s\[\'by_verb\'\] \?\? null\) \? \$s\[\'by_verb\'\] : \[\];.*?\n        return \$html \. \'<\/section>\';/s';
$replacement = "\n        return \$html . '</section>';";
$updated2 = preg_replace($pattern, $replacement, $updated, 1);
if (!is_string($updated2) || $updated2 === $updated) {
    fwrite(STDERR, "Unable to remove verbs/resources from LRS diagnostic block in {$file}\n");
    exit(1);
}
$updated = $updated2;

$oldReturn = "        return \$html . '</tbody></table></div>' . \$this->renderStrugglingLearners(\$dashboard) . '</section>';";
$newReturn = "        return \$html . '</tbody></table></div>' . \$this->renderStrugglingLearners(\$dashboard) . \$this->renderLrsAnalysisDetails(\$dashboard) . '</section>';";
$updated2 = str_replace($oldReturn, $newReturn, $updated);
if ($updated2 === $updated) {
    fwrite(STDERR, "Unable to insert LRS analysis details in renderAnalysis in {$file}\n");
    exit(1);
}
$updated = $updated2;

$method = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderLrsAnalysisDetails(array $dashboard): string
    {
        $html = '<section class="itxeb-cui-section"><h3>Verbes retournés par TRAX</h3>';
        $verbs = is_array($dashboard['by_verb'] ?? null) ? $dashboard['by_verb'] : [];
        if (count($verbs) === 0) {
            $html .= '<p><em>Aucun verbe TRAX retourné pour la période et le filtre sélectionnés.</em></p>';
        } else {
            $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr><th>Verbe</th><th style="width:120px">Nombre</th></tr></thead><tbody>';
            foreach (array_slice($verbs, 0, 20) as $verb) {
                $html .= '<tr><td><strong>' . $this->esc((string) ($verb['label'] ?? '')) . '</strong><br><small style="overflow-wrap:anywhere">' . $this->esc((string) ($verb['verb_id'] ?? '')) . '</small></td><td>' . $this->esc((string) ($verb['count'] ?? 0)) . '</td></tr>';
            }
            $html .= '</tbody></table></div>';
        }
        $html .= '</section>';

        $html .= '<section class="itxeb-cui-section"><h3>Ressources retournées par TRAX</h3>';
        $resources = is_array($dashboard['by_resource'] ?? null) ? $dashboard['by_resource'] : [];
        if (count($resources) === 0) {
            return $html . '<p><em>Aucune ressource TRAX retournée pour la période et le filtre sélectionnés.</em></p></section>';
        }
        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr><th>Ressource</th><th style="width:90px">Type</th><th style="width:80px">ref_id</th><th style="width:120px">Statements</th></tr></thead><tbody>';
        foreach (array_slice($resources, 0, 50) as $resource) {
            $html .= '<tr><td><strong>' . $this->esc((string) ($resource['title'] ?? '')) . '</strong><br><small style="overflow-wrap:anywhere">' . $this->esc((string) ($resource['object_id'] ?? ($resource['key'] ?? ''))) . '</small></td><td>' . $this->esc((string) ($resource['obj_type'] ?? '')) . '</td><td>' . $this->esc((string) ($resource['ref_id'] ?? 0)) . '</td><td>' . $this->esc((string) ($resource['count'] ?? 0)) . '</td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

PHP;
$marker = "    /** @param array<string,mixed> \$course */\n    private function renderExpert";
$updated2 = str_replace($marker, $method . $marker, $updated);
if ($updated2 === $updated) {
    fwrite(STDERR, "Unable to insert renderLrsAnalysisDetails method in {$file}\n");
    exit(1);
}
$updated = $updated2;

if (substr_count($updated, 'Verbes retournés par TRAX') !== 1 || substr_count($updated, 'Ressources retournées par TRAX') !== 1) {
    fwrite(STDERR, "Unexpected count for TRAX analysis details headings in {$file}\n");
    exit(1);
}
if (file_put_contents($file, $updated) === false) {
    fwrite(STDERR, "Cannot write {$file}\n");
    exit(1);
}
echo "LRS TRAX verbs/resources moved to Analysis in {$file}\n";
