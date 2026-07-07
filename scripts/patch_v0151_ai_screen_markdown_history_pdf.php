<?php
/**
 * V0.15.1 - Patch écran Analyse IA.
 *
 * A lancer depuis la racine du plugin IliasTraxEventBridge.
 * Ce script corrige le patch V0.15 initial en utilisant un marker plus robuste.
 */

function fail_v0151(string $message): void
{
    fwrite(STDERR, "ERREUR: {$message}\n");
    exit(1);
}

function replace_v0151(string &$content, string $old, string $new, string $label): void
{
    if (strpos($content, $new) !== false) {
        echo "OK: {$label} déjà présent\n";
        return;
    }
    if (strpos($content, $old) === false) {
        fail_v0151("bloc introuvable: {$label}");
    }
    $content = str_replace($old, $new, $content);
    echo "PATCH: {$label}\n";
}

function insert_before_v0151(string &$content, string $needle, string $insert, string $label): void
{
    if (strpos($content, 'private function renderAiMarkdown(string $markdown): string') !== false && $label === 'méthodes IA V0.15') {
        echo "OK: {$label} déjà présent\n";
        return;
    }
    $pos = strpos($content, $needle);
    if ($pos === false) {
        fail_v0151("marker introuvable: {$label}");
    }
    $content = substr($content, 0, $pos) . $insert . "\n" . substr($content, $pos);
    echo "PATCH: {$label}\n";
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/companion/IliasTraxEventBridgeCourseUI/classes')) {
    fail_v0151('lance ce script depuis la racine du plugin.');
}

$screen = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$history = $root . '/classes/class.ilIliasTraxEventBridgeAiAnalysisHistory.php';
if (!is_file($screen)) {
    fail_v0151("fichier écran introuvable: {$screen}");
}
if (!is_file($history)) {
    fail_v0151("classe historique IA introuvable: {$history}");
}

$c = file_get_contents($screen);
if (!is_string($c)) {
    fail_v0151("lecture impossible: {$screen}");
}
$original = $c;

replace_v0151($c,
    "    /** @var ilIliasTraxEventBridgeLrsCourseSummary|null */\n    private \$lrsSummary;\n    private string \$message = '';",
    "    /** @var ilIliasTraxEventBridgeLrsCourseSummary|null */\n    private \$lrsSummary;\n    /** @var ilIliasTraxEventBridgeAiAnalysisHistory|null */\n    private \$aiHistory;\n    private string \$message = '';",
    'propriété historique IA'
);

replace_v0151($c,
    "            \$aiAnalyzerPath = \$this->bridge->getMainPluginPath() . '/classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php';\n            if (is_file(\$aiAnalyzerPath)) { require_once \$aiAnalyzerPath; }",
    "            \$aiAnalyzerPath = \$this->bridge->getMainPluginPath() . '/classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php';\n            if (is_file(\$aiAnalyzerPath)) { require_once \$aiAnalyzerPath; }\n            \$aiHistoryPath = \$this->bridge->getMainPluginPath() . '/classes/class.ilIliasTraxEventBridgeAiAnalysisHistory.php';\n            if (is_file(\$aiHistoryPath)) { require_once \$aiHistoryPath; }\n            if (class_exists('ilIliasTraxEventBridgeAiAnalysisHistory')) {\n                \$this->aiHistory = new ilIliasTraxEventBridgeAiAnalysisHistory(\$this->bridge->getMainPluginPath());\n            }",
    'chargement historique IA'
);

replace_v0151($c,
    "        \$html = '<section class=\"itxeb-cui-section\"><h2>Analyse des ressources</h2><p>Ressources utilisées, peu utilisées, activées sans trace ou associées à des signaux pédagogiques.</p>' . \$this->renderPeriodSelector('showCourseAnalysis') . \$this->renderResourceFilter(\$course, 'showCourseAnalysis') . \$this->renderAnalyticsWarning() . \$this->renderAiAnalysisAction(\$course) . \$this->renderAiAnalysisResult() . \$this->renderPedagogicalSynthesis(\$dashboard);",
    "        \$html = '<section class=\"itxeb-cui-section itxeb-trainer-page\"><h2>Analyse formateur</h2><p>Vue opérationnelle des ressources utilisées, peu utilisées, activées sans trace ou associées à des signaux pédagogiques.</p>' . \$this->renderPeriodSelector('showCourseAnalysis') . \$this->renderResourceFilter(\$course, 'showCourseAnalysis') . \$this->renderAnalyticsWarning() . \$this->renderTrainerActionSummary(\$dashboard) . \$this->renderAiAnalysisAction(\$course) . \$this->renderAiAnalysisResult() . \$this->renderAiHistoryPanel(\$course) . \$this->renderPedagogicalSynthesis(\$dashboard);",
    'page analyse formateur'
);

replace_v0151($c,
    "        \$dashboard = \$this->loadDashboard(\$course);\n        \$this->aiAnalysisResult = (new ilIliasTraxEventBridgeCourseAiAnalyzer())->analyze(\$course, \$dashboard);\n        if (!empty(\$this->aiAnalysisResult['success'])) {\n            \$this->message = 'Analyse IA générée.';",
    "        \$dashboard = \$this->loadDashboard(\$course);\n        \$this->aiAnalysisResult = (new ilIliasTraxEventBridgeCourseAiAnalyzer())->analyze(\$course, \$dashboard);\n        if (!empty(\$this->aiAnalysisResult['success'])) {\n            \$this->saveAiAnalysisHistory(\$course, \$this->aiAnalysisResult);\n            \$this->message = 'Analyse IA générée et historisée.';",
    'sauvegarde historique après IA'
);

replace_v0151($c,
    "        return '<section class=\"itxeb-cui-section itxeb-ai-analysis-action\"><h3>Analyse IA du cours</h3>'\n            . '<p>Génère une synthèse pédagogique à partir des données xAPI agrégées de la période sélectionnée. En anonymisation stricte, aucun nom, courriel ou identité nominative apprenant n’est envoyé.</p>'\n            . '<p><a class=\"btn btn-primary\" href=\"' . \$this->esc(\$url) . '\">Générer une analyse IA du cours</a></p>'\n            . '</section>';",
    "        return '<section class=\"itxeb-cui-section itxeb-ai-analysis-action itxeb-trainer-card\"><h3>Analyse IA du cours</h3>'\n            . '<p>Génère une synthèse pédagogique à partir des données xAPI agrégées de la période sélectionnée. En anonymisation stricte, aucun nom, courriel ou identité nominative apprenant n’est envoyé.</p>'\n            . '<p><a class=\"btn btn-primary\" href=\"' . \$this->esc(\$url) . '\">Générer une nouvelle analyse IA</a></p>'\n            . '<p><small>La dernière analyse réussie est historisée localement et reprise dans l’export PDF.</small></p>'\n            . '</section>';",
    'bloc action IA'
);

replace_v0151($c,
    "        if (\$analysis !== '') {\n            \$html .= '<pre>' . \$this->esc(\$analysis) . '</pre>';\n        }\n        return \$html . '</section>';",
    "        if (\$analysis !== '') {\n            \$html .= \$this->renderAiMarkdown(\$analysis);\n        }\n        return \$html . '</section>';",
    'rendu Markdown IA'
);

replace_v0151($c,
    "        \$html .= \$this->pdfResourcesTable(\$dashboard);\n        return \$html . '</body></html>';",
    "        \$html .= \$this->pdfResourcesTable(\$dashboard);\n        \$html .= \$this->pdfAiAnalysisSection(\$course);\n        return \$html . '</body></html>';",
    'analyse IA dans PDF'
);

$methods = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderTrainerActionSummary(array $dashboard): string
    {
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $pedagogy = is_array($dashboard['pedagogy'] ?? null) ? $dashboard['pedagogy'] : [];
        $critical = (int) ($pedagogy['critical_count'] ?? 0);
        $watch = (int) ($pedagogy['watch_count'] ?? 0);
        $withoutTrace = (int) ($pedagogy['resources_without_trace'] ?? 0);
        $failed = (int) ($summary['tests_failed'] ?? 0);
        $passed = (int) ($summary['tests_passed'] ?? 0);
        $priority = $critical > 0 ? 'Priorité haute : traiter les ressources critiques.' : ($watch > 0 || $withoutTrace > 0 ? 'Priorité moyenne : vérifier les ressources à surveiller.' : 'Situation stable sur les données disponibles.');
        return '<div class="itxeb-trainer-summary">'
            . $this->metricCard('Priorité formateur', $critical > 0 ? 'Haute' : (($watch + $withoutTrace) > 0 ? 'Moyenne' : 'Normale'), $priority)
            . $this->metricCard('Ressources critiques', (string) $critical, 'À reprendre en premier')
            . $this->metricCard('À surveiller', (string) $watch, 'Signaux faibles')
            . $this->metricCard('Tests', (string) $passed . ' / ' . (string) $failed, 'Réussis / échoués')
            . '</div>';
    }

    /** @param array<string,mixed> $course @param array<string,mixed> $result */
    private function saveAiAnalysisHistory(array $course, array $result): void
    {
        if (!$this->aiHistory) {
            return;
        }
        try {
            $this->aiHistory->save($course, $this->getPeriodDays(), $result, $this->getCurrentUserId());
        } catch (Throwable $e) {
            error_log('[IliasTraxEventBridge] Historisation IA impossible: ' . $e->getMessage());
        }
    }

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

    private function renderAiMarkdown(string $markdown): string
    {
        $lines = preg_split('/\R/', trim($markdown));
        if (!is_array($lines)) {
            return '<div class="itxeb-ai-markdown"><p>' . $this->esc($markdown) . '</p></div>';
        }
        $html = '<div class="itxeb-ai-markdown">';
        $inList = false;
        $inTable = false;
        foreach ($lines as $line) {
            $trim = trim((string) $line);
            if ($trim === '' || $trim === '---') {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                if ($inTable) { $html .= '</tbody></table></div>'; $inTable = false; }
                continue;
            }
            if (preg_match('/^##\s*(.+)$/u', $trim, $m) === 1) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                if ($inTable) { $html .= '</tbody></table></div>'; $inTable = false; }
                $html .= '<h4>' . $this->renderInlineMarkdown((string) $m[1]) . '</h4>';
                continue;
            }
            if (preg_match('/^###\s*(.+)$/u', $trim, $m) === 1) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                if ($inTable) { $html .= '</tbody></table></div>'; $inTable = false; }
                $html .= '<h5>' . $this->renderInlineMarkdown((string) $m[1]) . '</h5>';
                continue;
            }
            if (strpos($trim, '|') === 0 && substr($trim, -1) === '|') {
                if (preg_match('/^\|\s*-+/', $trim) === 1) { continue; }
                if ($inList) { $html .= '</ul>'; $inList = false; }
                if (!$inTable) { $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-ai-table"><tbody>'; $inTable = true; }
                $html .= '<tr>';
                foreach (array_map('trim', explode('|', trim($trim, '|'))) as $cell) {
                    $html .= '<td>' . $this->renderInlineMarkdown($cell) . '</td>';
                }
                $html .= '</tr>';
                continue;
            }
            if (preg_match('/^-\s+(.+)$/u', $trim, $m) === 1) {
                if ($inTable) { $html .= '</tbody></table></div>'; $inTable = false; }
                if (!$inList) { $html .= '<ul>'; $inList = true; }
                $html .= '<li>' . $this->renderInlineMarkdown((string) $m[1]) . '</li>';
                continue;
            }
            if ($inList) { $html .= '</ul>'; $inList = false; }
            if ($inTable) { $html .= '</tbody></table></div>'; $inTable = false; }
            $html .= '<p>' . $this->renderInlineMarkdown($trim) . '</p>';
        }
        if ($inList) { $html .= '</ul>'; }
        if ($inTable) { $html .= '</tbody></table></div>'; }
        return $html . '</div>';
    }

    private function renderInlineMarkdown(string $text): string
    {
        $escaped = $this->esc($text);
        return preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $escaped) ?: $escaped;
    }

    /** @param array<string,mixed> $course */
    private function pdfAiAnalysisSection(array $course): string
    {
        if (!$this->aiHistory) {
            return '';
        }
        $record = $this->aiHistory->latest((int) ($course['course_ref_id'] ?? 0), $this->getPeriodDays());
        if ($record === []) {
            $record = $this->aiHistory->latest((int) ($course['course_ref_id'] ?? 0), 0);
        }
        $analysis = trim((string) ($record['analysis'] ?? ''));
        if ($analysis === '') {
            return '<h2>Analyse IA</h2><p><em>Aucune analyse IA historisée à inclure dans le PDF.</em></p>';
        }
        return '<h2>Analyse IA historisée</h2><p class="small">Générée le ' . $this->esc((string) ($record['created_at_utc'] ?? '')) . ' — ' . $this->esc((string) ($record['payload_summary'] ?? '')) . '</p>' . $this->renderAiMarkdown($analysis);
    }

PHP;
insert_before_v0151($c, "    /** @param array<string,mixed> \$course */\n    /** @param array<string,mixed> \$dashboard */\n    private function renderStrugglingLearners", $methods, 'méthodes IA V0.15');

if (strpos($c, '.itxeb-ai-markdown') === false) {
    $needle = '</style>';
    if (strpos($c, $needle) === false) {
        fail_v0151('balise style introuvable');
    }
    $css = '.itxeb-trainer-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin:12px 0}.itxeb-trainer-card{border-left:4px solid #337ab7}.itxeb-ai-markdown{border:1px solid #c8d6e5;background:#fff;padding:14px;border-radius:6px;line-height:1.55}.itxeb-ai-markdown h4{font-size:18px;margin:16px 0 8px;border-bottom:1px solid #d9e2ec;padding-bottom:4px}.itxeb-ai-markdown h5{font-size:15px;margin:12px 0 6px}.itxeb-ai-markdown ul{margin:6px 0 12px 22px}.itxeb-ai-markdown li{margin:4px 0}.itxeb-ai-table td:first-child{font-weight:700}.itxeb-ai-history table small{line-height:1.35}';
    $c = str_replace($needle, $css . $needle, $c);
    echo "PATCH: styles IA V0.15\n";
} else {
    echo "OK: styles IA V0.15 déjà présents\n";
}

if ($c !== $original) {
    if (file_put_contents($screen, $c) === false) {
        fail_v0151("écriture impossible: {$screen}");
    }
    echo "WRITE: {$screen}\n";
} else {
    echo "OK: écran déjà à jour\n";
}

foreach ([$screen, $history, $root . '/plugin.php', $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl'] as $file) {
    passthru('php -l ' . escapeshellarg($file), $code);
    if ($code !== 0) {
        fail_v0151("syntaxe PHP invalide: {$file}");
    }
}

echo "\nV0.15.1 appliquée : rendu Markdown IA, historique IA, PDF avec analyse IA.\n";
