<?php

/**
 * Patch generated Course UI screen to add an anonymized struggling learners block.
 *
 * The patch is applied after the companion templates are copied to the ILIAS
 * UIHook plugin directory. It is idempotent.
 */

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_struggling_learners.php /path/to/class.ilIliasTraxEventBridgeCourseUIScreen.php\n");
    exit(1);
}

$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Unable to read target file: {$file}\n");
    exit(1);
}

if (strpos($code, 'private function renderStrugglingLearners(') !== false) {
    echo "Struggling learners patch already present in {$file}\n";
    exit(0);
}

$analysisStart = strpos($code, 'private function renderAnalysis(array $course): string');
$expertStart = strpos($code, 'private function renderExpert(array $course): string');
if ($analysisStart === false || $expertStart === false || $expertStart <= $analysisStart) {
    fwrite(STDERR, "Patch failed: unable to locate renderAnalysis/renderExpert boundaries in {$file}\n");
    exit(1);
}

$beforeAnalysis = substr($code, 0, $analysisStart);
$analysisBlock = substr($code, $analysisStart, $expertStart - $analysisStart);
$afterAnalysis = substr($code, $expertStart);

$analysisReturn = "        return \$html . '</tbody></table></div></section>';";
$analysisReturnPatched = "        return \$html . '</tbody></table></div>' . \$this->renderStrugglingLearners(\$dashboard) . '</section>';";
if (strpos($analysisBlock, $analysisReturn) === false) {
    fwrite(STDERR, "Patch failed: unable to locate renderAnalysis final return in {$file}\n");
    exit(1);
}
$analysisBlock = str_replace($analysisReturn, $analysisReturnPatched, $analysisBlock);

$newMethod = <<<'PHP'
/** @param array<string,mixed> $dashboard */
    private function renderStrugglingLearners(array $dashboard): string
    {
        $rows = is_array($dashboard['expert_rows'] ?? null) ? $dashboard['expert_rows'] : [];
        $learners = [];

        foreach ($rows as $row) {
            if (!is_array($row) || (string) ($row['obj_type'] ?? '') !== 'tst') {
                continue;
            }
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $score = is_numeric($row['score_raw'] ?? null) ? (float) $row['score_raw'] : null;
            $success = $row['success'] ?? null;
            $verbId = (string) ($row['verb_id'] ?? '');
            $failed = ($success === false) || stripos($verbId, 'failed') !== false;
            $lowScore = $score !== null && $score < 50.0;

            if (!$failed && !$lowScore) {
                continue;
            }

            if (!isset($learners[$userId])) {
                $learners[$userId] = [
                    'anonymous_id' => 'Apprenant ' . substr(sha1('itxeb:' . (string) $userId), 0, 8),
                    'alerts' => 0,
                    'failed' => 0,
                    'low_scores' => 0,
                    'scores_total' => 0.0,
                    'scores_count' => 0,
                    'last_at' => '',
                    'resources' => [],
                ];
            }

            $learners[$userId]['alerts']++;
            if ($failed) {
                $learners[$userId]['failed']++;
            }
            if ($lowScore) {
                $learners[$userId]['low_scores']++;
            }
            if ($score !== null) {
                $learners[$userId]['scores_total'] += $score;
                $learners[$userId]['scores_count']++;
            }
            $createdAt = (string) ($row['created_at'] ?? '');
            if ($createdAt !== '' && $createdAt > (string) $learners[$userId]['last_at']) {
                $learners[$userId]['last_at'] = $createdAt;
            }
            $title = trim((string) ($row['object_title'] ?? ''));
            if ($title !== '') {
                $learners[$userId]['resources'][$title] = true;
            }
        }

        $visible = [];
        foreach ($learners as $learner) {
            if ((int) $learner['failed'] >= 2 || (int) $learner['low_scores'] >= 2 || (int) $learner['alerts'] >= 3) {
                $learner['avg_score'] = (int) $learner['scores_count'] > 0
                    ? round(((float) $learner['scores_total']) / max(1, (int) $learner['scores_count']), 1)
                    : null;
                $visible[] = $learner;
            }
        }

        usort($visible, static function (array $a, array $b): int {
            return ((int) $b['alerts'] <=> (int) $a['alerts']) ?: ((int) $b['failed'] <=> (int) $a['failed']);
        });
        $visible = array_slice($visible, 0, 10);

        $html = '<section class="itxeb-cui-section"><h3>Apprenants en difficulté</h3>'
            . '<p>Vue anonymisée : aucun nom ni courriel n’est affiché. Les identifiants sont des pseudonymes techniques.</p>';
        if (count($visible) === 0) {
            return $html . '<p><em>Aucun apprenant en difficulté détecté sur la période et le filtre sélectionnés.</em></p></section>';
        }

        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-struggling-table"><thead><tr><th>Apprenant</th><th>Alertes</th><th>Échecs</th><th>Scores faibles</th><th>Score moyen</th><th>Dernière alerte</th><th>Ressources concernées</th></tr></thead><tbody>';
        foreach ($visible as $learner) {
            $resources = array_keys((array) ($learner['resources'] ?? []));
            sort($resources);
            $resourceText = count($resources) === 0 ? '-' : implode(', ', array_slice($resources, 0, 3));
            if (count($resources) > 3) {
                $resourceText .= ' +' . (count($resources) - 3);
            }
            $scoreText = $learner['avg_score'] === null ? '-' : (string) $learner['avg_score'] . ' %';
            $html .= '<tr><td><span class="itxeb-signal itxeb-signal-warning">' . $this->esc((string) $learner['anonymous_id']) . '</span></td>'
                . '<td>' . $this->esc((string) ($learner['alerts'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($learner['failed'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($learner['low_scores'] ?? 0)) . '</td>'
                . '<td>' . $this->esc($scoreText) . '</td>'
                . '<td>' . $this->esc((string) ($learner['last_at'] ?? '')) . '</td>'
                . '<td>' . $this->esc($resourceText) . '</td></tr>';
        }

        return $html . '</tbody></table></div></section>';
    }

    /** @param array<string,mixed> $course */
    
PHP;

$updated = $beforeAnalysis . $analysisBlock . $newMethod . $afterAnalysis;

$styleOld = '#itxeb-course-ui-screen .itxeb-cui-watch-table{min-width:900px}';
$styleNew = '#itxeb-course-ui-screen .itxeb-cui-watch-table{min-width:900px}#itxeb-course-ui-screen .itxeb-struggling-table{min-width:1050px}';
$updatedWithStyle = str_replace($styleOld, $styleNew, $updated);
if ($updatedWithStyle === $updated) {
    fwrite(STDERR, "Patch failed: unable to locate table style in {$file}\n");
    exit(1);
}

if (file_put_contents($file, $updatedWithStyle) === false) {
    fwrite(STDERR, "Unable to write target file: {$file}\n");
    exit(1);
}

echo "Struggling learners patch applied to Analysis only in {$file}\n";
