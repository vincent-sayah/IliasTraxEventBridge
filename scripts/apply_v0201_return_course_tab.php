<?php
$root = getcwd();
$router = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php.tpl';
$servicesRoot = dirname(dirname(dirname($root)));
$live = $servicesRoot . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php';

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

function rep(string &$s, string $old, string $new, string $label): void {
    if (strpos($s, $new) !== false) {
        echo "OK: $label\n";
        return;
    }
    $pos = strpos($s, $old);
    if ($pos === false) {
        fwrite(STDERR, "BLOC INTROUVABLE: $label\n");
        exit(1);
    }
    $s = substr($s, 0, $pos) . $new . substr($s, $pos + strlen($old));
    echo "PATCH: $label\n";
}

$old = rf($router);
$r = $old;

rep(
    $r,
    "            $" . "tabs->addTab('itxeb_xapi_config', 'Configuration', $" . "this->link('showConfig'));",
    "            $" . "tabs->addTab('itxeb_xapi_config', 'Configuration', $" . "this->link('showConfig'));\n            $" . "tabs->addTab('itxeb_xapi_return_course', 'Retour contenu du cours', $" . "this->courseUrl($" . "this->getCourseRefId()));",
    'onglet retour contenu du cours'
);

rep(
    $r,
    "            if (!$" . "this->isExportCommand($" . "cmd)) {\n                $" . "html = $" . "this->renderCourseReturnBar($" . "this->getCourseRefId()) . $" . "html;\n            }",
    "            // Le retour au cours est maintenant affiché au même niveau que les onglets xAPI.\n            // On ne préfixe plus la page avec un bandeau séparé.",
    'suppression bandeau retour separe'
);

wf($router, $old, $r);

if (!copy($router, $live)) {
    fwrite(STDERR, "Copie live impossible: $live\n");
    exit(1);
}
echo "COPY: $router -> $live\n";
echo "V0.20.1 retour cours deplace dans les onglets\n";
