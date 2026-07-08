#!/usr/bin/env bash
set -euo pipefail

root=$(pwd)
f="$root/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl"
services="$(dirname "$(dirname "$(dirname "$root")")")"
live="$services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
live_plugin="$services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php"

test -f "$root/plugin.php" || { echo "ERREUR: lancer depuis la racine du plugin" >&2; exit 1; }
test -f "$f" || { echo "ERREUR: template absent" >&2; exit 1; }

tmp=$(mktemp /tmp/itxeb_v0191_XXXX.php)
cat > "$tmp" <<'PHP'
<?php
$file = $argv[1];
$content = file_get_contents($file);
if (!is_string($content)) { fwrite(STDERR, "lecture impossible\n"); exit(1); }
$original = $content;

function replace_block_once(string &$content, string $old, string $new, string $label): void {
    $pos = strpos($content, $old);
    if ($pos === false) {
        fwrite(STDERR, "bloc introuvable: " . $label . PHP_EOL);
        exit(1);
    }
    $content = substr($content, 0, $pos) . $new . substr($content, $pos + strlen($old));
    echo "PATCH: " . $label . PHP_EOL;
}

if (strpos($content, 'itxeb_ai_compare_ids') === false) {
    $old = <<<'OLD'
        $compareA = trim($this->requestValue($_GET, 'itxeb_ai_compare_a'));
        if ($compareA === '') { $compareA = trim($this->requestValue($_POST, 'itxeb_ai_compare_a')); }
        if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $compareA)) { $compareA = ''; }
        $compareB = trim($this->requestValue($_GET, 'itxeb_ai_compare_b'));
        if ($compareB === '') { $compareB = trim($this->requestValue($_POST, 'itxeb_ai_compare_b')); }
        if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $compareB)) { $compareB = ''; }
OLD;
    $new = <<<'NEW'
        $compareIds = [];
        $rawCompareIds = $_POST['itxeb_ai_compare_ids'] ?? null;
        $compareSelectionSubmitted = is_array($rawCompareIds);
        if (is_array($rawCompareIds)) {
            foreach ($rawCompareIds as $rawId) {
                if (!is_scalar($rawId)) { continue; }
                $candidate = trim((string) $rawId);
                if (preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $candidate) && !in_array($candidate, $compareIds, true)) {
                    $compareIds[] = $candidate;
                }
            }
        }

        $compareA = '';
        $compareB = '';
        if ($compareIds !== []) {
            $compareA = (string) ($compareIds[0] ?? '');
            $compareB = (string) ($compareIds[1] ?? '');
        } else {
            $compareA = trim($this->requestValue($_GET, 'itxeb_ai_compare_a'));
            if ($compareA === '') { $compareA = trim($this->requestValue($_POST, 'itxeb_ai_compare_a')); }
            if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $compareA)) { $compareA = ''; }
            $compareB = trim($this->requestValue($_GET, 'itxeb_ai_compare_b'));
            if ($compareB === '') { $compareB = trim($this->requestValue($_POST, 'itxeb_ai_compare_b')); }
            if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $compareB)) { $compareB = ''; }
            foreach ([$compareA, $compareB] as $candidate) {
                if ($candidate !== '' && !in_array($candidate, $compareIds, true)) { $compareIds[] = $candidate; }
            }
        }
NEW;
    replace_block_once($content, $old, $new, 'selection comparaison par cases');
} else {
    echo "OK: selection par cases deja presente" . PHP_EOL;
}

if (strpos($content, 'itxeb-ai-compare-check') === false) {
    $old = <<<'OLD'
            $compareAction = $recordId !== ''
                ? '<div class="itxeb-ai-compare-actions">'
                    . '<a class="btn btn-default btn-xs' . ($recordId === $compareA ? ' itxeb-active-compare' : '') . '" href="' . $this->esc($compareAUrl) . '">Analyse A</a>'
                    . '<a class="btn btn-default btn-xs' . ($recordId === $compareB ? ' itxeb-active-compare' : '') . '" href="' . $this->esc($compareBUrl) . '">Analyse B</a>'
                    . '</div>'
                : '-';
OLD;
    $new = <<<'NEW'
            $compareChecked = $recordId !== '' && in_array($recordId, $compareIds, true);
            $compareAction = $recordId !== ''
                ? '<label class="itxeb-ai-compare-check"><input form="itxeb-ai-compare-form" type="checkbox" name="itxeb_ai_compare_ids[]" value="' . $this->esc($recordId) . '"' . ($compareChecked ? ' checked="checked"' : '') . '> Sélectionner</label>'
                : '-';
NEW;
    replace_block_once($content, $old, $new, 'remplacement boutons Analyse A/B');
} else {
    echo "OK: cases deja presentes" . PHP_EOL;
}

if (strpos($content, 'itxeb-ai-compare-submit') === false) {
    $old = <<<'OLD'
        $html .= '</tbody></table></div>';
OLD;
    $new = <<<'NEW'
        $html .= '</tbody></table></div>';
        $compareFormAction = $this->currentUrlWith([
            'itxeb_cui_cmd' => 'showCourseAnalysis',
            'itxeb_course_ref_id' => (string) $courseRefId,
            'itxeb_period_days' => (string) $this->getPeriodDays(),
            'itxeb_filter_ref_id' => (string) $this->getSelectedResourceRefId(),
            'itxeb_filter_obj_type' => $this->getSelectedObjectType(),
            'itxeb_ai_history_id' => '',
            'itxeb_ai_compare_a' => '',
            'itxeb_ai_compare_b' => '',
        ]);
        $html .= '<form id="itxeb-ai-compare-form" class="itxeb-ai-compare-submit" method="post" action="' . $this->esc($compareFormAction) . '">'
            . '<input type="hidden" name="itxeb_cui_cmd" value="showCourseAnalysis">'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
            . '<input type="hidden" name="itxeb_period_days" value="' . $this->esc((string) $this->getPeriodDays()) . '">'
            . '<input type="hidden" name="itxeb_filter_ref_id" value="' . $this->esc((string) $this->getSelectedResourceRefId()) . '">'
            . '<input type="hidden" name="itxeb_filter_obj_type" value="' . $this->esc($this->getSelectedObjectType()) . '">'
            . '<button class="btn btn-primary btn-xs" type="submit">Comparer les 2 analyses sélectionnées</button> '
            . '<span class="itxeb-ai-compare-help">Cochez exactement deux analyses dans le tableau.</span>'
            . '</form>';
NEW;
    replace_block_once($content, $old, $new, 'formulaire bouton unique comparer');
} else {
    echo "OK: formulaire comparer deja present" . PHP_EOL;
}

if (strpos($content, 'Cochez exactement deux analyses IA historisées') === false) {
    $old = <<<'OLD'
            if ($compareA === '' || $compareB === '') {
                $html .= '<div class="itxeb-cui-alert">Sélectionnez une analyse A et une analyse B dans le tableau d’historique.</div>';
            } elseif ($compareA === $compareB) {
OLD;
    $new = <<<'NEW'
            if ($compareSelectionSubmitted && count($compareIds) !== 2) {
                $html .= '<div class="itxeb-cui-alert itxeb-cui-error">Cochez exactement deux analyses IA historisées pour lancer la comparaison.</div>';
            } elseif ($compareA === '' || $compareB === '') {
                $html .= '<div class="itxeb-cui-alert">Cochez deux analyses IA historisées dans le tableau, puis cliquez sur <strong>Comparer les 2 analyses sélectionnées</strong>.</div>';
            } elseif ($compareA === $compareB) {
NEW;
    replace_block_once($content, $old, $new, 'messages comparaison');
} else {
    echo "OK: messages deja presents" . PHP_EOL;
}

if (strpos($content, '.itxeb-ai-compare-check') === false) {
    $css = '.itxeb-ai-compare-check{display:inline-flex;gap:5px;align-items:center;font-weight:400;white-space:nowrap}.itxeb-ai-compare-check input{margin:0}.itxeb-ai-compare-submit{margin:10px 0 4px}.itxeb-ai-compare-help{margin-left:8px;color:#666}';
    $content = str_replace('</style>', $css . '</style>', $content);
    echo "PATCH: styles cases a cocher" . PHP_EOL;
} else {
    echo "OK: styles deja presents" . PHP_EOL;
}

if ($content !== $original) {
    file_put_contents($file, $content);
    echo "WRITE: " . $file . PHP_EOL;
} else {
    echo "OK: aucun changement source" . PHP_EOL;
}
PHP

php "$tmp" "$f"
rm -f "$tmp"

php -l "$f"
cp -f "$f" "$live"
php -l "$live"

sed -i "s/\$version = '[^']*';/\$version = '0.19.1-dev';/" "$root/plugin.php"
sed -i "s/\$version = '[^']*';/\$version = '0.7.1';/" "$root/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl"
sed -i "s/\$version = '[^']*';/\$version = '0.7.1';/" "$live_plugin"

php -l "$root/plugin.php"
php -l "$root/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl"
php -l "$live_plugin"

echo "V0.19.1 appliquee : cases a cocher actives"
