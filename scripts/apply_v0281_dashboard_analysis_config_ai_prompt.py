#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
V0.28.1 — réaménagement Tableau de bord / Analyse, modes réels,
activation Pilotage xAPI par cours et prompt IA configurable.

Base attendue : V0.27.1 stable.
Compatible Python 3.6.
"""
import os
import re
import shutil
import subprocess
import sys
import tempfile
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ILIAS_ROOT = Path(os.environ.get("ILIAS_ROOT", "/var/www/ilias")).resolve()
LIVE_COMPANION = ILIAS_ROOT / "public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI"
BACKUP_DIR = Path(tempfile.gettempdir()) / ("itxeb_v0281_backup_" + datetime.now().strftime("%Y%m%d%H%M%S"))

MAIN_PLUGIN = ROOT / "plugin.php"
CONFIG_FILE = ROOT / "classes/class.ilIliasTraxEventBridgeConfig.php"
CONFIG_GUI_FILE = ROOT / "classes/class.ilIliasTraxEventBridgeConfigGUI.php"
AI_ANALYZER_FILE = ROOT / "classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php"
SCREEN_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl"
BRIDGE_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIBridge.php.tpl"
UIHOOK_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl"
COMPANION_PLUGIN_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl"
LIVE_SCREEN = LIVE_COMPANION / "classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
LIVE_BRIDGE = LIVE_COMPANION / "classes/class.ilIliasTraxEventBridgeCourseUIBridge.php"
LIVE_UIHOOK = LIVE_COMPANION / "classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php"
LIVE_COMPANION_PLUGIN = LIVE_COMPANION / "plugin.php"


def fail(message):
    print("ERREUR: " + message, file=sys.stderr)
    sys.exit(1)


def read_file(path):
    if not path.is_file():
        fail("fichier introuvable: " + str(path))
    return path.read_text(encoding="utf-8")


def backup(path):
    if not path.is_file():
        return
    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    target = BACKUP_DIR / str(path).lstrip("/").replace("/", "__")
    shutil.copy2(str(path), str(target))


def write_file(path, content):
    backup(path)
    path.write_text(content, encoding="utf-8")


def method_bounds(content, method_name):
    starts = []
    for prefix in ["private function ", "public function "]:
        pos = content.find(prefix + method_name + "(")
        if pos >= 0:
            starts.append(pos)
    if len(starts) != 1:
        fail("méthode introuvable ou multiple: " + method_name + " count=" + str(len(starts)))
    start = starts[0]
    brace = content.find("{", start)
    if brace < 0:
        fail("accolade introuvable: " + method_name)
    depth = 0
    quote = ""
    escaped = False
    for i in range(brace, len(content)):
        ch = content[i]
        if quote:
            if escaped:
                escaped = False
            elif ch == "\\":
                escaped = True
            elif ch == quote:
                quote = ""
            continue
        if ch == "'" or ch == '"':
            quote = ch
        elif ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                return start, i + 1
    fail("fin de méthode introuvable: " + method_name)


def replace_method(content, method_name, new_method):
    start, end = method_bounds(content, method_name)
    return content[:start] + new_method.rstrip() + content[end:]


def replace_once(content, search, replace, label):
    count = content.count(search)
    if count != 1:
        fail("point de remplacement introuvable ou multiple: " + label + " count=" + str(count))
    return content.replace(search, replace, 1)


def patch_version(content, new_version, label):
    updated, count = re.subn(r"\$version\s*=\s*'[^']+';", "$version = '" + new_version + "';", content, count=1)
    if count != 1:
        fail("version introuvable: " + label)
    return updated


def lint_php_memory(name, content):
    temp_dir = Path(tempfile.mkdtemp(prefix="itxeb_v0281_lint_"))
    temp_file = temp_dir / (name + ".php")
    temp_file.write_text(content, encoding="utf-8")
    proc = subprocess.run(["php", "-l", str(temp_file)], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, universal_newlines=True)
    print(proc.stdout.strip())
    shutil.rmtree(str(temp_dir), ignore_errors=True)
    if proc.returncode != 0:
        fail("lint PHP mémoire KO: " + name)


def lint_php_file(path):
    if not path.is_file():
        return
    proc = subprocess.run(["php", "-l", str(path)], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, universal_newlines=True)
    print(proc.stdout.strip())
    if proc.returncode != 0:
        fail("lint PHP KO: " + str(path))


CONFIG_METHODS = r'''    public function isPilotageCourseRestrictionEnabled(): bool { return $this->getBool('pilotage_course_restriction_enabled', false); }
    public function setPilotageCourseRestrictionEnabled(bool $enabled): void { $this->setBool('pilotage_course_restriction_enabled', $enabled); }

    public function getPilotageCourseRefIdsText(): string
    {
        return trim($this->get('pilotage_course_ref_ids', ''));
    }

    public function setPilotageCourseRefIdsText(string $value): void
    {
        preg_match_all('/[0-9]+/', $value, $matches);
        $ids = [];
        foreach ((array) ($matches[0] ?? []) as $raw) {
            $id = (int) $raw;
            if ($id > 0) { $ids[$id] = $id; }
        }
        ksort($ids);
        $this->set('pilotage_course_ref_ids', implode("\n", array_map('strval', array_values($ids))));
    }

    /** @return array<int,int> */
    public function getPilotageCourseRefIds(): array
    {
        preg_match_all('/[0-9]+/', $this->getPilotageCourseRefIdsText(), $matches);
        $ids = [];
        foreach ((array) ($matches[0] ?? []) as $raw) {
            $id = (int) $raw;
            if ($id > 0) { $ids[$id] = $id; }
        }
        ksort($ids);
        return array_values($ids);
    }

    public function isPilotageEnabledForCourse(int $courseRefId): bool
    {
        if ($courseRefId <= 0) { return false; }
        if (!$this->isPilotageCourseRestrictionEnabled()) { return true; }
        return in_array($courseRefId, $this->getPilotageCourseRefIds(), true);
    }

    public function getDefaultAiSystemPrompt(): string
    {
        return implode("\n", [
            'Tu es un assistant pédagogique pour un formateur utilisant ILIAS connecté à un LRS TRAX.',
            'Tu analyses uniquement des indicateurs xAPI agrégés, anonymisés et déjà filtrés côté serveur.',
            'Tu ne dois jamais inventer de chiffres, de ressources, de noms, de profils ou de causes absentes du payload.',
            'Tu ne dois jamais identifier, classer ou évaluer nominativement un apprenant.',
            'Tu peux citer les titres de ressources pédagogiques, car ils servent au plan d’action du formateur.',
            'Tu dois distinguer clairement les constats mesurés, les hypothèses pédagogiques prudentes et les actions recommandées.',
            'Tu dois répondre en français, en Markdown, avec des formulations opérationnelles et directement exploitables.',
            'Respecte exactement la structure suivante :',
            '## 1. Synthèse opérationnelle',
            '## 2. Lecture des indicateurs',
            '## 3. Priorités formateur',
            '## 4. Ressources à traiter',
            '## 5. Actions pédagogiques recommandées',
            '## 6. Points d’attention anonymisés',
            '## 7. Limites et fiabilité',
            'Dans chaque section, reste concis. Utilise des puces actionnables lorsque c’est pertinent.',
            'Si les données sont insuffisantes, indique-le explicitement au lieu de produire une conclusion forte.',
        ]);
    }

    public function getAiSystemPrompt(): string
    {
        $prompt = trim($this->get('ai_system_prompt', ''));
        return $prompt !== '' ? $prompt : $this->getDefaultAiSystemPrompt();
    }

    public function setAiSystemPrompt(string $prompt): void
    {
        $prompt = trim($prompt);
        if ($prompt === '') { $this->resetAiSystemPrompt(); return; }
        $this->set('ai_system_prompt', substr($prompt, 0, 12000));
    }

    public function resetAiSystemPrompt(): void
    {
        $this->set('ai_system_prompt', '');
    }

'''

AI_SYSTEM_PROMPT_METHOD = r'''    private function systemPrompt(): string
    {
        // ITXEB V0.28.1 : prompt système configurable depuis la configuration du plugin.
        return $this->config->getAiSystemPrompt();
    }
'''

BRIDGE_METHODS = r'''    public function isPilotageEnabledForCourse(int $courseRefId): bool
    {
        if ($courseRefId <= 0) { return false; }
        if (!$this->loadMainConfigClass()) { return true; }
        try {
            $config = new ilIliasTraxEventBridgeConfig();
            if (method_exists($config, 'isPilotageEnabledForCourse')) {
                return (bool) $config->isPilotageEnabledForCourse($courseRefId);
            }
        } catch (Throwable $ignored) { return true; }
        return true;
    }

    private function loadMainConfigClass(): bool
    {
        if (class_exists('ilIliasTraxEventBridgeConfig')) { return true; }
        $path = $this->mainPluginPath . '/classes/class.ilIliasTraxEventBridgeConfig.php';
        if (!is_file($path)) { return false; }
        try { require_once $path; } catch (Throwable $ignored) { return false; }
        return class_exists('ilIliasTraxEventBridgeConfig');
    }

'''

RENDER_DASHBOARD = r'''    /** @param array<string,mixed> $course */
    private function renderDashboard(array $course): string
    {
        // ITXEB V0.28.1 dashboard without analysis duplication.
        $dashboard = $this->loadDashboard($course);
        $widgets = $this->dashboardWidgets((int) ($course['course_ref_id'] ?? 0));
        $mode = $this->dashboardModeLabel($this->dashboardDisplayMode((int) ($course['course_ref_id'] ?? 0)));
        $html = '<section class="itxeb-cui-section itxeb-dashboard-v028"><h2>Tableau de bord du cours</h2><p>Vue de décision rapide : état global, progression, activité et indicateurs essentiels. Mode actuel : <strong>' . $this->esc($mode) . '</strong>.</p>'
            . $this->renderPeriodSelector('showCourseDashboard') . $this->renderResourceFilter($course, 'showCourseDashboard') . $this->renderAnalyticsWarning();
        if (!empty($widgets['command_center'])) { $html .= $this->renderDashboardCommandCenter($dashboard); }
        if (!empty($widgets['success_gauge'])) { $html .= $this->renderCourseSuccessGauge($dashboard); }
        if (!empty($widgets['learner_funnel'])) { $html .= $this->renderLearnerFunnel($dashboard); }
        if (!empty($widgets['synthesis'])) { $html .= $this->renderPedagogicalSynthesis($dashboard, $course); }
        if (!empty($widgets['comparison'])) { $html .= $this->renderPeriodComparison($course); }
        if (!empty($widgets['activity_by_day']) || !empty($widgets['top_resources'])) { $html .= $this->renderDashboardActivityTopLayout($dashboard, !empty($widgets['activity_by_day']), !empty($widgets['top_resources'])); }
        if (!empty($widgets['verb_distribution'])) { $html .= $this->renderVerbDistribution($dashboard); }
        if (!empty($widgets['enabled_without_trace'])) { $html .= $this->renderEnabledWithoutTraceResources($dashboard); }
        return $html . '<section class="itxeb-cui-section"><h3>Aller plus loin</h3><p>Les actions recommandées, la matrice détaillée des ressources et les questions à fort taux d’échec sont disponibles dans l’onglet <strong>Analyse</strong>, afin de garder ce tableau de bord lisible.</p></section></section>';
    }
'''

SAVE_DASHBOARD = r'''    /** @param array<string,mixed> $course */
    private function saveDashboardPreferences(array $course): void
    {
        // ITXEB V0.28.1 : les modes appliquent un vrai préréglage.
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $currentMode = $this->dashboardDisplayMode($courseRefId);
        $requestedMode = $this->postString('dashboard_display_mode');
        if (!in_array($requestedMode, ['compact', 'standard', 'full'], true)) { $requestedMode = 'standard'; }
        $widgets = $this->dashboardDefaultWidgets($requestedMode);
        $modeChanged = $requestedMode !== $currentMode;
        if (!$modeChanged) {
            $enabled = array_fill_keys($this->postStringArray('dashboard_widgets'), true);
            foreach ($this->dashboardWidgetDefinitions() as $key => $label) {
                if (strpos($key, '__mode_') === 0) {
                    $widgets[$key] = $key === ('__mode_' . $requestedMode);
                    continue;
                }
                $widgets[$key] = isset($enabled[$key]);
            }
        }
        $this->repository->setDashboardWidgets($courseRefId, (int) ($course['course_obj_id'] ?? 0), $widgets, $this->getCurrentUserId());
        $this->message = $modeChanged ? 'Mode du tableau de bord appliqué : ' . $this->dashboardModeLabel($requestedMode) . '.' : 'Préférences du tableau de bord enregistrées.';
        $this->messageType = 'success';
    }
'''

DASHBOARD_DEFS = r'''    /** @return array<string,string> */
    private function dashboardWidgetDefinitions(): array
    {
        return [
            '__mode_compact' => 'Mode compact — décision rapide',
            '__mode_standard' => 'Mode standard — suivi formateur recommandé',
            '__mode_full' => 'Mode complet — tous les blocs disponibles',
            'command_center' => 'État global du cours',
            'success_gauge' => 'Jauge réussite du cours',
            'learner_funnel' => 'Entonnoir pédagogique',
            'synthesis' => 'Synthèse pédagogique',
            'comparison' => 'Comparaison entre périodes',
            'activity_by_day' => 'Activité dans le temps',
            'top_resources' => 'Top ressources',
            'verb_distribution' => 'Répartition des actions',
            'enabled_without_trace' => 'Ressources activées sans activité',
        ];
    }
'''

DASHBOARD_DEFAULTS = r'''    /** @return array<string,bool> */
    private function dashboardDefaultWidgets(string $mode): array
    {
        $all = [
            '__mode_compact' => false,
            '__mode_standard' => false,
            '__mode_full' => false,
            'command_center' => true,
            'success_gauge' => true,
            'learner_funnel' => true,
            'synthesis' => true,
            'comparison' => false,
            'activity_by_day' => true,
            'top_resources' => true,
            'verb_distribution' => false,
            'enabled_without_trace' => false,
        ];
        if ($mode === 'compact') {
            $all['__mode_compact'] = true;
            $all['synthesis'] = false;
            $all['activity_by_day'] = false;
            $all['top_resources'] = false;
            return $all;
        }
        if ($mode === 'full') {
            foreach ($all as $key => $value) {
                if (strpos($key, '__mode_') !== 0) { $all[$key] = true; }
            }
            $all['__mode_full'] = true;
            return $all;
        }
        $all['__mode_standard'] = true;
        return $all;
    }
'''

TEXTAREA_HELPERS = r'''    private function textareaRow(string $l, string $n, string $v, string $h, int $rows = 5): string
    {
        return '<tr><td><label for="' . $this->esc($n) . '">' . $this->esc($l) . '</label></td><td><textarea id="' . $this->esc($n) . '" name="' . $this->esc($n) . '" rows="' . $this->esc((string) max(2, $rows)) . '" class="form-control" style="width:100%;max-width:980px;font-family:monospace">' . $this->esc($v) . '</textarea><div class="small">' . $this->esc($h) . '</div></td></tr>';
    }

    private function redirectToConfigureAnchor(string $anchor): void
    {
        $url = $this->ctrl->getLinkTarget($this, 'configure') . '#' . rawurlencode($anchor);
        if (is_object($this->ctrl) && method_exists($this->ctrl, 'redirectToURL')) { $this->ctrl->redirectToURL($url); return; }
        header('Location: ' . $url);
        exit;
    }

'''


def patch_config(content):
    if "pilotage_course_ref_ids" in content or "getAiSystemPrompt" in content:
        fail("V0.28.1 semble déjà appliquée dans Config")
    return replace_once(content, "    private function yesNo(string $key): string\n", CONFIG_METHODS + "    private function yesNo(string $key): string\n", "insert config methods")


def patch_ai(content):
    if "prompt système configurable" in content:
        fail("V0.28.1 semble déjà appliquée dans CourseAiAnalyzer")
    return replace_method(content, "systemPrompt", AI_SYSTEM_PROMPT_METHOD)


def patch_bridge(content):
    if "isPilotageEnabledForCourse" in content:
        fail("V0.28.1 semble déjà appliquée dans bridge")
    content = replace_once(content, "            'can_manage' => $courseRefId > 0 && $this->canManageCourse($courseRefId),\n", "            'can_manage' => $courseRefId > 0 && $this->canManageCourse($courseRefId),\n            'pilotage_enabled_for_course' => $courseRefId > 0 && $this->isPilotageEnabledForCourse($courseRefId),\n", "bridge context")
    return replace_once(content, "    public function detectCourseRefId(): int\n", BRIDGE_METHODS + "    public function detectCourseRefId(): int\n", "bridge methods")


def patch_uihook(content):
    if "pilotage_enabled_for_course" in content:
        fail("V0.28.1 semble déjà appliquée dans UIHook")
    content = content.replace("empty($context['can_manage'])) {", "empty($context['can_manage']) || empty($context['pilotage_enabled_for_course'])) {")
    content = content.replace("empty($context['can_manage'])) { return; }", "empty($context['can_manage']) || empty($context['pilotage_enabled_for_course'])) { return; }")
    if "pilotage_enabled_for_course" not in content:
        fail("patch UIHook non appliqué")
    return content


def patch_screen(content):
    if "ITXEB V0.28.1 dashboard without analysis duplication" in content:
        fail("V0.28.1 semble déjà appliquée dans CourseUIScreen")
    content = replace_method(content, "renderDashboard", RENDER_DASHBOARD)
    content = replace_method(content, "saveDashboardPreferences", SAVE_DASHBOARD)
    content = replace_method(content, "dashboardWidgetDefinitions", DASHBOARD_DEFS)
    content = replace_method(content, "dashboardDefaultWidgets", DASHBOARD_DEFAULTS)
    content = content.replace("Analyse formateur", "Analyse des résultats", 1)
    content = content.replace(" . $this->renderPedagogicalSynthesis($dashboard, $course) . $this->renderResourceSignalMatrix($dashboard)", " . $this->renderResourceSignalMatrix($dashboard)")
    content = content.replace("Le réglage est enregistré pour ce cours et s’applique dans Tableau de bord et Analyse.", "Le réglage est enregistré pour ce cours et s’applique au Tableau de bord.")
    content = content.replace("<section class=\"itxeb-cui-section\"><h2>Activation xAPI</h2>", "<section id=\"itxeb-course-activation\" class=\"itxeb-cui-section\"><h2>Activation xAPI</h2>")
    content = content.replace("$this->currentRequestUri()) . '\\">'\n            . '<input type=\"hidden\" name=\"itxeb_cui_cmd\" value=\"saveCourseTracking\">", "$this->currentRequestUri() . '#itxeb-course-activation') . '\\">'\n            . '<input type=\"hidden\" name=\"itxeb_cui_cmd\" value=\"saveCourseTracking\">")
    content = content.replace("<section class=\"itxeb-cui-section\"><h2>Personnalisation du tableau de bord</h2>", "<section id=\"itxeb-dashboard-preferences\" class=\"itxeb-cui-section\"><h2>Personnalisation du tableau de bord</h2>")
    content = content.replace("(string) $courseRefId])) . '\\">'\n            . '<input type=\"hidden\" name=\"itxeb_cui_cmd\" value=\"showCourseTracking\">'\n            . '<input type=\"hidden\" name=\"itxeb_dashboard_save\" value=\"1\">", "(string) $courseRefId]) . '#itxeb-dashboard-preferences') . '\\">'\n            . '<input type=\"hidden\" name=\"itxeb_cui_cmd\" value=\"showCourseTracking\">'\n            . '<input type=\"hidden\" name=\"itxeb_dashboard_save\" value=\"1\">")
    content = content.replace("<section class=\"itxeb-cui-section\"><h2>Synthèse pédagogique</h2>", "<section id=\"itxeb-synthesis-preferences\" class=\"itxeb-cui-section\"><h2>Synthèse pédagogique</h2>")
    content = content.replace("(string) $courseRefId])) . '\\">'\n            . '<input type=\"hidden\" name=\"itxeb_cui_cmd\" value=\"showCourseTracking\">'\n            . '<input type=\"hidden\" name=\"itxeb_synthesis_cards_save\" value=\"1\">", "(string) $courseRefId]) . '#itxeb-synthesis-preferences') . '\\">'\n            . '<input type=\"hidden\" name=\"itxeb_cui_cmd\" value=\"showCourseTracking\">'\n            . '<input type=\"hidden\" name=\"itxeb_synthesis_cards_save\" value=\"1\">")
    content = replace_once(content, "        if (!$this->bridge->canManageCourse($courseRefId)) {\n            $course = $this->resolver->resolveCourse($courseRefId);\n            return $this->renderShell('<div class=\"itxeb-cui-alert itxeb-cui-error\">Accès refusé : droits de gestion du cours insuffisants.</div>' . $this->renderCourseSummary($course), $courseRefId, (string) ($course['course_title'] ?? ''), $cmd);\n        }\n\n", "        if (!$this->bridge->canManageCourse($courseRefId)) {\n            $course = $this->resolver->resolveCourse($courseRefId);\n            return $this->renderShell('<div class=\"itxeb-cui-alert itxeb-cui-error\">Accès refusé : droits de gestion du cours insuffisants.</div>' . $this->renderCourseSummary($course), $courseRefId, (string) ($course['course_title'] ?? ''), $cmd);\n        }\n        if (method_exists($this->bridge, 'isPilotageEnabledForCourse') && !$this->bridge->isPilotageEnabledForCourse($courseRefId)) {\n            $course = $this->resolver->resolveCourse($courseRefId);\n            return $this->renderShell('<div class=\"itxeb-cui-alert itxeb-cui-error\">Pilotage xAPI désactivé pour ce cours dans la configuration du plugin.</div>' . $this->renderCourseSummary($course), $courseRefId, (string) ($course['course_title'] ?? ''), $cmd);\n        }\n\n", "screen pilotage restriction")
    return content


def patch_config_gui(content):
    if "pilotage_course_ref_ids" in content or "ai_system_prompt" in content:
        fail("V0.28.1 semble déjà appliquée dans ConfigGUI")
    content = replace_once(content, "$html = '<section class=\"itxeb-section\"><h2>Configuration TRAX / cron</h2>", "$html = '<section id=\"itxeb-main-config\" class=\"itxeb-section\"><h2>Configuration TRAX / cron</h2>", "config section id")
    content = replace_once(content, "$this->esc($this->ctrl->getLinkTarget($this, 'saveConfig'))", "$this->esc($this->ctrl->getLinkTarget($this, 'saveConfig') . '#itxeb-main-config')", "config form anchor")
    content = replace_once(content, "$html .= $this->passwordRow('Secret client TRAX', 'trax_password', 'Laisser vide pour conserver le secret.');\n        $html .= '<tr><th colspan=\"2\"><h3>Configuration IA V0.13</h3>", "$html .= $this->passwordRow('Secret client TRAX', 'trax_password', 'Laisser vide pour conserver le secret.');\n        $html .= '<tr><th colspan=\"2\"><h3>Activation du bouton Pilotage xAPI par cours</h3><p>Par défaut, le bouton reste disponible pour les administrateurs de cours. Activez la restriction pour limiter son affichage aux cours listés ci-dessous.</p></th></tr>';\n        $html .= $this->checkboxRow('Limiter aux cours listés', 'pilotage_course_restriction_enabled', $this->config->isPilotageCourseRestrictionEnabled(), 'Si activé, le bouton Pilotage xAPI est affiché uniquement dans les cours dont le ref_id est listé.');\n        $html .= $this->textareaRow('Cours autorisés au Pilotage xAPI', 'pilotage_course_ref_ids', $this->config->getPilotageCourseRefIdsText(), 'Saisir les ref_id des cours autorisés, séparés par espace, virgule ou retour ligne. Exemple : 1234, 5678.', 5);\n        $html .= '<tr><th colspan=\"2\"><h3>Configuration IA V0.13</h3>", "config pilotage block")
    content = replace_once(content, "$html .= $this->checkboxRow('Journaliser les appels IA', 'ai_log_enabled', $this->config->isAiLogEnabled(), 'Journal technique uniquement, sans prompt sensible ni clé API.');\n        $html .= '<tr><td>État clé API IA</td>", "$html .= $this->checkboxRow('Journaliser les appels IA', 'ai_log_enabled', $this->config->isAiLogEnabled(), 'Journal technique uniquement, sans prompt sensible ni clé API.');\n        $html .= $this->textareaRow('Prompt système IA', 'ai_system_prompt', $this->config->getAiSystemPrompt(), 'Prompt utilisé par défaut pour générer l’analyse IA. Le payload JSON reste ajouté automatiquement par le plugin.', 14);\n        $html .= '<tr><td><label for=\"ai_prompt_reset\">Remettre le prompt IA par défaut</label></td><td><label><input id=\"ai_prompt_reset\" name=\"ai_prompt_reset\" type=\"checkbox\" value=\"1\"> restaurer le prompt par défaut</label><div class=\"small\">La case remplace le texte saisi par le prompt intégré d’origine.</div></td></tr>';\n        $html .= '<tr><td>État clé API IA</td>", "config prompt block")
    content = replace_once(content, "$this->config->setAiTraceLimit((int) $this->postString('ai_trace_limit'));\n        if ($this->postString('trax_password') !== '')", "$this->config->setAiTraceLimit((int) $this->postString('ai_trace_limit'));\n        $this->config->setPilotageCourseRestrictionEnabled($this->postString('pilotage_course_restriction_enabled') === '1');\n        $this->config->setPilotageCourseRefIdsText($this->postString('pilotage_course_ref_ids'));\n        if ($this->postString('ai_prompt_reset') === '1') { $this->config->resetAiSystemPrompt(); }\n        else { $this->config->setAiSystemPrompt($this->postString('ai_system_prompt')); }\n        if ($this->postString('trax_password') !== '')", "config save fields")
    content = content.replace("$this->success('Configuration enregistrée.');\n        $this->ctrl->redirect($this, 'configure');", "$this->success('Configuration enregistrée.');\n        $this->redirectToConfigureAnchor('itxeb-main-config');")
    content = replace_once(content, "    private function inputRow(string $l, string $n, string $v, string $h): string", TEXTAREA_HELPERS + "    private function inputRow(string $l, string $n, string $v, string $h): string", "textarea helpers")
    return content


print("V0.28.1 préflight: lecture fichiers")
main_plugin = read_file(MAIN_PLUGIN)
config = read_file(CONFIG_FILE)
config_gui = read_file(CONFIG_GUI_FILE)
ai = read_file(AI_ANALYZER_FILE)
screen = read_file(SCREEN_TEMPLATE)
bridge = read_file(BRIDGE_TEMPLATE)
uihook = read_file(UIHOOK_TEMPLATE)
companion_plugin = read_file(COMPANION_PLUGIN_TEMPLATE)
live_screen = read_file(LIVE_SCREEN) if LIVE_SCREEN.is_file() else ""
live_bridge = read_file(LIVE_BRIDGE) if LIVE_BRIDGE.is_file() else ""
live_uihook = read_file(LIVE_UIHOOK) if LIVE_UIHOOK.is_file() else ""
live_companion_plugin = read_file(LIVE_COMPANION_PLUGIN) if LIVE_COMPANION_PLUGIN.is_file() else ""

print("V0.28.1 préflight: vérification base V0.27.1")
if "0.27.1-dev" not in main_plugin: fail("plugin principal pas en base attendue 0.27.1-dev")
if "0.8.47" not in companion_plugin: fail("plugin compagnon template pas en base attendue 0.8.47")
for label, content, needles in [
    ("screen", screen, ["ITXEB V0.27.1 dashboard command center", "renderRecommendedActions", "renderResourceSignalMatrix"]),
    ("config", config, ["getAiProvider", "getAiTraceLimit"]),
    ("config gui", config_gui, ["renderConfigForm", "Configuration IA V0.13"]),
    ("ai", ai, ["private function systemPrompt", "Tu es un assistant pédagogique"]),
    ("bridge", bridge, ["getCourseContext", "canManageCourse"]),
    ("uihook", uihook, ["Pilotage xAPI", "can_manage"]),
]:
    for needle in needles:
        if needle not in content: fail("base incomplète " + label + ": " + needle)

print("V0.28.1 préflight: calcul patchs mémoire")
main2 = patch_version(main_plugin, "0.28.1-dev", "plugin principal")
companion2 = patch_version(companion_plugin, "0.8.48", "plugin compagnon")
live_companion2 = patch_version(live_companion_plugin, "0.8.48", "plugin compagnon live") if live_companion_plugin else ""
config2 = patch_config(config)
config_gui2 = patch_config_gui(config_gui)
ai2 = patch_ai(ai)
screen2 = patch_screen(screen)
bridge2 = patch_bridge(bridge)
uihook2 = patch_uihook(uihook)
live_screen2 = patch_screen(live_screen) if live_screen else ""
live_bridge2 = patch_bridge(live_bridge) if live_bridge else ""
live_uihook2 = patch_uihook(live_uihook) if live_uihook else ""

print("V0.28.1 préflight: contrôles mémoire")
checks = [
    ("main", "0.28.1-dev", main2),
    ("companion", "0.8.48", companion2),
    ("dashboard", "ITXEB V0.28.1 dashboard without analysis duplication", screen2),
    ("analyse", "Analyse des résultats", screen2),
    ("modes", "les modes appliquent un vrai préréglage", screen2),
    ("synthesis widget", "'synthesis' => 'Synthèse pédagogique'", screen2),
    ("pilotage config", "pilotage_course_ref_ids", config2),
    ("prompt config", "getAiSystemPrompt", config2),
    ("prompt gui", "Prompt système IA", config_gui2),
    ("pilotage gui", "Limiter aux cours listés", config_gui2),
    ("ai analyzer", "prompt système configurable", ai2),
    ("bridge", "isPilotageEnabledForCourse", bridge2),
    ("uihook", "pilotage_enabled_for_course", uihook2),
]
for label, needle, content in checks:
    if needle not in content: fail("contrôle mémoire KO: " + label)
start, end = method_bounds(screen2, "renderDashboard")
dm = screen2[start:end]
for forbidden in ["renderRecommendedActions", "renderResourceSignalMatrix", "renderQuestionFailureHotspots"]:
    if forbidden in dm: fail("redondance restante dans Dashboard: " + forbidden)
start, end = method_bounds(screen2, "renderAnalysis")
am = screen2[start:end]
if "renderPedagogicalSynthesis" in am: fail("redondance restante dans Analyse: renderPedagogicalSynthesis")

print("V0.28.1 préflight: lint PHP mémoire")
for name, content in [("plugin", main2), ("config", config2), ("config_gui", config_gui2), ("ai", ai2), ("screen", screen2), ("bridge", bridge2), ("uihook", uihook2), ("companion", companion2)]:
    lint_php_memory(name, content)

print("V0.28.1 préflight OK: écriture fichiers")
for path, content in [(MAIN_PLUGIN, main2), (CONFIG_FILE, config2), (CONFIG_GUI_FILE, config_gui2), (AI_ANALYZER_FILE, ai2), (SCREEN_TEMPLATE, screen2), (BRIDGE_TEMPLATE, bridge2), (UIHOOK_TEMPLATE, uihook2), (COMPANION_PLUGIN_TEMPLATE, companion2)]:
    write_file(path, content)
for path, content in [(LIVE_SCREEN, live_screen2), (LIVE_BRIDGE, live_bridge2), (LIVE_UIHOOK, live_uihook2), (LIVE_COMPANION_PLUGIN, live_companion2)]:
    if path.is_file() and content:
        write_file(path, content)

print("V0.28.1 contrôle PHP fichiers écrits")
for path in [MAIN_PLUGIN, CONFIG_FILE, CONFIG_GUI_FILE, AI_ANALYZER_FILE, SCREEN_TEMPLATE, BRIDGE_TEMPLATE, UIHOOK_TEMPLATE, COMPANION_PLUGIN_TEMPLATE, LIVE_SCREEN, LIVE_BRIDGE, LIVE_UIHOOK, LIVE_COMPANION_PLUGIN]:
    lint_php_file(path)
print("V0.28.1 appliquée : dashboard/analyse séparés, modes réels, ancres enregistrer, pilotage par cours, prompt IA configurable.")
print("Versions : plugin principal 0.28.1-dev / compagnon 0.8.48")
print("Backups: " + str(BACKUP_DIR))
