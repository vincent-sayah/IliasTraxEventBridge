#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
V0.26.0 — Taux de réussite du cours dans la synthèse pédagogique.

Objectif :
- lorsque la progression ILIAS du cours est paramétrée, afficher le taux de réussite du cours ;
- affichage dans Tableau de bord > Synthèse pédagogique ;
- affichage dans Analyse > Synthèse pédagogique ;
- calcul côté ILIAS local, pas depuis TRAX, via la progression d'apprentissage du cours.

Compatible Python 3.6.
Le script fait un préflight complet avant écriture.
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
BACKUP_DIR = Path(tempfile.gettempdir()) / ("itxeb_v0260_backup_" + datetime.now().strftime("%Y%m%d%H%M%S"))

SUMMARY_FILE = ROOT / "classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php"
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


COURSE_PROGRESS_METHODS = r'''    private function courseProgressSummary(int $courseRefId, int $courseObjId): array
    {
        $empty = [
            'configured' => false,
            'available' => false,
            'mode' => 0,
            'total' => 0,
            'completed' => 0,
            'failed' => 0,
            'in_progress' => 0,
            'not_attempted' => 0,
            'success_rate' => null,
            'label' => 'Progression du cours non paramétrée',
            'hint' => '',
            'source' => 'ILIAS learning progress',
        ];

        if ($courseObjId <= 0) {
            $empty['hint'] = 'course_obj_id indisponible.';
            return $empty;
        }

        $db = $this->getIliasDb();
        if (!is_object($db)) {
            $empty['hint'] = 'Base ILIAS indisponible.';
            return $empty;
        }

        $mode = $this->courseProgressMode($db, $courseObjId);
        if ($mode <= 0) {
            return $empty;
        }

        $constants = $this->courseProgressStatusConstants();
        $participantIds = $this->courseProgressParticipantIds($db, $courseObjId);
        $statuses = $this->courseProgressStatuses($db, $courseObjId);

        $userIds = $participantIds;
        if (count($userIds) === 0) {
            $userIds = array_keys($statuses);
        }
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        sort($userIds);

        $total = count($userIds);
        $completed = 0;
        $failed = 0;
        $inProgress = 0;
        $notAttempted = 0;

        foreach ($userIds as $userId) {
            $status = isset($statuses[$userId]) ? (int) $statuses[$userId] : (int) $constants['not_attempted'];
            if ($status === (int) $constants['completed']) {
                $completed++;
            } elseif ($status === (int) $constants['failed']) {
                $failed++;
            } elseif ($status === (int) $constants['in_progress']) {
                $inProgress++;
            } else {
                $notAttempted++;
            }
        }

        $rate = $total > 0 ? round(($completed / max(1, $total)) * 100, 1) : null;
        return [
            'configured' => true,
            'available' => true,
            'mode' => $mode,
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'in_progress' => $inProgress,
            'not_attempted' => $notAttempted,
            'success_rate' => $rate,
            'label' => $rate === null ? '-' : ((string) $rate . ' %'),
            'hint' => $total > 0
                ? ($completed . ' réussi(s) / ' . $total . ' apprenant(s)')
                : 'Progression paramétrée, aucun apprenant trouvé.',
            'source' => 'ILIAS learning progress',
        ];
    }

    private function getIliasDb()
    {
        try {
            global $DIC;
            if (isset($DIC) && is_object($DIC) && method_exists($DIC, 'database')) {
                return $DIC->database();
            }
        } catch (Throwable $ignored) {
            // fallback global $ilDB ci-dessous
        }

        try {
            global $ilDB;
            if (isset($ilDB) && is_object($ilDB)) {
                return $ilDB;
            }
        } catch (Throwable $ignored) {
            return null;
        }

        return null;
    }

    private function courseProgressMode($db, int $courseObjId): int
    {
        if (!$this->dbTableExists($db, 'ut_lp_settings')) {
            return 0;
        }
        try {
            $res = $db->query('SELECT u_mode FROM ut_lp_settings WHERE obj_id = ' . $db->quote($courseObjId, 'integer'));
            $row = $db->fetchAssoc($res);
            return is_array($row) && is_numeric($row['u_mode'] ?? null) ? (int) $row['u_mode'] : 0;
        } catch (Throwable $ignored) {
            return 0;
        }
    }

    private function courseProgressStatusConstants(): array
    {
        $completed = 2;
        $failed = 3;
        $inProgress = 1;
        $notAttempted = 0;

        try {
            if (class_exists('ilLPStatus')) {
                if (defined('ilLPStatus::LP_STATUS_COMPLETED_NUM')) {
                    $completed = (int) constant('ilLPStatus::LP_STATUS_COMPLETED_NUM');
                }
                if (defined('ilLPStatus::LP_STATUS_FAILED_NUM')) {
                    $failed = (int) constant('ilLPStatus::LP_STATUS_FAILED_NUM');
                }
                if (defined('ilLPStatus::LP_STATUS_IN_PROGRESS_NUM')) {
                    $inProgress = (int) constant('ilLPStatus::LP_STATUS_IN_PROGRESS_NUM');
                }
                if (defined('ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM')) {
                    $notAttempted = (int) constant('ilLPStatus::LP_STATUS_NOT_ATTEMPTED_NUM');
                }
            }
        } catch (Throwable $ignored) {
            // valeurs numériques de secours
        }

        return [
            'completed' => $completed,
            'failed' => $failed,
            'in_progress' => $inProgress,
            'not_attempted' => $notAttempted,
        ];
    }

    /** @return array<int,int> */
    private function courseProgressParticipantIds($db, int $courseObjId): array
    {
        $ids = [];

        try {
            if (class_exists('ilCourseParticipants') && is_callable(['ilCourseParticipants', '_getInstanceByObjId'])) {
                $participants = ilCourseParticipants::_getInstanceByObjId($courseObjId);
                if (is_object($participants)) {
                    foreach (['getMembers', 'getParticipants'] as $method) {
                        if (method_exists($participants, $method)) {
                            $values = $participants->{$method}();
                            if (is_array($values)) {
                                foreach ($values as $value) {
                                    if (is_scalar($value) && (int) $value > 0) {
                                        $ids[(int) $value] = (int) $value;
                                    } elseif (is_array($value) && isset($value['usr_id']) && (int) $value['usr_id'] > 0) {
                                        $ids[(int) $value['usr_id']] = (int) $value['usr_id'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (Throwable $ignored) {
            // fallback SQL ci-dessous
        }

        if (count($ids) === 0 && $this->dbTableExists($db, 'crs_members')) {
            try {
                $res = $db->query('SELECT usr_id FROM crs_members WHERE obj_id = ' . $db->quote($courseObjId, 'integer'));
                while ($row = $db->fetchAssoc($res)) {
                    if (is_array($row) && isset($row['usr_id']) && (int) $row['usr_id'] > 0) {
                        $ids[(int) $row['usr_id']] = (int) $row['usr_id'];
                    }
                }
            } catch (Throwable $ignored) {
                // fallback aux lignes de statut uniquement
            }
        }

        return array_values($ids);
    }

    /** @return array<int,int> */
    private function courseProgressStatuses($db, int $courseObjId): array
    {
        $statuses = [];
        if (!$this->dbTableExists($db, 'ut_lp_marks')) {
            return $statuses;
        }
        try {
            $res = $db->query('SELECT usr_id, status FROM ut_lp_marks WHERE obj_id = ' . $db->quote($courseObjId, 'integer'));
            while ($row = $db->fetchAssoc($res)) {
                if (!is_array($row)) {
                    continue;
                }
                $usrId = isset($row['usr_id']) ? (int) $row['usr_id'] : 0;
                if ($usrId > 0 && is_numeric($row['status'] ?? null)) {
                    $statuses[$usrId] = (int) $row['status'];
                }
            }
        } catch (Throwable $ignored) {
            return [];
        }
        return $statuses;
    }

    private function dbTableExists($db, string $table): bool
    {
        if (!is_object($db) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        try {
            if (method_exists($db, 'tableExists')) {
                return (bool) $db->tableExists($table);
            }
        } catch (Throwable $ignored) {
            // fallback SELECT ci-dessous
        }
        try {
            $db->query('SELECT 1 FROM ' . $table . ' WHERE 1 = 0');
            return true;
        } catch (Throwable $ignored) {
            return false;
        }
    }
'''


FINALIZE_PEDAGOGY_METHOD = r'''    /** @param array<string,mixed> $pedagogy @param array<string,mixed> $summary @return array<string,mixed> */
    private function finalizePedagogy(array $pedagogy, array $summary): array
    {
        $lines = [];
        $activeLearners = count((array) ($summary['learners'] ?? []));
        $totalStatements = (int) ($summary['returned'] ?? 0);
        $courseProgress = is_array($summary['course_progress'] ?? null) ? $summary['course_progress'] : [];

        if ($totalStatements <= 0) {
            $lines[] = 'Aucune trace TRAX/LRS trouvée sur la période.';
        } else {
            $lines[] = $totalStatements . ' trace(s) TRAX/LRS analysée(s) sur la période.';
        }

        if ($activeLearners > 0) {
            $lines[] = $activeLearners . ' apprenant(s) actif(s) détecté(s).';
        }

        if (!empty($courseProgress['configured'])) {
            $rate = is_numeric($courseProgress['success_rate'] ?? null) ? (string) $courseProgress['success_rate'] . ' %' : '-';
            $lines[] = 'Progression ILIAS du cours : ' . $rate . ' de réussite ('
                . (string) ($courseProgress['completed'] ?? 0) . ' / '
                . (string) ($courseProgress['total'] ?? 0) . ' apprenant(s)).';
        }

        if ((int) $pedagogy['resources_without_trace'] > 0) {
            $lines[] = $pedagogy['resources_without_trace'] . ' ressource(s) activée(s) ne présentent aucune trace TRAX sur la période.';
        }

        $mediaTotal = (int) ($summary['summary']['mediacast_media_total'] ?? 0);
        if ($mediaTotal > 0) {
            $lines[] = $mediaTotal . ' action(s) MediaCast détectée(s) sur des vidéos ou médias externes.';
        }

        if ((int) $pedagogy['critical_count'] > 0) {
            $lines[] = $pedagogy['critical_count'] . ' ressource(s) sont en statut critique.';
        }

        if ((int) $pedagogy['watch_count'] > 0) {
            $lines[] = $pedagogy['watch_count'] . ' ressource(s) sont à surveiller.';
        }

        if ((int) $pedagogy['critical_count'] === 0 && (int) $pedagogy['watch_count'] === 0 && $totalStatements > 0) {
            $lines[] = 'Aucun signal pédagogique défavorable détecté sur la période.';
        }

        $pedagogy['synthesis_lines'] = $lines;
        return $pedagogy;
    }
'''


PEDAGOGICAL_SYNTHESIS_METHOD = r'''    /** @param array<string,mixed> $dashboard */
    private function renderPedagogicalSynthesis(array $dashboard): string
    {
        // ITXEB V0.26.0 course progress success rate in synthesis.
        $pedagogy = is_array($dashboard['pedagogy'] ?? null) ? $dashboard['pedagogy'] : [];
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $courseProgress = is_array($dashboard['course_progress'] ?? null) ? $dashboard['course_progress'] : [];
        $lines = is_array($pedagogy['synthesis_lines'] ?? null) ? $pedagogy['synthesis_lines'] : [];
        $html = '<div class="itxeb-pedagogy-summary itxeb-v024-synthesis"><h3>Synthèse pédagogique</h3><div class="itxeb-pedagogy-kpis itxeb-v024-synthesis-kpis">';
        if (!empty($courseProgress['configured'])) {
            $rate = is_numeric($courseProgress['success_rate'] ?? null) ? (string) $courseProgress['success_rate'] . ' %' : '-';
            $hint = (string) ($courseProgress['hint'] ?? 'Progression ILIAS du cours');
            if ($hint === '') {
                $hint = 'Progression ILIAS du cours';
            }
            $html .= $this->metricCardWithIcon('Réussite du cours', $rate, $hint, '🏁');
        }
        $html .= $this->metricCardWithIcon('OK', (string) ($pedagogy['ok_count'] ?? 0), 'Ressources sans signal', '✅')
            . $this->metricCardWithIcon('À surveiller', (string) ($pedagogy['watch_count'] ?? 0), 'Signal faible', '⚠️')
            . $this->metricCardWithIcon('Critiques', (string) ($pedagogy['critical_count'] ?? 0), 'Priorité', '🚨')
            . $this->metricCardWithIcon('Sans activité enregistrée', (string) ($pedagogy['resources_without_trace'] ?? 0), 'Ressources sans activité', '🔇')
            . $this->metricCardWithIcon('Données d’apprentissage', (string) ($summary['total'] ?? 0), 'Lecture des données', '📊')
            . $this->metricCardWithIcon('Apprenants actifs', (string) ($summary['active_learners'] ?? 0), 'Comptage anonyme', '👥')
            . $this->metricCardWithIcon('Ressources utilisées', (string) ($summary['resources_with_traces'] ?? 0) . ' / ' . (string) ($summary['resources_total'] ?? 0), 'Au moins une activité enregistrée', '📚')
            . $this->metricCardWithIcon('Lots de données lus', (string) ($dashboard['pages'] ?? 0), 'lecture par lots', '📦')
            . $this->metricCardWithIcon('Score moyen', $summary['avg_score_raw'] === null ? '-' : (string) $summary['avg_score_raw'] . ' %', 'Tests', '🎯')
            . '</div>';
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


def patch_summary(content):
    if "ITXEB V0.26.0 course progress success rate" in content:
        fail("V0.26.0 semble déjà appliquée dans LrsCourseSummary")
    if "'course_progress'" in content:
        fail("course_progress existe déjà dans LrsCourseSummary")

    search = """                'mediacast_media_total' => 0,
                'mediacast_media_unique' => 0,
                'mediacast_media_learners' => 0,
"""
    replace = """                'mediacast_media_total' => 0,
                'mediacast_media_unique' => 0,
                'mediacast_media_learners' => 0,
                'course_success_rate' => null,
                'course_progress_configured' => false,
"""
    content = replace_once(content, search, replace, "ajout résumé progression cours")

    search = """            'by_mediacast_media' => [],
            'mediacast_media_learners' => [],
            'expert_rows' => [],
"""
    replace = """            'by_mediacast_media' => [],
            'mediacast_media_learners' => [],
            'course_progress' => $this->courseProgressSummary($courseRefId, $courseObjId),
            'expert_rows' => [],
"""
    content = replace_once(content, search, replace, "ajout course_progress top-level")

    search = """        $summary['summary']['avg_score_raw'] = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : null;
        unset($summary['learners'], $summary['scores']);
        return $summary;
"""
    replace = """        $summary['summary']['avg_score_raw'] = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : null;
        $courseProgress = is_array($summary['course_progress'] ?? null) ? $summary['course_progress'] : [];
        $summary['summary']['course_progress_configured'] = !empty($courseProgress['configured']);
        $summary['summary']['course_success_rate'] = is_numeric($courseProgress['success_rate'] ?? null) ? (float) $courseProgress['success_rate'] : null;
        unset($summary['learners'], $summary['scores']);
        return $summary;
"""
    content = replace_once(content, search, replace, "finalize course_success_rate")

    content = replace_method(content, "finalizePedagogy", FINALIZE_PEDAGOGY_METHOD)

    marker = "    private function courseActivityId(int $courseRefId, int $courseObjId): string\n"
    if marker not in content:
        fail("point insertion courseProgressSummary introuvable")
    content = content.replace(marker, COURSE_PROGRESS_METHODS.rstrip() + "\n\n" + marker, 1)
    return content


def patch_screen(content):
    if "ITXEB V0.26.0 course progress success rate in synthesis" in content:
        fail("V0.26.0 semble déjà appliquée dans CourseUIScreen")
    content = replace_method(content, "renderPedagogicalSynthesis", PEDAGOGICAL_SYNTHESIS_METHOD)
    return content


def lint_php_memory(name, content):
    temp_dir = Path(tempfile.mkdtemp(prefix="itxeb_v0260_lint_"))
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


print("V0.26.0 préflight: lecture fichiers")
summary = read_file(SUMMARY_FILE)
screen = read_file(SCREEN_TEMPLATE)
main_plugin = read_file(MAIN_PLUGIN)
companion_plugin = read_file(COMPANION_PLUGIN_TEMPLATE)
live_screen = read_file(LIVE_SCREEN) if LIVE_SCREEN.is_file() else ""
live_companion_plugin = read_file(LIVE_COMPANION_PLUGIN) if LIVE_COMPANION_PLUGIN.is_file() else ""

print("V0.26.0 préflight: vérification structure")n
for method in ["finalizePedagogy", "courseActivityId"]:
    method_bounds(summary, method)
for method in ["renderPedagogicalSynthesis"]:
    method_bounds(screen, method)
if "0.25.6-dev" not in main_plugin:
    fail("plugin principal pas en base attendue 0.25.6-dev")
if "0.8.44" not in companion_plugin:
    fail("plugin compagnon template pas en base attendue 0.8.44")

print("V0.26.0 préflight: calcul patchs mémoire")
summary2 = patch_summary(summary)
screen2 = patch_screen(screen)
main_plugin2 = patch_version(main_plugin, "0.26.0-dev", "plugin principal")
companion_plugin2 = patch_version(companion_plugin, "0.8.45", "plugin compagnon template")
live_companion_plugin2 = patch_version(live_companion_plugin, "0.8.45", "plugin compagnon live") if live_companion_plugin else ""

print("V0.26.0 préflight: contrôles mémoire")
checks = [
    ("summary marker", "courseProgressSummary", summary2),
    ("summary top-level", "'course_progress' => $this->courseProgressSummary($courseRefId, $courseObjId)", summary2),
    ("summary rate", "course_success_rate", summary2),
    ("summary settings", "ut_lp_settings", summary2),
    ("summary marks", "ut_lp_marks", summary2),
    ("summary members", "crs_members", summary2),
    ("screen marker", "ITXEB V0.26.0 course progress success rate in synthesis", screen2),
    ("screen label", "Réussite du cours", screen2),
    ("version main", "0.26.0-dev", main_plugin2),
    ("version companion", "0.8.45", companion_plugin2),
]
for label, needle, content in checks:
    if needle not in content:
        fail("contrôle mémoire KO: " + label)

print("V0.26.0 préflight: lint PHP mémoire")
lint_php_memory("LrsCourseSummary", summary2)
lint_php_memory("CourseUIScreen", screen2)
lint_php_memory("plugin", main_plugin2)
lint_php_memory("companion_plugin", companion_plugin2)

print("V0.26.0 préflight OK: écriture fichiers")
write_file(SUMMARY_FILE, summary2)
write_file(SCREEN_TEMPLATE, screen2)
write_file(MAIN_PLUGIN, main_plugin2)
write_file(COMPANION_PLUGIN_TEMPLATE, companion_plugin2)
if LIVE_SCREEN.is_file():
    write_file(LIVE_SCREEN, screen2)
if live_companion_plugin2:
    write_file(LIVE_COMPANION_PLUGIN, live_companion_plugin2)

print("V0.26.0 contrôle PHP fichiers écrits")
for path in [SUMMARY_FILE, SCREEN_TEMPLATE, MAIN_PLUGIN, COMPANION_PLUGIN_TEMPLATE, LIVE_SCREEN, LIVE_COMPANION_PLUGIN]:
    lint_php_file(path)

print("V0.26.0 appliquée : taux de réussite du cours ajouté dans Synthèse pédagogique.")
print("Versions : plugin principal 0.26.0-dev / compagnon 0.8.45")
print("Backups: " + str(BACKUP_DIR))
