#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# V0.28.5 — déplacement des boutons de purge dans leurs blocs fonctionnels.
#
# Objectifs :
# - retirer "Vider le journal debug" et "Vider l'outbox xAPI locale" du bloc
#   Diagnostics TRAX / cron ;
# - placer "Vider l'outbox xAPI locale" dans le bloc Outbox xAPI locale ;
# - placer "Vider le journal debug" dans le bloc Derniers événements ILIAS reçus ;
# - conserver le retour sur la bonne zone de page après purge.
#
# Base attendue : V0.28.4 appliquée localement.
# Compatible Python 3.6.

import re
import shutil
import subprocess
import sys
import tempfile
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BACKUP_DIR = Path(tempfile.gettempdir()) / ("itxeb_v0285_backup_" + datetime.now().strftime("%Y%m%d%H%M%S"))
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


def replace_method(content, method_name, new_method):
    start, end = method_bounds(content, method_name)
    return content[:start] + new_method.rstrip() + "\n" + content[end:]


def patch_version(content, new_version):
    updated, count = re.subn(r"\$version\s*=\s*'[^']+';", "$version = '" + new_version + "';", content, count=1)
    if count != 1:
        fail("version plugin principal introuvable")
    return updated


def replace_case(content, old, new, label):
    if old in content:
        return content.replace(old, new, 1)
    if new in content:
        return content
    fail("case introuvable: " + label)


def php_lint_content(content, name):
    temp_dir = Path(tempfile.mkdtemp(prefix="itxeb_v0285_lint_"))
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


DIAGNOSTICS_METHOD = """    private function renderDiagnosticsTraxCron(): string
    {
        $html = '<section id="itxeb-diagnostics-trax-cron" class="itxeb-section"><h2>Diagnostics TRAX / cron</h2><div class="itxeb-section-body">'
            . '<p>Derniers résultats des tests de connexion, lecture, écriture, IA, envoi manuel et cron. Les actions de purge sont placées dans leurs blocs fonctionnels : Outbox xAPI locale et Derniers événements ILIAS reçus.</p>'
            . '<table class="std itxeb-state-table"><tbody>'
            . $this->diagRow('Dernier test connexion', $this->config->getLastTraxTestAt(), $this->config->getLastTraxTestSuccess(), $this->config->getLastTraxTestHttpStatus(), $this->config->getLastTraxTestMessage())
            . $this->diagRow('Dernier test lecture TRAX/LRS', $this->config->getLastLrsReadAt(), $this->config->getLastLrsReadSuccess(), $this->config->getLastLrsReadHttpStatus(), $this->config->getLastLrsReadMessage())
            . $this->diagRow('Dernier test écriture TRAX/LRS', $this->config->getLastLrsWriteAt(), $this->config->getLastLrsWriteSuccess(), $this->config->getLastLrsWriteHttpStatus(), $this->config->getLastLrsWriteMessage())
            . $this->diagRow('Dernier test IA', $this->config->getLastAiTestAt(), $this->config->getLastAiTestSuccess(), $this->config->getLastAiTestHttpStatus(), $this->config->getLastAiTestMessage())
            . $this->diagRow('Dernier envoi manuel', $this->config->getLastTraxSendAt(), $this->config->getLastTraxSendSuccess(), $this->config->getLastTraxSendHttpStatus(), $this->config->getLastTraxSendMessage())
            . $this->diagRow('Dernier cron', $this->config->getLastCronAt(), $this->config->getLastCronSuccess(), $this->config->getLastCronHttpStatus(), $this->config->getLastCronMessage())
            . '</tbody></table>';
        $html .= '<div class="itxeb-action-row">'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testTraxConnection') . '#itxeb-diagnostics-trax-cron') . '"><button class="btn btn-default" type="submit">Tester connexion TRAX</button></form>'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testLrsRead') . '#itxeb-diagnostics-trax-cron') . '"><button class="btn btn-default" type="submit">Tester lecture TRAX/LRS</button></form>'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testLrsWrite') . '#itxeb-diagnostics-trax-cron') . '"><button class="btn btn-warning" type="submit">Créer un statement test TRAX/LRS</button></form>'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testAiConfiguration') . '#itxeb-diagnostics-trax-cron') . '"><button class="btn btn-default" type="submit">Tester configuration IA</button></form>'
            . '</div>';
        return $html . '</div></section>';
    }
"""

RENDER_OUTBOX_METHOD = """    private function renderOutbox(): string
    {
        $rows = $this->outbox->findRecent(50);
        $html = '<section id="itxeb-outbox" class="itxeb-section"><h2>Outbox xAPI locale</h2><div class="itxeb-section-body">'
            . '<p>File technique locale des statements xAPI générés avant envoi vers TRAX/LRS.</p>'
            . '<div class="itxeb-action-row"><a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearOutbox')) . '">Vider l’outbox xAPI locale</a></div>';
        if (count($rows) === 0) {
            return $html . '<p><em>Aucun statement xAPI généré pour le moment.</em></p></div></section>';
        }
        $html .= '<div class="table-responsive"><table class="std itxeb-events"><thead><tr><th>ID / date</th><th>Statut</th><th>Retry</th><th>Verb</th><th>Objet</th><th>Erreur / statement</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $err = trim((string)($r['last_error'] ?? ''));
            $html .= '<tr><td>#' . $this->esc((string)($r['id'] ?? '')) . '<br><small>' . $this->esc((string)($r['created_at'] ?? '')) . '</small></td><td><span class="itxeb-badge ' . $this->statusBadgeClass((string)($r['status'] ?? '')) . '">' . $this->esc((string)($r['status'] ?? '')) . '</span></td><td>' . $this->esc((string)($r['retry_count'] ?? '0')) . ' / ' . $this->esc((string)($r['max_retry'] ?? $this->config->getMaxRetry())) . '</td><td><code>' . $this->esc((string)($r['verb_id'] ?? '')) . '</code></td><td>user ' . $this->esc((string)($r['user_id'] ?? '')) . '<br>ref ' . $this->esc((string)($r['ref_id'] ?? '')) . '<br>obj ' . $this->esc((string)($r['obj_id'] ?? '')) . '<br>' . $this->esc((string)($r['obj_type'] ?? '')) . '</td><td>' . ($err !== '' ? '<div>' . $this->esc($err) . '</div>' : '') . '<details><summary>Statement</summary><pre>' . $this->esc($this->formatPayload((string)($r['statement_json'] ?? ''))) . '</pre></details></td></tr>';
        }
        return $html . '</tbody></table></div></div></section>';
    }
"""

RENDER_RECENT_EVENTS_METHOD = """    private function renderRecentEvents(): string
    {
        $rows = $this->repo->findRecent(100);
        $html = '<section id="itxeb-recent-events" class="itxeb-section"><h2>Derniers événements ILIAS reçus</h2><div class="itxeb-section-body">'
            . '<p>Journal debug des derniers événements ILIAS reçus par le plugin.</p>'
            . '<div class="itxeb-action-row"><a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearLog')) . '">Vider le journal debug</a></div>';
        if (count($rows) === 0) {
            return $html . '<p><em>Aucun événement journalisé pour le moment.</em></p></div></section>';
        }
        $html .= '<div class="table-responsive"><table class="std itxeb-events"><thead><tr><th>ID / date</th><th>Événement</th><th>Objet</th><th>URI</th><th>Payload</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr><td>#' . $this->esc((string)($r['id'] ?? '')) . '<br><small>' . $this->esc((string)($r['created_at'] ?? '')) . '</small></td><td>' . $this->esc((string)($r['component'] ?? '')) . '<br><strong>' . $this->esc((string)($r['event_name'] ?? '')) . '</strong></td><td>user ' . $this->esc((string)($r['user_id'] ?? '')) . '<br>ref ' . $this->esc((string)($r['ref_id'] ?? '')) . '<br>obj ' . $this->esc((string)($r['obj_id'] ?? '')) . '<br>' . $this->esc((string)($r['obj_type'] ?? '')) . '</td><td><code>' . $this->esc((string)($r['request_uri'] ?? '')) . '</code></td><td><details><summary>Payload</summary><pre>' . $this->esc($this->formatPayload((string)($r['payload_json'] ?? ''))) . '</pre></details></td></tr>';
        }
        return $html . '</tbody></table></div></div></section>';
    }
"""


def patch_config_gui(content):
    content = replace_method(content, "renderDiagnosticsTraxCron", DIAGNOSTICS_METHOD)
    content = replace_method(content, "renderOutbox", RENDER_OUTBOX_METHOD)
    content = replace_method(content, "renderRecentEvents", RENDER_RECENT_EVENTS_METHOD)

    content = replace_case(
        content,
        "case 'clearLog': $this->repo->clear(); $this->success('Journal vidé.'); $this->ctrl->redirect($this, 'configure'); break;",
        "case 'clearLog': $this->repo->clear(); $this->success('Journal debug vidé.'); $this->redirectConfigureAnchor('itxeb-recent-events'); break;",
        "clearLog"
    )
    content = replace_case(
        content,
        "case 'clearOutbox': $this->outbox->clear(); $this->success('Outbox vidée.'); $this->ctrl->redirect($this, 'configure'); break;",
        "case 'clearOutbox': $this->outbox->clear(); $this->success('Outbox xAPI locale vidée.'); $this->redirectConfigureAnchor('itxeb-outbox'); break;",
        "clearOutbox"
    )
    return content


print("V0.28.5 préflight: lecture fichiers")
main_plugin = read_file(MAIN_PLUGIN)
config_gui = read_file(CONFIG_GUI_FILE)

print("V0.28.5 préflight: vérification base")
if "0.28.4-dev" not in main_plugin and "0.28." not in main_plugin:
    fail("base inattendue côté plugin principal")
for needle in ["renderDiagnosticsTraxCron", "renderOutbox", "renderRecentEvents", "redirectConfigureAnchor"]:
    method_bounds(config_gui, needle)

print("V0.28.5 préflight: calcul patchs mémoire")
main_plugin2 = patch_version(main_plugin, "0.28.5-dev")
config_gui2 = patch_config_gui(config_gui)

print("V0.28.5 préflight: contrôles mémoire")
if "0.28.5-dev" not in main_plugin2:
    fail("version 0.28.5-dev absente")
for needle in [
    "id=\"itxeb-outbox\"",
    "id=\"itxeb-recent-events\"",
    "Vider l’outbox xAPI locale",
    "Vider le journal debug",
    "redirectConfigureAnchor('itxeb-outbox')",
    "redirectConfigureAnchor('itxeb-recent-events')",
    "Les actions de purge sont placées dans leurs blocs fonctionnels",
]:
    if needle not in config_gui2:
        fail("contrôle mémoire KO: " + needle)

start, end = method_bounds(config_gui2, "renderDiagnosticsTraxCron")
diagnostics = config_gui2[start:end]
if "clearLog" in diagnostics or "clearOutbox" in diagnostics or "Vider le journal debug" in diagnostics or "Vider l’outbox xAPI locale" in diagnostics:
    fail("les boutons de purge sont encore présents dans Diagnostics TRAX / cron")

print("V0.28.5 préflight: lint PHP mémoire")
php_lint_content(config_gui2, "ConfigGUI")
php_lint_content(main_plugin2, "plugin")

print("V0.28.5 préflight OK: écriture fichiers")
write_file(MAIN_PLUGIN, main_plugin2)
write_file(CONFIG_GUI_FILE, config_gui2)

print("V0.28.5 contrôle PHP fichiers écrits")
php_lint_file(MAIN_PLUGIN)
php_lint_file(CONFIG_GUI_FILE)

print("V0.28.5 appliquée : boutons de purge déplacés dans leurs blocs fonctionnels.")
print("Version : plugin principal 0.28.5-dev")
print("Backups: " + str(BACKUP_DIR))
