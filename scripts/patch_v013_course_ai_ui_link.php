<?php
/**
 * Correctif V0.13 - transforme le bouton Analyse IA en lien GET interne.
 *
 * Dans certains écrans ILIAS/Delos, un formulaire POST placé dans un onglet
 * injecté par UIHook peut être repris par la navigation native du cours et
 * renvoyer vers Contenu ou Membres. Les onglets internes xAPI fonctionnent en
 * GET via currentUrlWith(), on applique donc le même principe au lancement IA.
 *
 * À lancer depuis la racine du plugin EventHook IliasTraxEventBridge :
 * php scripts/patch_v013_course_ai_ui_link.php
 */

function itxeb_link_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: " . $message . PHP_EOL);
    exit(1);
}

function itxeb_link_patch_file(string $file): bool
{
    if (!is_file($file)) {
        echo "IGNORE: fichier absent: {$file}" . PHP_EOL;
        return false;
    }

    $content = file_get_contents($file);
    if (!is_string($content)) {
        itxeb_link_fail("lecture impossible: {$file}");
    }

    $startMarker = "    /** @param array<string,mixed> \$course */\n    private function renderAiAnalysisAction(array \$course): string\n";
    $endMarker = "\n\n    private function renderAiAnalysisResult";

    $start = strpos($content, $startMarker);
    if ($start === false) {
        if (strpos($content, 'itxeb-ai-analysis-action') === false) {
            itxeb_link_fail("méthode renderAiAnalysisAction introuvable dans {$file}");
        }
        echo "OK: méthode IA présente mais point de remplacement non standard: {$file}" . PHP_EOL;
        return false;
    }

    $end = strpos($content, $endMarker, $start);
    if ($end === false) {
        itxeb_link_fail("fin de méthode renderAiAnalysisAction introuvable dans {$file}");
    }

    $replacement = <<<'PHP'
    /** @param array<string,mixed> $course */
    private function renderAiAnalysisAction(array $course): string
    {
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $url = $this->currentUrlWith([
            'itxeb_cui_cmd' => 'generateCourseAiAnalysis',
            'itxeb_course_ref_id' => (string) $courseRefId,
            'itxeb_period_days' => (string) $this->getPeriodDays(),
            'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
            'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
        ]);

        return '<section class="itxeb-cui-section itxeb-ai-analysis-action"><h3>Analyse IA du cours</h3>'
            . '<p>Génère une synthèse pédagogique à partir des données xAPI agrégées de la période sélectionnée. En anonymisation stricte, aucun nom, courriel ou identité nominative apprenant n’est envoyé.</p>'
            . '<p><a class="btn btn-primary" href="' . $this->esc($url) . '">Générer une analyse IA du cours</a></p>'
            . '</section>';
    }
PHP;

    $newContent = substr($content, 0, $start) . $replacement . substr($content, $end);
    if ($newContent === $content) {
        echo "OK: déjà corrigé: {$file}" . PHP_EOL;
        return false;
    }

    if (file_put_contents($file, $newContent) === false) {
        itxeb_link_fail("écriture impossible: {$file}");
    }

    echo "PATCH: {$file}" . PHP_EOL;
    return true;
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_link_fail('lance ce script depuis la racine du plugin EventHook IliasTraxEventBridge.');
}

$candidates = [];
$candidates[] = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';

$eventHookSuffix = '/Services/EventHandling/EventHook/IliasTraxEventBridge';
$uiHookSuffix = '/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
if (substr($root, -strlen($eventHookSuffix)) === $eventHookSuffix) {
    $candidates[] = substr($root, 0, -strlen($eventHookSuffix)) . $uiHookSuffix . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
}

$changed = false;
foreach (array_unique($candidates) as $file) {
    $changed = itxeb_link_patch_file($file) || $changed;
}

echo $changed ? "Correctif lien GET Analyse IA appliqué." . PHP_EOL : "Aucune modification nécessaire." . PHP_EOL;
