<?php
$root = getcwd();
$hook = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl';

function read_strict(string $file): string {
    $data = file_get_contents($file);
    if (!is_string($data)) {
        fwrite(STDERR, "lecture impossible: $file\n");
        exit(1);
    }
    return $data;
}

function write_if_changed(string $file, string $old, string $new): void {
    if ($old !== $new) {
        file_put_contents($file, $new);
        echo "WRITE: $file\n";
    } else {
        echo "OK: aucun changement $file\n";
    }
}

function replace_once(string &$content, string $old, string $new, string $label): void {
    if (strpos($content, $new) !== false) {
        echo "OK: $label\n";
        return;
    }
    $pos = strpos($content, $old);
    if ($pos === false) {
        fwrite(STDERR, "BLOC INTROUVABLE: $label\n");
        exit(1);
    }
    $content = substr($content, 0, $pos) . $new . substr($content, $pos + strlen($old));
    echo "PATCH: $label\n";
}

$old = read_strict($hook);
$h = $old;

replace_once(
    $h,
    "    /** @var ilIliasTraxEventBridgeCourseUIBridge */\n    private \$bridge;",
    "    /** @var ilIliasTraxEventBridgeCourseUIBridge */\n    private \$bridge;\n    private static bool \$pilotageToolbarAdded = false;",
    'garde anti doublon toolbar'
);

if (strpos($h, 'self::$pilotageToolbarAdded') === false || strpos($h, 'self::$pilotageToolbarAdded = true;') === false) {
    $h = str_replace(
        "    public function modifyGUI(\$a_comp, \$a_part, \$a_par = []): void\n    {\n        try {",
        "    public function modifyGUI(\$a_comp, \$a_part, \$a_par = []): void\n    {\n        try {\n            if (self::\$pilotageToolbarAdded) { return; }",
        $h
    );
    $h = str_replace(
        "                \$toolbar->addButton('Pilotage xAPI', \$this->buildRouterUrl(\$courseRefId, 'showDashboard'));",
        "                \$toolbar->addButton('Pilotage xAPI', \$this->buildRouterUrl(\$courseRefId, 'showDashboard'));\n                self::\$pilotageToolbarAdded = true;",
        $h
    );
    echo "PATCH: activation garde anti doublon\n";
} else {
    echo "OK: garde anti doublon deja active\n";
}

$pattern = "/\s*\$url = \$this->buildRouterUrl\(\$courseRefId, 'showDashboard'\);\s*\$newHtml = \$this->injectCourseEntryButton\(\$html, \$url\);\s*return \$newHtml !== \$html\s*\? \['mode' => ilUIHookPluginGUI::REPLACE, 'html' => \$newHtml\]\s*: \['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''\];/s";
$replacement = "\n        return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];";
$new = preg_replace($pattern, $replacement, $h, 1, $count);
if (!is_string($new)) {
    fwrite(STDERR, "regex suppression encart impossible\n");
    exit(1);
}
if ($count > 0) {
    $h = $new;
    echo "PATCH: suppression injection ancien bloc contenu\n";
} else {
    echo "OK: ancien bloc contenu deja neutralise\n";
}

write_if_changed($hook, $old, $h);

echo "V0.20.1 correctif toolbar/bloc pret\n";
