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

rep(
    $s,
    "        $" . "html = $" . "this->renderMessage()\n            . ($" . "this->renderInnerTabs ? $" . "this->renderInnerTabs($" . "courseRefId, $" . "cmd) : '')\n            . $" . "this->renderView($" . "course, $" . "cmd);\n\n        return $" . "this->renderShell($" . "html, $" . "courseRefId, (string) ($" . "course['course_title'] ?? ''), $" . "cmd);",
    "        $" . "html = $" . "this->renderMessage()\n            . ($" . "this->renderInnerTabs ? $" . "this->renderInnerTabs($" . "courseRefId, $" . "cmd) : '')\n            . $" . "this->renderView($" . "course, $" . "cmd);\n        if ($" . "cmd !== 'showCourseExpert' && $" . "cmd !== 'exportCourseExpertCsv') {\n            $" . "html = $" . "this->simplifyNonExpertWording($" . "html);\n        }\n\n        return $" . "this->renderShell($" . "html, $" . "courseRefId, (string) ($" . "course['course_title'] ?? ''), $" . "cmd);",
    'activation vocabulaire simplifie hors Expert'
);

if (strpos($s, 'private function simplifyNonExpertWording(string $html): string') === false) {
    $method = <<<'PHP'
    private function simplifyNonExpertWording(string $html): string
    {
        $replacements = [
            'Vue synthétique des statements xAPI présents dans TRAX pour ce cours.' => 'Vue synthétique des données d’apprentissage pour ce cours.',
            'statements xAPI présents dans TRAX' => 'données d’apprentissage disponibles',
            'statements retournés par TRAX' => 'données d’apprentissage retournées par la source',
            'statement xAPI TRAX' => 'donnée d’apprentissage',
            'statement xAPI' => 'donnée d’apprentissage',
            'statements xAPI' => 'données d’apprentissage',
            'statement TRAX' => 'donnée d’activité',
            'statements TRAX' => 'données d’activité',
            'Statements TRAX' => 'Données d’apprentissage',
            'Statements xAPI' => 'Données d’apprentissage',
            'Sans statement TRAX' => 'Ressources sans activité',
            'Données xAPI agrégées' => 'Données d’apprentissage regroupées',
            'données xAPI agrégées' => 'données d’apprentissage regroupées',
            'traces xAPI/TRAX' => 'données d’apprentissage',
            'traces xAPI' => 'données d’apprentissage',
            'trace xAPI' => 'donnée d’apprentissage',
            'Trace xAPI' => 'Donnée d’apprentissage',
            'Au moins une trace' => 'Au moins une activité enregistrée',
            'sans trace' => 'sans activité enregistrée',
            'Sans trace' => 'Sans activité enregistrée',
            'Ressources traçables' => 'Ressources suivies',
            'ressources traçables' => 'ressources suivies',
            'ressource traçable' => 'ressource suivie',
            'Aucune ressource traçable détectée' => 'Aucune ressource suivie détectée',
            'Activation xAPI' => 'Activation du suivi d’apprentissage',
            'Activer les traces xAPI pour ce cours' => 'Activer le suivi des activités pour ce cours',
            'Enregistrer la configuration xAPI' => 'Enregistrer la configuration du suivi',
            'Configuration xAPI du cours enregistrée.' => 'Configuration du suivi d’apprentissage enregistrée.',
            'Configuration xAPI du cours réinitialisée.' => 'Configuration du suivi d’apprentissage réinitialisée.',
            'xAPI cours' => 'Suivi du cours',
            'Supervision technique de l’envoi xAPI' => 'État technique du suivi',
            'envois xAPI' => 'envois de données',
            'file locale d’envoi vers TRAX' => 'file d’attente locale d’envoi des données',
            'outbox locale' => 'file d’attente locale',
            'Outbox locale' => 'File d’attente locale',
            'Total outbox' => 'Total file d’attente',
            'TRAX / LRS direct' => 'Source des données d’apprentissage',
            'TRAX/LRS direct' => 'source de données principale',
            'TRAX/LRS' => 'source de données',
            'TRAX et les logs d’envoi' => 'la source de données et les journaux d’envoi',
            'configuration TRAX' => 'configuration de la source de données',
            'Lecture directe de TRAX/LRS' => 'Lecture directe des données d’apprentissage',
            'TRAX' => 'source de données',
            'Lecture LRS' => 'Lecture des données',
            'Lecture directe de source de données/LRS' => 'Lecture directe des données d’apprentissage',
            'Lecture directe de source de données' => 'Lecture directe des données d’apprentissage',
            'Lecture des données/LRS' => 'Lecture des données',
            'État LRS' => 'État de la source',
            'Pages LRS' => 'Lots de données lus',
            'Pagination LRS' => 'Lecture par lots',
            'More LRS restant' => 'Données restantes à lire',
            'Activité cours xAPI' => 'Activité du cours',
            'Source du suivi xAPI' => 'Source du suivi d’apprentissage',
            'GET /statements' => 'lecture des données',
            'pagination' => 'lecture par lots',
            'Pagination' => 'Lecture par lots',
            'payload' => 'données envoyées',
            'Payload' => 'Données envoyées',
            'Résumé données envoyées' => 'Résumé des données',
            'Date UTC' => 'Date',
            'Répartition des verbes' => 'Répartition des actions',
            'Verbes xAPI' => 'Types d’actions',
            'verbes xAPI' => 'types d’actions',
            'verbes' => 'actions',
            'Verbes' => 'Actions',
            'pseudonymes techniques' => 'codes anonymes',
            'Pseudonymes techniques' => 'Codes anonymes',
            'HTTP ' => 'Réponse ',
            '>course_ref_id<' => '>Identifiant du cours<',
            '>course_obj_id<' => '>Identifiant interne du cours<',
            '<th>ref_id</th>' => '<th>Identifiant page</th>',
            '<th>obj_id</th>' => '<th>Identifiant interne</th>',
        ];
        return strtr($html, $replacements);
    }

PHP;
    $marker = "    /** @param array<string,mixed> $" . "course */\n    private function renderCourseSummary(array $" . "course): string";
    $pos = strpos($s, $marker);
    if ($pos === false) {
        fwrite(STDERR, "Point insertion simplifyNonExpertWording introuvable\n");
        exit(1);
    }
    $s = substr($s, 0, $pos) . $method . substr($s, $pos);
    echo "PATCH: ajout simplifyNonExpertWording\n";
} else {
    echo "OK: simplifyNonExpertWording deja presente\n";
}

wf($screen, $old, $s);
set_version($mainPlugin, '0.20.2-dev');
set_version($companionPlugin, '0.8.2');

if (!copy($screen, $liveScreen)) {
    fwrite(STDERR, "Copie live impossible: $liveScreen\n");
    exit(1);
}
if (!copy($companionPlugin, $livePlugin)) {
    fwrite(STDERR, "Copie live impossible: $livePlugin\n");
    exit(1);
}
echo "COPY: screen + plugin companion live\n";
echo "V0.20.2 appliquee : vocabulaire simplifie hors onglet Expert\n";
