#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# V0.28.3 — correction du filtrage du bouton Pilotage xAPI par cours.
#
# Objectifs :
# - ne plus confondre "ouvrir la configuration xAPI d'un cours" et
#   "autoriser le bouton Pilotage xAPI dans un cours" ;
# - ajouter une vraie section globale dans la configuration du plugin pour
#   gérer plusieurs ref_id de cours ;
# - permettre de retirer un cours autorisé en décochant sa ligne ;
# - restaurer le comportement historique par défaut : bouton visible dans tous
#   les cours pour les administrateurs de cours, sauf si le mode "sélection"
#   est choisi ;
# - garder le retour sur la même zone de page après enregistrement ;
# - rétablir l'affichage du bouton Pilotage xAPI quand le cours est autorisé.
#
# Base attendue : V0.27.1 ou V0.28.2 en cours de test.
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
BACKUP_DIR = Path(tempfile.gettempdir()) / ("itxeb_v0283_backup_" + datetime.now().strftime("%Y%m%d%H%M%S"))

MAIN_PLUGIN = ROOT / "plugin.php"
CONFIG_FILE = ROOT / "classes/class.ilIliasTraxEventBridgeConfig.php"
CONFIG_GUI_FILE = ROOT / "classes/class.ilIliasTraxEventBridgeConfigGUI.php"
BRIDGE_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIBridge.php.tpl"
UIHOOK_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl"
COMPANION_PLUGIN_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl"

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
                continue
            if ch == "\\":
                escaped = True
                continue
            if ch == quote:
                quote = ""
            continue
        if ch == "'" or ch == '"':
            quote = ch
            continue
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                return start, i + 1
    fail("fin de méthode introuvable: " + method_name)


def replace_method(content, method_name, new_method):
    start, end = method_bounds(content, method_name)
    return content[:start] + new_method.rstrip() + content[end:]


def insert_before_method(content, method_name, block):
    start, _end = method_bounds(content, method_name)
    return content[:start] + block.rstrip() + "\n\n" + content[start:]


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


def method_exists(content, method_name):
    return ("private function " + method_name + "(") in content or ("public function " + method_name + "(") in content


CONFIG_PILOTAGE_METHODS = r'''    public function getPilotageAccessMode(): string
    {
        $mode = strtolower(trim($this->get('pilotage_access_mode', 'all')));
        return in_array($mode, ['all', 'selected'], true) ? $mode : 'all';
    }

    public function setPilotageAccessMode(string $mode): void
    {
        $mode = strtolower(trim($mode));
        $this->set('pilotage_access_mode', in_array($mode, ['all', 'selected'], true) ? $mode : 'all');
    }

    /** @return array<int,int> */
    public function getPilotageCourseRefIds(): array
    {
        return $this->parseCourseRefIds($this->get('pilotage_course_ref_ids', ''));
    }

    public function getPilotageCourseRefIdsText(): string
    {
        $ids = $this->getPilotageCourseRefIds();
        return implode("\n", array_map('strval', $ids));
    }

    /** @param array<int,int> $ids */
    public function setPilotageCourseRefIds(array $ids): void
    {
        $clean = [];
        foreach ($ids as $id) {
            $value = (int) $id;
            if ($value > 0) {
                $clean[$value] = $value;
            }
        }
        $clean = array_values($clean);
        sort($clean);
        $this->set('pilotage_course_ref_ids', implode("\n", array_map('strval', $clean)));
    }

    public function setPilotageCourseRefIdsFromText(string $text): void
    {
        $this->setPilotageCourseRefIds($this->parseCourseRefIds($text));
    }

    public function isPilotageEnabledForCourse(int $courseRefId): bool
    {
        if ($courseRefId <= 0) {
            return false;
        }
        if ($this->getPilotageAccessMode() === 'all') {
            return true;
        }
        return in_array($courseRefId, $this->getPilotageCourseRefIds(), true);
    }

    /** @return array<int,int> */
    private function parseCourseRefIds(string $text): array
    {
        $ids = [];
        if (preg_match_all('/\d+/', $text, $matches)) {
            foreach ($matches[0] as $raw) {
                $value = (int) $raw;
                if ($value > 0) {
                    $ids[$value] = $value;
                }
            }
        }
        $ids = array_values($ids);
        sort($ids);
        return $ids;
    }
'''


CONFIG_GUI_PILOTAGE_METHODS = r'''    private function renderPilotageAccessForm(): string
    {
        $mode = $this->config->getPilotageAccessMode();
        $ids = $this->config->getPilotageCourseRefIds();
        $action = $this->ctrl->getLinkTarget($this, 'savePilotageAccess') . '#itxeb-pilotage-access';

        $html = '<section id="itxeb-pilotage-access" class="itxeb-section"><h2>Bouton Pilotage xAPI dans les cours</h2>'
            . '<p>Cette section décide dans quels cours le bouton <strong>Pilotage xAPI</strong> est affiché aux administrateurs du cours. Elle ne remplace pas la configuration xAPI du cours ni l’activation des ressources.</p>'
            . '<form method="post" action="' . $this->esc($action) . '"><table class="std itxeb-form-table"><tbody>'
            . '<tr><td>Mode d’affichage</td><td>'
            . '<label><input type="radio" name="pilotage_access_mode" value="all"' . ($mode === 'all' ? ' checked="checked"' : '') . '> Tous les cours où l’utilisateur est administrateur du cours</label><br>'
            . '<label><input type="radio" name="pilotage_access_mode" value="selected"' . ($mode === 'selected' ? ' checked="checked"' : '') . '> Seulement les cours listés ci-dessous</label>'
            . '<div class="small">Le contrôle des droits reste conservé : le bouton n’est jamais affiché à un utilisateur qui n’administre pas le cours.</div>'
            . '</td></tr>';

        $html .= '<tr><td>Cours actuellement autorisés</td><td>';
        if (count($ids) === 0) {
            $html .= '<em>Aucun cours listé.</em>';
        } else {
            foreach ($ids as $id) {
                $html .= '<label class="itxeb-course-ref-choice"><input type="checkbox" name="pilotage_keep_ids[]" value="' . $this->esc((string) $id) . '" checked="checked"> ref_id ' . $this->esc((string) $id) . '</label><br>';
            }
            $html .= '<div class="small">Pour retirer la fonctionnalité d’un cours, décocher son ref_id puis enregistrer.</div>';
        }
        $html .= '</td></tr>';

        $html .= '<tr><td><label for="pilotage_course_ref_ids_add">Ajouter des cours</label></td><td>'
            . '<textarea id="pilotage_course_ref_ids_add" name="pilotage_course_ref_ids_add" rows="4" class="form-control" placeholder="210&#10;211&#10;245"></textarea>'
            . '<div class="small">Saisir un ou plusieurs <code>ref_id</code> de cours, séparés par retour ligne, virgule, espace ou point-virgule.</div>'
            . '</td></tr>';

        if ($mode === 'selected' && count($ids) === 0) {
            $html .= '<tr><td>Attention</td><td><span class="itxeb-badge itxeb-badge-warn">Aucun cours autorisé</span> En mode sélection, le bouton Pilotage xAPI ne s’affiche dans aucun cours tant qu’aucun ref_id n’est enregistré.</td></tr>';
        }

        return $html . '</tbody></table><p><button class="btn btn-primary" type="submit">Enregistrer les cours autorisés</button></p></form></section>';
    }

    private function savePilotageAccess(): void
    {
        $mode = $this->postString('pilotage_access_mode');
        if (!in_array($mode, ['all', 'selected'], true)) {
            $mode = 'all';
        }

        $ids = $this->postIntArray('pilotage_keep_ids');
        $addText = $this->postString('pilotage_course_ref_ids_add');
        if (preg_match_all('/\d+/', $addText, $matches)) {
            foreach ($matches[0] as $raw) {
                $value = (int) $raw;
                if ($value > 0) {
                    $ids[] = $value;
                }
            }
        }

        $this->config->setPilotageAccessMode($mode);
        $this->config->setPilotageCourseRefIds($ids);

        $saved = $this->config->getPilotageCourseRefIds();
        if ($mode === 'selected' && count($saved) === 0) {
            $this->failure('Aucun cours autorisé : le bouton Pilotage xAPI sera masqué dans tous les cours.');
        } else {
            $this->success('Configuration du bouton Pilotage xAPI enregistrée.');
        }
        $this->redirectConfigureAnchor('itxeb-pilotage-access');
    }

    private function redirectConfigureAnchor(string $anchor): void
    {
        $anchor = preg_replace('/[^a-zA-Z0-9_-]/', '', $anchor);
        if ($anchor === '') {
            $this->ctrl->redirect($this, 'configure');
            return;
        }
        $url = $this->ctrl->getLinkTarget($this, 'configure') . '#' . $anchor;
        if (class_exists('ilUtil') && method_exists('ilUtil', 'redirect')) {
            ilUtil::redirect($url);
            return;
        }
        $this->ctrl->redirect($this, 'configure');
    }

    /** @return array<int,int> */
    private function postIntArray(string $key): array
    {
        $values = isset($_POST[$key]) && is_array($_POST[$key]) ? $_POST[$key] : [];
        $out = [];
        foreach ($values as $value) {
            if (is_scalar($value) && (int) $value > 0) {
                $out[(int) $value] = (int) $value;
            }
        }
        $out = array_values($out);
        sort($out);
        return $out;
    }
'''


BRIDGE_METHODS = r'''    private function loadMainConfigClass(): bool
    {
        if (!$this->isMainPluginAvailable()) {
            return false;
        }
        $path = $this->mainPluginPath . '/classes/class.ilIliasTraxEventBridgeConfig.php';
        if (!is_file($path)) {
            return false;
        }
        require_once $path;
        return class_exists('ilIliasTraxEventBridgeConfig');
    }

    public function isPilotageEnabledForCourse(int $courseRefId): bool
    {
        if ($courseRefId <= 0) {
            return false;
        }
        if (!$this->loadMainConfigClass()) {
            // Sécurité de compatibilité : si la classe de configuration n'est pas
            // lisible, on ne casse pas le comportement historique du bouton.
            return true;
        }
        try {
            $config = new ilIliasTraxEventBridgeConfig();
            if (method_exists($config, 'isPilotageEnabledForCourse')) {
                return $config->isPilotageEnabledForCourse($courseRefId);
            }
        } catch (Throwable $ignored) {
            return true;
        }
        return true;
    }
'''


def remove_method_if_exists(content, name):
    while method_exists(content, name):
        content = replace_method(content, name, "")
    return content


def patch_config(content):
    for name in [
        "getPilotageAccessMode", "setPilotageAccessMode", "getPilotageCourseRefIds",
        "getPilotageCourseRefIdsText", "setPilotageCourseRefIds",
        "setPilotageCourseRefIdsFromText", "isPilotageEnabledForCourse",
        "parseCourseRefIds",
    ]:
        content = remove_method_if_exists(content, name)
    return insert_before_method(content, "yesNo", CONFIG_PILOTAGE_METHODS)


def patch_config_gui(content):
    if "case 'savePilotageAccess':" not in content:
        search = "case 'saveConfig': $this->saveConfig(); break;"
        replace = "case 'saveConfig': $this->saveConfig(); break;\n            case 'savePilotageAccess': $this->savePilotageAccess(); break;"
        content = replace_once(content, search, replace, "switch savePilotageAccess")

    if "$this->renderPilotageAccessForm()" not in content:
        search = ". $this->renderCourseTrackingAccess()\n"
        replace = ". $this->renderPilotageAccessForm()\n            . $this->renderCourseTrackingAccess()\n"
        content = replace_once(content, search, replace, "configure renderPilotageAccessForm")

    content = content.replace(
        '<section class="itxeb-section"><h2>Configuration xAPI par cours</h2>',
        '<section class="itxeb-section"><h2>Ouvrir la configuration xAPI d’un cours</h2>'
    )
    content = content.replace(
        'Cette section admin reste disponible pour ouvrir directement un cours par <code>ref_id</code>.',
        'Cette section ouvre l’écran de configuration xAPI interne d’un cours par <code>ref_id</code>. Pour afficher ou masquer le bouton Pilotage xAPI dans les cours, utiliser la section précédente.'
    )

    for name in ["renderPilotageAccessForm", "savePilotageAccess", "redirectConfigureAnchor", "postIntArray"]:
        content = remove_method_if_exists(content, name)
    return insert_before_method(content, "renderCourseTrackingAccess", CONFIG_GUI_PILOTAGE_METHODS)


def patch_bridge(content):
    for name in ["loadMainConfigClass", "isPilotageEnabledForCourse"]:
        content = remove_method_if_exists(content, name)

    if "'pilotage_enabled_for_course'" in content:
        content = re.sub(
            r"'pilotage_enabled_for_course'\s*=>\s*[^,\n]+,",
            "'pilotage_enabled_for_course' => $courseRefId > 0 && $this->isPilotageEnabledForCourse($courseRefId),",
            content,
            count=1,
        )
    else:
        search = "'can_manage' => $courseRefId > 0 && $this->canManageCourse($courseRefId),"
        replace = search + "\n            'pilotage_enabled_for_course' => $courseRefId > 0 && $this->isPilotageEnabledForCourse($courseRefId),"
        content = replace_once(content, search, replace, "bridge pilotage context")

    return insert_before_method(content, "detectCourseRefId", BRIDGE_METHODS)


def patch_uihook(content):
    search1 = "if ($courseRefId <= 0 || empty($context['main_plugin_available']) || empty($context['course_tracking_classes_available']) || empty($context['can_manage'])) {"
    replace1 = "if ($courseRefId <= 0 || empty($context['main_plugin_available']) || empty($context['course_tracking_classes_available']) || empty($context['can_manage']) || empty($context['pilotage_enabled_for_course'])) {"
    content = content.replace(search1, replace1)
    if "pilotage_enabled_for_course" not in content:
        fail("uihook: condition pilotage_enabled_for_course non injectée")
    return content


def lint_php_memory(name, content):
    temp_dir = Path(tempfile.mkdtemp(prefix="itxeb_v0283_lint_"))
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


print("V0.28.3 préflight: lecture fichiers")
main_plugin = read_file(MAIN_PLUGIN)
config = read_file(CONFIG_FILE)
config_gui = read_file(CONFIG_GUI_FILE)
bridge = read_file(BRIDGE_TEMPLATE)
uihook = read_file(UIHOOK_TEMPLATE)
companion_plugin = read_file(COMPANION_PLUGIN_TEMPLATE)
live_bridge = read_file(LIVE_BRIDGE) if LIVE_BRIDGE.is_file() else ""
live_uihook = read_file(LIVE_UIHOOK) if LIVE_UIHOOK.is_file() else ""
live_companion_plugin = read_file(LIVE_COMPANION_PLUGIN) if LIVE_COMPANION_PLUGIN.is_file() else ""

print("V0.28.3 préflight: vérification base")
if "0.27.1-dev" not in main_plugin and "0.28." not in main_plugin:
    fail("base inattendue côté plugin principal")
for needle in ["renderCourseTrackingAccess", "renderConfigForm", "saveConfig"]:
    method_bounds(config_gui, needle)
for needle in ["getCourseContext", "detectCourseRefId", "canManageCourse"]:
    method_bounds(bridge, needle)
for needle in ["getHTML", "modifyGUI"]:
    method_bounds(uihook, needle)

print("V0.28.3 préflight: calcul patchs mémoire")
config2 = patch_config(config)
config_gui2 = patch_config_gui(config_gui)
bridge2 = patch_bridge(bridge)
uihook2 = patch_uihook(uihook)
main_plugin2 = patch_version(main_plugin, "0.28.3-dev", "plugin principal")
companion_plugin2 = patch_version(companion_plugin, "0.8.50", "plugin compagnon template")
live_bridge2 = patch_bridge(live_bridge) if live_bridge else ""
live_uihook2 = patch_uihook(live_uihook) if live_uihook else ""
live_companion_plugin2 = patch_version(live_companion_plugin, "0.8.50", "plugin compagnon live") if live_companion_plugin else ""

print("V0.28.3 préflight: contrôles mémoire")
checks = [
    ("config mode", "getPilotageAccessMode", config2),
    ("config selected", "pilotage_access_mode", config2),
    ("config list", "pilotage_course_ref_ids", config2),
    ("config gui form", "renderPilotageAccessForm", config_gui2),
    ("config gui save", "savePilotageAccess", config_gui2),
    ("config gui section", "Bouton Pilotage xAPI dans les cours", config_gui2),
    ("config gui keep", "pilotage_keep_ids", config_gui2),
    ("config gui add", "pilotage_course_ref_ids_add", config_gui2),
    ("bridge context", "pilotage_enabled_for_course", bridge2),
    ("bridge config", "isPilotageEnabledForCourse", bridge2),
    ("uihook condition", "pilotage_enabled_for_course", uihook2),
    ("version main", "0.28.3-dev", main_plugin2),
    ("version companion", "0.8.50", companion_plugin2),
]
for label, needle, content in checks:
    if needle not in content:
        fail("contrôle mémoire KO: " + label)

print("V0.28.3 préflight: lint PHP mémoire")
lint_php_memory("Config", config2)
lint_php_memory("ConfigGUI", config_gui2)
lint_php_memory("CourseUIBridge", bridge2)
lint_php_memory("CourseUIUIHookGUI", uihook2)
lint_php_memory("plugin", main_plugin2)
lint_php_memory("companion_plugin", companion_plugin2)

print("V0.28.3 préflight OK: écriture fichiers")
write_file(CONFIG_FILE, config2)
write_file(CONFIG_GUI_FILE, config_gui2)
write_file(BRIDGE_TEMPLATE, bridge2)
write_file(UIHOOK_TEMPLATE, uihook2)
write_file(MAIN_PLUGIN, main_plugin2)
write_file(COMPANION_PLUGIN_TEMPLATE, companion_plugin2)

if LIVE_BRIDGE.is_file() and live_bridge2:
    write_file(LIVE_BRIDGE, live_bridge2)
if LIVE_UIHOOK.is_file() and live_uihook2:
    write_file(LIVE_UIHOOK, live_uihook2)
if LIVE_COMPANION_PLUGIN.is_file() and live_companion_plugin2:
    write_file(LIVE_COMPANION_PLUGIN, live_companion_plugin2)

print("V0.28.3 contrôle PHP fichiers écrits")
for path in [CONFIG_FILE, CONFIG_GUI_FILE, BRIDGE_TEMPLATE, UIHOOK_TEMPLATE, MAIN_PLUGIN, COMPANION_PLUGIN_TEMPLATE, LIVE_BRIDGE, LIVE_UIHOOK, LIVE_COMPANION_PLUGIN]:
    lint_php_file(path)

print("V0.28.3 appliquée : correction du bouton Pilotage xAPI par cours.")
print("Versions : plugin principal 0.28.3-dev / compagnon 0.8.50")
print("Backups: " + str(BACKUP_DIR))
