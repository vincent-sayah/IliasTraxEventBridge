#!/usr/bin/env bash
set -euo pipefail

ILIAS_ROOT="${ILIAS_ROOT:-/var/www/ilias}"
PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_HOOK="$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php"
PATCHER="$PLUGIN_ROOT/scripts/patch_course_ui_native_tabs.php"

if [[ ! -f "$TARGET_HOOK" ]]; then
  echo "Generated Course UI hook not found: $TARGET_HOOK" >&2
  echo "Run scripts/install_course_ui_companion.sh first." >&2
  exit 1
fi

if [[ ! -f "$PATCHER" ]]; then
  echo "Native tab patcher not found: $PATCHER" >&2
  exit 1
fi

php "$PATCHER" "$TARGET_HOOK"
php -l "$TARGET_HOOK"

echo "Native Suivi xAPI tab fix completed."
