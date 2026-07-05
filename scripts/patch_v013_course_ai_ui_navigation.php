<?php
/**
 * Correctif V0.13 - navigation du bouton Analyse IA.
 *
 * Symptôme corrigé : au clic sur "Générer une analyse IA du cours", ILIAS peut
 * retomber sur l'onglet standard Contenu ou Membres si le formulaire POST pointe
 * vers une URL reconstruite au lieu de l'URL courante du support UIHook.
 *
 * À lancer depuis la racine du plugin EventHook IliasTraxEventBridge :
 * php scripts/patch_v013_course_ai_ui_navigation.php
 */

function itxeb_nav_fail(string $message): void
{
    fwrite(STDERR, "ERREUR: " . $message . PHP_EOL);
    exit(1);
}

function itxeb_nav_patch_file(string $file): bool
{
    if (!is_file($file)) {
        echo "IGNORE: fichier absent: {$file}" . PHP_EOL;
        return false;
    }

    $content = file_get_contents($file);
    if (!is_string($content)) {
        itxeb_nav_fail("lecture impossible: {$file}");
    }

    $original = $content;

    $pattern = '~\$action\s*=\s*\$this->currentUrlWith\(\[\s*\'itxeb_cui_cmd\'\s*=>\s*\'generateCourseAiAnalysis\',\s*\'itxeb_course_ref_id\'\s*=>\s*\(string\)\s*\$courseRefId,\s*\'itxeb_period_days\'\s*=>\s*\(string\)\s*\$this->getPeriodDays\(\),\s*\'itxeb_filter_ref_id\'\s*=>\s*\(string\)\s*\$this->getSelectedResourceRefId\(\),\s*\'itxeb_filter_obj_type\'\s*=>\s*\$this->getSelectedObjectType\(\),\s*\]\);~s';
    $replacement = '$action = $this->currentRequestUri();';
    $content = preg_replace($pattern, $replacement, $content, 1, $count);
    if ($content === null) {
        itxeb_nav_fail("erreur regex dans {$file}");
    }

    if ($count === 0 && strpos($content, '$action = $this->currentRequestUri();') === false && strpos($content, 'generateCourseAiAnalysis') !== false) {
        itxeb_nav_fail("bloc action Analyse IA introuvable dans {$file}");
    }

    $needle = ". '<input type=\"hidden\" name=\"itxeb_course_ref_id\" value=\"' . $this->esc((string) $courseRefId) . '\">'\n"
        . "            . '<p><button class=\"btn btn-primary\" type=\"submit\">Générer une analyse IA du cours</button></p>'";
    $insert = ". '<input type=\"hidden\" name=\"itxeb_course_ref_id\" value=\"' . $this->esc((string) $courseRefId) . '\">'\n"
        . "            . '<input type=\"hidden\" name=\"itxeb_period_days\" value=\"' . $this->esc((string) $this->getPeriodDays()) . '\">'\n"
        . "            . '<input type=\"hidden\" name=\"itxeb_filter_ref_id\" value=\"' . $this->esc((string) $this->getSelectedResourceRefId()) . '\">'\n"
        . "            . '<input type=\"hidden\" name=\"itxeb_filter_obj_type\" value=\"' . $this->esc($this->getSelectedObjectType()) . '\">'\n"
        . "            . '<p><button class=\"btn btn-primary\" type=\"submit\">Générer une analyse IA du cours</button></p>'";

    if (strpos($content, 'name="itxeb_period_days" value="') === false) {
        if (strpos($content, $needle) === false) {
            itxeb_nav_fail("point insertion champs cachés Analyse IA introuvable dans {$file}");
        }
        $content = str_replace($needle, $insert, $content);
    }

    if ($content === $original) {
        echo "OK: déjà corrigé: {$file}" . PHP_EOL;
        return false;
    }

    if (file_put_contents($file, $content) === false) {
        itxeb_nav_fail("écriture impossible: {$file}");
    }

    echo "PATCH: {$file}" . PHP_EOL;
    return true;
}

$root = getcwd();
if (!is_file($root . '/plugin.php') || !is_dir($root . '/classes')) {
    itxeb_nav_fail('lance ce script depuis la racine du plugin EventHook IliasTraxEventBridge.');
}

$candidates = [];
$candidates[] = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';

$eventHookSuffix = '/Services/EventHandling/EventHook/IliasTraxEventBridge';
$uiHookSuffix = '/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
if (substr($root, -strlen($eventHookSuffix)) === $eventHookSuffix) {
    $candidates[] = substr($root, 0, -strlen($eventHookSuffix)) . $uiHookSuffix . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
}

$changed = false;
foreach (array_unique($candidates) as $file) {
    $changed = itxeb_nav_patch_file($file) || $changed;
}

echo $changed ? "Correctif navigation Analyse IA appliqué." . PHP_EOL : "Aucune modification nécessaire." . PHP_EOL;
