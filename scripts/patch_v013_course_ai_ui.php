<?php
/**
 * Patch V0.13 - branchement de l'analyse IA dans l'écran Cours > Suivi xAPI > Analyse.
 *
 * À lancer depuis la racine du plugin EventHook IliasTraxEventBridge :
 * php scripts/patch_v013_course_ai_ui.php
 *
 * Le script patche :
 * - le template compagnon dans le dépôt ;
 * - le plugin compagnon UIHook installé, si le dossier est présent.
 */

function itxeb_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: " . $message . PHP_EOL);
    exit(1);
}

function itxeb_patch_file(string $file): bool
{
    if (!is_file($file)) {
        echo "IGNORE: fichier absent: {$file}" . PHP_EOL;
        return false;
    }

    $content = file_get_contents($file);
    if (!is_string($content)) {
        itxeb_fail("lecture impossible: {$file}");
    }

    $original = $content;

    if (strpos($content, 'aiAnalysisResult') === false) {
        $needle = "    private string $" . "messageType = 'info';\n";
        $insert = "    private string $" . "messageType = 'info';\n"
            . "    /** @var array<string,mixed>|null */\n"
            . "    private $" . "aiAnalysisResult = null;\n";
        if (strpos($content, $needle) === false) {
            itxeb_fail("point insertion propriété IA introuvable dans {$file}");
        }
        $content = str_replace($needle, $insert, $content);
    }

    if (strpos($content, 'CourseAiAnalyzer.php') === false) {
        $needle = "            if (class_exists('ilIliasTraxEventBridgeLrsCourseSummary')) {\n"
            . "                $" . "this->lrsSummary = new ilIliasTraxEventBridgeLrsCourseSummary();\n"
            . "            }\n";
        $insert = $needle
            . "            $" . "aiAnalyzerPath = $" . "this->bridge->getMainPluginPath() . '/classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php';\n"
            . "            if (is_file($" . "aiAnalyzerPath)) { require_once $" . "aiAnalyzerPath; }\n";
        if (strpos($content, $needle) === false) {
            itxeb_fail("point insertion chargement analyseur IA introuvable dans {$file}");
        }
        $content = str_replace($needle, $insert, $content);
    }

    if (strpos($content, "generateCourseAiAnalysis") === false) {
        $needle = "        $" . "course = $" . "this->resolver->resolveCourse($" . "courseRefId);\n"
            . "        if ($" . "cmd === 'exportCourseDashboardPdf') {\n";
        $insert = "        $" . "course = $" . "this->resolver->resolveCourse($" . "courseRefId);\n"
            . "        if ($" . "cmd === 'generateCourseAiAnalysis') {\n"
            . "            $" . "this->runCourseAiAnalysis($" . "course);\n"
            . "            $" . "cmd = 'showCourseAnalysis';\n"
            . "        }\n"
            . "        if ($" . "cmd === 'exportCourseDashboardPdf') {\n";
        if (strpos($content, $needle) === false) {
            itxeb_fail("point insertion commande IA introuvable dans {$file}");
        }
        $content = str_replace($needle, $insert, $content);
    }

    if (strpos($content, 'renderAiAnalysisAction') === false) {
        $needle = "$" . "html = '<section class=\"itxeb-cui-section\"><h2>Analyse des ressources</h2><p>Ressources utilisées, peu utilisées, activées sans trace ou associées à des signaux pédagogiques.</p>' . $" . "this->renderPeriodSelector('showCourseAnalysis') . $" . "this->renderResourceFilter($" . "course, 'showCourseAnalysis') . $" . "this->renderAnalyticsWarning() . $" . "this->renderPedagogicalSynthesis($" . "dashboard);";
        $insert = "$" . "html = '<section class=\"itxeb-cui-section\"><h2>Analyse des ressources</h2><p>Ressources utilisées, peu utilisées, activées sans trace ou associées à des signaux pédagogiques.</p>' . $" . "this->renderPeriodSelector('showCourseAnalysis') . $" . "this->renderResourceFilter($" . "course, 'showCourseAnalysis') . $" . "this->renderAnalyticsWarning() . $" . "this->renderAiAnalysisAction($" . "course) . $" . "this->renderAiAnalysisResult() . $" . "this->renderPedagogicalSynthesis($" . "dashboard);";
        if (strpos($content, $needle) === false) {
            itxeb_fail("point insertion bouton IA introuvable dans {$file}");
        }
        $content = str_replace($needle, $insert, $content);
    }

    if (strpos($content, 'private function runCourseAiAnalysis') === false) {
        $needle = "    /** @param array<string,mixed> $" . "course */\n    /** @param array<string,mixed> $" . "dashboard */\n    private function renderStrugglingLearners(array $" . "dashboard): string\n";
        $methods = <<<'PHP'
    /** @param array<string,mixed> $course */
    private function runCourseAiAnalysis(array $course): void
    {
        if (!class_exists('ilIliasTraxEventBridgeCourseAiAnalyzer')) {
            $this->aiAnalysisResult = [
                'success' => false,
                'http_status' => 0,
                'message' => 'Service analyse IA indisponible.',
                'analysis' => '',
                'payload_summary' => '',
            ];
            $this->message = 'Analyse IA impossible : service indisponible.';
            $this->messageType = 'error';
            return;
        }

        $dashboard = $this->loadDashboard($course);
        $this->aiAnalysisResult = (new ilIliasTraxEventBridgeCourseAiAnalyzer())->analyze($course, $dashboard);
        if (!empty($this->aiAnalysisResult['success'])) {
            $this->message = 'Analyse IA générée.';
            $this->messageType = 'success';
        } else {
            $this->message = 'Analyse IA échouée : ' . (string) ($this->aiAnalysisResult['message'] ?? 'erreur inconnue');
            $this->messageType = 'error';
        }
    }

    /** @param array<string,mixed> $course */
    private function renderAiAnalysisAction(array $course): string
    {
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $action = $this->currentUrlWith([
            'itxeb_cui_cmd' => 'generateCourseAiAnalysis',
            'itxeb_course_ref_id' => (string) $courseRefId,
            'itxeb_period_days' => (string) $this->getPeriodDays(),
            'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
            'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
        ]);

        return '<section class="itxeb-cui-section itxeb-ai-analysis-action"><h3>Analyse IA du cours</h3>'
            . '<p>Génère une synthèse pédagogique à partir des données xAPI agrégées de la période sélectionnée. En anonymisation stricte, aucun nom, courriel ou identité nominative apprenant n’est envoyé.</p>'
            . '<form method="post" action="' . $this->esc($action) . '">'
            . '<input type="hidden" name="itxeb_cui_cmd" value="generateCourseAiAnalysis">'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
            . '<p><button class="btn btn-primary" type="submit">Générer une analyse IA du cours</button></p>'
            . '</form></section>';
    }

    private function renderAiAnalysisResult(): string
    {
        if ($this->aiAnalysisResult === null) {
            return '';
        }

        $success = !empty($this->aiAnalysisResult['success']);
        $http = (string) ($this->aiAnalysisResult['http_status'] ?? '0');
        $message = (string) ($this->aiAnalysisResult['message'] ?? '');
        $payloadSummary = (string) ($this->aiAnalysisResult['payload_summary'] ?? '');
        $analysis = trim((string) ($this->aiAnalysisResult['analysis'] ?? ''));
        $class = $success ? 'itxeb-cui-alert itxeb-cui-success' : 'itxeb-cui-alert itxeb-cui-error';

        $html = '<section class="itxeb-cui-section itxeb-ai-analysis-result"><h3>Résultat analyse IA</h3>'
            . '<div class="' . $class . '"><strong>HTTP ' . $this->esc($http) . '</strong> — ' . $this->esc($message) . '</div>';
        if ($payloadSummary !== '') {
            $html .= '<p><small>' . $this->esc($payloadSummary) . '</small></p>';
        }
        if ($analysis !== '') {
            $html .= '<pre>' . $this->esc($analysis) . '</pre>';
        }
        return $html . '</section>';
    }

PHP;
        if (strpos($content, $needle) === false) {
            itxeb_fail("point insertion méthodes IA introuvable dans {$file}");
        }
        $content = str_replace($needle, $methods . $needle, $content);
    }

    if ($content === $original) {
        echo "OK: déjà patché: {$file}" . PHP_EOL;
        return false;
    }

    if (file_put_contents($file, $content) === false) {
        itxeb_fail("écriture impossible: {$file}");
    }

    echo "PATCH: {$file}" . PHP_EOL;
    return true;
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_fail('lance ce script depuis la racine du plugin EventHook IliasTraxEventBridge.');
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
    $changed = itxeb_patch_file($file) || $changed;
}

echo $changed ? "Patch UI IA V0.13 appliqué." . PHP_EOL : "Aucune modification nécessaire." . PHP_EOL;
