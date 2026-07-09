<?php
$root = getcwd();
$analytics = $root . '/classes/class.ilIliasTraxEventBridgeCourseAnalyticsRepository.php';
$template = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$servicesRoot = dirname(dirname(dirname($root)));
$live = $servicesRoot . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';

function read_file_strict(string $file): string
{
    $s = file_get_contents($file);
    if (!is_string($s)) {
        fwrite(STDERR, "Lecture impossible: $file\n");
        exit(1);
    }
    return $s;
}

function write_if_changed(string $file, string $old, string $new): void
{
    if ($old !== $new) {
        file_put_contents($file, $new);
        echo "WRITE: $file\n";
    } else {
        echo "OK: aucun changement $file\n";
    }
}

function ensure_hotspots_ui(string $file): void
{
    if (!is_file($file)) {
        fwrite(STDERR, "Fichier absent: $file\n");
        exit(1);
    }
    $old = read_file_strict($file);
    $s = $old;

    if (strpos($s, 'private function renderQuestionFailureHotspots(array $dashboard): string') === false) {
        $method = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderQuestionFailureHotspots(array $dashboard): string
    {
        $risks = is_array($dashboard['question_risks'] ?? null) ? $dashboard['question_risks'] : [];
        $html = '<section class="itxeb-cui-section itxeb-question-risks"><h3>Questions à fort taux d’échec</h3>';
        if (count($risks) === 0) {
            return $html . '<p><em>Aucune question à fort taux d’échec détectée sur la période sélectionnée.</em></p></section>';
        }
        $html .= '<p>Seules les questions problématiques sont remontées ici. Toutes les questions restent tracées dans TRAX et visibles côté Expert.</p>';
        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr>'
            . '<th>Priorité</th><th>Question</th><th>Test</th><th>Réponses</th><th>Échecs / non-réponses</th><th>Taux d’échec</th><th>Score moyen</th><th>Dernière trace</th>'
            . '</tr></thead><tbody>';
        foreach (array_slice($risks, 0, 10) as $risk) {
            if (!is_array($risk)) { continue; }
            $avg = ($risk['avg_score'] ?? null) === null ? '-' : (string) $risk['avg_score'] . ' %';
            $failure = is_numeric($risk['failure_rate'] ?? null) ? (string) $risk['failure_rate'] . ' %' : '-';
            $label = (string) ($risk['risk_label'] ?? 'À surveiller');
            $class = $label === 'Critique' ? 'itxeb-pedagogy-critical' : 'itxeb-pedagogy-watch';
            $html .= '<tr>'
                . '<td><span class="itxeb-pedagogy-badge ' . $class . '">' . $this->esc($label) . '</span></td>'
                . '<td><strong>' . $this->esc((string) ($risk['question_title'] ?? '')) . '</strong><br><small>Question ' . $this->esc((string) ($risk['question_id'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc((string) ($risk['test_title'] ?? '')) . '<br><small>ref_id ' . $this->esc((string) ($risk['ref_id'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc((string) ($risk['attempts'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) (((int) ($risk['failed'] ?? 0)) + ((int) ($risk['unanswered'] ?? 0)))) . '</td>'
                . '<td>' . $this->esc($failure) . '</td>'
                . '<td>' . $this->esc($avg) . '</td>'
                . '<td>' . $this->esc((string) ($risk['last_at'] ?? '')) . '</td>'
                . '</tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

PHP;
        $marker = '    private function pedagogicalBadgeClass(string $status): string';
        $pos = strpos($s, $marker);
        if ($pos === false) {
            fwrite(STDERR, "Point insertion renderQuestionFailureHotspots introuvable dans $file\n");
            exit(1);
        }
        $s = substr($s, 0, $pos) . $method . substr($s, $pos);
        echo "PATCH: méthode renderQuestionFailureHotspots dans $file\n";
    }

    $needle = '$this->renderPedagogicalSynthesis($dashboard)';
    $replacement = '$this->renderPedagogicalSynthesis($dashboard) . $this->renderQuestionFailureHotspots($dashboard)';
    if (strpos($s, 'renderPedagogicalSynthesis($dashboard) . $this->renderQuestionFailureHotspots($dashboard)') === false) {
        $count = 0;
        $s = str_replace($needle, $replacement, $s, $count);
        echo "PATCH: insertion appel renderQuestionFailureHotspots dans $file ($count remplacement(s))\n";
    } else {
        echo "OK: appels renderQuestionFailureHotspots déjà présents dans $file\n";
    }

    if (strpos($s, 'Questions à fort taux d’échec') === false || strpos($s, 'renderQuestionFailureHotspots($dashboard)') === false) {
        fwrite(STDERR, "Correction incomplète dans $file\n");
        exit(1);
    }

    write_if_changed($file, $old, $s);
}

if (!is_file($analytics)) {
    fwrite(STDERR, "Analytics introuvable: $analytics\n");
    exit(1);
}
$analyticsText = read_file_strict($analytics);
if (strpos($analyticsText, 'question_risks') === false || strpos($analyticsText, 'question_score_percent') === false) {
    fwrite(STDERR, "La partie analytics V0.21.2 n'est pas appliquée. Lance d'abord scripts/apply_v0212_question_failure_dashboard.php et colle sa sortie.\n");
    exit(1);
}

ensure_hotspots_ui($template);
if (!is_dir(dirname($live))) {
    fwrite(STDERR, "Répertoire live UIHook absent: " . dirname($live) . "\n");
    exit(1);
}
copy($template, $live);
echo "COPY: template écran vers UIHook live\n";
ensure_hotspots_ui($live);

echo "V0.21.2 UI forcée : bloc Questions à fort taux d’échec présent dans le template et dans le plugin live.\n";
