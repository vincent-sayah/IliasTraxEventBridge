#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
V0.25.4 — Affichage nominatif des apprenants dans Analyse et Expert.

Ce script effectue les vérifications avant toute écriture :
- présence des fichiers ;
- présence des méthodes à modifier ;
- présence des versions ;
- génération des patchs en mémoire ;
- écriture seulement si tous les contrôles mémoire sont OK.
"""

from __future__ import annotations

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
BACKUP_DIR = Path(tempfile.gettempdir()) / ("itxeb_v0254_backup_" + datetime.now().strftime("%Y%m%d%H%M%S"))

SUMMARY_FILE = ROOT / "classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php"
SCREEN_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl"
LIVE_SCREEN = LIVE_COMPANION / "classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
MAIN_PLUGIN = ROOT / "plugin.php"
COMPANION_PLUGIN_TEMPLATE = ROOT / "companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl"
LIVE_COMPANION_PLUGIN = LIVE_COMPANION / "plugin.php"


def fail(message: str) -> None:
    print(f"ERREUR: {message}", file=sys.stderr)
    raise SystemExit(1)


def read_file(path: Path) -> str:
    if not path.is_file():
        fail(f"fichier introuvable: {path}")
    return path.read_text(encoding="utf-8")


def backup(path: Path) -> None:
    if not path.is_file():
        return
    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    target = BACKUP_DIR / str(path).lstrip("/").replace("/", "__")
    shutil.copy2(path, target)


def write_file(path: Path, content: str) -> None:
    backup(path)
    path.write_text(content, encoding="utf-8")


def method_bounds(content: str, method_name: str) -> tuple[int, int]:
    needle = f"private function {method_name}("
    start = content.find(needle)
    if start < 0:
        fail(f"méthode introuvable: {method_name}")
    if content.find(needle, start + 1) >= 0:
        fail(f"méthode en double: {method_name}")
    brace = content.find("{", start)
    if brace < 0:
        fail(f"accolade introuvable: {method_name}")
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
    fail(f"fin de méthode introuvable: {method_name}")


def replace_method(content: str, method_name: str, new_method: str) -> str:
    start, end = method_bounds(content, method_name)
    return content[:start] + new_method.rstrip() + content[end:]


def replace_once(content: str, search: str, replace: str, label: str) -> str:
    if replace in content:
        return content
    pos = content.find(search)
    if pos < 0:
        fail(f"point de remplacement introuvable: {label}")
    return content[:pos] + replace + content[pos + len(search):]


def patch_version(content: str, new_version: str, label: str) -> str:
    pattern = re.compile(r"\$version\s*=\s*'[^']+';")
    if not pattern.search(content):
        fail(f"version introuvable: {label}")
    return pattern.sub(f"$version = '{new_version}';", content, count=1)


def patch_summary(content: str) -> str:
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
        method = """    /** @param array<string,mixed> $statement */
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

"""
        content = replace_once(content, marker, method + marker, "insertion actorDisplayName")
    return content


STRUGGLING_METHOD = '''    private function renderStrugglingLearners(array $dashboard): string
    {
        // ITXEB V0.25.4 learner identity display preflight.
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
        // ITXEB V0.25.4 learner identity display preflight.
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


def patch_screen(content: str) -> str:
    method_bounds(content, "renderStrugglingLearners")
    method_bounds(content, "renderExpert")
    method_bounds(content, "sendExpertCsv")

    content = content.replace('<div class= itxeb-dashboard-top-card"', '<div class="itxeb-dashboard-top-card"')
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


def lint(path: Path) -> None:
    if not path.is_file():
        return
    proc = subprocess.run(["php", "-l", str(path)], text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    print(proc.stdout.strip())
    if proc.returncode != 0:
        fail(f"lint PHP KO: {path}")


def check_screen(content: str, label: str) -> None:
    for needle in [
        "learner_identity",
        "Vue nominative",
        "<th>User ID</th><th>Apprenant</th><th>Verbe</th>",
        "ITXEB V0.25.4 learner identity",
    ]:
        if needle not in content:
            fail(f"contrôle final KO dans {label}: manque {needle}")
    for forbidden in ["anonymous_id", "Vue anonymisée"]:
        if forbidden in content:
            fail(f"contrôle final KO dans {label}: reste {forbidden}")


def main() -> None:
    print("V0.25.4 préflight: lecture des fichiers")
    summary_source = read_file(SUMMARY_FILE)
    screen_source = read_file(SCREEN_TEMPLATE)
    main_plugin_source = read_file(MAIN_PLUGIN)
    companion_plugin_source = read_file(COMPANION_PLUGIN_TEMPLATE)

    print("V0.25.4 préflight: calcul des patchs en mémoire")
    summary_patched = patch_summary(summary_source)
    screen_patched = patch_screen(screen_source)
    main_plugin_patched = patch_version(main_plugin_source, "0.25.4-dev", "plugin principal")
    companion_plugin_patched = patch_version(companion_plugin_source, "0.8.42", "plugin compagnon template")

    if "private function actorDisplayName(" not in summary_patched or "'learner_identity' =>" not in summary_patched:
        fail("contrôle mémoire KO summary learner_identity")
    check_screen(screen_patched, "screen template mémoire")

    print("V0.25.4 préflight OK: écriture des fichiers")
    write_file(SUMMARY_FILE, summary_patched)
    write_file(SCREEN_TEMPLATE, screen_patched)
    write_file(MAIN_PLUGIN, main_plugin_patched)
    write_file(COMPANION_PLUGIN_TEMPLATE, companion_plugin_patched)

    if LIVE_SCREEN.is_file():
        write_file(LIVE_SCREEN, screen_patched)
    if LIVE_COMPANION_PLUGIN.is_file():
        write_file(LIVE_COMPANION_PLUGIN, patch_version(read_file(LIVE_COMPANION_PLUGIN), "0.8.42", "plugin compagnon live"))

    print("V0.25.4 contrôle PHP")
    for path in [SUMMARY_FILE, SCREEN_TEMPLATE, MAIN_PLUGIN, COMPANION_PLUGIN_TEMPLATE, LIVE_SCREEN, LIVE_COMPANION_PLUGIN]:
        lint(path)

    check_screen(read_file(SCREEN_TEMPLATE), "screen template")
    if LIVE_SCREEN.is_file():
        check_screen(read_file(LIVE_SCREEN), "screen live")

    for path, version in [(MAIN_PLUGIN, "0.25.4-dev"), (COMPANION_PLUGIN_TEMPLATE, "0.8.42"), (LIVE_COMPANION_PLUGIN, "0.8.42")]:
        if path.is_file() and f"$version = '{version}';" not in read_file(path):
            fail(f"contrôle final KO version {version}: {path}")

    print("V0.25.4 appliquée : identité apprenant non anonymisée dans Analyse et Expert.")
    print(f"Backups: {BACKUP_DIR}")


if __name__ == "__main__":
    main()
