<?php
/**
 * V0.19 - Comparaison de deux analyses IA historisees.
 *
 * A lancer depuis la racine du plugin principal IliasTraxEventBridge.
 * La comparaison est locale : aucun appel IA, aucun impact xAPI/TRAX/outbox.
 */

function itxeb_v019_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: {$message}\n");
    exit(1);
}

function itxeb_v019_read(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content)) {
        itxeb_v019_fail("lecture impossible: {$path}");
    }
    return $content;
}

function itxeb_v019_write(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        itxeb_v019_fail("ecriture impossible: {$path}");
    }
    echo "WRITE: {$path}\n";
}

function itxeb_v019_lint(string $path): void
{
    passthru('php -l ' . escapeshellarg($path), $code);
    if ($code !== 0) {
        itxeb_v019_fail("syntaxe invalide: {$path}");
    }
}

function itxeb_v019_version(string $file, string $version): void
{
    if (!is_file($file)) {
        echo "WARN: fichier absent: {$file}\n";
        return;
    }
    $content = itxeb_v019_read($file);
    $new = preg_replace("~\$version\s*=\s*'[^']+';~", "\$version = '" . $version . "';", $content);
    if (!is_string($new)) {
        itxeb_v019_fail("version impossible: {$file}");
    }
    if ($new !== $content) {
        itxeb_v019_write($file, $new);
    } else {
        echo "OK: version deja {$version}: {$file}\n";
    }
    itxeb_v019_lint($file);
}

function itxeb_v019_patch_screen(string $path): void
{
    echo "\n== Ecran companion template ==\n";
    $content = itxeb_v019_read($path);
    $original = $content;

    if (strpos($content, 'itxeb_ai_compare_a') === false || strpos($content, 'itxeb-ai-compare-grid') === false) {
        $pattern = '/    \/\*\* @param array<string,mixed> \$course \*\/\n    private function renderAiHistoryPanel\(array \$course\): string\n    \{.*?\n    \}\n\n    private function renderAiMarkdown/s';
        $panel = <<<'PHP'
    /** @param array<string,mixed> $course */
    private function renderAiHistoryPanel(array $course): string
    {
        if (!$this->aiHistory) {
            return '';
        }
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $records = $this->aiHistory->list($courseRefId, 20);
        if (count($records) === 0) {
            return '<section class="itxeb-cui-section itxeb-ai-history"><h3>Historique des analyses IA</h3><p><em>Aucune analyse IA historisée pour ce cours.</em></p></section>';
        }

        $compareA = trim($this->requestValue($_GET, 'itxeb_ai_compare_a'));
        if ($compareA === '') { $compareA = trim($this->requestValue($_POST, 'itxeb_ai_compare_a')); }
        if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $compareA)) { $compareA = ''; }
        $compareB = trim($this->requestValue($_GET, 'itxeb_ai_compare_b'));
        if ($compareB === '') { $compareB = trim($this->requestValue($_POST, 'itxeb_ai_compare_b')); }
        if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $compareB)) { $compareB = ''; }

        $selectedId = $this->getSelectedAiHistoryId();
        $selectedRecord = [];
        $compareARecord = [];
        $compareBRecord = [];
        $html = '<section class="itxeb-cui-section itxeb-ai-history"><h3>Historique des analyses IA</h3><p>Dernières analyses générées pour ce cours. Les actions permettent de relire, de comparer ou de retirer une analyse de l’historique visible sans modifier les traces xAPI/TRAX.</p><div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr><th>Date UTC</th><th>Période</th><th>Statut</th><th>Résumé payload</th><th>Comparer</th><th>Action</th></tr></thead><tbody>';
        foreach (array_slice($records, 0, 10) as $record) {
            $recordId = (string) ($record['id'] ?? '');
            $isSelected = $recordId !== '' && $recordId === $selectedId;
            if ($isSelected) { $selectedRecord = $record; }
            if ($recordId !== '' && $recordId === $compareA) { $compareARecord = $record; }
            if ($recordId !== '' && $recordId === $compareB) { $compareBRecord = $record; }
            $detailUrl = $this->currentUrlWith([
                'itxeb_cui_cmd' => 'showCourseAnalysis',
                'itxeb_course_ref_id' => (string) $courseRefId,
                'itxeb_period_days' => (string) $this->getPeriodDays(),
                'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
                'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
                'itxeb_ai_history_id' => $recordId,
            ]);
            $archiveUrl = $this->currentUrlWith([
                'itxeb_cui_cmd' => 'archiveCourseAiHistory',
                'itxeb_course_ref_id' => (string) $courseRefId,
                'itxeb_period_days' => (string) $this->getPeriodDays(),
                'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
                'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
                'itxeb_ai_history_id' => $recordId,
            ]);
            $compareAUrl = $this->currentUrlWith([
                'itxeb_cui_cmd' => 'showCourseAnalysis',
                'itxeb_course_ref_id' => (string) $courseRefId,
                'itxeb_period_days' => (string) $this->getPeriodDays(),
                'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
                'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
                'itxeb_ai_history_id' => '',
                'itxeb_ai_compare_a' => $recordId,
            ]);
            $compareBUrl = $this->currentUrlWith([
                'itxeb_cui_cmd' => 'showCourseAnalysis',
                'itxeb_course_ref_id' => (string) $courseRefId,
                'itxeb_period_days' => (string) $this->getPeriodDays(),
                'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
                'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
                'itxeb_ai_history_id' => '',
                'itxeb_ai_compare_b' => $recordId,
            ]);
            $detailAction = $recordId !== ''
                ? ($isSelected ? '<strong>Affiché</strong>' : '<a class="btn btn-default btn-xs" href="' . $this->esc($detailUrl) . '">Voir le détail</a>')
                : '-';
            $archiveAction = $recordId !== ''
                ? '<form method="post" class="itxeb-ai-history-archive-form" action="' . $this->esc($archiveUrl) . '" onsubmit="return confirm(\'Retirer cette analyse IA de l’historique visible ?\');">'
                    . '<input type="hidden" name="itxeb_cui_cmd" value="archiveCourseAiHistory">'
                    . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
                    . '<input type="hidden" name="itxeb_period_days" value="' . $this->esc((string) $this->getPeriodDays()) . '">'
                    . '<input type="hidden" name="itxeb_filter_ref_id" value="' . $this->esc((string) $this->getSelectedResourceRefId()) . '">'
                    . '<input type="hidden" name="itxeb_filter_obj_type" value="' . $this->esc($this->getSelectedObjectType()) . '">'
                    . '<input type="hidden" name="itxeb_ai_history_id" value="' . $this->esc($recordId) . '">'
                    . '<input type="hidden" name="itxeb_ai_history_confirm" value="1">'
                    . '<button class="btn btn-default btn-xs itxeb-danger" type="submit">Retirer</button>'
                    . '</form>'
                : '';
            $compareAction = $recordId !== ''
                ? '<div class="itxeb-ai-compare-actions">'
                    . '<a class="btn btn-default btn-xs' . ($recordId === $compareA ? ' itxeb-active-compare' : '') . '" href="' . $this->esc($compareAUrl) . '">Analyse A</a>'
                    . '<a class="btn btn-default btn-xs' . ($recordId === $compareB ? ' itxeb-active-compare' : '') . '" href="' . $this->esc($compareBUrl) . '">Analyse B</a>'
                    . '</div>'
                : '-';
            $action = '<div class="itxeb-ai-history-actions">' . $detailAction . $archiveAction . '</div>';
            $html .= '<tr><td>' . $this->esc((string) ($record['created_at_utc'] ?? '')) . '</td>'
                . '<td>' . $this->esc((string) ($record['period_days'] ?? '')) . ' jour(s)</td>'
                . '<td>' . (!empty($record['success']) ? 'OK' : 'Erreur') . '</td>'
                . '<td><small>' . $this->esc((string) ($record['payload_summary'] ?? '')) . '</small></td>'
                . '<td>' . $compareAction . '</td>'
                . '<td>' . $action . '</td></tr>';
        }
        $html .= '</tbody></table></div>';

        if ($compareA !== '' || $compareB !== '') {
            $clearUrl = $this->currentUrlWith([
                'itxeb_cui_cmd' => 'showCourseAnalysis',
                'itxeb_course_ref_id' => (string) $courseRefId,
                'itxeb_period_days' => (string) $this->getPeriodDays(),
                'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
                'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
                'itxeb_ai_history_id' => '',
                'itxeb_ai_compare_a' => '',
                'itxeb_ai_compare_b' => '',
            ]);
            $html .= '<div class="itxeb-ai-compare"><h4>Comparaison de deux analyses IA historisées</h4>'
                . '<p><a class="btn btn-default btn-xs" href="' . $this->esc($clearUrl) . '">Réinitialiser la comparaison</a></p>';
            if ($compareA === '' || $compareB === '') {
                $html .= '<div class="itxeb-cui-alert">Sélectionnez une analyse A et une analyse B dans le tableau d’historique.</div>';
            } elseif ($compareA === $compareB) {
                $html .= '<div class="itxeb-cui-alert itxeb-cui-error">Choisissez deux analyses différentes pour la comparaison.</div>';
            } elseif ($compareARecord === [] || $compareBRecord === []) {
                $html .= '<div class="itxeb-cui-alert itxeb-cui-error">Une des deux analyses sélectionnées n’est plus disponible dans l’historique visible.</div>';
            } else {
                $aPayload = (string) ($compareARecord['payload_summary'] ?? '');
                $bPayload = (string) ($compareBRecord['payload_summary'] ?? '');
                $aStatements = $this->extractAiHistorySummaryCount($aPayload, 'statement');
                $bStatements = $this->extractAiHistorySummaryCount($bPayload, 'statement');
                $aResources = $this->extractAiHistorySummaryCount($aPayload, 'ressource');
                $bResources = $this->extractAiHistorySummaryCount($bPayload, 'ressource');
                $aAnalysis = trim((string) ($compareARecord['analysis'] ?? ''));
                $bAnalysis = trim((string) ($compareBRecord['analysis'] ?? ''));
                $statementDelta = ($aStatements >= 0 && $bStatements >= 0) ? $this->formatSignedNumber($bStatements - $aStatements) . ' statement(s)' : 'non calculable';
                $resourceDelta = ($aResources >= 0 && $bResources >= 0) ? $this->formatSignedNumber($bResources - $aResources) . ' ressource(s)' : 'non calculable';
                $periodDelta = (int) ($compareBRecord['period_days'] ?? 0) - (int) ($compareARecord['period_days'] ?? 0);
                $lengthDelta = $this->formatSignedNumber(strlen($bAnalysis) - strlen($aAnalysis)) . ' caractère(s)';
                $html .= '<table class="itxeb-cui-table itxeb-ai-compare-meta"><thead><tr><th></th><th>Analyse A</th><th>Analyse B</th></tr></thead><tbody>'
                    . '<tr><td>Date UTC</td><td>' . $this->esc((string) ($compareARecord['created_at_utc'] ?? '')) . '</td><td>' . $this->esc((string) ($compareBRecord['created_at_utc'] ?? '')) . '</td></tr>'
                    . '<tr><td>Période</td><td>' . $this->esc((string) ($compareARecord['period_days'] ?? '')) . ' jour(s)</td><td>' . $this->esc((string) ($compareBRecord['period_days'] ?? '')) . ' jour(s)</td></tr>'
                    . '<tr><td>HTTP</td><td>' . $this->esc((string) ($compareARecord['http_status'] ?? '')) . '</td><td>' . $this->esc((string) ($compareBRecord['http_status'] ?? '')) . '</td></tr>'
                    . '<tr><td>Résumé payload</td><td><small>' . $this->esc($aPayload) . '</small></td><td><small>' . $this->esc($bPayload) . '</small></td></tr>'
                    . '</tbody></table>';
                $html .= '<div class="itxeb-ai-compare-summary"><strong>Synthèse locale</strong><ul>'
                    . '<li>Variation des statements entre B et A : <strong>' . $this->esc($statementDelta) . '</strong>.</li>'
                    . '<li>Variation des ressources entre B et A : <strong>' . $this->esc($resourceDelta) . '</strong>.</li>'
                    . '<li>Variation de période entre B et A : <strong>' . $this->esc($this->formatSignedNumber($periodDelta)) . ' jour(s)</strong>.</li>'
                    . '<li>Variation de longueur du texte d’analyse : <strong>' . $this->esc($lengthDelta) . '</strong>.</li>'
                    . '</ul></div>';
                $html .= '<div class="itxeb-ai-compare-grid"><div><h5>Analyse A</h5>' . ($aAnalysis !== '' ? $this->renderAiMarkdown($aAnalysis) : '<p><em>Analyse vide.</em></p>') . '</div>'
                    . '<div><h5>Analyse B</h5>' . ($bAnalysis !== '' ? $this->renderAiMarkdown($bAnalysis) : '<p><em>Analyse vide.</em></p>') . '</div></div>';
            }
            $html .= '</div>';
        }

        if ($selectedId !== '') {
            if ($selectedRecord === []) {
                foreach ($records as $record) {
                    if ((string) ($record['id'] ?? '') === $selectedId) {
                        $selectedRecord = $record;
                        break;
                    }
                }
            }
            if ($selectedRecord === []) {
                // V0.18.1 : l'identifiant peut rester dans l'URL juste après le retrait
                // d'une analyse. Dans ce cas on n'affiche pas d'erreur : le tableau mis
                // à jour suffit à confirmer que l'analyse n'est plus visible.
            } else {
                $analysis = trim((string) ($selectedRecord['analysis'] ?? ''));
                $closeUrl = $this->currentUrlWith([
                    'itxeb_cui_cmd' => 'showCourseAnalysis',
                    'itxeb_course_ref_id' => (string) $courseRefId,
                    'itxeb_period_days' => (string) $this->getPeriodDays(),
                    'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
                    'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
                    'itxeb_ai_history_id' => '',
                ]);
                $html .= '<div class="itxeb-ai-history-detail"><h4>Détail de l’analyse IA historisée</h4>'
                    . '<p><small>ID : ' . $this->esc((string) ($selectedRecord['id'] ?? ''))
                    . ' — générée le ' . $this->esc((string) ($selectedRecord['created_at_utc'] ?? ''))
                    . ' — période ' . $this->esc((string) ($selectedRecord['period_days'] ?? '')) . ' jour(s)'
                    . ' — HTTP ' . $this->esc((string) ($selectedRecord['http_status'] ?? '')) . '</small></p>'
                    . '<p><small>' . $this->esc((string) ($selectedRecord['payload_summary'] ?? '')) . '</small></p>'
                    . ($analysis !== '' ? $this->renderAiMarkdown($analysis) : '<p><em>Analyse vide.</em></p>')
                    . '<p><a class="btn btn-default btn-xs" href="' . $this->esc($closeUrl) . '">Masquer le détail</a></p>'
                    . '</div>';
            }
        }

        return $html . '</section>';
    }
PHP;
        $replacement = $panel . "\n\n    private function renderAiMarkdown";
        $content = preg_replace($pattern, $replacement, $content, 1, $count);
        if (!is_string($content) || $count !== 1) {
            itxeb_v019_fail('remplacement panneau historique impossible');
        }
        echo "PATCH: panneau historique IA avec comparaison\n";
    } else {
        echo "OK: comparaison deja presente dans le panneau historique\n";
    }

    if (strpos($content, 'private function extractAiHistorySummaryCount(string $summary, string $word): int') === false) {
        $helper = <<<'PHP'
    private function extractAiHistorySummaryCount(string $summary, string $word): int
    {
        if (preg_match('/([0-9]+)\s+' . preg_quote($word, '/') . '/ui', $summary, $m) === 1) {
            return (int) $m[1];
        }
        return -1;
    }

PHP;
        $marker = '    private function renderAiMarkdown(string $markdown): string';
        $pos = strpos($content, $marker);
        if ($pos === false) {
            itxeb_v019_fail('marker renderAiMarkdown introuvable');
        }
        $content = substr($content, 0, $pos) . $helper . substr($content, $pos);
        echo "PATCH: helper extractAiHistorySummaryCount\n";
    } else {
        echo "OK: helper comparaison deja present\n";
    }

    if (strpos($content, '.itxeb-ai-compare-grid') === false) {
        $css = '.itxeb-ai-compare{border:2px solid #c8d6e5;background:#f8fbff;padding:14px;margin-top:14px;border-radius:6px}.itxeb-ai-compare h4{margin-top:0}.itxeb-ai-compare-actions{display:flex;gap:5px;align-items:center;flex-wrap:wrap}.itxeb-active-compare{font-weight:700;background:#eaf4ff;border-color:#337ab7}.itxeb-ai-compare-meta{margin:8px 0 12px}.itxeb-ai-compare-summary{border:1px solid #d9e2ec;background:#fff;padding:10px;border-radius:5px;margin:10px 0}.itxeb-ai-compare-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:12px;margin-top:12px}.itxeb-ai-compare-grid h5{font-size:16px;font-weight:700;margin:0 0 8px}';
        if (strpos($content, '</style>') === false) {
            itxeb_v019_fail('style introuvable');
        }
        $content = str_replace('</style>', $css . '</style>', $content);
        echo "PATCH: styles comparaison IA\n";
    } else {
        echo "OK: styles comparaison IA deja presents\n";
    }

    if ($content !== $original) {
        itxeb_v019_write($path, $content);
    } else {
        echo "OK: ecran template inchange\n";
    }
    itxeb_v019_lint($path);
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_v019_fail('lance ce script depuis la racine du plugin principal IliasTraxEventBridge.');
}

$template = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$servicesDir = dirname(dirname(dirname($root)));
$liveBase = $servicesDir . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$liveScreen = $liveBase . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';

itxeb_v019_patch_screen($template);

if (is_file($liveScreen)) {
    if (!copy($template, $liveScreen)) {
        itxeb_v019_fail("copie template vers live impossible: {$liveScreen}");
    }
    echo "COPY: template V0.19 vers companion live\n";
    itxeb_v019_lint($liveScreen);
} else {
    echo "WARN: companion live absent: {$liveScreen}\n";
}

itxeb_v019_version($root . '/plugin.php', '0.19.0-dev');
itxeb_v019_version($root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl', '0.7.0');
itxeb_v019_version($liveBase . '/plugin.php', '0.7.0');

echo "\nV0.19 appliquee : comparaison de deux analyses IA historisees.\n";
