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

    $oldAction = <<<'PHP'
        $action = $this->currentUrlWith([
            'itxeb_cui_cmd' => 'generateCourseAiAnalysis',
            'itxeb_course_ref_id' => (string) $courseRefId,
            'itxeb_period_days' => (string) $this->getPeriodDays(),
            'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
            'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
        ]);
PHP;

    $newAction = <<<'PHP'
        $action = $this->currentRequestUri();
PHP;

    if (strpos($content, $oldAction) !== false) {
        $content = str_replace($oldAction, $newAction, $content);
    } elseif (strpos($content, '$action = $this->currentRequestUri();') === false && strpos($content, 'generateCourseAiAnalysis') !== false) {
        $pattern = '~\$action\s*=\s*\$this->currentUrlWith\(\[.*?generateCourseAiAnalysis.*?\]\);~s';
        $content = preg_replace($pattern, '$action = $this->currentRequestUri();', $content, 1, $count);
        if ($content === null) {
            itxeb_nav_fail("erreur regex dans {$file}");
        }
        if ((int) $count === 0) {
            itxeb_nav_fail("bloc action Analyse IA introuvable dans {$file}");
        }
    }

    $oldHidden = <<<'PHP'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
            . '<p><button class="btn btn-primary" type="submit">Générer une analyse IA du cours</button></p>'
PHP;

    $newHidden = <<<'PHP'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
            . '<input type="hidden" name="itxeb_period_days" value="' . $this->esc((string) $this->getPeriodDays()) . '">'
            . '<input type="hidden" name="itxeb_filter_ref_id" value="' . $this->esc((string) $this->getSelectedResourceRefId()) . '">'
            . '<input type="hidden" name="itxeb_filter_obj_type" value="' . $this->esc($this->getSelectedObjectType()) . '">'
            . '<p><button class="btn btn-primary" type="submit">Générer une analyse IA du cours</button></p>'
PHP;

    if (strpos($content, 'name="itxeb_period_days" value="') === false && strpos($content, 'Générer une analyse IA du cours') !== false) {
        if (strpos($content, $oldHidden) === false) {
            itxeb_nav_fail("point insertion champs cachés Analyse IA introuvable dans {$file}");
        }
        $content = str_replace($oldHidden, $newHidden, $content);
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
