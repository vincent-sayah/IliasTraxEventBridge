#!/usr/bin/env bash
set -euo pipefail

ILIAS_ROOT="${ILIAS_ROOT:-/var/www/ilias}"
PLUGIN_ROOT="${PLUGIN_ROOT:-$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge}"
COMPANION_ROOT="$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI"
TEMPLATE_SCREEN="$PLUGIN_ROOT/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl"
ACTIVE_SCREEN="$COMPANION_ROOT/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
WRAPPER="$PLUGIN_ROOT/scripts/install_course_ui_companion_with_standalone_fix.sh"

print_title() {
  printf '\n==== %s ====\n' "$1"
}

check_file() {
  local label="$1"
  local path="$2"
  if [[ -f "$path" ]]; then
    printf 'OK   %-28s %s\n' "$label" "$path"
  else
    printf 'MISS %-28s %s\n' "$label" "$path"
  fi
}

check_marker() {
  local label="$1"
  local marker="$2"
  local file="$3"
  if [[ ! -f "$file" ]]; then
    printf 'MISS %-34s target file missing\n' "$label"
    return 0
  fi
  if grep -q "$marker" "$file"; then
    printf 'OK   %-34s %s\n' "$label" "$marker"
  else
    printf 'MISS %-34s %s\n' "$label" "$marker"
  fi
}

print_title "V0.13 companion UI audit"
printf 'ILIAS_ROOT     : %s\n' "$ILIAS_ROOT"
printf 'PLUGIN_ROOT    : %s\n' "$PLUGIN_ROOT"
printf 'COMPANION_ROOT : %s\n' "$COMPANION_ROOT"

print_title "Files"
check_file "template screen" "$TEMPLATE_SCREEN"
check_file "active screen" "$ACTIVE_SCREEN"
check_file "install wrapper" "$WRAPPER"

print_title "Plugin branch and version"
if [[ -d "$PLUGIN_ROOT/.git" ]]; then
  git -C "$PLUGIN_ROOT" branch --show-current || true
  grep -n '\$version' "$PLUGIN_ROOT/plugin.php" || true
else
  echo "No .git directory found in plugin root."
fi

print_title "Active V0.12 markers"
check_marker "pedagogical synthesis" "renderPedagogicalSynthesis" "$ACTIVE_SCREEN"
check_marker "struggling learners" "renderStrugglingLearners" "$ACTIVE_SCREEN"
check_marker "expert CSV pedagogy" "pedagogical_status" "$ACTIVE_SCREEN"
check_marker "failure rate export" "resource_failure_rate" "$ACTIVE_SCREEN"
check_marker "V0.12 header layout" "itxeb-v012-header" "$ACTIVE_SCREEN"
check_marker "analysis reason font" "itxeb-cui-analysis-table td:nth-child(2)" "$ACTIVE_SCREEN"
check_marker "outbox technical supervision" "renderOutboxTechnicalSupervision" "$ACTIVE_SCREEN"
check_marker "LRS primary views" "TRAX/LRS" "$ACTIVE_SCREEN"

print_title "Template V0.12 markers"
check_marker "pedagogical synthesis" "renderPedagogicalSynthesis" "$TEMPLATE_SCREEN"
check_marker "struggling learners" "renderStrugglingLearners" "$TEMPLATE_SCREEN"
check_marker "expert CSV pedagogy" "pedagogical_status" "$TEMPLATE_SCREEN"
check_marker "failure rate export" "resource_failure_rate" "$TEMPLATE_SCREEN"
check_marker "V0.12 header layout" "itxeb-v012-header" "$TEMPLATE_SCREEN"
check_marker "analysis reason font" "itxeb-cui-analysis-table td:nth-child(2)" "$TEMPLATE_SCREEN"
check_marker "outbox technical supervision" "renderOutboxTechnicalSupervision" "$TEMPLATE_SCREEN"
check_marker "LRS primary views" "TRAX/LRS" "$TEMPLATE_SCREEN"

print_title "Patchers referenced by wrapper"
if [[ -f "$WRAPPER" ]]; then
  grep -nE 'patch_course_ui|fix_course_ui|install_course_ui_companion' "$WRAPPER" || true
else
  echo "Wrapper missing."
fi

print_title "PHP syntax check"
if [[ -d "$COMPANION_ROOT" ]]; then
  find "$COMPANION_ROOT" -name '*.php' -print0 | xargs -0 -n1 php -l
else
  echo "Companion root missing."
fi

print_title "Diff summary template vs active screen"
if [[ -f "$TEMPLATE_SCREEN" && -f "$ACTIVE_SCREEN" ]]; then
  TMP_TEMPLATE="$(mktemp)"
  cp "$TEMPLATE_SCREEN" "$TMP_TEMPLATE"
  diff -u "$TMP_TEMPLATE" "$ACTIVE_SCREEN" | sed -n '1,220p' || true
  rm -f "$TMP_TEMPLATE"
else
  echo "Cannot diff: template or active screen missing."
fi

print_title "Audit completed"
