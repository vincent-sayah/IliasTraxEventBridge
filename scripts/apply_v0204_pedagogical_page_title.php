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

$replacements = [
    "'Suivi xAPI — ' . $courseTitle" => "'Pilotage pédagogique — ' . $courseTitle",
    "'Suivi xAPI — configuration du cours'" => "'Pilotage pédagogique — configuration du cours'",
    "<h1>Suivi xAPI — " => "<h1>Pilotage pédagogique — ",
    "<title>Suivi xAPI" => "<title>Pilotage pédagogique",
];

foreach ($replacements as $from => $to) {
    $s = str_replace($from, $to, $s);
}

if ($s !== $before) {
    echo "PATCH: titre principal remplace par Pilotage pedagogique\n";
} else {
    echo "OK: titre principal deja en vocabulaire pedagogique\n";
}

wf($screen, $old, $s);
set_version($mainPlugin, '0.20.4-dev');
set_version($companionPlugin, '0.8.4');

if (!copy($screen, $liveScreen)) {
    fwrite(STDERR, "Copie live impossible: $liveScreen\n");
    exit(1);
}
if (!copy($companionPlugin, $livePlugin)) {
    fwrite(STDERR, "Copie live impossible: $livePlugin\n");
    exit(1);
}
echo "COPY: screen + companion plugin live\n";
echo "V0.20.4 appliquee : titre Pilotage pedagogique\n";
