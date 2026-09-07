#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
V0.25.5 — Affichage nominatif des apprenants dans Analyse et Expert.

Script compatible Python 3.6.
Aucune écriture n'est faite tant que tous les contrôles mémoire ne sont pas OK.
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
BACKUP_DIR = Path(tempfile.gettempdir()) / ("itxeb_v0255_backup_" + datetime.now().strftime("%Y%m%d%H%M%S"))

SUMMARY_FILE = ROOT / "classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php"
SCREEN_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl"
LIVE_SCREEN = LIVE_COMPANION / "classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
MAIN_PLUGIN = ROOT / "plugin.php"
COMPANION_PLUGIN_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl"
LIVE_COMPANION_PLUGIN = LIVE_COMPANION / "plugin.php"


def fail(message):
    print("ERREUR: " + message, file=sys.stderr)
    raise SystemExit(1)


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


def replace_once(content, search, replace, label):
    if replace in content:
        return content
    count = content.count(search)
    if count != 1:
        fail("point de remplacement introuvable ou multiple: {0} count={1}".format(label, count))
    return content.replace(search, replace, 1)


def method_bounds(content, method_name):
    needle = "private function " + method_name + "("
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


def replace_method(content, method_name, method):
    start, end = method_bounds(content, method_name)
    return content[:start] + method.rstrip() + content[end:]


def patch_version(content, new_version, label):
    updated, count = re.subn(r"\$version\s*=\s*'[^']+';", "$version = '" + new_version + "';", content, count=1)
    if count != 1:
        fail("version introuvable: " + label)
    return updated


ACTOR_DISPLAY_METHOD = '''    /** @param array<string,mixed> $statement */
    private function actorDisplayName(array $statement, string $actorKey = ''): string
    {
        $name = $statement['actor']['name'] ?? '';
        if (is_scalar($name) && trim((string) $name) !== '') {
            return trim((string) $name);
        }

        $accountName = $statement['actor']['account']['name'] ?? '';
        if (is_scalar($accountName) && trim((string) $accountName) !== '') {
            return trim((string) $accountName);
        }

        $mbox = $statement['actor']['mbox'] ?? '';
        if (is_scalar($mbox) && trim((string) $mbox) !== '') {
            $mail = preg_replace('/^mailto:/i', '', trim((string) $mbox));
            return is_string($mail) && $mail !== '' ? $mail : trim((string) $mbox);
        }

        if ($actorKey !== '') {
            if (strpos($actorKey, 'account:') === 0) {
                return substr($actorKey, 8);
            }
            if (strpos($actorKey, 'mbox:') === 0) {
                $value = substr($actorKey, 5);
                $mail = preg_replace('/^mailto:/i', '', $value);
                return is_string($mail) && $mail !== '' ? $mail : $value;
            }
            return $actorKey;
        }

        return '';
    }

'''


def patch_lrs_summary(content):
    method_bounds(content, "addStatement")
    method_bounds(content, "actorKey")

    search = """            'user_id' => $actorKey === '' ? '' : substr(sha1($actorKey), 0, 10),
            'verb_label' => $verbLabel,
"""
    replace = """            'user_id' => $actorKey === '' ? '' : substr(sha1($actorKey), 0, 10),
            'learner_identity' => $this->actorDisplayName($statement, $actorKey),
            'verb_label' => $verbLabel,
"""
    content = replace_once(content, search, replace, "ajout learner_identity dans expert_rows")

    if "private function actorDisplayName(" not in content:
        marker = """    /** @param array<string,mixed> $statement */
    private function actorKey(array $statement): string
"""
        content = replace_once(content, marker, ACTOR_DISPLAY_METHOD + marker, "insertion actorDisplayName")

    return content


STRUGGLING_METHOD = '''    private function renderStrugglingLearners(array $dashboard): string
    {
        // ITXEB V0.25.5 learner identity display py36 preflight.
        $rows = is_array($dashboard['expert_rows'] ?? null) ? $dashboard['expert_rows'] : [];
        $learners = [];

        foreach ($rows as $row) {
            if (!is_array($row) || (string) ($row['obj_type'] ?? '') !== 'tst') {
                continue;
            }

            $learnerIdentity = trim((string) ($row['learner_identity'] ?? ''));
            $technicalUserId = trim((string) ($row['user_id'] ?? ''));
            $learnerDisplay = $learnerIdentity !== '' ? $learnerIdentity : $technicalUserId;
            if ($learnerDisplay === '') {
                continue;
            }
            $learnerKey = $learnerIdentity !== '' ? 'identity:' . strtolower($learnerIdentity) : 'user:' . $technicalUserId;

            $score = is_numeric($row['score_raw'] ?? null) ? (float) $row['score_raw'] : null;
            $success = $row['success'] ?? null;
            $verbId = (string) ($row['verb_id'] ?? '');
            $failed = ($success === false) || stripos($verbId, 'failed') !== false;
            $lowScore = $score !== null && $score < 50.0;

            if (!$failed && !$lowScore) {
                continue;
            }

            if (!isset($learners[$learnerKey])) {
                $learners[$learnerKey] = [
                    'learner_identity' => $learnerDisplay,
                    'technical_user_id' => $technicalUserId,
                    'alerts' => 0,
                    'failed' => 0,
                    'low_scores' => 0,
                    'scores_total' => 0.0,
                    'scores_count' => 0,
                    'last_at' => '',
                    'resources' => [],
                ];
            }

            $learners[$learnerKey]['alerts']++;
            if ($failed) {
                $learners[$learnerKey]['failed']++;
            }
            if ($lowScore) {
                $learners[$learnerKey]['low_scores']++;
            }
            if ($score !== null) {
                $learners[$learnerKey]['scores_total'] += $score;
                $learners[$learnerKey]['scores_count']++;
            }
            $createdAt = (string) ($row['created_at'] ?? '');
            if ($createdAt !== '' && $createdAt > (string) $learners[$learnerKey]['last_at']) {
                $learners[$learnerKey]['last_at'] = $createdAt;
            }
            $title = trim((string) ($row['object_title'] ?? ''));
            if ($title !== '') {
                $learners[$learnerKey]['resources'][$title] = true;
            }
        }

        $visible = [];
        foreach ($learners as $learner) {
            if ((int) $learner['failed'] >= 2 || (int) $learner['low_scores'] >= 2 || (int) $learner['alerts'] >= 3) {
                $learner['avg_score'] = (int) $learner['scores_count'] > 0
                    ? round(((float) $learner['scores_total']) / max(1, (int) $learner['scores_count']), 1)
                    : null;
                $visible[] = $learner;
            }
        }

        usort($visible, static function (array $a, array $b): int {
            return ((int) $b['alerts'] <=> (int) $a['alerts']) ?: ((int) $b['failed'] <=> (int) $a['failed']);
        });
        $visible = array_slice($visible, 0, 10);

        $html = '<section class="itxeb-cui-section"><h3>Apprenants en difficulté</h3>'
            . '<p>Vue nominative : les apprenants sont affichés avec l’identité transmise par TRAX/LRS afin de faciliter le suivi formateur.</p>';
        if (count($visible) === 0) {
            return $html . '<p><em>Aucun apprenant en difficulté détecté sur la période et le filtre sélectionnés.</em></p></section>';
        }

        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-struggling-table"><thead><tr><th>Apprenant</th><th>Alertes</th><th>Échecs</th><th>Scores faibles</th><th>Score moyen</th><th>Dernière alerte</th><th>Ressources concernées</th></tr></thead><tbody>';
        foreach ($visible as $learner) {
            $resources = array_keys((array) ($learner['resources'] ?? []));
            sort($resources);
            $resourceText = count($resources) === 0 ? '-' : implode(', ', array_slice($resources, 0, 3));
            if (count($resources) > 3) {
                $resourceText .= ' +' . (count($resources) - 3);
            }
            $scoreText = $learner['avg_score'] === null ? '-' : (string) $learner['avg_score'] . ' %';
            $html .= '<tr><td><span class="itxeb-signal itxeb-signal-warning">' . $this->esc((string) ($learner['learner_identity'] ?? '')) . '</span></td>'
                . '<td>' . $this->esc((string) ($learner['alerts'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($learner['failed'] ?? 0)) . '</td>'
                . '<td>' . $this->esc((string) ($learner['low_scores'] ?? 0)) . '</td>'
                . '<td>' . $this->esc($scoreText) . '</td>'
                . '<td>' . $this->esc((string) ($learner['last_at'] ?? '')) . '</td>'
                . '<td>' . $this->esc($resourceText) . '</td></tr>';
        }

        return $html . '</tbody></table></div></section>';
    }'''


EXPERT_METHOD = '''    /** @param array<string,mixed> $course */
    private function renderExpert(array $course): string
    {
        // ITXEB V0.25.5 learner identity display py36 preflight.
        $dashboard = $this->loadDashboard($course);
        $rows = is_array($dashboard['expert_rows'] ?? null) ? $dashboard['expert_rows'] : [];
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $exportUrl = $this->currentUrlWith([
            'itxeb_cui_cmd' => 'exportCourseExpertCsv',
            'itxeb_course_ref_id' => (string) $courseRefId,
            'itxeb_period_days' => (string) $this->getPeriodDays(),
            'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
            'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
        ]);
        $html = '<section class="itxeb-cui-section"><h2>Traces détaillées</h2><p>Vue support des 200 derniers statements retournés par TRAX pour ce cours.</p>'
            . $this->renderPeriodSelector('showCourseExpert') . $this->renderResourceFilter($course, 'showCourseExpert') . $this->renderAnalyticsWarning()
            . '<p><a class="btn btn-default itxeb-export-button" href="' . $this->esc($exportUrl) . '">Exporter CSV</a></p>';
        if (count($rows) === 0) {
            return $html . '<p><em>Aucun statement xAPI TRAX pour cette période ou cette ressource.</em></p></section>';
        }
        $html .= '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-cui-expert-table"><thead><tr><th>Date</th><th>User ID</th><th>Apprenant</th><th>Verbe</th><th>Ressource</th><th>Type</th><th>Score</th><th>Completion</th><th>Success</th><th>Source</th><th>Statement ID</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $learnerIdentity = trim((string) ($row['learner_identity'] ?? ''));
            if ($learnerIdentity === '') {
                $learnerIdentity = (string) ($row['user_id'] ?? '');
            }
            $html .= '<tr><td>' . $this->esc((string) ($row['created_at'] ?? '')) . '</td><td>' . $this->esc((string) ($row['user_id'] ?? 0)) . '</td>'
                . '<td>' . $this->esc($learnerIdentity) . '</td>'
                . '<td>' . $this->esc((string) ($row['verb_label'] ?? '')) . '<br><small>' . $this->esc((string) ($row['verb_id'] ?? '')) . '</small></td>'
                . '<td><strong>' . $this->esc((string) ($row['object_title'] ?? '')) . '</strong><br><small>ref_id ' . $this->esc((string) ($row['ref_id'] ?? 0)) . '</small></td>'
                . '<td>' . $this->esc((string) ($row['obj_type'] ?? '')) . '</td><td>' . $this->esc($row['score_raw'] === null ? '-' : (string) $row['score_raw'] . ' %') . '</td>'
                . '<td>' . $this->esc($this->nullableBoolLabel($row['completion'] ?? null)) . '</td><td>' . $this->esc($this->nullableBoolLabel($row['success'] ?? null)) . '</td><td>' . $this->esc((string) ($row['status'] ?? 'TRAX')) . '</td>'
                . '<td><small>' . $this->esc((string) ($row['statement_uuid'] ?? '')) . '</small></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }'''


def patch_screen(content):
    method_bounds(content, "renderStrugglingLearners")
    method_bounds(content, "renderExpert")
    method_bounds(content, "sendExpertCsv")

    content = content.replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"')
    content = content.replace('<div class= "itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"')
    content = replace_method(content, "renderStrugglingLearners", STRUGGLING_METHOD)
    content = replace_method(content, "renderExpert", EXPERT_METHOD)

    search = """                'date', 'course_ref_id', 'filter_ref_id', 'user_id',
                'verb_label', 'verb_id', 'resource_title', 'ref_id', 'obj_id', 'obj_type',
"""
    replace = """                'date', 'course_ref_id', 'filter_ref_id', 'user_id', 'learner_identity',
                'verb_label', 'verb_id', 'resource_title', 'ref_id', 'obj_id', 'obj_type',
"""
    content = replace_once(content, search, replace, "colonne learner_identity CSV Expert")

    search = """                    (string) ($row['user_id'] ?? 0),
                    (string) ($row['verb_label'] ?? ''),
"""
    replace = """                    (string) ($row['user_id'] ?? 0),
                    (string) ($row['learner_identity'] ?? ''),
                    (string) ($row['verb_label'] ?? ''),
"""
    content = replace_once(content, search, replace, "valeur learner_identity CSV Expert")
    return content


def lint_content(label, content):
    tmp_dir = Path(tempfile.mkdtemp(prefix="itxeb_v0255_lint_"))
    tmp_file = tmp_dir / (label + ".php")
    tmp_file.write_text(content, encoding="utf-8")
    proc = subprocess.Popen(["php", "-l", str(tmp_file)], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, universal_newlines=True)
    out, _ = proc.communicate()
    print(out.strip())
    shutil.rmtree(str(tmp_dir), ignore_errors=True)
    if proc.returncode != 0:
        fail("lint PHP mémoire KO: " + label)


def lint_file(path):
    if not path.is_file():
        return
    proc = subprocess.Popen(["php", "-l", str(path)], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, universal_newlines=True)
    out, _ = proc.communicate()
    print(out.strip())
    if proc.returncode != 0:
        fail("lint PHP KO: " + str(path))


def final_memory_checks(summary, screen, main_plugin, companion_plugin):
    checks = [
        (summary, "learner_identity", "LrsCourseSummary learner_identity"),
        (summary, "private function actorDisplayName", "LrsCourseSummary actorDisplayName"),
        (screen, "ITXEB V0.25.5 learner identity", "Screen marker V0.25.5"),
        (screen, "Vue nominative", "Screen Vue nominative"),
        (screen, "<th>User ID</th><th>Apprenant</th><th>Verbe</th>", "Screen colonne Expert Apprenant"),
        (screen, "learner_identity", "Screen learner_identity"),
        (screen, "'user_id', 'learner_identity'", "CSV learner_identity"),
        (main_plugin, "$version = '0.25.5-dev';", "version main"),
        (companion_plugin, "$version = '0.8.43';", "version companion"),
    ]
    for content, needle, label in checks:
        if needle not in content:
            fail("contrôle mémoire KO: " + label)
    if "anonymous_id" in screen or "Vue anonymisée" in screen:
        fail("contrôle mémoire KO: ancien anonymat encore présent dans Screen")


def main():
    print("V0.25.5 préflight: lecture des fichiers")
    summary_src = read_file(SUMMARY_FILE)
    screen_src = read_file(SCREEN_TEMPLATE)
    main_plugin_src = read_file(MAIN_PLUGIN)
    companion_plugin_src = read_file(COMPANION_PLUGIN_TEMPLATE)

    print("V0.25.5 préflight: vérification structure")
    for name in ["addStatement", "actorKey"]:
        method_bounds(summary_src, name)
    for name in ["renderStrugglingLearners", "renderExpert", "sendExpertCsv"]:
        method_bounds(screen_src, name)
    if "$version" not in main_plugin_src:
        fail("$version absent plugin principal")
    if "$version" not in companion_plugin_src:
        fail("$version absent plugin compagnon")

    print("V0.25.5 préflight: calcul des patchs en mémoire")
    summary_new = patch_lrs_summary(summary_src)
    screen_new = patch_screen(screen_src)
    main_plugin_new = patch_version(main_plugin_src, "0.25.5-dev", "plugin principal")
    companion_plugin_new = patch_version(companion_plugin_src, "0.8.43", "plugin compagnon template")

    print("V0.25.5 préflight: contrôles mémoire")
    final_memory_checks(summary_new, screen_new, main_plugin_new, companion_plugin_new)

    print("V0.25.5 préflight: lint PHP mémoire")
    lint_content("LrsCourseSummary", summary_new)
    lint_content("CourseUIScreen", screen_new)
    lint_content("plugin", main_plugin_new)
    lint_content("companion_plugin", companion_plugin_new)

    print("V0.25.5 préflight OK: écriture des fichiers")
    write_file(SUMMARY_FILE, summary_new)
    write_file(SCREEN_TEMPLATE, screen_new)
    write_file(MAIN_PLUGIN, main_plugin_new)
    write_file(COMPANION_PLUGIN_TEMPLATE, companion_plugin_new)

    if LIVE_SCREEN.is_file():
        write_file(LIVE_SCREEN, screen_new)
    if LIVE_COMPANION_PLUGIN.is_file():
        live_plugin_src = read_file(LIVE_COMPANION_PLUGIN)
        live_plugin_new = patch_version(live_plugin_src, "0.8.43", "plugin compagnon live")
        write_file(LIVE_COMPANION_PLUGIN, live_plugin_new)

    print("V0.25.5 contrôle PHP fichiers écrits")
    for path in [SUMMARY_FILE, SCREEN_TEMPLATE, MAIN_PLUGIN, COMPANION_PLUGIN_TEMPLATE, LIVE_SCREEN, LIVE_COMPANION_PLUGIN]:
        lint_file(path)

    print("V0.25.5 appliquée : identité apprenant non anonymisée dans Analyse et Expert.")
    print("Versions : plugin principal 0.25.5-dev / compagnon 0.8.43")
    print("Backups: " + str(BACKUP_DIR))


if __name__ == "__main__":
    main()
