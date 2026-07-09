<?php
$root = getcwd();
$factory = $root . '/classes/class.ilIliasTraxEventBridgeStatementFactory.php';
if (!is_file($factory)) {
    fwrite(STDERR, "Fichier introuvable: $factory\n");
    exit(1);
}
$s = file_get_contents($factory);
if (!is_string($s)) {
    fwrite(STDERR, "Lecture impossible: $factory\n");
    exit(1);
}
$old = $s;
$patterns = [
    "context(\$record, 'test_question_resul ', 'tst')",
    "context(\$record, 'test_question_resul', 'tst')",
    "context(\$record, 'test_question_resul ', 'tst')",
];
foreach ($patterns as $bad) {
    $s = str_replace($bad, "context(\$record, 'test_question_result', 'tst')", $s);
}
if (strpos($s, "context(\$record, 'test_question_result', 'tst')") === false) {
    fwrite(STDERR, "Contexte test_question_result introuvable après correction.\n");
    exit(1);
}
if ($s !== $old) {
    file_put_contents($factory, $s);
    echo "PATCH: source_event des questions corrige en test_question_result\n";
} else {
    echo "OK: source_event des questions deja correct\n";
}
echo "V0.21.1 correctif source_event question applique.\n";
