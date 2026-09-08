#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
V0.26.2 — Configuration des cartes de Synthèse pédagogique.

Objectifs :
- remplacer l'icône de la carte Réussite du cours par un symbole diplôme ;
- permettre de choisir, par cours, les cartes affichées dans Synthèse pédagogique ;
- réglage disponible dans l'onglet Configuration ;
- affichage pris en compte dans Tableau de bord et Analyse.

Base attendue côté serveur : V0.26.1 déjà appliquée et fonctionnelle.
Compatible Python 3.6.
Préflight complet avant écriture.
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
BACKUP_DIR = Path(tempfile.gettempdir()) / ("itxeb_v0262_backup_" + datetime.now().strftime("%Y%m%d%H%M%S"))

REPOSITORY_FILE = ROOT / "classes/class.ilIliasTraxEventBridgeCourseTrackingRepository.php"
SCREEN_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl"
LIVE_SCREEN = LIVE_COMPANION / "classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
MAIN_PLUGIN = ROOT / "plugin.php"
COMPANION_PLUGIN_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl"
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
    needle = "private function " + method_name + "("
    start = content.find(needle)
    if start < 0:
        needle = "public function " + method_name + "("
        start = content.find(needle)
    if start < 0:
        fail("méthode introuvable: " + method_name)
    if content.find(needle, start + 1) >= 0:
        fail("méthode en double: " + method_name)
    brace = content.find("{", start)
    if brace < 0:
        fail("accolade introuvable: " + method_name)
    depth = 0
    i = brace
    while i < len(content):
        ch = content[i]
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


REPOSITORY_METHODS = r'''    public function synthesisCardsAvailable(): bool
    {
        if (!$this->courseTableExists() || !method_exists($this->db, 'tableColumnExists') || !method_exists($this->db, 'addTableColumn')) {
            return false;
        }
        if (!$this->db->tableColumnExists(self::COURSE_TABLE, 'synthesis_cards_json')) {
            $this->db->addTableColumn(self::COURSE_TABLE, 'synthesis_cards_json', [
                'type' => 'clob', 'notnull' => false,
            ]);
        }
        if (!$this->db->tableColumnExists(self::COURSE_TABLE, 'synthesis_cards_updated_at')) {
            $this->db->addTableColumn(self::COURSE_TABLE, 'synthesis_cards_updated_at', [
                'type' => 'text', 'length' => 19, 'notnull' => true, 'default' => '',
            ]);
        }
        if (!$this->db->tableColumnExists(self::COURSE_TABLE, 'synthesis_cards_updated_by')) {
            $this->db->addTableColumn(self::COURSE_TABLE, 'synthesis_cards_updated_by', [
                'type' => 'integer', 'length' => 8, 'notnull' => true, 'default' => 0,
            ]);
        }
        return $this->db->tableColumnExists(self::COURSE_TABLE, 'synthesis_cards_json');
    }

    /** @return array<string,bool> */
    public function getSynthesisCards(int $courseRefId): array
    {
        if ($courseRefId <= 0 || !$this->synthesisCardsAvailable()) {
            return [];
        }
        try {
            $set = $this->db->query(
                'SELECT synthesis_cards_json FROM ' . self::COURSE_TABLE
                . ' WHERE course_ref_id = ' . $courseRefId
            );
            $row = $this->db->fetchAssoc($set);
            $json = is_array($row) ? (string) ($row['synthesis_cards_json'] ?? '') : '';
            if (trim($json) === '') {
                return [];
            }
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                return [];
            }
            $result = [];
            foreach ($decoded as $key => $value) {
                if (is_string($key)) {
                    $result[$key] = (bool) $value;
                }
            }
            return $result;
        } catch (Throwable $ignored) {
            return [];
        }
    }

    /** @param array<string,bool> $cards */
    public function setSynthesisCards(int $courseRefId, int $courseObjId, array $cards, int $updatedBy = 0): void
    {
        if ($courseRefId <= 0 || !$this->courseTableExists()) {
            return;
        }

        if (!$this->isCourseConfigured($courseRefId)) {
            $this->setCourseEnabled($courseRefId, $courseObjId, false, $updatedBy);
        }

        if (!$this->synthesisCardsAvailable()) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $json = json_encode($cards, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            $json = '{}';
        }

        $this->db->manipulate(
            'UPDATE ' . self::COURSE_TABLE
            . ' SET synthesis_cards_json = ' . $this->db->quote($json, 'text')
            . ', synthesis_cards_updated_at = ' . $this->db->quote($now, 'text')
            . ', synthesis_cards_updated_by = ' . max(0, $updatedBy)
            . ' WHERE course_ref_id = ' . $courseRefId
        );
    }
'''


SAVE_SYNTHESIS_METHOD = r'''    /** @param array<string,mixed> $course */
    private function saveSynthesisCardsPreferences(array $course): void
    {
        $cards = [];
        $enabled = array_fill_keys($this->postStringArray('synthesis_cards'), true);
        foreach ($this->synthesisCardDefinitions() as $key => $label) {
            $cards[$key] = isset($enabled[$key]);
        }
        if ($this->repository && method_exists($this->repository, 'setSynthesisCards')) {
            $this->repository->setSynthesisCards((int) ($course['course_ref_id'] ?? 0), (int) ($course['course_obj_id'] ?? 0), $cards, $this->getCurrentUserId());
            $this->message = 'Préférences de synthèse pédagogique enregistrées.';
            $this->messageType = 'success';
        } else {
            $this->message = 'Préférences de synthèse pédagogique indisponibles.';
            $this->messageType = 'error';
        }
    }
'''


SYNTHESIS_FORM_METHOD = r'''    /** @param array<string,mixed> $course */
    private function renderSynthesisCardsPreferencesForm(array $course): string
    {
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $cards = $this->synthesisCards($courseRefId);
        $html = '<section class="itxeb-cui-section"><h2>Synthèse pédagogique</h2>'
            . '<p>Choisir les cartes visibles dans le bloc <strong>Synthèse pédagogique</strong>. Le réglage est enregistré pour ce cours et s’applique dans Tableau de bord et Analyse.</p>'
            . '<form method="post" action="' . $this->esc($this->currentUrlWith(['itxeb_cui_cmd' => 'showCourseTracking', 'itxeb_course_ref_id' => (string) $courseRefId])) . '">'
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


SYNTHESIS_METHODS = r'''    /** @return array<string,string> */
    private function synthesisCardDefinitions(): array
    {
        return [
            'course_success_rate' => 'Réussite du cours',
            'ok' => 'Ressources OK',
            'watch' => 'Ressources à surveiller',
            'critical' => 'Ressources critiques',
            'without_activity' => 'Ressources sans activité enregistrée',
            'learning_data' => 'Données d’apprentissage',
            'active_learners' => 'Apprenants actifs',
            'resources_used' => 'Ressources utilisées',
            'pages_read' => 'Lots de données lus',
            'avg_score' => 'Score moyen',
        ];
    }

    /** @return array<string,bool> */
    private function synthesisCards(int $courseRefId): array
    {
        $defaults = [];
        foreach ($this->synthesisCardDefinitions() as $key => $label) {
            $defaults[$key] = true;
        }
        if (!$this->repository || !method_exists($this->repository, 'getSynthesisCards')) {
            return $defaults;
        }
        return array_merge($defaults, $this->repository->getSynthesisCards($courseRefId));
    }
'''


PEDAGOGICAL_SYNTHESIS_METHOD = r'''    /** @param array<string,mixed> $dashboard @param array<string,mixed> $course */
    private function renderPedagogicalSynthesis(array $dashboard, array $course = []): string
    {
        // ITXEB V0.26.2 configurable synthesis cards.
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $visibleCards = $this->synthesisCards($courseRefId);
        $pedagogy = is_array($dashboard['pedagogy'] ?? null) ? $dashboard['pedagogy'] : [];
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $courseProgress = is_array($dashboard['course_progress'] ?? null) ? $dashboard['course_progress'] : [];
        $lines = is_array($pedagogy['synthesis_lines'] ?? null) ? $pedagogy['synthesis_lines'] : [];
        $html = '<div class="itxeb-pedagogy-summary itxeb-v024-synthesis"><h3>Synthèse pédagogique</h3><div class="itxeb-pedagogy-kpis itxeb-v024-synthesis-kpis">';

        if (!empty($visibleCards['course_success_rate']) && !empty($courseProgress['configured'])) {
            $html .= $this->metricCardWithIcon(
                'Réussite du cours',
                is_numeric($courseProgress['success_rate'] ?? null) ? (string) $courseProgress['success_rate'] . ' %' : '-',
                (string) ($courseProgress['hint'] ?? 'Progression ILIAS'),
                '🎓'
            );
        }
        if (!empty($visibleCards['ok'])) {
            $html .= $this->metricCardWithIcon('OK', (string) ($pedagogy['ok_count'] ?? 0), 'Ressources sans signal', '✅');
        }
        if (!empty($visibleCards['watch'])) {
            $html .= $this->metricCardWithIcon('À surveiller', (string) ($pedagogy['watch_count'] ?? 0), 'Signal faible', '⚠️');
        }
        if (!empty($visibleCards['critical'])) {
            $html .= $this->metricCardWithIcon('Critiques', (string) ($pedagogy['critical_count'] ?? 0), 'Priorité', '🚨');
        }
        if (!empty($visibleCards['without_activity'])) {
            $html .= $this->metricCardWithIcon('Sans activité enregistrée', (string) ($pedagogy['resources_without_trace'] ?? 0), 'Ressources sans activité', '🔇');
        }
        if (!empty($visibleCards['learning_data'])) {
            $html .= $this->metricCardWithIcon('Données d’apprentissage', (string) ($summary['total'] ?? 0), 'Lecture des données', '📊');
        }
        if (!empty($visibleCards['active_learners'])) {
            $html .= $this->metricCardWithIcon('Apprenants actifs', (string) ($summary['active_learners'] ?? 0), 'Comptage anonyme', '👥');
        }
        if (!empty($visibleCards['resources_used'])) {
            $html .= $this->metricCardWithIcon('Ressources utilisées', (string) ($summary['resources_with_traces'] ?? 0) . ' / ' . (string) ($summary['resources_total'] ?? 0), 'Au moins une activité enregistrée', '📚');
        }
        if (!empty($visibleCards['pages_read'])) {
            $html .= $this->metricCardWithIcon('Lots de données lus', (string) ($dashboard['pages'] ?? 0), 'lecture par lots', '📦');
        }
        if (!empty($visibleCards['avg_score'])) {
            $html .= $this->metricCardWithIcon('Score moyen', $summary['avg_score_raw'] === null ? '-' : (string) $summary['avg_score_raw'] . ' %', 'Tests', '🎯');
        }

        $html .= '</div>';
        if (count($lines) > 0) {
            $html .= '<ul class="itxeb-pedagogy-lines">';
            foreach ($lines as $line) {
                if (is_scalar($line) && trim((string) $line) !== '') {
                    $html .= '<li>' . $this->esc((string) $line) . '</li>';
                }
            }
            $html .= '</ul>';
        }
        return $html . '</div>';
    }
'''


def patch_repository(content):
    if "public function getSynthesisCards" in content:
        fail("V0.26.2 semble déjà appliquée dans CourseTrackingRepository")
    marker = "    /** @return array<int,array<string,mixed>> */\n    public function findResourceConfigs(int $courseRefId): array\n"
    if marker not in content:
        fail("point insertion getSynthesisCards introuvable")
    return content.replace(marker, REPOSITORY_METHODS.rstrip() + "\n\n" + marker, 1)


def patch_screen(content):
    if "ITXEB V0.26.2 configurable synthesis cards" in content:
        fail("V0.26.2 semble déjà appliquée dans CourseUIScreen")

    # Gestion POST dans handle().
    search = """        } elseif ($this->postString('itxeb_dashboard_save') === '1') {
            $course = $this->resolver->resolveCourse($courseRefId);
            $this->saveDashboardPreferences($course);
            $cmd = 'showCourseTracking';
        } elseif ($cmd === 'enableAllCourseTracking') {
"""
    replace = """        } elseif ($this->postString('itxeb_dashboard_save') === '1') {
            $course = $this->resolver->resolveCourse($courseRefId);
            $this->saveDashboardPreferences($course);
            $cmd = 'showCourseTracking';
        } elseif ($this->postString('itxeb_synthesis_cards_save') === '1') {
            $course = $this->resolver->resolveCourse($courseRefId);
            $this->saveSynthesisCardsPreferences($course);
            $cmd = 'showCourseTracking';
        } elseif ($cmd === 'enableAllCourseTracking') {
"""
    content = replace_once(content, search, replace, "handle save synthesis cards")

    # Méthode de sauvegarde après saveDashboardPreferences.
    marker = "    private function setAll(int $courseRefId, bool $enabled): void\n"
    if marker not in content:
        fail("point insertion saveSynthesisCardsPreferences introuvable")
    content = content.replace(marker, SAVE_SYNTHESIS_METHOD.rstrip() + "\n\n" + marker, 1)

    # Formulaire Configuration après Personnalisation du tableau de bord.
    marker = "    /** @param array<string,mixed> $course */\n    private function renderResourcesTable(array $course): string\n"
    if marker not in content:
        fail("point insertion renderSynthesisCardsPreferencesForm introuvable")
    content = content.replace(marker, SYNTHESIS_FORM_METHOD.rstrip() + "\n\n" + marker, 1)

    # Chaîne de rendu configuration.
    search = "return $this->renderCourseSummary($course) . $this->renderConfigForm($course) . $this->renderDashboardPreferencesForm($course) . $this->renderOutboxTechnicalSupervision($course) . $this->renderLrsDirectSummary($course) . $this->renderBulkActions((int) ($course['course_ref_id'] ?? 0));"
    replace = "return $this->renderCourseSummary($course) . $this->renderConfigForm($course) . $this->renderDashboardPreferencesForm($course) . $this->renderSynthesisCardsPreferencesForm($course) . $this->renderOutboxTechnicalSupervision($course) . $this->renderLrsDirectSummary($course) . $this->renderBulkActions((int) ($course['course_ref_id'] ?? 0));"
    content = replace_once(content, search, replace, "renderView ajout formulaire synthèse")

    # Appels dans Tableau de bord et Analyse.
    content = content.replace("$this->renderPedagogicalSynthesis($dashboard) .", "$this->renderPedagogicalSynthesis($dashboard, $course) .")

    # Définitions des cartes avant dashboardWidgetDefinitions.
    marker = "    /** @return array<string,string> */\n    private function dashboardWidgetDefinitions(): array\n"
    if marker not in content:
        fail("point insertion synthesisCardDefinitions introuvable")
    content = content.replace(marker, SYNTHESIS_METHODS.rstrip() + "\n\n" + marker, 1)

    # Rendu de la synthèse.
    content = replace_method(content, "renderPedagogicalSynthesis", PEDAGOGICAL_SYNTHESIS_METHOD)
    return content


def lint_php_memory(name, content):
    temp_dir = Path(tempfile.mkdtemp(prefix="itxeb_v0262_lint_"))
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


print("V0.26.2 préflight: lecture fichiers")
repository = read_file(REPOSITORY_FILE)
screen = read_file(SCREEN_TEMPLATE)
main_plugin = read_file(MAIN_PLUGIN)
companion_plugin = read_file(COMPANION_PLUGIN_TEMPLATE)
live_companion_plugin = read_file(LIVE_COMPANION_PLUGIN) if LIVE_COMPANION_PLUGIN.is_file() else ""

print("V0.26.2 préflight: vérification base V0.26.1")
if "0.26.1-dev" not in main_plugin:
    fail("plugin principal pas en base attendue 0.26.1-dev")
if "0.8.45" not in companion_plugin:
    fail("plugin compagnon template pas en base attendue 0.8.45")
for needle in ["courseProgressSummary", "course_success_rate", "Réussite du cours"]:
    if needle not in screen and needle not in read_file(ROOT / "classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php"):
        fail("base V0.26.1 incomplète, marqueur absent: " + needle)

method_bounds(repository, "setDashboardWidgets")
method_bounds(screen, "handle")
method_bounds(screen, "saveDashboardPreferences")
method_bounds(screen, "renderDashboardPreferencesForm")
method_bounds(screen, "renderResourcesTable")
method_bounds(screen, "renderPedagogicalSynthesis")
method_bounds(screen, "dashboardWidgetDefinitions")

print("V0.26.2 préflight: calcul patchs mémoire")
repository2 = patch_repository(repository)
screen2 = patch_screen(screen)
main_plugin2 = patch_version(main_plugin, "0.26.2-dev", "plugin principal")
companion_plugin2 = patch_version(companion_plugin, "0.8.46", "plugin compagnon template")
live_companion_plugin2 = patch_version(live_companion_plugin, "0.8.46", "plugin compagnon live") if live_companion_plugin else ""

print("V0.26.2 préflight: contrôles mémoire")
checks = [
    ("repo getSynthesisCards", "public function getSynthesisCards", repository2),
    ("repo setSynthesisCards", "public function setSynthesisCards", repository2),
    ("repo colonne", "synthesis_cards_json", repository2),
    ("screen save", "saveSynthesisCardsPreferences", screen2),
    ("screen form", "renderSynthesisCardsPreferencesForm", screen2),
    ("screen definitions", "synthesisCardDefinitions", screen2),
    ("screen marker", "ITXEB V0.26.2 configurable synthesis cards", screen2),
    ("screen icon", "🎓", screen2),
    ("screen course arg dashboard", "renderPedagogicalSynthesis($dashboard, $course)", screen2),
    ("version main", "0.26.2-dev", main_plugin2),
    ("version companion", "0.8.46", companion_plugin2),
]
for label, needle, content in checks:
    if needle not in content:
        fail("contrôle mémoire KO: " + label)

print("V0.26.2 préflight: lint PHP mémoire")
lint_php_memory("CourseTrackingRepository", repository2)
lint_php_memory("CourseUIScreen", screen2)
lint_php_memory("plugin", main_plugin2)
lint_php_memory("companion_plugin", companion_plugin2)

print("V0.26.2 préflight OK: écriture fichiers")
write_file(REPOSITORY_FILE, repository2)
write_file(SCREEN_TEMPLATE, screen2)
write_file(MAIN_PLUGIN, main_plugin2)
write_file(COMPANION_PLUGIN_TEMPLATE, companion_plugin2)
if LIVE_SCREEN.is_file():
    write_file(LIVE_SCREEN, screen2)
if LIVE_COMPANION_PLUGIN.is_file() and live_companion_plugin2:
    write_file(LIVE_COMPANION_PLUGIN, live_companion_plugin2)

print("V0.26.2 contrôle PHP fichiers écrits")
for path in [REPOSITORY_FILE, SCREEN_TEMPLATE, MAIN_PLUGIN, COMPANION_PLUGIN_TEMPLATE, LIVE_SCREEN, LIVE_COMPANION_PLUGIN]:
    lint_php_file(path)

print("V0.26.2 appliquée : icône diplôme et configuration des cartes de Synthèse pédagogique.")
print("Versions : plugin principal 0.26.2-dev / compagnon 0.8.46")
print("Backups: " + str(BACKUP_DIR))
