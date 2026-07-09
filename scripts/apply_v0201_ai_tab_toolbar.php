<?php
$root = getcwd();
$screen = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$router = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php.tpl';
$hook = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl';
$main = $root . '/plugin.php';
$comp = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';

function rf($f){$s=file_get_contents($f); if(!is_string($s)){fwrite(STDERR,"read fail $f\n"); exit(1);} return $s;}
function wf($f,$a,$b){if($a!==$b){file_put_contents($f,$b); echo "WRITE $f\n";} else {echo "OK $f\n";}}
function rep(&$s,$a,$b,$label){if(strpos($s,$b)!==false){echo "OK $label\n"; return;} $p=strpos($s,$a); if($p===false){fwrite(STDERR,"missing $label\n"); exit(1);} $s=substr($s,0,$p).$b.substr($s,$p+strlen($a)); echo "PATCH $label\n";}
function ver($f,$v){$a=rf($f); $b=preg_replace("/\\$version = '[^']*';/","\$version = '$v';",$a,1); if(!is_string($b)){fwrite(STDERR,"version fail $f\n"); exit(1);} wf($f,$a,$b);}

$a=rf($screen); $s=$a;
rep($s,"            \$cmd = 'showCourseAnalysis';\n        }\n\n        \$course = \$this->resolver->resolveCourse(\$courseRefId);\n        if (\$cmd === 'generateCourseAiAnalysis') {\n            \$this->runCourseAiAnalysis(\$course);\n            \$cmd = 'showCourseAnalysis';\n        }","            \$cmd = 'showCourseAiAnalysis';\n        }\n\n        \$course = \$this->resolver->resolveCourse(\$courseRefId);\n        if (\$cmd === 'generateCourseAiAnalysis') {\n            \$this->runCourseAiAnalysis(\$course);\n            \$cmd = 'showCourseAiAnalysis';\n        }",'retours IA');
rep($s,"        if (\$cmd === 'showCourseAnalysis') {\n            return \$this->renderAnalysis(\$course);\n        }","        if (\$cmd === 'showCourseAnalysis') {\n            return \$this->renderAnalysis(\$course);\n        }\n        if (\$cmd === 'showCourseAiAnalysis') {\n            return \$this->renderAiAnalysis(\$course);\n        }",'vue IA');
rep($s,'        $html = \'<section class="itxeb-cui-section itxeb-trainer-page"><h2>Analyse formateur</h2><div style="border:2px solid #c8d6e5;background:#f8fbff;border-radius:6px;padding:12px 14px;margin:10px 0 14px"><strong>Mode d’emploi rapide</strong><ul style="margin:8px 0 0 18px"><li>Choisir la période de suivi.</li><li>Lire les signaux critiques et à surveiller.</li><li>Cocher deux analyses IA historisées pour les comparer.</li></ul></div><p style="color:#555">Vue opérationnelle des ressources utilisées, peu utilisées, activées sans trace ou associées à des signaux pédagogiques.</p>\' . $this->renderPeriodSelector(\'showCourseAnalysis\') . $this->renderResourceFilter($course, \'showCourseAnalysis\') . $this->renderAnalyticsWarning() . $this->renderTrainerActionSummary($dashboard) . $this->renderAiAnalysisAction($course) . $this->renderAiAnalysisResult() . $this->renderAiHistoryPanel($course) . $this->renderPedagogicalSynthesis($dashboard);','        $html = \'<section class="itxeb-cui-section itxeb-trainer-page"><h2>Analyse formateur</h2><div style="border:2px solid #c8d6e5;background:#f8fbff;border-radius:6px;padding:12px 14px;margin:10px 0 14px"><strong>Mode d’emploi rapide</strong><ul style="margin:8px 0 0 18px"><li>Choisir la période de suivi.</li><li>Lire les signaux critiques et à surveiller.</li><li>Utiliser l’onglet Analyse IA pour générer ou comparer les synthèses IA.</li></ul></div><p style="color:#555">Vue opérationnelle des ressources utilisées, peu utilisées, activées sans trace ou associées à des signaux pédagogiques.</p>\' . $this->renderPeriodSelector(\'showCourseAnalysis\') . $this->renderResourceFilter($course, \'showCourseAnalysis\') . $this->renderAnalyticsWarning() . $this->renderTrainerActionSummary($dashboard) . $this->renderPedagogicalSynthesis($dashboard);','analyse sans IA');
$method=<<<'CODE'
    /** @param array<string,mixed> $course */
    private function renderAiAnalysis(array $course): string
    {
        $html = '<section class="itxeb-cui-section itxeb-ai-page"><h2>Analyse IA</h2>'
            . '<div style="border:2px solid #c8d6e5;background:#f8fbff;border-radius:6px;padding:12px 14px;margin:10px 0 14px"><strong>Mode d’emploi IA</strong><ul style="margin:8px 0 0 18px"><li>Générer une synthèse IA à partir des traces TRAX/LRS.</li><li>Relire les analyses historisées du cours.</li><li>Comparer deux analyses IA pour suivre l’évolution du cours.</li></ul></div>'
            . '<p style="color:#555">Cet onglet regroupe uniquement les fonctions d’analyse IA. Les traces xAPI/TRAX ne sont pas modifiées.</p>'
            . $this->renderPeriodSelector('showCourseAiAnalysis')
            . $this->renderResourceFilter($course, 'showCourseAiAnalysis')
            . $this->renderAnalyticsWarning()
            . $this->renderAiAnalysisAction($course)
            . $this->renderAiAnalysisResult()
            . $this->renderAiHistoryPanel($course);
        return $html . '</section>';
    }

CODE;
if(strpos($s,'private function renderAiAnalysis(array $course): string')===false){$m="    /** @param array<string,mixed> \$course */\n    private function renderAiAnalysisAction(array \$course): string"; $p=strpos($s,$m); if($p===false){fwrite(STDERR,"missing insert IA\n"); exit(1);} $s=substr($s,0,$p).$method.substr($s,$p); echo "PATCH method IA\n";} else echo "OK method IA\n";
$start=strpos($s,"    /** @param array<string,mixed> \$course */\n    private function renderAiAnalysisAction(array \$course): string"); $end=strpos($s,"    private function extractAiHistorySummaryCount",$start); if($start===false||$end===false){fwrite(STDERR,"missing IA segment\n"); exit(1);} $seg=substr($s,$start,$end-$start); $seg2=str_replace('showCourseAnalysis','showCourseAiAnalysis',$seg); $s=substr($s,0,$start).$seg2.substr($s,$end);
rep($s,"        \$tabs = ['showCourseDashboard' => 'Tableau de bord', 'showCourseAnalysis' => 'Analyse', 'showCourseExpert' => 'Expert', 'showCourseTracking' => 'Configuration'];","        \$tabs = ['showCourseDashboard' => 'Tableau de bord', 'showCourseAnalysis' => 'Analyse', 'showCourseAiAnalysis' => 'Analyse IA', 'showCourseExpert' => 'Expert', 'showCourseTracking' => 'Configuration'];",'tabs internes');
rep($s,"        return in_array(\$cmd, ['showCourseDashboard', 'showCourseAnalysis', 'showCourseExpert', 'exportCourseExpertCsv', 'exportCourseDashboardPdf'], true)\n            ? (\$cmd === 'exportCourseExpertCsv' ? 'showCourseExpert' : (\$cmd === 'exportCourseDashboardPdf' ? 'showCourseDashboard' : \$cmd))\n            : 'showCourseTracking';","        return in_array(\$cmd, ['showCourseDashboard', 'showCourseAnalysis', 'showCourseAiAnalysis', 'showCourseExpert', 'exportCourseExpertCsv', 'exportCourseDashboardPdf'], true)\n            ? (\$cmd === 'exportCourseExpertCsv' ? 'showCourseExpert' : (\$cmd === 'exportCourseDashboardPdf' ? 'showCourseDashboard' : \$cmd))\n            : 'showCourseTracking';",'normalisation IA');
wf($screen,$a,$s);

$a=rf($router); $r=$a;
rep($r,"    public function showAnalysis(): void { \$this->render(\$this->getRouterCommand('showAnalysis')); }\n    public function showExpert(): void { \$this->render(\$this->getRouterCommand('showExpert')); }","    public function showAnalysis(): void { \$this->render(\$this->getRouterCommand('showAnalysis')); }\n    public function showAiAnalysis(): void { \$this->render(\$this->getRouterCommand('showAiAnalysis')); }\n    public function showExpert(): void { \$this->render(\$this->getRouterCommand('showExpert')); }",'router method');
$r=str_replace("'showAnalysis', 'showExpert'","'showAnalysis', 'showAiAnalysis', 'showExpert'",$r);
$r=str_replace("'showCourseAnalysis' => 'showAnalysis',\n            'showCourseExpert' => 'showExpert'","'showCourseAnalysis' => 'showAnalysis',\n            'showCourseAiAnalysis' => 'showAiAnalysis',\n            'showCourseExpert' => 'showExpert'",$r);
$r=str_replace("'showAnalysis' => 'showCourseAnalysis',\n            'showExpert' => 'showCourseExpert'","'showAnalysis' => 'showCourseAnalysis',\n            'showAiAnalysis' => 'showCourseAiAnalysis',\n            'showExpert' => 'showCourseExpert'",$r);
$r=str_replace("\$tabs->addTab('itxeb_xapi_analysis', 'Analyse', \$this->link('showAnalysis'));\n            \$tabs->addTab('itxeb_xapi_expert', 'Expert', \$this->link('showExpert'));","\$tabs->addTab('itxeb_xapi_analysis', 'Analyse', \$this->link('showAnalysis'));\n            \$tabs->addTab('itxeb_xapi_ai_analysis', 'Analyse IA', \$this->link('showAiAnalysis'));\n            \$tabs->addTab('itxeb_xapi_expert', 'Expert', \$this->link('showExpert'));",$r);
$r=str_replace("'showAnalysis' => 'itxeb_xapi_analysis',\n                'generateAiAnalysis' => 'itxeb_xapi_analysis'","'showAnalysis' => 'itxeb_xapi_analysis',\n                'showAiAnalysis' => 'itxeb_xapi_ai_analysis',\n                'generateAiAnalysis' => 'itxeb_xapi_ai_analysis'",$r);
wf($router,$a,$r);

$a=rf($hook); $h=$a;
rep($h,"    public function modifyGUI(\$a_comp, \$a_part, \$a_par = []): void\n    {\n    }","    public function modifyGUI(\$a_comp, \$a_part, \$a_par = []): void\n    {\n        try {\n            if (\$this->isRoutedPluginRequest() || !\$this->isCourseContentRequest()) { return; }\n            \$context = \$this->getCurrentCourseContext();\n            \$courseRefId = (int) (\$context['course_ref_id'] ?? 0);\n            if (\$courseRefId <= 0 || empty(\$context['main_plugin_available']) || empty(\$context['course_tracking_classes_available']) || empty(\$context['can_manage'])) { return; }\n            if (!isset(\$GLOBALS['DIC']) || !is_object(\$GLOBALS['DIC']) || !method_exists(\$GLOBALS['DIC'], 'toolbar')) { return; }\n            \$toolbar = \$GLOBALS['DIC']->toolbar();\n            if (is_object(\$toolbar) && method_exists(\$toolbar, 'addButton')) {\n                \$toolbar->addButton('Pilotage xAPI', \$this->buildRouterUrl(\$courseRefId, 'showDashboard'));\n            }\n        } catch (Throwable \$ignored) {}\n    }",'toolbar button');
rep($h,"        \$url = \$this->buildRouterUrl(\$courseRefId, 'showDashboard');\n        \$newHtml = \$this->injectCourseEntryButton(\$html, \$url);\n        return \$newHtml !== \$html\n            ? ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => \$newHtml]\n            : ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];","        return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];",'remove content entry');
wf($hook,$a,$h);
ver($main,'0.20.1-dev');
ver($comp,'0.8.1');
echo "V0.20.1 ready\n";
