<?php
/**
 * V0.18 - Retrait contrôlé d'une analyse IA historisée.
 *
 * Le retrait ne modifie jamais TRAX/LRS, l'outbox ou les traces xAPI.
 * Le fichier JSON est déplacé hors de l'historique visible vers :
 * var/ai_analysis_history_deleted/
 */

function itxeb_v018_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: {$message}\n");
    exit(1);
}

function itxeb_v018_read(string $path): string
{
    $content = file_get_contents($path);
    if (!is_string($content)) {
        itxeb_v018_fail("lecture impossible: {$path}");
    }
    return $content;
}

function itxeb_v018_write(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        itxeb_v018_fail("ecriture impossible: {$path}");
    }
    echo "WRITE: {$path}\n";
}

function itxeb_v018_lint(string $path): void
{
    passthru('php -l ' . escapeshellarg($path), $code);
    if ($code !== 0) {
        itxeb_v018_fail("syntaxe invalide: {$path}");
    }
}

function itxeb_v018_version(string $file, string $version): void
{
    if (!is_file($file)) {
        echo "WARN: fichier absent: {$file}\n";
        return;
    }
    $content = itxeb_v018_read($file);
    $new = preg_replace("~\$version\s*=\s*'[^']+';~", "\$version = '" . $version . "';", $content);
    if (!is_string($new)) {
        itxeb_v018_fail("version impossible: {$file}");
    }
    if ($new !== $content) {
        itxeb_v018_write($file, $new);
    } else {
        echo "OK: version deja {$version}: {$file}\n";
    }
    itxeb_v018_lint($file);
}

function itxeb_v018_patch_history(string $path): void
{
    echo "\n== Historique IA ==\n";
    $content = itxeb_v018_read($path);
    $original = $content;

    if (strpos($content, 'public function archive(int $courseRefId, string $id): bool') === false) {
        $method = <<<'PHP'
    public function archive(int $courseRefId, string $id): bool
    {
        if ($courseRefId <= 0 || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $id)) {
            return false;
        }
        $file = $this->recordFile($courseRefId, $id);
        if (!is_file($file)) {
            return false;
        }
        $archiveDir = dirname($this->dir) . '/ai_analysis_history_deleted';
        if (!is_dir($archiveDir)) {
            @mkdir($archiveDir, 0750, true);
        }
        if (!is_dir($archiveDir)) {
            return false;
        }
        @chmod($archiveDir, 0750);
        $target = $archiveDir . '/' . basename($file) . '.archived-' . gmdate('YmdHis');
        return @rename($file, $target);
    }

PHP;
        $marker = '    private function ensureDir(): void';
        $pos = strpos($content, $marker);
        if ($pos === false) {
            itxeb_v018_fail('marker ensureDir introuvable');
        }
        $content = substr($content, 0, $pos) . $method . substr($content, $pos);
        echo "PATCH: methode archive historique IA\n";
    } else {
        echo "OK: methode archive deja presente\n";
    }

    if ($content !== $original) {
        itxeb_v018_write($path, $content);
    } else {
        echo "OK: historique IA inchange\n";
    }
    itxeb_v018_lint($path);
}

function itxeb_v018_patch_screen(string $path): void
{
    echo "\n== Ecran companion template ==\n";
    $content = itxeb_v018_read($path);
    $original = $content;

    if (strpos($content, "elseif ($" . "cmd === 'archiveCourseAiHistory')") === false) {
        $old = <<<'PHP'
        } elseif ($cmd === 'resetCourseTracking') {
            $this->resetCourse($courseRefId);
            $cmd = 'showCourseTracking';
        }
PHP;
        $new = <<<'PHP'
        } elseif ($cmd === 'resetCourseTracking') {
            $this->resetCourse($courseRefId);
            $cmd = 'showCourseTracking';
        } elseif ($cmd === 'archiveCourseAiHistory') {
            $this->archiveAiHistory($courseRefId);
            $cmd = 'showCourseAnalysis';
        }
PHP;
        if (strpos($content, $old) === false) {
            itxeb_v018_fail('bloc commandes introuvable');
        }
        $content = str_replace($old, $new, $content);
        echo "PATCH: commande archiveCourseAiHistory\n";
    } else {
        echo "OK: commande archive deja presente\n";
    }

    if (strpos($content, 'private function archiveAiHistory(int $courseRefId): void') === false) {
        $method = <<<'PHP'
    private function archiveAiHistory(int $courseRefId): void
    {
        if (!$this->aiHistory) {
            $this->message = 'Historique IA indisponible.';
            $this->messageType = 'error';
            return;
        }
        $id = $this->getSelectedAiHistoryId();
        if ($id === '' || $this->postString('itxeb_ai_history_confirm') !== '1') {
            $this->message = 'Retrait IA annulé : confirmation manquante ou identifiant invalide.';
            $this->messageType = 'error';
            return;
        }
        try {
            if ($this->aiHistory->archive($courseRefId, $id)) {
                unset($_POST['itxeb_ai_history_id'], $_GET['itxeb_ai_history_id']);
                $this->message = 'Analyse IA historisée retirée de l’historique visible.';
                $this->messageType = 'success';
            } else {
                $this->message = 'Analyse IA historisée introuvable ou retrait impossible.';
                $this->messageType = 'error';
            }
        } catch (Throwable $e) {
            error_log('[IliasTraxEventBridge] Retrait historique IA impossible: ' . $e->getMessage());
            $this->message = 'Retrait de l’analyse IA historisée impossible.';
            $this->messageType = 'error';
        }
    }

PHP;
        $marker = '    /** @param array<string,mixed> $course */' . "\n" . '    private function renderView';
        $pos = strpos($content, $marker);
        if ($pos === false) {
            itxeb_v018_fail('marker renderView introuvable');
        }
        $content = substr($content, 0, $pos) . $method . substr($content, $pos);
        echo "PATCH: methode archiveAiHistory\n";
    } else {
        echo "OK: methode archiveAiHistory deja presente\n";
    }

    if (strpos($content, 'itxeb-ai-history-archive-form') === false) {
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

        $selectedId = $this->getSelectedAiHistoryId();
        $selectedRecord = [];
        $html = '<section class="itxeb-cui-section itxeb-ai-history"><h3>Historique des analyses IA</h3><p>Dernières analyses générées pour ce cours. Les actions permettent de relire ou de retirer une analyse de l’historique visible sans modifier les traces xAPI/TRAX.</p><div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table"><thead><tr><th>Date UTC</th><th>Période</th><th>Statut</th><th>Résumé payload</th><th>Action</th></tr></thead><tbody>';
        foreach (array_slice($records, 0, 10) as $record) {
            $recordId = (string) ($record['id'] ?? '');
            $isSelected = $recordId !== '' && $recordId === $selectedId;
            if ($isSelected) {
                $selectedRecord = $record;
            }
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
            $action = '<div class="itxeb-ai-history-actions">' . $detailAction . $archiveAction . '</div>';
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
        $replacement = $panel . "\n\n    private function renderAiMarkdown";
        $content = preg_replace($pattern, $replacement, $content, 1, $count);
        if (!is_string($content) || $count !== 1) {
            itxeb_v018_fail('remplacement panneau historique impossible');
        }
        echo "PATCH: panneau historique IA avec retrait controle\n";
    } else {
        echo "OK: panneau historique avec retrait deja present\n";
    }

    if (strpos($content, '.itxeb-ai-history-actions') === false) {
        $css = '.itxeb-ai-history-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.itxeb-ai-history-archive-form{display:inline;margin:0}.itxeb-danger{color:#8a1f11;border-color:#d8b8b2;background:#fff5f3}.itxeb-danger:hover{background:#ffe5e0}';
        if (strpos($content, '</style>') === false) {
            itxeb_v018_fail('style introuvable');
        }
        $content = str_replace('</style>', $css . '</style>', $content);
        echo "PATCH: styles retrait historique IA\n";
    } else {
        echo "OK: styles retrait historique IA deja presents\n";
    }

    if ($content !== $original) {
        itxeb_v018_write($path, $content);
    } else {
        echo "OK: ecran template inchange\n";
    }
    itxeb_v018_lint($path);
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_v018_fail('lance ce script depuis la racine du plugin principal IliasTraxEventBridge.');
}

$history = $root . '/classes/class.ilIliasTraxEventBridgeAiAnalysisHistory.php';
$template = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$servicesDir = dirname(dirname(dirname($root)));
$liveBase = $servicesDir . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$liveScreen = $liveBase . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';

itxeb_v018_patch_history($history);
itxeb_v018_patch_screen($template);

if (is_file($liveScreen)) {
    if (!copy($template, $liveScreen)) {
        itxeb_v018_fail("copie template vers live impossible: {$liveScreen}");
    }
    echo "COPY: template V0.18 vers companion live\n";
    itxeb_v018_lint($liveScreen);
} else {
    echo "WARN: companion live absent: {$liveScreen}\n";
}

itxeb_v018_version($root . '/plugin.php', '0.18.0-dev');
itxeb_v018_version($root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl', '0.6.0');
itxeb_v018_version($liveBase . '/plugin.php', '0.6.0');

echo "\nV0.18 appliquee : retrait controle des analyses IA historisees.\n";
