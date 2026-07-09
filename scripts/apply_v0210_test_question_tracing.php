<?php
$root = getcwd();
$plugin = $root . '/plugin.php';
$extractor = $root . '/classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php';

if (!is_file($plugin) || !is_file($extractor)) {
    fwrite(STDERR, "Répertoire plugin invalide ou classe extracteur absente.\n");
    exit(1);
}

$pluginText = file_get_contents($plugin);
if (!is_string($pluginText)) {
    fwrite(STDERR, "Lecture plugin.php impossible.\n");
    exit(1);
}
$pluginText2 = preg_replace('/\$version = \'[^\']*\';/', '$version = \'0.21.0-dev\';', $pluginText, 1);
if (!is_string($pluginText2)) {
    fwrite(STDERR, "Mise à jour version impossible.\n");
    exit(1);
}
if ($pluginText2 !== $pluginText) {
    file_put_contents($plugin, $pluginText2);
    echo "WRITE: plugin.php\n";
}

echo "V0.21.0 préparation installée.\n";
echo "Étape suivante: diagnostic du schéma ILIAS des tests avant activation des traces par question.\n";
