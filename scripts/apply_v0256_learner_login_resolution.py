#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import re, shutil, subprocess, sys, tempfile
from pathlib import Path
from datetime import datetime

ROOT = Path.cwd()
SUMMARY = ROOT / "classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php"
PLUGIN = ROOT / "plugin.php"
CTPL = ROOT / "companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl"
LIVE_PLUGIN = Path("/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php")
BK = Path("/tmp") / ("itxeb_v0256_login_backup_" + datetime.now().strftime("%Y%m%d%H%M%S"))

def fail(m):
    print("ERREUR: " + m, file=sys.stderr)
    sys.exit(1)

def rd(p):
    if not p.is_file():
        fail("fichier introuvable: " + str(p))
    return p.read_text(encoding="utf-8")

def wr(p, c):
    BK.mkdir(parents=True, exist_ok=True)
    if p.is_file():
        shutil.copy2(str(p), str(BK / str(p).lstrip("/").replace("/", "__")))
    p.write_text(c, encoding="utf-8")

def lint_mem(name, c):
    d = Path(tempfile.mkdtemp(prefix="itxeb_v0256_lint_"))
    f = d / (name + ".php")
    f.write_text(c, encoding="utf-8")
    r = subprocess.run(["php", "-l", str(f)], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, universal_newlines=True)
    print(r.stdout.strip())
    shutil.rmtree(str(d), ignore_errors=True)
    if r.returncode != 0:
        fail("lint PHP mémoire KO: " + name)

def lint_file(p):
    if not p.is_file():
        return
    r = subprocess.run(["php", "-l", str(p)], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, universal_newlines=True)
    print(r.stdout.strip())
    if r.returncode != 0:
        fail("lint PHP KO: " + str(p))

def version(c, v, label):
    c, n = re.subn(r"\$version\s*=\s*'[^']+';", "$version = '" + v + "';", c, 1)
    if n != 1:
        fail("version introuvable: " + label)
    return c

IDENTITY = r'''    /** @param array<string,mixed> $statement */
    private function actorDisplayName(array $statement, string $actorKey = ''): string
    {
        // ITXEB V0.25.6 learner login resolution.
        $accountName = $statement['actor']['account']['name'] ?? '';

        if (is_scalar($accountName)) {
            $login = $this->lookupIliasLoginFromActorValue((string) $accountName);
            if ($login !== '') { return $login; }
        }

        if ($actorKey !== '') {
            $login = $this->lookupIliasLoginFromActorValue($actorKey);
            if ($login !== '') { return $login; }
        }

        $name = $statement['actor']['name'] ?? '';
        if (is_scalar($name) && trim((string) $name) !== '') {
            $nameText = trim((string) $name);
            $login = $this->lookupIliasLoginFromActorValue($nameText);
            return $login !== '' ? $login : $nameText;
        }

        $mbox = $statement['actor']['mbox'] ?? '';
        if (is_scalar($mbox) && trim((string) $mbox) !== '') {
            $mail = preg_replace('/^mailto:/i', '', trim((string) $mbox));
            return is_string($mail) && $mail !== '' ? $mail : trim((string) $mbox);
        }

        if (is_scalar($accountName) && trim((string) $accountName) !== '') {
            return trim((string) $accountName);
        }

        return $actorKey;
    }

    private function lookupIliasLoginFromActorValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') { return ''; }

        if (strpos($value, 'account:') === 0) {
            $value = trim(substr($value, 8));
        }

        if (preg_match('/^ilias-user-([0-9]+)$/', $value, $m) === 1) {
            return $this->lookupIliasLogin((int) $m[1]);
        }

        return '';
    }

    private function lookupIliasLogin(int $userId): string
    {
        if ($userId <= 0) { return ''; }

        if (isset($this->actorLoginCache[$userId])) {
            return $this->actorLoginCache[$userId];
        }

        $login = '';

        try {
            if (class_exists('ilObjUser') && is_callable(['ilObjUser', '_lookupLogin'])) {
                $v = ilObjUser::_lookupLogin($userId);
                if (is_scalar($v) && trim((string) $v) !== '') {
                    $login = trim((string) $v);
                }
            }
        } catch (Throwable $ignored) {
            $login = '';
        }

        if ($login === '') {
            $login = $this->lookupIliasLoginWithDIC($userId);
        }

        if ($login === '') {
            $login = $this->lookupIliasLoginWithGlobalDb($userId);
        }

        $this->actorLoginCache[$userId] = $login;
        return $login;
    }

    private function lookupIliasLoginWithDIC(int $userId): string
    {
        try {
            global $DIC;
            if (!isset($DIC) || !is_object($DIC) || !method_exists($DIC, 'database')) {
                return '';
            }
            return $this->lookupIliasLoginWithDb($DIC->database(), $userId);
        } catch (Throwable $ignored) {
            return '';
        }
    }

    private function lookupIliasLoginWithGlobalDb(int $userId): string
    {
        try {
            global $ilDB;
            if (!isset($ilDB) || !is_object($ilDB)) {
                return '';
            }
            return $this->lookupIliasLoginWithDb($ilDB, $userId);
        } catch (Throwable $ignored) {
            return '';
        }
    }

    private function lookupIliasLoginWithDb($db, int $userId): string
    {
        if (!is_object($db) || !method_exists($db, 'query') || !method_exists($db, 'fetchAssoc') || !method_exists($db, 'quote')) {
            return '';
        }

        try {
            $res = $db->query('SELECT login FROM usr_data WHERE usr_id = ' . $db->quote($userId, 'integer'));
            $row = $db->fetchAssoc($res);
            return is_array($row) && is_scalar($row['login'] ?? null) ? trim((string) $row['login']) : '';
        } catch (Throwable $ignored) {
            return '';
        }
    }'''

print("V0.25.6 préflight: lecture fichiers")
s = rd(SUMMARY)
p = rd(PLUGIN)
cp = rd(CTPL)
lp = rd(LIVE_PLUGIN) if LIVE_PLUGIN.is_file() else ""

if "learner_identity" not in s or "private function actorDisplayName" not in s:
    fail("V0.25.5 n'est pas présent dans LrsCourseSummary")

if "private $actorLoginCache" not in s:
    marker = "    /** @var ilIliasTraxEventBridgeLrsReadClient */\n    private $client;\n"
    if marker not in s:
        fail("propriété client introuvable")
    s = s.replace(marker, marker + "\n    /** @var array<int,string> */\n    private $actorLoginCache = [];\n", 1)

start = s.find("    /** @param array<string,mixed> $statement */\n    private function actorDisplayName")
end_marker = "    /** @param array<string,mixed> $statement */\n    private function actorKey(array $statement): string"
end = s.find(end_marker, start)

if start < 0 or end < 0:
    fail("bloc actorDisplayName / actorKey introuvable")

s2 = s[:start] + IDENTITY.rstrip() + "\n\n" + s[end:]
p2 = version(p, "0.25.6-dev", "plugin principal")
cp2 = version(cp, "0.8.44", "plugin compagnon template")
lp2 = version(lp, "0.8.44", "plugin compagnon live") if lp else ""

print("V0.25.6 préflight: contrôles mémoire")
for label, needle, content in [
    ("marker", "ITXEB V0.25.6 learner login resolution", s2),
    ("lookup ilias-user", "ilias-user-([0-9]+)", s2),
    ("lookup usr_data", "SELECT login FROM usr_data WHERE usr_id", s2),
    ("version principale", "0.25.6-dev", p2),
    ("version compagnon", "0.8.44", cp2),
]:
    if needle not in content:
        fail("contrôle mémoire KO: " + label)

print("V0.25.6 préflight: lint PHP mémoire")
lint_mem("LrsCourseSummary", s2)
lint_mem("plugin", p2)
lint_mem("companion_plugin", cp2)

print("V0.25.6 préflight OK: écriture")
wr(SUMMARY, s2)
wr(PLUGIN, p2)
wr(CTPL, cp2)
if lp2:
    wr(LIVE_PLUGIN, lp2)

print("V0.25.6 contrôle PHP fichiers écrits")
for f in [SUMMARY, PLUGIN, CTPL, LIVE_PLUGIN]:
    lint_file(f)

print("V0.25.6 appliquée : ilias-user-ID est résolu en login ILIAS si le compte existe dans usr_data.")
print("Versions : plugin principal 0.25.6-dev / compagnon 0.8.44")
print("Backups: " + str(BK))
