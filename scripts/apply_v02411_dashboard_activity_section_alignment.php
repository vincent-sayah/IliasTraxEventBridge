<?php
/**
 * V0.24.11-dev
 * Corrige l'alignement du titre "Activité dans le temps".
 *
 * Cause : V0.24.10 rendait le bloc activité dans un <section> sans la classe
 * itxeb-cui-section. Comme ce bloc est enfant de la section Dashboard, la règle
 * CSS globale le plaçait en colonne contenu, d'où le décalage du titre.
 *
 * Correctif : le bloc activité devient une vraie section ILIAS alignée :
 * - classe itxeb-cui-section ajoutée au <section> ;
 * - largeur forcée sur toute la grille parent ;
 * - première colonne fixée à 260px, comme les autres titres ;
 * - correction de la valeur CSS .95fr si nécessaire.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$screenTemplate = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$mainPlugin = $root . '/plugin.php';
$companionPlugin = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$liveScreen = '/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = '/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php';

function itxeb_read_02411(string $path): string
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

function itxeb_write_02411(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: écriture impossible: $path\n");
        exit(1);
    }
    echo "WRITE: $path\n";
}

function itxeb_patch_screen_02411(string $screen): string
{
    if (strpos($screen, 'class ilIliasTraxEventBridgeCourseUIScreen') === false) {
        fwrite(STDERR, "ERREUR: classe ilIliasTraxEventBridgeCourseUIScreen absente.\n");
        exit(1);
    }
    if (strpos($screen, 'renderDashboardActivityTopLayout') === false) {
        fwrite(STDERR, "ERREUR: V0.24.10 doit être appliquée avant V0.24.11.\n");
        exit(1);
    }

    $changed = 0;

    // Le bloc activité doit être une section ILIAS pour récupérer le même modèle
    // de grille que "Comparaison entre périodes" et les autres blocs.
    $before = $screen;
    $screen = str_replace(
        '<section class="itxeb-dashboard-activity-final"><h3>Activité dans le temps</h3>',
        '<section class="itxeb-cui-section itxeb-dashboard-activity-final"><h3>Activité dans le temps</h3>',
        $screen
    );
    if ($screen !== $before) {
        $changed++;
        echo "PATCH: section Activité convertie en itxeb-cui-section\n";
    }

    // Idempotence : si une exécution précédente a déjà ajouté la classe, on ne
    // duplique rien. On corrige seulement le CSS de la grille.
    $screen = str_replace(
        '#itxeb-course-ui-screen .itxeb-dashboard-activity-final{display:grid;grid-template-columns:minmax(190px,260px) minmax(0,1fr);',
        '#itxeb-course-ui-screen .itxeb-dashboard-activity-final.itxeb-cui-section{grid-column:1 / -1!important;display:grid;grid-template-columns:260px minmax(0,1fr);',
        $screen,
        $count
    );
    if ($count > 0) {
        $changed += $count;
        echo "PATCH: grille Activité alignée sur la colonne titre 260px\n";
    }

    $screen = str_replace(
        '#itxeb-course-ui-screen .itxeb-dashboard-activity-final.itxeb-cui-section{display:grid;grid-template-columns:minmax(190px,260px) minmax(0,1fr);',
        '#itxeb-course-ui-screen .itxeb-dashboard-activity-final.itxeb-cui-section{grid-column:1 / -1!important;display:grid;grid-template-columns:260px minmax(0,1fr);',
        $screen,
        $count2
    );
    if ($count2 > 0) {
        $changed += $count2;
        echo "PATCH: grille Activité déjà sectionnée réalignée\n";
    }

    // Sécurise le cas où le style a déjà été modifié autrement.
    $screen = preg_replace(
        '~#itxeb-course-ui-screen \\.itxeb-dashboard-activity-final(?:\\.itxeb-cui-section)?\{([^}]*)grid-template-columns:minmax\(190px,260px\) minmax\(0,1fr\);~',
        '#itxeb-course-ui-screen .itxeb-dashboard-activity-final.itxeb-cui-section{grid-column:1 / -1!important;display:grid;grid-template-columns:260px minmax(0,1fr);',
        $screen,
        -1,
        $regexCount
    ) ?? $screen;
    if ($regexCount > 0) {
        $changed += $regexCount;
        echo "PATCH: grille Activité réalignée par regex\n";
    }

    // Correction d'une faute possible introduite dans les essais précédents.
    $screen = str_replace('minmax(420px,95fr)', 'minmax(420px,.95fr)', $screen, $typoCount);
    if ($typoCount > 0) {
        $changed += $typoCount;
        echo "PATCH: typo CSS 95fr corrigée en .95fr\n";
    }

    // Marqueur de contrôle.
    if (strpos($screen, 'ITXEB V0.24.11 activity title aligned') === false) {
        $screen = str_replace(
            'ITXEB V0.24.10 final activity title alignment',
            'ITXEB V0.24.11 activity title aligned',
            $screen,
            $markerCount
        );
        if ($markerCount > 0) {
            $changed += $markerCount;
            echo "PATCH: marqueur V0.24.11 ajouté\n";
        }
    }

    if (strpos($screen, '<section class="itxeb-cui-section itxeb-dashboard-activity-final"><h3>Activité dans le temps</h3>') === false) {
        fwrite(STDERR, "ERREUR: section Activité alignée introuvable après patch.\n");
        exit(1);
    }

    if (strpos($screen, 'grid-template-columns:260px minmax(0,1fr)') === false) {
        fwrite(STDERR, "ERREUR: grille Activité 260px introuvable après patch.\n");
        exit(1);
    }

    if ($changed === 0) {
        echo "SKIP: V0.24.11 déjà appliquée\n";
    }

    return $screen;
}

$screen = itxeb_read_02411($screenTemplate);
$screen = itxeb_patch_screen_02411($screen);
itxeb_write_02411($screenTemplate, $screen);

if (is_file($liveScreen)) {
    itxeb_write_02411($liveScreen, $screen);
}

$plugin = itxeb_read_02411($mainPlugin);
$plugin = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.24.11-dev';", $plugin) ?? $plugin;
itxeb_write_02411($mainPlugin, $plugin);

$companion = itxeb_read_02411($companionPlugin);
$companion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.31';", $companion) ?? $companion;
itxeb_write_02411($companionPlugin, $companion);

if (is_file($livePlugin)) {
    $liveCompanion = itxeb_read_02411($livePlugin);
    $liveCompanion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.31';", $liveCompanion) ?? $liveCompanion;
    itxeb_write_02411($livePlugin, $liveCompanion);
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

echo "V0.24.11 dashboard activity title alignment applied.\n";
