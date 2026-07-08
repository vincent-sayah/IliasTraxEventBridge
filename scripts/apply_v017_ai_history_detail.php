<?php
/**
 * V0.17 - Consultation détaillée d'une analyse IA historisée.
 *
 * A lancer depuis la racine du plugin principal IliasTraxEventBridge.
 * Le script modifie le template companion et, si présent, le plugin companion live.
 */

function fail_v017(string $message): void
{
    fwrite(STDERR, "ERREUR: {$message}\n");
    exit(1);
}

function read_v017(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content)) {
        fail_v017("lecture impossible: {$path}");
    }
    return $content;
}

function write_v017(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fail_v017("écriture impossible: {$path}");
    }
    echo "WRITE: {$path}\n";
}

function replace_required_v017(string &$content, string $old, string $new, string $label): void
{
    if (strpos($content, $new) !== false) {
        echo "OK: {$label} déjà présent\n";
        return;
    }
    if (strpos($content, $old) === false) {
        fail_v017("bloc introuvable: {$label}");
    }
    $content = str_replace($old, $new, $content);
    echo "PATCH: {$label}\n";
}

function insert_before_v017(string &$content, string $needle, string $insert, string $label): void
{
    if (strpos($content, 'private function getSelectedAiHistoryId(): string') !== false) {
        echo "OK: {$label} déjà présent\n";
        return;
    }
    $pos = strpos($content, $needle);
    if ($pos === false) {
        fail_v017("marker introuvable: {$label}");
    }
    $content = substr($content, 0, $pos) . $insert . "\n" . substr($content, $pos);
    echo "PATCH: {$label}\n";
}

function patch_screen_v017(string $screen): void
{
    echo "\n== Patch écran: {$screen} ==\n";
    $content = read_v017($screen);
    $original = $content;

    $old = <<<'PHP'
    /** @param array<string,mixed> $course */
    private function renderAiHistoryPanel(array $course): string
    {
        if (!$this->aiHistory) {
            return '';
        }
        $records = $this->aiHistory->list((int) ($course['course_ref_id'] ?? 0), 5);
        if (count($records) === 0) {
            return '<section class="itxeb-cui-section itxeb-ai-history"><h3>Historique des analyses IA</h3><p><em>Aucune analyse IA historisée pour ce cours.</em></p></section>';
        }
        $html = '<section class="itxeb-cui-section itxeb-ai-history"><h3>Historique des analyses IA</h3><p>Dernières analyses générées pour ce cours.</p><div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr><th>Date UTC</th><th>Période</th><th>Statut</th><th>Résumé payload</th></tr></thead><tbody>';
        foreach ($records as $record) {
            $html .= '<tr><td>' . $this->esc((string) ($record['created_at_utc'] ?? '')) . '</td>'
                . '<td>' . $this->esc((string) ($record['period_days'] ?? '')) . ' jour(s)</td>'
                . '<td>' . (!empty($record['success']) ? 'OK' : 'Erreur') . '</td>'
                . '<td><small>' . $this->esc((string) ($record['payload_summary'] ?? '')) . '</small></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }
PHP;

    $new = <<<'PHP'
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

        $selectedId = $this->getSelectedAiHistoryId();
        $selectedRecord = [];
        $html = '<section class="itxeb-cui-section itxeb-ai-history"><h3>Historique des analyses IA</h3><p>Dernières analyses générées pour ce cours. Cliquez sur <strong>Voir le détail</strong> pour relire une analyse historisée sans relancer l’IA.</p><div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr><th>Date UTC</th><th>Période</th><th>Statut</th><th>Résumé payload</th><th>Action</th></tr></thead><tbody>';
        foreach (array_slice($records, 0, 10) as $record) {
            $recordId = (string) ($record['id'] ?? '');
            $isSelected = $recordId !== '' && $recordId === $selectedId;
            if ($isSelected) {
                $selectedRecord = $record;
            }
            $url = $this->currentUrlWith([
                'itxeb_cui_cmd' => 'showCourseAnalysis',
                'itxeb_course_ref_id' => (string) $courseRefId,
                'itxeb_period_days' => (string) $this->getPeriodDays(),
                'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
                'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
                'itxeb_ai_history_id' => $recordId,
            ]);
            $action = $recordId !== ''
                ? ($isSelected ? '<strong>Affiché</strong>' : '<a class="btn btn-default btn-xs" href="' . $this->esc($url) . '">Voir le détail</a>')
                : '-';
            $html .= '<tr><td>' . $this->esc((string) ($record['created_at_utc'] ?? '')) . '</td>'
                . '<td>' . $this->esc((string) ($record['period_days'] ?? '')) . ' jour(s)</td>'
                . '<td>' . (!empty($record['success']) ? 'OK' : 'Erreur') . '</td>'
                . '<td><small>' . $this->esc((string) ($record['payload_summary'] ?? '')) . '</small></td>'
                . '<td>' . $action . '</td></tr>';
        }
        $html .= '</tbody></table></div>';

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
                $html .= '<div class="itxeb-cui-alert itxeb-cui-error">Analyse IA historisée introuvable pour cet identifiant.</div>';
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

    replace_required_v017($content, $old, $new, 'historique IA détaillé');

    $method = <<<'PHP'
    private function getSelectedAiHistoryId(): string
    {
        $value = trim($this->requestValue($_GET, 'itxeb_ai_history_id'));
        if ($value === '') {
            $value = trim($this->requestValue($_POST, 'itxeb_ai_history_id'));
        }
        if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $value)) {
            return '';
        }
        return $value;
    }

PHP;
    insert_before_v017($content, '    /** @param array<string,mixed> $course @return array<string,string> */' . "\n" . '    private function availableObjectTypes', $method, 'méthode getSelectedAiHistoryId');

    if (strpos($content, '.itxeb-ai-history-detail') === false) {
        $needle = '</style>';
        if (strpos($content, $needle) === false) {
            fail_v017('balise style introuvable');
        }
        $css = '.itxeb-ai-history-detail{border:2px solid #c8d6e5;background:#f8fbff;padding:14px;margin-top:14px;border-radius:6px}.itxeb-ai-history-detail h4{margin-top:0}.itxeb-ai-history .btn-xs{padding:2px 7px;font-size:12px;line-height:1.4}';
        $content = str_replace($needle, $css . $needle, $content);
        echo "PATCH: styles détail historique IA\n";
    } else {
        echo "OK: styles détail historique IA déjà présents\n";
    }

    if ($content !== $original) {
        write_v017($screen, $content);
    } else {
        echo "OK: écran inchangé\n";
    }

    passthru('php -l ' . escapeshellarg($screen), $code);
    if ($code !== 0) {
        fail_v017("syntaxe invalide: {$screen}");
    }
}

function update_version_v017(string $file, string $version): void
{
    if (!is_file($file)) {
        echo "WARN: version non mise à jour, fichier absent: {$file}\n";
        return;
    }
    $content = read_v017($file);
    $new = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '" . $version . "';", $content);
    if (!is_string($new)) {
        fail_v017("regex version impossible: {$file}");
    }
    if ($new !== $content) {
        write_v017($file, $new);
    } else {
        echo "OK: version déjà {$version}: {$file}\n";
    }
    passthru('php -l ' . escapeshellarg($file), $code);
    if ($code !== 0) {
        fail_v017("syntaxe invalide: {$file}");
    }
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    fail_v017('lance ce script depuis la racine du plugin principal IliasTraxEventBridge.');
}

$templateScreen = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$servicesDir = dirname(dirname(dirname($root)));
$liveBase = $servicesDir . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$liveScreen = $liveBase . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';

if (is_file($templateScreen)) {
    patch_screen_v017($templateScreen);
} else {
    fail_v017("template introuvable: {$templateScreen}");
}

if (is_file($liveScreen)) {
    patch_screen_v017($liveScreen);
} else {
    echo "WARN: plugin companion live non trouvé: {$liveScreen}\n";
}

update_version_v017($root . '/plugin.php', '0.17.0-dev');
update_version_v017($root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl', '0.5.0');
update_version_v017($liveBase . '/plugin.php', '0.5.0');

echo "\nV0.17 appliquée : consultation détaillée des analyses IA historisées.\n";
