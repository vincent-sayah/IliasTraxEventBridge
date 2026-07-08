#!/usr/bin/env bash
set -euo pipefail

root=$(pwd)
f="$root/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl"
live="$(dirname "$(dirname "$(dirname "$root")")")/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
live_plugin="$(dirname "$(dirname "$(dirname "$root")")")/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php"

test -f "$root/plugin.php" || { echo "ERREUR: lancer depuis la racine du plugin" >&2; exit 1; }
test -f "$f" || { echo "ERREUR: template absent" >&2; exit 1; }

php -r '
$f=$argv[1];
$c=file_get_contents($f);
if ($c===false) { fwrite(STDERR,"lecture impossible\n"); exit(1); }
$old=$c;
$sel=<<<TXT
        \$compareIds = [];
        \$rawCompareIds = \$this->requestRawValue(\$_POST, "itxeb_ai_compare_ids");
        \$compareSelectionSubmitted = is_array(\$rawCompareIds);
        if (is_array(\$rawCompareIds)) {
            foreach (\$rawCompareIds as \$rawId) {
                if (!is_scalar(\$rawId)) { continue; }
                \$candidate = trim((string) \$rawId);
                if (preg_match("/^[a-zA-Z0-9_-]{1,80}$/", \$candidate) && !in_array(\$candidate, \$compareIds, true)) { \$compareIds[] = \$candidate; }
            }
        }
        \$compareA = "";
        \$compareB = "";
        if (\$compareIds !== []) {
            \$compareA = (string) (\$compareIds[0] ?? "");
            \$compareB = (string) (\$compareIds[1] ?? "");
        } else {
            \$compareA = trim(\$this->requestValue(\$_GET, "itxeb_ai_compare_a"));
            if (\$compareA === "") { \$compareA = trim(\$this->requestValue(\$_POST, "itxeb_ai_compare_a")); }
            if (!preg_match("/^[a-zA-Z0-9_-]{1,80}$/", \$compareA)) { \$compareA = ""; }
            \$compareB = trim(\$this->requestValue(\$_GET, "itxeb_ai_compare_b"));
            if (\$compareB === "") { \$compareB = trim(\$this->requestValue(\$_POST, "itxeb_ai_compare_b")); }
            if (!preg_match("/^[a-zA-Z0-9_-]{1,80}$/", \$compareB)) { \$compareB = ""; }
            foreach ([\$compareA, \$compareB] as \$candidate) { if (\$candidate !== "" && !in_array(\$candidate, \$compareIds, true)) { \$compareIds[] = \$candidate; } }
        }
TXT;
if (strpos($c,"itxeb_ai_compare_ids")===false) {
  $c=preg_replace("~        \\\$compareA = trim\\(\\\$this->requestValue\\(\\\$_GET, '\''itxeb_ai_compare_a'\''\\)\\);.*?        if \\(!preg_match\\('\''/\\^\\[a-zA-Z0-9_-\\]\\{1,80\\}\\$/'\'', \\\$compareB\\)\\) \\{ \\\$compareB = '\'''\''; \\}\n~s", $sel."\n", $c, 1, $n);
  if ($n!==1) { fwrite(STDERR,"bloc selection introuvable\n"); exit(1); }
}
$check=<<<TXT
            \$compareChecked = \$recordId !== "" && in_array(\$recordId, \$compareIds, true);
            \$compareAction = \$recordId !== ""
                ? "<label class=\"itxeb-ai-compare-check\"><input form=\"itxeb-ai-compare-form\" type=\"checkbox\" name=\"itxeb_ai_compare_ids[]\" value=\"" . \$this->esc(\$recordId) . "\"" . (\$compareChecked ? " checked=\"checked\"" : "") . "> Sélectionner</label>"
                : "-";
TXT;
if (strpos($c,"itxeb-ai-compare-check")===false) {
  $c=preg_replace("~            \\\$compareAction = \\\$recordId !== '\''\''.*?                : '\''-'\'';\n~s", $check, $c, 1, $n);
  if ($n!==1) { fwrite(STDERR,"bloc action introuvable\n"); exit(1); }
}
$form=<<<TXT
        \$html .= "</tbody></table></div>";
        \$compareFormAction = \$this->currentUrlWith([
            "itxeb_cui_cmd" => "showCourseAnalysis",
            "itxeb_course_ref_id" => (string) \$courseRefId,
            "itxeb_period_days" => (string) \$this->getPeriodDays(),
            "itxeb_filter_ref_id" => (string) \$this->getSelectedResourceRefId(),
            "itxeb_filter_obj_type" => \$this->getSelectedObjectType(),
            "itxeb_ai_history_id" => "",
            "itxeb_ai_compare_a" => "",
            "itxeb_ai_compare_b" => "",
        ]);
        \$html .= "<form id=\"itxeb-ai-compare-form\" class=\"itxeb-ai-compare-submit\" method=\"post\" action=\"" . \$this->esc(\$compareFormAction) . "\">"
            . "<input type=\"hidden\" name=\"itxeb_cui_cmd\" value=\"showCourseAnalysis\">"
            . "<input type=\"hidden\" name=\"itxeb_course_ref_id\" value=\"" . \$this->esc((string) \$courseRefId) . "\">"
            . "<input type=\"hidden\" name=\"itxeb_period_days\" value=\"" . \$this->esc((string) \$this->getPeriodDays()) . "\">"
            . "<input type=\"hidden\" name=\"itxeb_filter_ref_id\" value=\"" . \$this->esc((string) \$this->getSelectedResourceRefId()) . "\">"
            . "<input type=\"hidden\" name=\"itxeb_filter_obj_type\" value=\"" . \$this->esc(\$this->getSelectedObjectType()) . "\">"
            . "<button class=\"btn btn-primary btn-xs\" type=\"submit\">Comparer les 2 analyses sélectionnées</button> "
            . "<span class=\"itxeb-ai-compare-help\">Cochez exactement deux analyses dans le tableau.</span>"
            . "</form>";

        if (\$compareA !== "" || \$compareB !== "") {
TXT;
if (strpos($c,"itxeb-ai-compare-submit")===false) {
  $c=str_replace("        \$html .= '\''</tbody></table></div>'\'';\n\n        if (\$compareA !== '\'''\'' || \$compareB !== '\'''\'') {", $form, $c);
}
$c=str_replace("            if (\$compareA === '\'''\'' || \$compareB === '\'''\'') {\n                \$html .= '\''<div class=\"itxeb-cui-alert\">Sélectionnez une analyse A et une analyse B dans le tableau d’historique.</div>'\'';\n            } elseif (\$compareA === \$compareB) {", "            if (\$compareSelectionSubmitted && count(\$compareIds) !== 2) {\n                \$html .= '\''<div class=\"itxeb-cui-alert itxeb-cui-error\">Cochez exactement deux analyses IA historisées pour lancer la comparaison.</div>'\'';\n            } elseif (\$compareA === '\'''\'' || \$compareB === '\'''\'') {\n                \$html .= '\''<div class=\"itxeb-cui-alert\">Cochez deux analyses IA historisées dans le tableau, puis cliquez sur <strong>Comparer les 2 analyses sélectionnées</strong>.</div>'\'';\n            } elseif (\$compareA === \$compareB) {", $c);
if (strpos($c,".itxeb-ai-compare-check")===false) {
  $css=".itxeb-ai-compare-check{display:inline-flex;gap:5px;align-items:center;font-weight:400;white-space:nowrap}.itxeb-ai-compare-check input{margin:0}.itxeb-ai-compare-submit{margin:10px 0 4px}.itxeb-ai-compare-help{margin-left:8px;color:#666}";
  $c=str_replace("</style>",$css."</style>",$c);
}
if ($c!==$old) { file_put_contents($f,$c); echo "WRITE: $f\n"; } else { echo "OK: deja applique\n"; }
' "$f"

php -l "$f"
cp -f "$f" "$live"
php -l "$live"
sed -i "s/\$version = '[^']*';/\$version = '0.19.1-dev';/" "$root/plugin.php"
sed -i "s/\$version = '[^']*';/\$version = '0.7.1';/" "$root/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl"
sed -i "s/\$version = '[^']*';/\$version = '0.7.1';/" "$live_plugin"
php -l "$root/plugin.php"
php -l "$root/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl"
php -l "$live_plugin"
echo "V0.19.1 appliquee"
