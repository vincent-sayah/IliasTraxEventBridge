#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# V0.28.4 — présentation gauche/droite de la configuration plugin.
# Base attendue : V0.28.3 appliquée localement. Compatible Python 3.6.

import re
import shutil
import subprocess
import sys
import tempfile
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BACKUP_DIR = Path(tempfile.gettempdir()) / ("itxeb_v0284_backup_" + datetime.now().strftime("%Y%m%d%H%M%S"))
MAIN_PLUGIN = ROOT / "plugin.php"
CONFIG_GUI_FILE = ROOT / "classes/class.ilIliasTraxEventBridgeConfigGUI.php"


def fail(message):
    print("ERREUR: " + message, file=sys.stderr)
    sys.exit(1)


def read_file(path):
    if not path.is_file():
        fail("fichier introuvable: " + str(path))
    return path.read_text(encoding="utf-8")


def backup(path):
    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    shutil.copy2(str(path), str(BACKUP_DIR / str(path).lstrip("/").replace("/", "__")))


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


def method_exists(content, method_name):
    return ("private function " + method_name + "(") in content or ("public function " + method_name + "(") in content


def replace_method(content, method_name, new_method):
    start, end = method_bounds(content, method_name)
    return content[:start] + new_method.rstrip() + "\n" + content[end:]


def insert_before_method(content, method_name, block):
    start, _end = method_bounds(content, method_name)
    return content[:start] + block.rstrip() + "\n\n" + content[start:]


def patch_version(content, new_version):
    updated, count = re.subn(r"\$version\s*=\s*'[^']+';", "$version = '" + new_version + "';", content, count=1)
    if count != 1:
        fail("version plugin principal introuvable")
    return updated


def php_lint_content(content, name):
    temp_dir = Path(tempfile.mkdtemp(prefix="itxeb_v0284_lint_"))
    temp_file = temp_dir / (name + ".php")
    temp_file.write_text(content, encoding="utf-8")
    proc = subprocess.run(["php", "-l", str(temp_file)], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, universal_newlines=True)
    print(proc.stdout.strip())
    shutil.rmtree(str(temp_dir), ignore_errors=True)
    if proc.returncode != 0:
        fail("lint PHP mémoire KO: " + name)


def php_lint_file(path):
    proc = subprocess.run(["php", "-l", str(path)], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, universal_newlines=True)
    print(proc.stdout.strip())
    if proc.returncode != 0:
        fail("lint PHP fichier KO: " + str(path))


CONFIGURE_METHOD = r'''    private function configure(): void
    {
        $html = $this->styles() . '<div id="itxeb-config-page"><h1>IliasTraxEventBridge — configuration</h1>'
            . '<p class="itxeb-config-intro"><strong>V0.28.4 :</strong> page de configuration réorganisée avec une lecture gauche/droite, identique à l’esprit des vues Tableau de bord et Analyse.</p>'
            . $this->renderHealthCheck()
            . $this->renderState()
            . $this->renderDiagnosticsTraxCron()
            . $this->renderPilotageAccessForm()
            . $this->renderConfigForm()
            . $this->renderSendActions()
            . $this->renderAdminDashboard()
            . $this->renderDenyLogSupervision()
            . $this->renderOutbox()
            . $this->renderRecentEvents()
            . '</div>';
        $this->setContent($html);
    }
'''

RENDER_STATE_METHOD = r'''    private function renderState(): string
    {
        $html = '<section id="itxeb-state" class="itxeb-section"><h2>État</h2><table class="std itxeb-state-table"><tbody>';
        foreach ([
            'Plugin actif' => $this->config->isEnabled() ? 'oui' : 'non',
            'Mode debug' => $this->config->isDebugEnabled() ? 'oui' : 'non',
            'Génération xAPI locale' => $this->config->isLocalXapiGenerationEnabled() ? 'oui' : 'non',
            'Cron plugin' => $this->config->isCronEnabled() ? 'activé' : 'désactivé',
            'Diagnostic traces refusées' => $this->config->isDenyLogEnabled() ? 'activé' : 'désactivé',
            'Analyse IA' => $this->config->isAiEnabled() ? 'activée' : 'désactivée',
            'Clé API IA' => $this->config->getAiApiKeyStatus(),
            'Bouton Pilotage xAPI' => method_exists($this->config, 'getPilotageAccessMode') && $this->config->getPilotageAccessMode() === 'selected' ? 'limité aux cours sélectionnés' : 'tous les cours administrés',
        ] as $k => $v) {
            $html .= '<tr><td>' . $this->esc($k) . '</td><td><strong>' . $this->esc($v) . '</strong></td></tr>';
        }
        $html .= '<tr><td>Endpoint statements</td><td><code>' . $this->esc($this->config->getStatementsEndpoint()) . '</code></td></tr>';
        return $html . '</tbody></table></section>';
    }
'''

DIAGNOSTICS_METHOD = r'''    private function renderDiagnosticsTraxCron(): string
    {
        $html = '<section id="itxeb-diagnostics-trax-cron" class="itxeb-section"><h2>Diagnostics TRAX / cron</h2><div class="itxeb-section-body">'
            . '<p>Derniers résultats des tests de connexion, lecture, écriture, IA, envoi manuel et cron.</p>'
            . '<table class="std itxeb-state-table"><tbody>'
            . $this->diagRow('Dernier test connexion', $this->config->getLastTraxTestAt(), $this->config->getLastTraxTestSuccess(), $this->config->getLastTraxTestHttpStatus(), $this->config->getLastTraxTestMessage())
            . $this->diagRow('Dernier test lecture TRAX/LRS', $this->config->getLastLrsReadAt(), $this->config->getLastLrsReadSuccess(), $this->config->getLastLrsReadHttpStatus(), $this->config->getLastLrsReadMessage())
            . $this->diagRow('Dernier test écriture TRAX/LRS', $this->config->getLastLrsWriteAt(), $this->config->getLastLrsWriteSuccess(), $this->config->getLastLrsWriteHttpStatus(), $this->config->getLastLrsWriteMessage())
            . $this->diagRow('Dernier test IA', $this->config->getLastAiTestAt(), $this->config->getLastAiTestSuccess(), $this->config->getLastAiTestHttpStatus(), $this->config->getLastAiTestMessage())
            . $this->diagRow('Dernier envoi manuel', $this->config->getLastTraxSendAt(), $this->config->getLastTraxSendSuccess(), $this->config->getLastTraxSendHttpStatus(), $this->config->getLastTraxSendMessage())
            . $this->diagRow('Dernier cron', $this->config->getLastCronAt(), $this->config->getLastCronSuccess(), $this->config->getLastCronHttpStatus(), $this->config->getLastCronMessage())
            . '</tbody></table>';
        $html .= '<div class="itxeb-action-row">'
            . '<a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearLog')) . '">Vider le journal debug</a> '
            . '<a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearOutbox')) . '">Vider l’outbox xAPI locale</a>'
            . '</div>';
        $html .= '<div class="itxeb-action-row">'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testTraxConnection') . '#itxeb-diagnostics-trax-cron') . '"><button class="btn btn-default" type="submit">Tester connexion TRAX</button></form>'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testLrsRead') . '#itxeb-diagnostics-trax-cron') . '"><button class="btn btn-default" type="submit">Tester lecture TRAX/LRS</button></form>'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testLrsWrite') . '#itxeb-diagnostics-trax-cron') . '"><button class="btn btn-warning" type="submit">Créer un statement test TRAX/LRS</button></form>'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testAiConfiguration') . '#itxeb-diagnostics-trax-cron') . '"><button class="btn btn-default" type="submit">Tester configuration IA</button></form>'
            . '</div>';
        return $html . '</div></section>';
    }
'''

RENDER_CONFIG_FORM_METHOD = r'''    private function renderConfigForm(): string
    {
        $action = $this->ctrl->getLinkTarget($this, 'saveConfig');
        $html = '<form method="post" action="' . $this->esc($action . '#itxeb-config-trax') . '">';
        $html .= '<section id="itxeb-config-trax" class="itxeb-section"><h2>Configuration TRAX / cron</h2><div class="itxeb-section-body">'
            . '<p><strong>Important :</strong> cette section pilote les paramètres techniques TRAX et cron. Le diagnostic des traces refusées doit rester désactivé en exploitation courante.</p>'
            . '<table class="std itxeb-form-table"><tbody>';
        $html .= $this->checkboxRow('Activer le cron plugin', 'cron_enabled', $this->config->isCronEnabled(), 'Autorise le job cron du plugin. Le job cron ILIAS global doit aussi être activé dans les tâches cron.');
        $html .= $this->checkboxRow('Activer le diagnostic des traces refusées', 'deny_log_enabled', $this->config->isDenyLogEnabled(), 'À activer uniquement à la demande. Si activé sur une plateforme volumineuse, la table evnt_evhk_itxeb_dlog peut grossir rapidement.');
        foreach ([['Endpoint xAPI TRAX', 'trax_endpoint', $this->config->getTraxEndpoint(), 'Endpoint xAPI racine ou URL complète /statements.'], ['Identifiant client TRAX', 'trax_username', $this->config->getTraxUsername(), 'Client xAPI autorisé à écrire.'], ['Version xAPI', 'xapi_version', $this->config->getXapiVersion(), 'Recommandé : 1.0.3.'], ['Timeout HTTP', 'http_timeout', (string) $this->config->getHttpTimeout(), 'Entre 2 et 120 secondes.'], ['Taille batch', 'batch_size', (string) $this->config->getBatchSize(), 'Entre 1 et 100 statements.'], ['Max retry', 'max_retry', (string) $this->config->getMaxRetry(), 'Nombre maximum de tentatives par statement.'], ['Base URL ILIAS forcée', 'ilias_base_url', $this->config->getIliasBaseUrl(), 'Optionnel. Utilisé pour les IRIs xAPI.']] as $r) { $html .= $this->inputRow($r[0], $r[1], $r[2], $r[3]); }
        $html .= $this->passwordRow('Secret client TRAX', 'trax_password', 'Laisser vide pour conserver le secret.');
        $html .= '</tbody></table><p><button class="btn btn-primary" type="submit" name="itxeb_return_anchor" value="itxeb-config-trax" formaction="' . $this->esc($action . '#itxeb-config-trax') . '">Enregistrer TRAX / cron</button></p></div></section>';
        $html .= '<section id="itxeb-config-ia" class="itxeb-section"><h2>Configuration IA</h2><div class="itxeb-section-body">'
            . '<p>Analyse optionnelle. Le prompt système est modifiable depuis cette page ; le prompt par défaut peut être restauré à tout moment.</p>'
            . '<table class="std itxeb-form-table"><tbody>';
        $html .= $this->checkboxRow('Activer l’analyse IA', 'ai_enabled', $this->config->isAiEnabled(), 'Active les fonctions IA. Désactivé par défaut.');
        foreach ([['Fournisseur IA', 'ai_provider', $this->config->getAiProvider(), 'Exemple : vibe, mistral, passerelle interne.'], ['URL API IA', 'ai_api_url', $this->config->getAiApiUrl(), 'Exemple Mistral : https://api.mistral.ai/v1/chat/completions'], ['Modèle IA', 'ai_model', $this->config->getAiModel(), 'Exemple Mistral : mistral-small-latest'], ['Timeout IA', 'ai_timeout', (string) $this->config->getAiTimeout(), 'Entre 2 et 120 secondes.'], ['Mode anonymisation', 'ai_anonymization_mode', $this->config->getAiAnonymizationMode(), 'Valeurs autorisées : strict, pseudonymized, none. Recommandé : strict.'], ['Limite de traces IA', 'ai_trace_limit', (string) $this->config->getAiTraceLimit(), 'Nombre maximum d’éléments agrégés envoyés à l’IA, entre 1 et 1000.']] as $r) { $html .= $this->inputRow($r[0], $r[1], $r[2], $r[3]); }
        $html .= $this->checkboxRow('Journaliser les appels IA', 'ai_log_enabled', $this->config->isAiLogEnabled(), 'Journal technique uniquement, sans prompt sensible ni clé API.');
        if (method_exists($this->config, 'getAiSystemPrompt')) {
            $html .= $this->textareaRow('Prompt système IA', 'ai_system_prompt', $this->config->getAiSystemPrompt(), 'Prompt envoyé dans le message system. Modifier uniquement si la consigne pédagogique ou le format attendu doit changer.', 14);
            $html .= '<tr><td>Prompt IA par défaut</td><td><button class="btn btn-default" type="submit" name="ai_prompt_reset_default" value="1" formaction="' . $this->esc($action . '#itxeb-config-ia') . '">Remettre le prompt IA par défaut</button><div class="small">Le prompt par défaut sera restauré lors de l’enregistrement.</div></td></tr>';
        }
        $html .= '<tr><td>État clé API IA</td><td><strong>' . $this->esc($this->config->getAiApiKeyStatus()) . '</strong><div class="small">La valeur réelle de la clé n’est jamais affichée.</div></td></tr>';
        $html .= $this->passwordRow('Clé API IA', 'ai_api_key', 'Laisser vide pour conserver la clé déjà enregistrée. Saisir une nouvelle valeur remplace la clé existante.');
        $html .= '<tr><td><label for="ai_api_key_clear">Supprimer la clé API IA</label></td><td><label><input id="ai_api_key_clear" name="ai_api_key_clear" type="checkbox" value="1"> supprimer la clé enregistrée</label><div class="small">À cocher uniquement pour retirer la clé stockée dans la configuration du plugin. Si une nouvelle clé est saisie en même temps, elle sera enregistrée.</div></td></tr>';
        $html .= '</tbody></table><p><button class="btn btn-primary" type="submit" name="itxeb_return_anchor" value="itxeb-config-ia" formaction="' . $this->esc($action . '#itxeb-config-ia') . '">Enregistrer IA</button></p></div></section>';
        return $html . '</form>';
    }
'''

SAVE_CONFIG_METHOD = r'''    private function saveConfig(): void
    {
        $this->config->setCronEnabled($this->postString('cron_enabled') === '1');
        $this->config->setDenyLogEnabled($this->postString('deny_log_enabled') === '1');
        $this->config->setAiEnabled($this->postString('ai_enabled') === '1');
        $this->config->setAiLogEnabled($this->postString('ai_log_enabled') === '1');
        foreach (['TraxEndpoint' => 'trax_endpoint', 'TraxUsername' => 'trax_username', 'XapiVersion' => 'xapi_version', 'IliasBaseUrl' => 'ilias_base_url', 'AiProvider' => 'ai_provider', 'AiApiUrl' => 'ai_api_url', 'AiModel' => 'ai_model', 'AiAnonymizationMode' => 'ai_anonymization_mode'] as $m => $k) { $this->config->{'set' . $m}($this->postString($k)); }
        $this->config->setHttpTimeout((int) $this->postString('http_timeout'));
        $this->config->setBatchSize((int) $this->postString('batch_size'));
        $this->config->setMaxRetry((int) $this->postString('max_retry'));
        $this->config->setAiTimeout((int) $this->postString('ai_timeout'));
        $this->config->setAiTraceLimit((int) $this->postString('ai_trace_limit'));
        if (method_exists($this->config, 'resetAiSystemPrompt') && $this->postString('ai_prompt_reset_default') === '1') { $this->config->resetAiSystemPrompt(); }
        elseif (method_exists($this->config, 'setAiSystemPrompt')) { $this->config->setAiSystemPrompt($this->postString('ai_system_prompt')); }
        if ($this->postString('trax_password') !== '') { $this->config->setTraxPassword($this->postString('trax_password')); }
        if ($this->postString('ai_api_key_clear') === '1') { $this->config->clearStoredAiApiKey(); }
        if ($this->postString('ai_api_key') !== '') { $this->config->setStoredAiApiKey($this->postString('ai_api_key')); }
        $this->success('Configuration enregistrée.');
        $anchor = $this->postString('itxeb_return_anchor');
        if ($this->postString('ai_prompt_reset_default') === '1') { $anchor = 'itxeb-config-ia'; }
        if ($anchor === '') { $anchor = 'itxeb-config-trax'; }
        if (method_exists($this, 'redirectConfigureAnchor')) { $this->redirectConfigureAnchor($anchor); return; }
        $this->configure();
    }
'''

RENDER_COURSE_TRACKING_ACCESS_METHOD = r'''    private function renderCourseTrackingAccess(): string
    {
        // ITXEB V0.28.4 : le bloc "Ouvrir la configuration xAPI d’un cours" est retiré de la page de configuration plugin.
        return '';
    }
'''

STYLES_METHOD = r'''    private function styles(): string
    {
        return '<style>'
            . '#itxeb-config-page{max-width:none;width:100%;margin:0 0 4rem 0;padding:0}'
            . '#itxeb-config-page h1{font-size:28px;font-weight:700;margin:0 0 6px;line-height:1.2}'
            . '#itxeb-config-page .itxeb-config-intro{border:2px solid #c8d6e5;background:#f8fbff;border-radius:6px;padding:12px 14px;margin:0 0 16px}'
            . '#itxeb-config-page .itxeb-section{display:grid;grid-template-columns:260px minmax(0,1fr);column-gap:24px;row-gap:8px;border-top:1px solid #d9d9d9;padding:16px 0;margin:0;background:#fff}'
            . '#itxeb-config-page .itxeb-section>h2{grid-column:1;margin:0!important;padding:5px 0 0!important;border:0!important;font-size:16px!important;line-height:1.35;color:#333;font-weight:700}'
            . '#itxeb-config-page .itxeb-section>:not(h2){grid-column:2;min-width:0;margin-top:0}'
            . '#itxeb-config-page .itxeb-section-body{min-width:0}'
            . '#itxeb-config-page .itxeb-section p{color:#555;margin:.1rem 0 .65rem}'
            . '#itxeb-config-page table.std{width:100%;border-collapse:collapse;background:#fff;border:2px solid #c8d6e5;box-shadow:0 1px 4px rgba(0,0,0,.08)}'
            . '#itxeb-config-page table.std th,#itxeb-config-page table.std td{padding:.55rem .7rem;vertical-align:top;line-height:1.35;border:1px solid #ddd}'
            . '#itxeb-config-page table.std th{background:#f7f7f7;font-weight:700;border-bottom:2px solid #c8d6e5}'
            . '#itxeb-config-page .itxeb-state-table,#itxeb-config-page .itxeb-form-table{max-width:none}'
            . '#itxeb-config-page .itxeb-form-table td:first-child,#itxeb-config-page .itxeb-state-table td:first-child{width:260px;font-weight:700;color:#333}'
            . '#itxeb-config-page input.form-control,#itxeb-config-page textarea.form-control{max-width:980px;width:100%}'
            . '#itxeb-config-page .small{color:#666;margin-top:.25rem;line-height:1.35}'
            . '#itxeb-config-page .itxeb-summary{display:flex;flex-wrap:wrap;gap:.5rem;margin:.5rem 0 1rem}'
            . '#itxeb-config-page .itxeb-summary span{background:#f5f5f5;border:1px solid #ddd;padding:.35rem .55rem;border-radius:4px}'
            . '#itxeb-config-page .itxeb-dashboard-block{margin:.8rem 0 1.1rem}'
            . '#itxeb-config-page .itxeb-badge{display:inline-block;padding:.2rem .45rem;border-radius:3px;background:#eee;font-weight:600}'
            . '#itxeb-config-page .itxeb-badge-ok{background:#dff0d8}'
            . '#itxeb-config-page .itxeb-badge-warn{background:#fcf8e3}'
            . '#itxeb-config-page .itxeb-badge-error{background:#f2dede}'
            . '#itxeb-config-page .itxeb-badge-muted{background:#eee}'
            . '#itxeb-config-page .itxeb-health table td:nth-child(2){width:8rem;text-align:center}'
            . '#itxeb-config-page .itxeb-action-row{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:.75rem 0}'
            . '#itxeb-config-page .itxeb-action-row form{display:inline-block;margin:0}'
            . '#itxeb-config-page .itxeb-course-ref-choice{display:inline-block;margin:.1rem 0}'
            . '#itxeb-config-page pre{max-height:320px;overflow:auto;white-space:pre-wrap;word-break:break-word;overflow-wrap:anywhere}'
            . '#itxeb-config-page code{white-space:normal;word-break:break-word;overflow-wrap:anywhere}'
            . '@media(max-width:900px){#itxeb-config-page .itxeb-section{grid-template-columns:1fr;column-gap:0}#itxeb-config-page .itxeb-section>h2,#itxeb-config-page .itxeb-section>:not(h2){grid-column:1}#itxeb-config-page .itxeb-form-table td:first-child,#itxeb-config-page .itxeb-state-table td:first-child{width:auto}}'
            . '</style>';
    }
'''


def patch_config_gui(content):
    content = replace_method(content, "configure", CONFIGURE_METHOD)
    content = replace_method(content, "renderState", RENDER_STATE_METHOD)
    if method_exists(content, "renderDiagnosticsTraxCron"):
        content = replace_method(content, "renderDiagnosticsTraxCron", DIAGNOSTICS_METHOD)
    else:
        content = insert_before_method(content, "diagRow", DIAGNOSTICS_METHOD)
    content = replace_method(content, "renderConfigForm", RENDER_CONFIG_FORM_METHOD)
    content = replace_method(content, "saveConfig", SAVE_CONFIG_METHOD)
    content = replace_method(content, "renderCourseTrackingAccess", RENDER_COURSE_TRACKING_ACCESS_METHOD)
    content = replace_method(content, "styles", STYLES_METHOD)
    return content


print("V0.28.4 préflight: lecture fichiers")
main_plugin = read_file(MAIN_PLUGIN)
config_gui = read_file(CONFIG_GUI_FILE)

print("V0.28.4 préflight: vérification base")
if "0.28.3-dev" not in main_plugin and "0.28." not in main_plugin:
    fail("base inattendue côté plugin principal")
for needle in ["configure", "renderHealthCheck", "renderState", "renderPilotageAccessForm", "renderCourseTrackingAccess", "renderConfigForm", "renderSendActions", "renderAdminDashboard", "renderDenyLogSupervision", "renderOutbox", "renderRecentEvents", "saveConfig", "styles", "diagRow"]:
    method_bounds(config_gui, needle)
if "savePilotageAccess" not in config_gui or "pilotage_access_mode" not in config_gui:
    fail("V0.28.3 attendu : section Pilotage xAPI non détectée")

print("V0.28.4 préflight: calcul patchs mémoire")
main_plugin2 = patch_version(main_plugin, "0.28.4-dev")
config_gui2 = patch_config_gui(config_gui)

print("V0.28.4 préflight: contrôles mémoire")
if "0.28.4-dev" not in main_plugin2:
    fail("version 0.28.4-dev absente")
for needle in ["IliasTraxEventBridge — configuration", "renderDiagnosticsTraxCron", "Bouton Pilotage xAPI dans les cours", "Configuration TRAX / cron", "Configuration IA", "grid-template-columns:260px minmax(0,1fr)", "le bloc \"Ouvrir la configuration xAPI d’un cours\" est retiré"]:
    if needle not in config_gui2:
        fail("contrôle mémoire KO: " + needle)
if "setPilotageCourseRefIdsRaw($this->postString('pilotage_course_ref_ids'))" in config_gui2:
    fail("saveConfig écrase encore pilotage_course_ref_ids")

print("V0.28.4 préflight: lint PHP mémoire")
php_lint_content(config_gui2, "ConfigGUI")
php_lint_content(main_plugin2, "plugin")

print("V0.28.4 préflight OK: écriture fichiers")
write_file(MAIN_PLUGIN, main_plugin2)
write_file(CONFIG_GUI_FILE, config_gui2)

print("V0.28.4 contrôle PHP fichiers écrits")
php_lint_file(MAIN_PLUGIN)
php_lint_file(CONFIG_GUI_FILE)

print("V0.28.4 appliquée : configuration plugin réorganisée en grille gauche/droite et bloc ancien supprimé.")
print("Version : plugin principal 0.28.4-dev")
print("Backups: " + str(BACKUP_DIR))
