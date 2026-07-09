<?php
$root = getcwd();
$screen = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$mainPlugin = $root . '/plugin.php';
$companionPlugin = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$servicesRoot = dirname(dirname(dirname($root)));
$liveRoot = $servicesRoot . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$liveScreen = $liveRoot . '/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = $liveRoot . '/plugin.php';

function rf(string $file): string {
    $s = file_get_contents($file);
    if (!is_string($s)) {
        fwrite(STDERR, "Lecture impossible: $file\n");
        exit(1);
    }
    return $s;
}
function wf(string $file, string $old, string $new): void {
    if ($old !== $new) {
        file_put_contents($file, $new);
        echo "WRITE: $file\n";
    } else {
        echo "OK: aucun changement $file\n";
    }
}
function set_version(string $file, string $version): void {
    $old = rf($file);
    $new = preg_replace("/\\$version = '[^']*';/", "\$version = '$version';", $old, 1);
    if (!is_string($new)) {
        fwrite(STDERR, "Version impossible: $file\n");
        exit(1);
    }
    wf($file, $old, $new);
}

$old = rf($screen);
$s = $old;

$before = $s;
$s = str_replace(
    '<section class="itxeb-cui-section itxeb-ai-analysis-action itxeb-trainer-card"><h3>Analyse IA du cours</h3>',
    '<section class="itxeb-cui-section itxeb-ai-analysis-action"><h3>Analyse IA du cours</h3>',
    $s
);
$s = str_replace(
    '<section class="itxeb-cui-section itxeb-ai-analysis-action itxeb-trainer-card"><h3>Analyse IA du cours</h3>',
    '<section class="itxeb-cui-section itxeb-ai-analysis-action"><h3>Analyse IA du cours</h3>',
    $s
);
if ($s !== $before) { echo "PATCH: suppression barre bleue carte Analyse IA\n"; } else { echo "OK: barre bleue deja absente\n"; }

$patterns = [
    "/\s*\. '<p>Génère une synthèse pédagogique à partir des données xAPI agrégées de la période sélectionnée\. En anonymisation stricte, aucun nom, courriel ou identité nominative apprenant n’est envoyé\.<\/p>'/u",
    "/\s*\. '<p>Génère une synthèse pédagogique à partir des données d’apprentissage regroupées de la période sélectionnée\. En anonymisation stricte, aucun nom, courriel ou identité nominative apprenant n’est envoyé\.<\/p>'/u",
    "/\s*\. '<p>Génère une synthèse pédagogique à partir des données d’apprentissage regroupées de la période sélectionnée\.[^']*<\/p>'/u",
];
$replaced = false;
foreach ($patterns as $pattern) {
    $new = preg_replace($pattern, "\n            . '<p>Génère une synthèse pédagogique.</p>'", $s, 1, $count);
    if (!is_string($new)) {
        fwrite(STDERR, "Erreur regex texte court Analyse IA\n");
        exit(1);
    }
    if ($count > 0) {
        $s = $new;
        $replaced = true;
        break;
    }
}
echo $replaced ? "PATCH: texte court Analyse IA du cours\n" : "OK: texte Analyse IA deja court\n";

$removePatterns = [
    "/\s*\. '<p style=\"color:#555\">Cet onglet regroupe uniquement les fonctions d’analyse IA\. Les traces xAPI\/TRAX ne sont pas modifiées\.<\/p>'/u",
    "/\s*\. '<p style=\"color:#555\">Cet onglet regroupe uniquement les fonctions d’analyse IA\. Les données d’apprentissage ne sont pas modifiées\.<\/p>'/u",
    "/\s*\. '<p style=\"color:#555\">Cet onglet regroupe uniquement les fonctions d’analyse IA\.[^']*<\/p>'/u",
];
$removed = false;
foreach ($removePatterns as $pattern) {
    $new = preg_replace($pattern, '', $s, 1, $count);
    if (!is_string($new)) {
        fwrite(STDERR, "Erreur regex suppression phrase onglet IA\n");
        exit(1);
    }
    if ($count > 0) {
        $s = $new;
        $removed = true;
        break;
    }
}
echo $removed ? "PATCH: suppression phrase onglet IA\n" : "OK: phrase onglet IA deja absente\n";

wf($screen, $old, $s);
set_version($mainPlugin, '0.20.3-dev');
set_version($companionPlugin, '0.8.3');

if (!copy($screen, $liveScreen)) {
    fwrite(STDERR, "Copie live impossible: $liveScreen\n");
    exit(1);
}
if (!copy($companionPlugin, $livePlugin)) {
    fwrite(STDERR, "Copie live impossible: $livePlugin\n");
    exit(1);
}
echo "COPY: screen + companion plugin live\n";
echo "V0.20.3 appliquee : nettoyage onglet Analyse IA\n";
