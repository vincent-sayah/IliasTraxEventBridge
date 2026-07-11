<?php
/**
 * V0.24.1-dev
 * Affichage tableau de bord/analyse : masque le bloc Questions a fort taux d'echec
 * lorsque le filtre courant ne concerne pas les tests ILIAS.
 *
 * Regle :
 * - afficher si toutes les ressources / tous les types sont selectionnes ;
 * - afficher si le type selectionne est tst ;
 * - afficher si une ressource precise selectionnee est de type tst ;
 * - masquer pour toute autre ressource ou tout autre type.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$screenTemplate = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$mainPlugin = $root . '/plugin.php';
$companionPlugin = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$liveScreen = '/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = '/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php';

function itxeb_v0241_read(string $path): string
{
    if (!is_file($path)) {
        fwrite(STDERR, "ERREUR: fichier introuvable: $path\n");
        exit(1);
    }
    $content = file_get_contents($path);
    if (!is_string($content)) {
        fwrite(STDERR, "ERREUR: lecture impossible: $path\n");
        exit(1);
    }
    return $content;
}

function itxeb_v0241_write(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: ecriture impossible: $path\n");
        exit(1);
    }
    echo "WRITE: $path\n";
}

function itxeb_v0241_patch_screen(string $screen, string $label): string
{
    if (strpos($screen, 'ITXEB V0.24.1 question hotspot visibility filter') !== false) {
        echo "SKIP: $label deja patche V0.24.1\n";
        return $screen;
    }

    $oldCall = " . \$this->renderQuestionFailureHotspots(\$dashboard, \$course)";
    $newCall = " . (\$this->shouldRenderQuestionFailureHotspots(\$course) ? \$this->renderQuestionFailureHotspots(\$dashboard, \$course) : '')";
    $count = substr_count($screen, $oldCall);
    if ($count < 1) {
        fwrite(STDERR, "ERREUR: appel renderQuestionFailureHotspots introuvable dans $label\n");
        exit(1);
    }
    $screen = str_replace($oldCall, $newCall, $screen);
    echo "PATCH: $label appels Questions a fort taux d'echec conditionnes ($count)\n";

    $needle = <<<'PHP'
    /** @param array<string,mixed> $dashboard @param array<string,mixed> $course */
    private function renderQuestionFailureHotspots(array $dashboard, array $course): string
PHP;

    if (strpos($screen, $needle) === false) {
        fwrite(STDERR, "ERREUR: point d'insertion helper shouldRenderQuestionFailureHotspots introuvable dans $label\n");
        exit(1);
    }

    $helper = <<<'PHP'
    /** @param array<string,mixed> $course */
    private function shouldRenderQuestionFailureHotspots(array $course): bool
    {
        // ITXEB V0.24.1 question hotspot visibility filter.
        // Le bloc Questions a fort taux d'echec ne doit apparaitre que pour les tests.
        $selectedRefId = $this->getSelectedResourceRefId();
        if ($selectedRefId > 0) {
            foreach ((array) ($course['resources'] ?? []) as $resource) {
                if (is_array($resource) && (int) ($resource['ref_id'] ?? 0) === $selectedRefId) {
                    return (string) ($resource['obj_type'] ?? '') === 'tst';
                }
            }
            return false;
        }

        $selectedObjectType = $this->getSelectedObjectType();
        return $selectedObjectType === '' || $selectedObjectType === 'tst';
    }

PHP;

    $screen = str_replace($needle, $helper . $needle, $screen);
    echo "PATCH: $label helper shouldRenderQuestionFailureHotspots ajoute\n";
    return $screen;
}

$screen = itxeb_v0241_read($screenTemplate);
$screen = itxeb_v0241_patch_screen($screen, 'template CourseUIScreen');
itxeb_v0241_write($screenTemplate, $screen);

if (is_file($liveScreen)) {
    $live = itxeb_v0241_read($liveScreen);
    $live = itxeb_v0241_patch_screen($live, 'live CourseUIScreen');
    itxeb_v0241_write($liveScreen, $live);
}

$plugin = itxeb_v0241_read($mainPlugin);
$plugin = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.24.1-dev';", $plugin) ?? $plugin;
itxeb_v0241_write($mainPlugin, $plugin);

$companion = itxeb_v0241_read($companionPlugin);
$companion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.21';", $companion) ?? $companion;
itxeb_v0241_write($companionPlugin, $companion);

if (is_file($livePlugin)) {
    $liveCompanion = itxeb_v0241_read($livePlugin);
    $liveCompanion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.21';", $liveCompanion) ?? $liveCompanion;
    itxeb_v0241_write($livePlugin, $liveCompanion);
}

$filesToLint = [$mainPlugin, $companionPlugin, $screenTemplate];
if (is_file($livePlugin)) { $filesToLint[] = $livePlugin; }
if (is_file($liveScreen)) { $filesToLint[] = $liveScreen; }
foreach ($filesToLint as $file) {
    passthru('php -l ' . escapeshellarg($file), $code);
    if ($code !== 0) {
        fwrite(STDERR, "ERREUR: syntaxe PHP invalide: $file\n");
        exit(1);
    }
}

echo "V0.24.1 dashboard question hotspot filter applied.\n";
