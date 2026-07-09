<?php
$root = getcwd();
$template = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$classFile = $root . '/classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php';
$plugin = $root . '/plugin.php';
$companionPlugin = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$servicesRoot = dirname(dirname(dirname($root)));
$live = $servicesRoot . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = $servicesRoot . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php';

function read_strict(string $file): string
{
    $s = file_get_contents($file);
    if (!is_string($s)) {
        fwrite(STDERR, "Lecture impossible: $file\n");
        exit(1);
    }
    return $s;
}

function write_changed(string $file, string $old, string $new): void
{
    if ($old !== $new) {
        file_put_contents($file, $new);
        echo "WRITE: $file\n";
    } else {
        echo "OK: aucun changement $file\n";
    }
}

function set_version_safe(string $file, string $version): void
{
    $old = read_strict($file);
    $new = preg_replace('/\$version\s*=\s*\'[^\']*\';/', '$version = \'' . $version . '\';', $old, 1);
    if (!is_string($new)) {
        fwrite(STDERR, "Version impossible: $file\n");
        exit(1);
    }
    write_changed($file, $old, $new);
}

function patch_screen(string $file): void
{
    $old = read_strict($file);
    $s = $old;

    if (strpos($s, 'private function renderQuestionFailureHotspots(array $dashboard, array $course): string') === false) {
        $method = <<<'PHP'
    /** @param array<string,mixed> $dashboard @param array<string,mixed> $course */
    private function renderQuestionFailureHotspots(array $dashboard, array $course): string
    {
        $risks = is_array($dashboard['question_risks'] ?? null) ? $dashboard['question_risks'] : [];
        if ($risks === []) {
            $path = $this->bridge->getMainPluginPath() . '/classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php';
            if (is_file($path)) {
                require_once $path;
            }
            if (class_exists('ilIliasTraxEventBridgeQuestionRiskRepository')) {
                $allowedRefIds = [];
                foreach ((array) ($course['resources'] ?? []) as $resource) {
                    if (is_array($resource) && (string) ($resource['obj_type'] ?? '') === 'tst') {
                        $rid = (int) ($resource['ref_id'] ?? 0);
                        if ($rid > 0) { $allowedRefIds[] = $rid; }
                    }
                }
                try {
                    $risks = (new ilIliasTraxEventBridgeQuestionRiskRepository())->build($this->getPeriodDays(), $allowedRefIds, $this->getSelectedResourceRefId());
                } catch (Throwable $ignored) {
                    $risks = [];
                }
            }
        }

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
            fwrite(STDERR, "Point insertion méthode introuvable dans $file\n");
            exit(1);
        }
        $s = substr($s, 0, $pos) . $method . substr($s, $pos);
        echo "PATCH: méthode renderQuestionFailureHotspots\n";
    }

    $s = str_replace(
        '$this->renderPedagogicalSynthesis($dashboard) . $this->renderQuestionFailureHotspots($dashboard)',
        '$this->renderPedagogicalSynthesis($dashboard) . $this->renderQuestionFailureHotspots($dashboard, $course)',
        $s
    );

    if (strpos($s, 'renderQuestionFailureHotspots($dashboard, $course)') === false) {
        $needle = '$this->renderPedagogicalSynthesis($dashboard)';
        $replacement = '$this->renderPedagogicalSynthesis($dashboard) . $this->renderQuestionFailureHotspots($dashboard, $course)';
        $count = 0;
        $s = str_replace($needle, $replacement, $s, $count);
        echo "PATCH: appels renderQuestionFailureHotspots ($count remplacement(s))\n";
        if ($count < 1) {
            fwrite(STDERR, "Aucun appel renderPedagogicalSynthesis trouvé dans $file\n");
            exit(1);
        }
    } else {
        echo "OK: appels renderQuestionFailureHotspots déjà présents\n";
    }

    if (strpos($s, 'Questions à fort taux d’échec') === false || strpos($s, 'ilIliasTraxEventBridgeQuestionRiskRepository') === false) {
        fwrite(STDERR, "Patch UI incomplet dans $file\n");
        exit(1);
    }

    write_changed($file, $old, $s);
}

foreach ([$template, $classFile, $plugin, $companionPlugin] as $f) {
    if (!is_file($f)) {
        fwrite(STDERR, "Fichier absent: $f\n");
        exit(1);
    }
}

patch_screen($template);
set_version_safe($plugin, '0.21.2-dev');
set_version_safe($companionPlugin, '0.8.5');

if (!is_dir(dirname($live))) {
    fwrite(STDERR, "Répertoire live absent: " . dirname($live) . "\n");
    exit(1);
}
if (!copy($template, $live)) {
    fwrite(STDERR, "Copie impossible vers $live\n");
    exit(1);
}
echo "COPY: écran template vers UIHook live\n";
if (is_file($livePlugin)) {
    copy($companionPlugin, $livePlugin);
    echo "COPY: plugin.php.tpl vers UIHook live/plugin.php\n";
}

echo "V0.21.2b appliquée : bloc Questions à fort taux d’échec calculé depuis l’outbox locale.\n";
