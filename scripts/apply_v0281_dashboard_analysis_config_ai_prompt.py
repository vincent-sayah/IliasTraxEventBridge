#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# V0.28.2 — correction V0.28.1 et application complète.
#
# Objectifs :
# - séparer clairement Tableau de bord et Analyse ;
# - rendre les modes Compact / Standard / Complet réellement différents ;
# - conserver la position de page après les boutons Enregistrer grâce aux ancres ;
# - restreindre le bouton Pilotage xAPI aux cours déclarés dans la configuration plugin ;
# - rendre le prompt système IA configurable avec retour au prompt par défaut.
#
# Base attendue : V0.27.1 stable.
# Compatible Python 3.6.

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
BACKUP_DIR = Path(tempfile.gettempdir()) / ("itxeb_v0282_backup_" + datetime.now().strftime("%Y%m%d%H%M%S"))

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


def replace_once(content, old, new, label):
    count = content.count(old)
    if count != 1:
        fail(label + " : remplacement attendu 1 fois, trouvé " + str(count))
    return content.replace(old, new, 1)


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
    i = brace
    while i < len(content):
        ch = content[i]
        if quote:
            if escaped:
                escaped = False
            elif ch == "\\":
                escaped = True
            elif ch == quote:
                quote = ""
            i += 1
            continue
        if ch == "'" or ch == '"':
            quote = ch
            i += 1
            continue
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                return start, i + 1
        i += 1
    fail("fin de méthode introuvable: " + method_name)


def replace_method(content, method_name, new_method):
    start, end = method_bounds(content, method_name)
    return content[:start] + new_method.rstrip() + "\n" + content[end:]


def insert_before(content, marker, addition, label):
    count = content.count(marker)
    if count != 1:
        fail(label + " : marqueur attendu 1 fois, trouvé " + str(count))
    return content.replace(marker, addition.rstrip() + "\n\n" + marker, 1)


def php_lint_content(content, name):
    tmpdir = Path(tempfile.mkdtemp(prefix="itxeb_v0282_lint_"))
    path = tmpdir / name
    path.write_text(content, encoding="utf-8")
    result = subprocess.run(["php", "-l", str(path)], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, universal_newlines=True)
    print(result.stdout.strip())
    if result.returncode != 0:
        fail("lint PHP mémoire échoué: " + name)


def php_lint_file(path):
    if not path.is_file():
        return
    result = subprocess.run(["php", "-l", str(path)], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, universal_newlines=True)
    print(result.stdout.strip())
    if result.returncode != 0:
        fail("lint PHP fichier échoué: " + str(path))


def bump_plugin_version(content):
    content = re.sub(r"\$version\s*=\s*'0\.27\.1-dev';", "$version = '0.28.2-dev';", content)
    content = re.sub(r'\$version\s*=\s*"0\.27\.1-dev";', '$version = "0.28.2-dev";', content)
    if "0.28.2-dev" not in content:
        fail("version plugin principal non mise à jour")
    return content


def bump_companion_version(content):
    content = re.sub(r"\$version\s*=\s*'0\.8\.47';", "$version = '0.8.49';", content)
    content = re.sub(r'\$version\s*=\s*"0\.8\.47";', '$version = "0.8.49";', content)
    if "0.8.49" not in content:
        fail("version plugin compagnon non mise à jour")
    return content


CONFIG_METHODS = r'''
    /** @return array<int,int> */
    public function getPilotageCourseRefIds(): array
    {
        $raw = $this->getPilotageCourseRefIdsRaw();
        if ($raw === '') {
            return [];
        }

        $ids = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $part) {
            $id = (int) trim((string) $part);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        ksort($ids);
        return array_values($ids);
    }

    public function getPilotageCourseRefIdsRaw(): string
    {
        return trim($this->get('pilotage_course_ref_ids', ''));
    }

    public function setPilotageCourseRefIdsRaw(string $raw): void
    {
        $ids = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $part) {
            $id = (int) trim((string) $part);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        ksort($ids);
        $this->set('pilotage_course_ref_ids', implode("\n", array_values($ids)));
    }

    public function isPilotageEnabledForCourse(int $courseRefId): bool
    {
        if ($courseRefId <= 0) {
            return false;
        }
        $ids = array_fill_keys($this->getPilotageCourseRefIds(), true);
        return isset($ids[$courseRefId]);
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
        $stored = trim($this->get('ai_system_prompt', ''));
        return $stored !== '' ? $stored : $this->getDefaultAiSystemPrompt();
    }

    public function setAiSystemPrompt(string $prompt): void
    {
        $this->set('ai_system_prompt', trim($prompt));
    }

    public function resetAiSystemPrompt(): void
    {
        $this->set('ai_system_prompt', '');
    }
'''

CONFIG_GUI_RENDER_CONFIG_FORM = r'''
    private function renderConfigForm(): string
    {
        $html = '<section id="itxeb-config-form" class="itxeb-section"><h2>Configuration TRAX / cron</h2><p><strong>Important :</strong> cette section pilote les paramètres techniques. Le diagnostic des traces refusées doit rester désactivé en exploitation courante et être activé uniquement pendant une analyse ciblée.</p>';
        $html .= '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'saveConfig') . '#itxeb-config-form') . '"><table class="std itxeb-form-table">';
        $html .= $this->checkboxRow('Activer le cron plugin', 'cron_enabled', $this->config->isCronEnabled(), 'Autorise le job cron du plugin. Le job cron ILIAS global doit aussi être activé dans les tâches cron.');
        $html .= $this->checkboxRow('Activer le diagnostic des traces refusées', 'deny_log_enabled', $this->config->isDenyLogEnabled(), 'À activer uniquement à la demande. Si activé sur une plateforme volumineuse, la table evnt_evhk_itxeb_dlog peut grossir rapidement.');
        $html .= $this->textareaRow('Cours avec bouton Pilotage xAPI', 'pilotage_course_ref_ids', $this->config->getPilotageCourseRefIdsRaw(), 'Un ref_id de cours par ligne, ou séparés par virgule. Le bouton Pilotage xAPI reste réservé aux administrateurs des cours listés. Si la liste est vide, le bouton n’est affiché dans aucun cours.', 4);
        foreach ([['Endpoint xAPI TRAX', 'trax_endpoint', $this->config->getTraxEndpoint(), 'Endpoint xAPI racine ou URL complète /statements.'], ['Identifiant client TRAX', 'trax_username', $this->config->getTraxUsername(), 'Client xAPI autorisé à écrire.'], ['Version xAPI', 'xapi_version', $this->config->getXapiVersion(), 'Recommandé : 1.0.3.'], ['Timeout HTTP', 'http_timeout', (string) $this->config->getHttpTimeout(), 'Entre 2 et 120 secondes.'], ['Taille batch', 'batch_size', (string) $this->config->getBatchSize(), 'Entre 1 et 100 statements.'], ['Max retry', 'max_retry', (string) $this->config->getMaxRetry(), 'Nombre maximum de tentatives par statement.'], ['Base URL ILIAS forcée', 'ilias_base_url', $this->config->getIliasBaseUrl(), 'Optionnel. Utilisé pour les IRIs xAPI.']] as $r) { $html .= $this->inputRow($r[0], $r[1], $r[2], $r[3]); }
        $html .= $this->passwordRow('Secret client TRAX', 'trax_password', 'Laisser vide pour conserver le secret.');
        $html .= '<tr><th colspan="2"><h3>Configuration IA</h3><p>Analyse optionnelle. Le prompt système est modifiable depuis cette page. Le prompt par défaut reste conservé dans le plugin et peut être restauré.</p></th></tr>';
        $html .= $this->checkboxRow('Activer l’analyse IA', 'ai_enabled', $this->config->isAiEnabled(), 'Active les fonctions IA. Désactivé par défaut.');
        foreach ([['Fournisseur IA', 'ai_provider', $this->config->getAiProvider(), 'Exemple : vibe, mistral, passerelle interne.'], ['URL API IA', 'ai_api_url', $this->config->getAiApiUrl(), 'Exemple Mistral : https://api.mistral.ai/v1/chat/completions'], ['Modèle IA', 'ai_model', $this->config->getAiModel(), 'Exemple Mistral : mistral-small-latest'], ['Timeout IA', 'ai_timeout', (string) $this->config->getAiTimeout(), 'Entre 2 et 120 secondes.'], ['Mode anonymisation', 'ai_anonymization_mode', $this->config->getAiAnonymizationMode(), 'Valeurs autorisées : strict, pseudonymized, none. Recommandé : strict.'], ['Limite de traces IA', 'ai_trace_limit', (string) $this->config->getAiTraceLimit(), 'Nombre maximum d’éléments agrégés envoyés à l’IA, entre 1 et 1000.']] as $r) { $html .= $this->inputRow($r[0], $r[1], $r[2], $r[3]); }
        $html .= $this->checkboxRow('Journaliser les appels IA', 'ai_log_enabled', $this->config->isAiLogEnabled(), 'Journal technique uniquement, sans prompt sensible ni clé API.');
        $html .= $this->textareaRow('Prompt système IA', 'ai_system_prompt', $this->config->getAiSystemPrompt(), 'Prompt envoyé dans le message system. Modifier uniquement si la consigne pédagogique ou le format attendu doit changer.', 14);
        $html .= '<tr><td>Prompt IA par défaut</td><td><button class="btn btn-default" type="submit" name="ai_prompt_reset_default" value="1">Remettre le prompt IA par défaut</button><div class="small">Le prompt par défaut sera restauré lors de l’enregistrement.</div></td></tr>';
        $html .= '<tr><td>État clé API IA</td><td><strong>' . $this->esc($this->config->getAiApiKeyStatus()) . '</strong><div class="small">La valeur réelle de la clé n’est jamais affichée.</div></td></tr>';
        $html .= $this->passwordRow('Clé API IA', 'ai_api_key', 'Laisser vide pour conserver la clé déjà enregistrée. Saisir une nouvelle valeur remplace la clé existante.');
        $html .= '<tr><td><label for="ai_api_key_clear">Supprimer la clé API IA</label></td><td><label><input id="ai_api_key_clear" name="ai_api_key_clear" type="checkbox" value="1"> supprimer la clé enregistrée</label><div class="small">À cocher uniquement pour retirer la clé stockée dans la configuration du plugin. Si une nouvelle clé est saisie en même temps, elle sera enregistrée.</div></td></tr>';
        return $html . '</table><p><button class="btn btn-primary" type="submit">Enregistrer</button></p></form>'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testTraxConnection')) . '"><p><button class="btn btn-default" type="submit">Tester connexion TRAX</button></p></form>'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testLrsRead')) . '"><p><button class="btn btn-default" type="submit">Tester lecture TRAX/LRS</button> <span class="small">Effectue uniquement un <code>GET /statements?limit=1</code>, sans créer de trace.</span></p></form>'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testLrsWrite')) . '"><p><button class="btn btn-warning" type="submit">Créer un statement test TRAX/LRS</button> <span class="small"><strong>Attention :</strong> crée volontairement un statement xAPI de diagnostic dans TRAX/LRS.</span></p></form>'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testAiConfiguration')) . '"><p><button class="btn btn-default" type="submit">Tester configuration IA</button> <span class="small">Contrôle la configuration locale sans envoyer de traces xAPI à l’IA.</span></p></form></section>';
    }
'''

CONFIG_GUI_SAVE_CONFIG = r'''
    private function saveConfig(): void
    {
        $this->config->setCronEnabled($this->postString('cron_enabled') === '1');
        $this->config->setDenyLogEnabled($this->postString('deny_log_enabled') === '1');
        $this->config->setAiEnabled($this->postString('ai_enabled') === '1');
        $this->config->setAiLogEnabled($this->postString('ai_log_enabled') === '1');
        $this->config->setPilotageCourseRefIdsRaw($this->postString('pilotage_course_ref_ids'));
        foreach (['TraxEndpoint' => 'trax_endpoint', 'TraxUsername' => 'trax_username', 'XapiVersion' => 'xapi_version', 'IliasBaseUrl' => 'ilias_base_url', 'AiProvider' => 'ai_provider', 'AiApiUrl' => 'ai_api_url', 'AiModel' => 'ai_model', 'AiAnonymizationMode' => 'ai_anonymization_mode'] as $m => $k) { $this->config->{'set' . $m}($this->postString($k)); }
        $this->config->setHttpTimeout((int) $this->postString('http_timeout'));
        $this->config->setBatchSize((int) $this->postString('batch_size'));
        $this->config->setMaxRetry((int) $this->postString('max_retry'));
        $this->config->setAiTimeout((int) $this->postString('ai_timeout'));
        $this->config->setAiTraceLimit((int) $this->postString('ai_trace_limit'));
        if ($this->postString('ai_prompt_reset_default') === '1') {
            $this->config->resetAiSystemPrompt();
        } else {
            $this->config->setAiSystemPrompt($this->postString('ai_system_prompt'));
        }
        if ($this->postString('trax_password') !== '') { $this->config->setTraxPassword($this->postString('trax_password')); }
        if ($this->postString('ai_api_key_clear') === '1') { $this->config->clearStoredAiApiKey(); }
        if ($this->postString('ai_api_key') !== '') { $this->config->setStoredAiApiKey($this->postString('ai_api_key')); }
        $this->success('Configuration enregistrée.');
        $this->configure();
    }
'''

TEXTAREA_ROW_METHOD = r'''
    private function textareaRow(string $l, string $n, string $v, string $h, int $rows = 6): string
    {
        return '<tr><td><label for="' . $this->esc($n) . '">' . $this->esc($l) . '</label></td><td><textarea id="' . $this->esc($n) . '" name="' . $this->esc($n) . '" rows="' . $this->esc((string) max(2, $rows)) . '" class="form-control" style="width:100%;max-width:980px">' . $this->esc($v) . '</textarea><div class="small">' . $this->esc($h) . '</div></td></tr>';
    }
'''

SCREEN_SAVE_DASHBOARD = r'''
    private function saveDashboardPreferences(array $course): void
    {
        // ITXEB V0.28.2 dashboard presets: les modes appliquent un vrai préréglage.
        $requestedMode = $this->postString('dashboard_display_mode');
        if (!in_array($requestedMode, ['compact', 'standard', 'full'], true)) {
            $requestedMode = 'standard';
        }

        $widgets = $this->dashboardDefaultWidgets($requestedMode);
        $this->repository->setDashboardWidgets((int) ($course['course_ref_id'] ?? 0), (int) ($course['course_obj_id'] ?? 0), $widgets, $this->getCurrentUserId());
        $this->message = 'Affichage du tableau de bord enregistré : mode ' . $this->dashboardModeLabel($requestedMode) . '.';
        $this->messageType = 'success';
    }
'''

SCREEN_RENDER_CONFIG_FORM = r'''
    private function renderConfigForm(array $course): string
    {
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        return '<section id="itxeb-course-activation" class="itxeb-cui-section"><h2>Activation xAPI</h2>'
            . '<form method="post" action="' . $this->esc($this->currentRequestUri() . '#itxeb-course-activation') . '">'
            . '<input type="hidden" name="itxeb_cui_cmd" value="saveCourseTracking">'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
            . '<p><label><input type="checkbox" name="course_enabled" value="1"' . (!empty($course['course_enabled']) ? ' checked="checked"' : '') . '> Activer les traces xAPI pour ce cours</label></p>'
            . $this->renderResourcesTable($course)
            . '<p><button class="btn btn-primary" type="submit">Enregistrer la configuration xAPI</button></p>'
            . '</form></section>';
    }
'''

SCREEN_RENDER_DASHBOARD_PREFS = r'''
    private function renderDashboardPreferencesForm(array $course): string
    {
        // ITXEB V0.28.2 dashboard display modes.
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $mode = $this->dashboardDisplayMode($courseRefId);
        $html = '<section id="itxeb-dashboard-preferences" class="itxeb-cui-section"><h2>Personnalisation du tableau de bord</h2>'
            . '<p>Choisir un mode de lecture. Chaque mode applique maintenant un vrai préréglage de blocs visibles.</p>'
            . '<div class="itxeb-cui-alert"><strong>Compact :</strong> état global, réussite du cours, entonnoir pédagogique.<br>'
            . '<strong>Standard :</strong> compact + synthèse pédagogique, activité, top ressources.<br>'
            . '<strong>Complet :</strong> standard + comparaison, actions xAPI, ressources sans activité.</div>'
            . '<form method="post" action="' . $this->esc($this->currentUrlWith(['itxeb_cui_cmd' => 'showCourseTracking', 'itxeb_course_ref_id' => (string) $courseRefId]) . '#itxeb-dashboard-preferences') . '">'
            . '<input type="hidden" name="itxeb_cui_cmd" value="showCourseTracking">'
            . '<input type="hidden" name="itxeb_dashboard_save" value="1">'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
            . '<div class="itxeb-dashboard-mode-grid">';
        foreach (['compact' => 'Compact — décision rapide', 'standard' => 'Standard — suivi formateur recommandé', 'full' => 'Complet — tous les blocs disponibles du tableau de bord'] as $modeKey => $label) {
            $html .= '<label class="itxeb-widget-choice itxeb-dashboard-mode-choice"><input type="radio" name="dashboard_display_mode" value="' . $this->esc($modeKey) . '"' . ($mode === $modeKey ? ' checked="checked"' : '') . '> <strong>' . $this->esc($label) . '</strong></label>';
        }
        return $html . '</div><p><button class="btn btn-default" type="submit">Enregistrer l’affichage du tableau de bord</button></p></form></section>';
    }
'''

SCREEN_RENDER_SYNTHESIS_PREFS = r'''
    private function renderSynthesisCardsPreferencesForm(array $course): string
    {
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $cards = $this->synthesisCards($courseRefId);
        $html = '<section id="itxeb-synthesis-cards" class="itxeb-cui-section"><h2>Synthèse pédagogique</h2>'
            . '<p>Choisir les cartes visibles dans le bloc <strong>Synthèse pédagogique</strong>. Le réglage est enregistré pour ce cours et s’applique dans le Tableau de bord.</p>'
            . '<form method="post" action="' . $this->esc($this->currentUrlWith(['itxeb_cui_cmd' => 'showCourseTracking', 'itxeb_course_ref_id' => (string) $courseRefId]) . '#itxeb-synthesis-cards') . '">'
            . '<input type="hidden" name="itxeb_cui_cmd" value="showCourseTracking">'
            . '<input type="hidden" name="itxeb_synthesis_cards_save" value="1">'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
            . '<div class="itxeb-widget-grid">';
        foreach ($this->synthesisCardDefinitions() as $key => $label) {
            $html .= '<label class="itxeb-widget-choice"><input type="checkbox" name="synthesis_cards[]" value="' . $this->esc($key) . '"' . (!empty($cards[$key]) ? ' checked="checked"' : '') . '> ' . $this->esc($label) . '</label>';
        }
        return $html . '</div><p><button class="btn btn-default" type="submit">Enregistrer la synthèse pédagogique</button></p></form></section>';
    }
'''

SCREEN_RENDER_DASHBOARD = r'''
    private function renderDashboard(array $course): string
    {
        // ITXEB V0.28.2 dashboard without analysis duplication.
        $dashboard = $this->loadDashboard($course);
        $widgets = $this->dashboardWidgets((int) ($course['course_ref_id'] ?? 0));
        $mode = $this->dashboardModeLabel($this->dashboardDisplayMode((int) ($course['course_ref_id'] ?? 0)));
        $html = '<section class="itxeb-cui-section itxeb-dashboard-v027"><h2>Tableau de bord du cours</h2><p>Vue rapide de décision pour le formateur. Les détails d’investigation sont dans l’onglet <strong>Analyse</strong>. Mode actuel : <strong>' . $this->esc($mode) . '</strong>.</p>'
            . $this->renderPeriodSelector('showCourseDashboard') . $this->renderResourceFilter($course, 'showCourseDashboard') . $this->renderAnalyticsWarning();

        if (!empty($widgets['command_center'])) {
            $html .= $this->renderDashboardCommandCenter($dashboard);
        }
        if (!empty($widgets['success_gauge'])) {
            $html .= $this->renderCourseSuccessGauge($dashboard);
        }
        if (!empty($widgets['learner_funnel'])) {
            $html .= $this->renderLearnerFunnel($dashboard);
        }
        if (!empty($widgets['pedagogical_synthesis'])) {
            $html .= $this->renderPedagogicalSynthesis($dashboard, $course);
        }
        if (!empty($widgets['comparison'])) {
            $html .= $this->renderPeriodComparison($course);
        }
        if (!empty($widgets['activity_by_day']) || !empty($widgets['top_resources'])) {
            $html .= $this->renderDashboardActivityTopLayout($dashboard, !empty($widgets['activity_by_day']), !empty($widgets['top_resources']));
        }
        if (!empty($widgets['verb_distribution'])) {
            $html .= $this->renderVerbDistribution($dashboard);
        }
        if (!empty($widgets['enabled_without_trace'])) {
            $html .= $this->renderEnabledWithoutTraceResources($dashboard);
        }
        return $html . '</section>';
    }
'''

SCREEN_RENDER_ANALYSIS = r'''
    private function renderAnalysis(array $course): string
    {
        // ITXEB V0.28.2 analysis page: analyse des résultats sans doublon dashboard.
        $dashboard = $this->loadDashboard($course);
        $resources = is_array($dashboard['by_resource'] ?? null) ? $dashboard['by_resource'] : [];
        $html = '<section class="itxeb-cui-section itxeb-trainer-page"><h2>Analyse des résultats</h2><div style="border:2px solid #c8d6e5;background:#f8fbff;border-radius:6px;padding:12px 14px;margin:10px 0 14px"><strong>Mode d’emploi rapide</strong><ul style="margin:8px 0 0 18px"><li>Choisir la période de suivi.</li><li>Lire les actions recommandées, les signaux ressources et les questions à fort taux d’échec.</li><li>Utiliser l’onglet Analyse IA pour générer ou comparer les synthèses IA.</li></ul></div><p style="color:#555">Vue d’investigation : ressources utilisées, ressources à surveiller, questions problématiques, apprenants à accompagner et médias MediaCast vus.</p>' . $this->renderPeriodSelector('showCourseAnalysis') . $this->renderResourceFilter($course, 'showCourseAnalysis') . $this->renderAnalyticsWarning() . $this->renderTrainerActionSummary($dashboard) . $this->renderRecommendedActions($dashboard, $course) . $this->renderResourceSignalMatrix($dashboard) . ($this->shouldRenderQuestionFailureHotspots($course) ? $this->renderQuestionFailureHotspots($dashboard, $course) : '') . $this->renderMediaCastMediaDashboard($dashboard);
        if (count($resources) === 0) {
            return $html . '<p><em>Aucune ressource traçable détectée.</em></p></section>';
        }
        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-cui-analysis-table"><thead><tr><th>Statut</th><th>Raison</th><th>Ressource</th><th>Type</th><th>xAPI</th><th>Traces</th><th>Apprenants</th><th>Dernière trace</th><th>Score moyen</th><th>Tests</th><th>Taux échec</th></tr></thead><tbody>';
        foreach ($resources as $stats) {
            $testText = (int) ($stats['test_attempts'] ?? 0) > 0 ? (string) ($stats['test_passed'] ?? 0) . ' réussis / ' . (string) ($stats['test_failed'] ?? 0) . ' échoués' : '-';
            $score = $stats['avg_score_raw'] === null ? '-' : (string) $stats['avg_score_raw'] . ' %';
            $failureRate = is_numeric($stats['failure_rate'] ?? null) ? (string) $stats['failure_rate'] . ' %' : '-';
            $status = (string) ($stats['pedagogical_status'] ?? '');
            $label = (string) ($stats['pedagogical_label'] ?? ($stats['signal'] ?? ''));
            $reason = (string) ($stats['pedagogical_reason'] ?? '');
            $html .= '<tr><td><span class="itxeb-pedagogy-badge ' . $this->pedagogicalBadgeClass($status) . '">' . $this->esc($label) . '</span></td>'
                . '<td><small>' . $this->esc($reason) . '</small></td>'
                . '<td><strong>' . $this->esc((string) ($stats['title'] ?? '')) . '</strong><br><small>' . $this->esc((string) ($stats['path'] ?? '')) . '</small></td>'
                . '<td>' . $this->esc((string) ($stats['obj_type'] ?? '')) . '<br><small>' . $this->esc((string) ($stats['resource_family'] ?? '')) . '</small></td>'
                . '<td>' . (!empty($stats['enabled']) ? 'activé' : 'désactivé') . '</td><td>' . $this->esc((string) ($stats['traces'] ?? 0)) . '</td><td>' . $this->esc((string) ($stats['learners_count'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($stats['last_at'] ?? '')) . '</td><td>' . $this->esc($score) . '</td><td>' . $this->esc($testText) . '</td><td>' . $this->esc($failureRate) . '</td></tr>';
        }
        return $html . '</tbody></table></div>' . $this->renderStrugglingLearners($dashboard) . '</section>';
    }
'''

SCREEN_DASHBOARD_WIDGET_DEFINITIONS = r'''
    private function dashboardWidgetDefinitions(): array
    {
        return [
            '__mode_compact' => 'Mode compact — décision rapide',
            '__mode_standard' => 'Mode standard — suivi formateur recommandé',
            '__mode_full' => 'Mode complet — tous les blocs disponibles',
            'command_center' => 'État global du cours',
            'success_gauge' => 'Jauge réussite du cours',
            'learner_funnel' => 'Entonnoir pédagogique',
            'pedagogical_synthesis' => 'Synthèse pédagogique',
            'comparison' => 'Comparaison entre périodes',
            'activity_by_day' => 'Activité par jour',
            'verb_distribution' => 'Actions xAPI',
            'top_resources' => 'Top ressources',
            'enabled_without_trace' => 'Ressources sans statement TRAX',
            'recommended_actions' => 'Actions recommandées — Analyse uniquement',
            'resource_matrix' => 'Matrice ressources — Analyse uniquement',
        ];
    }
'''

SCREEN_DASHBOARD_WIDGETS = r'''
    private function dashboardWidgets(int $courseRefId): array
    {
        if (!$this->repository) {
            return $this->dashboardDefaultWidgets('standard');
        }
        $stored = $this->repository->getDashboardWidgets($courseRefId);
        $mode = $this->dashboardDisplayModeFromStored($stored);
        return $this->dashboardDefaultWidgets($mode);
    }
'''

SCREEN_DASHBOARD_DEFAULTS = r'''
    private function dashboardDefaultWidgets(string $mode): array
    {
        $all = [
            '__mode_compact' => false,
            '__mode_standard' => false,
            '__mode_full' => false,
            'command_center' => false,
            'success_gauge' => false,
            'learner_funnel' => false,
            'pedagogical_synthesis' => false,
            'comparison' => false,
            'activity_by_day' => false,
            'verb_distribution' => false,
            'top_resources' => false,
            'enabled_without_trace' => false,
            'recommended_actions' => false,
            'resource_matrix' => false,
        ];

        if ($mode === 'compact') {
            $all['__mode_compact'] = true;
            $all['command_center'] = true;
            $all['success_gauge'] = true;
            $all['learner_funnel'] = true;
            return $all;
        }

        if ($mode === 'full') {
            $all['__mode_full'] = true;
            $all['command_center'] = true;
            $all['success_gauge'] = true;
            $all['learner_funnel'] = true;
            $all['pedagogical_synthesis'] = true;
            $all['comparison'] = true;
            $all['activity_by_day'] = true;
            $all['verb_distribution'] = true;
            $all['top_resources'] = true;
            $all['enabled_without_trace'] = true;
            return $all;
        }

        $all['__mode_standard'] = true;
        $all['command_center'] = true;
        $all['success_gauge'] = true;
        $all['learner_funnel'] = true;
        $all['pedagogical_synthesis'] = true;
        $all['activity_by_day'] = true;
        $all['top_resources'] = true;
        return $all;
    }
'''


def patch_config(content):
    if "public function getAiSystemPrompt()" not in content:
        content = insert_before(content, "    private function yesNo(string $key): string", CONFIG_METHODS, "config methods")
    return content


def patch_config_gui(content):
    content = replace_method(content, "renderConfigForm", CONFIG_GUI_RENDER_CONFIG_FORM)
    content = replace_method(content, "saveConfig", CONFIG_GUI_SAVE_CONFIG)
    if "private function textareaRow(" not in content:
        content = insert_before(content, "    private function passwordRow(string $l, string $n, string $h): string", TEXTAREA_ROW_METHOD, "textareaRow")
    return content


def patch_ai_analyzer(content):
    new_method = r'''
    private function systemPrompt(): string
    {
        return $this->config->getAiSystemPrompt();
    }
'''
    return replace_method(content, "systemPrompt", new_method)


def patch_bridge(content):
    if "'class.ilIliasTraxEventBridgeConfig.php'," not in content:
        content = replace_once(
            content,
            "        foreach ([\n            'class.ilIliasTraxEventBridgeCourseTrackingRepository.php',",
            "        foreach ([\n            'class.ilIliasTraxEventBridgeConfig.php',\n            'class.ilIliasTraxEventBridgeCourseTrackingRepository.php',",
            "bridge require config"
        )
    if "'pilotage_enabled_for_course'" not in content:
        content = replace_once(
            content,
            "            'can_manage' => $courseRefId > 0 && $this->canManageCourse($courseRefId),\n            'main_plugin_available' => $this->isMainPluginAvailable(),",
            "            'can_manage' => $courseRefId > 0 && $this->canManageCourse($courseRefId),\n            'pilotage_enabled_for_course' => $courseRefId > 0 && $this->isPilotageEnabledForCourse($courseRefId),\n            'main_plugin_available' => $this->isMainPluginAvailable(),",
            "bridge context pilotage"
        )
    if "public function isPilotageEnabledForCourse(" not in content:
        method = r'''
    public function isPilotageEnabledForCourse(int $courseRefId): bool
    {
        if ($courseRefId <= 0) {
            return false;
        }

        try {
            if (!class_exists('ilIliasTraxEventBridgeConfig')) {
                $path = $this->mainPluginPath . '/classes/class.ilIliasTraxEventBridgeConfig.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
            if (class_exists('ilIliasTraxEventBridgeConfig')) {
                $config = new ilIliasTraxEventBridgeConfig();
                return $config->isPilotageEnabledForCourse($courseRefId);
            }
        } catch (Throwable $ignored) {
            return false;
        }

        return false;
    }
'''
        content = insert_before(content, "    public function canManageCourse(int $courseRefId): bool", method, "bridge isPilotageEnabledForCourse")
    return content


def patch_uihook(content):
    content = content.replace(
        "empty($context['can_manage'])) {",
        "empty($context['can_manage']) || empty($context['pilotage_enabled_for_course'])) {"
    )
    content = content.replace(
        "empty($context['can_manage'])) {\n            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];\n        }",
        "empty($context['can_manage']) || empty($context['pilotage_enabled_for_course'])) {\n            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];\n        }"
    )
    if "pilotage_enabled_for_course" not in content:
        fail("UIHook : condition pilotage_enabled_for_course non ajoutée")
    return content


def patch_screen(content):
    content = replace_method(content, "saveDashboardPreferences", SCREEN_SAVE_DASHBOARD)
    content = replace_method(content, "renderConfigForm", SCREEN_RENDER_CONFIG_FORM)
    content = replace_method(content, "renderDashboardPreferencesForm", SCREEN_RENDER_DASHBOARD_PREFS)
    content = replace_method(content, "renderSynthesisCardsPreferencesForm", SCREEN_RENDER_SYNTHESIS_PREFS)
    content = replace_method(content, "renderDashboard", SCREEN_RENDER_DASHBOARD)
    content = replace_method(content, "renderAnalysis", SCREEN_RENDER_ANALYSIS)
    content = replace_method(content, "dashboardWidgetDefinitions", SCREEN_DASHBOARD_WIDGET_DEFINITIONS)
    content = replace_method(content, "dashboardWidgets", SCREEN_DASHBOARD_WIDGETS)
    content = replace_method(content, "dashboardDefaultWidgets", SCREEN_DASHBOARD_DEFAULTS)
    return content


def main():
    print("V0.28.2 préflight: lecture fichiers")
    files = {
        MAIN_PLUGIN: read_file(MAIN_PLUGIN),
        CONFIG_FILE: read_file(CONFIG_FILE),
        CONFIG_GUI_FILE: read_file(CONFIG_GUI_FILE),
        AI_ANALYZER_FILE: read_file(AI_ANALYZER_FILE),
        SCREEN_TEMPLATE: read_file(SCREEN_TEMPLATE),
        BRIDGE_TEMPLATE: read_file(BRIDGE_TEMPLATE),
        UIHOOK_TEMPLATE: read_file(UIHOOK_TEMPLATE),
        COMPANION_PLUGIN_TEMPLATE: read_file(COMPANION_PLUGIN_TEMPLATE),
    }

    print("V0.28.2 préflight: vérification base V0.27.1")
    if "0.27.1-dev" not in files[MAIN_PLUGIN]:
        fail("base plugin principal inattendue: 0.27.1-dev non trouvé")
    if "0.8.47" not in files[COMPANION_PLUGIN_TEMPLATE]:
        fail("base plugin compagnon inattendue: 0.8.47 non trouvé")
    if "renderDashboardCommandCenter" not in files[SCREEN_TEMPLATE]:
        fail("base V0.27.1 dashboard command center non détectée")

    print("V0.28.2 préflight: calcul patchs mémoire")
    new_main = bump_plugin_version(files[MAIN_PLUGIN])
    new_config = patch_config(files[CONFIG_FILE])
    new_config_gui = patch_config_gui(files[CONFIG_GUI_FILE])
    new_ai = patch_ai_analyzer(files[AI_ANALYZER_FILE])
    new_screen = patch_screen(files[SCREEN_TEMPLATE])
    new_bridge = patch_bridge(files[BRIDGE_TEMPLATE])
    new_uihook = patch_uihook(files[UIHOOK_TEMPLATE])
    new_companion_plugin = bump_companion_version(files[COMPANION_PLUGIN_TEMPLATE])

    print("V0.28.2 préflight: contrôles mémoire")
    controls = [
        (new_main, ["0.28.2-dev"]),
        (new_config, ["getAiSystemPrompt", "isPilotageEnabledForCourse", "pilotage_course_ref_ids"]),
        (new_config_gui, ["Prompt système IA", "pilotage_course_ref_ids", "ai_prompt_reset_default", "#itxeb-config-form"]),
        (new_ai, ["getAiSystemPrompt"]),
        (new_screen, ["ITXEB V0.28.2", "Analyse des résultats", "itxeb-dashboard-preferences", "pedagogical_synthesis"]),
        (new_bridge, ["pilotage_enabled_for_course", "isPilotageEnabledForCourse"]),
        (new_uihook, ["pilotage_enabled_for_course"]),
        (new_companion_plugin, ["0.8.49"]),
    ]
    for text, needles in controls:
        for needle in needles:
            if needle not in text:
                fail("contrôle mémoire manquant: " + needle)

    print("V0.28.2 préflight: lint PHP mémoire")
    php_lint_content(new_main, "plugin.php")
    php_lint_content(new_config, "Config.php")
    php_lint_content(new_config_gui, "ConfigGUI.php")
    php_lint_content(new_ai, "AiAnalyzer.php")
    php_lint_content(new_screen, "CourseUIScreen.php")
    php_lint_content(new_bridge, "CourseUIBridge.php")
    php_lint_content(new_uihook, "CourseUIUIHookGUI.php")
    php_lint_content(new_companion_plugin, "companion_plugin.php")

    print("V0.28.2 préflight OK: écriture fichiers")
    write_file(MAIN_PLUGIN, new_main)
    write_file(CONFIG_FILE, new_config)
    write_file(CONFIG_GUI_FILE, new_config_gui)
    write_file(AI_ANALYZER_FILE, new_ai)
    write_file(SCREEN_TEMPLATE, new_screen)
    write_file(BRIDGE_TEMPLATE, new_bridge)
    write_file(UIHOOK_TEMPLATE, new_uihook)
    write_file(COMPANION_PLUGIN_TEMPLATE, new_companion_plugin)

    if LIVE_COMPANION.is_dir():
        if LIVE_SCREEN.is_file():
            write_file(LIVE_SCREEN, new_screen)
        if LIVE_BRIDGE.is_file():
            write_file(LIVE_BRIDGE, new_bridge)
        if LIVE_UIHOOK.is_file():
            write_file(LIVE_UIHOOK, new_uihook)
        if LIVE_COMPANION_PLUGIN.is_file():
            write_file(LIVE_COMPANION_PLUGIN, new_companion_plugin)

    print("V0.28.2 contrôle PHP fichiers écrits")
    for path in [
        MAIN_PLUGIN,
        CONFIG_FILE,
        CONFIG_GUI_FILE,
        AI_ANALYZER_FILE,
        SCREEN_TEMPLATE,
        BRIDGE_TEMPLATE,
        UIHOOK_TEMPLATE,
        COMPANION_PLUGIN_TEMPLATE,
        LIVE_SCREEN,
        LIVE_BRIDGE,
        LIVE_UIHOOK,
        LIVE_COMPANION_PLUGIN,
    ]:
        php_lint_file(path)

    print("V0.28.2 appliquée : dashboard/analyse séparés, modes réels, ancres enregistrer, pilotage par cours, prompt IA configurable.")
    print("Versions : plugin principal 0.28.2-dev / compagnon 0.8.49")
    print("Backups: " + str(BACKUP_DIR))


if __name__ == "__main__":
    main()
