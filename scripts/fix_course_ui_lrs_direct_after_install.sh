#!/usr/bin/env bash
set -euo pipefail

ILIAS_ROOT="${ILIAS_ROOT:-/var/www/ilias}"
PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_SCREEN="$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
PATCHER="$PLUGIN_ROOT/scripts/patch_course_ui_lrs_direct_summary.php"

if [[ ! -f "$TARGET_SCREEN" ]]; then
  echo "Generated Course UI screen not found: $TARGET_SCREEN" >&2
  echo "Run scripts/install_course_ui_companion.sh first." >&2
  exit 1
fi

if [[ ! -f "$PATCHER" ]]; then
  echo "LRS direct patcher not found: $PATCHER" >&2
  exit 1
fi

php "$PATCHER" "$TARGET_SCREEN"
php -l "$TARGET_SCREEN"

if ! grep -q "TRAX / LRS direct" "$TARGET_SCREEN"; then
  echo "LRS direct block was not found after patching: $TARGET_SCREEN" >&2
  exit 1
fi

echo "LRS direct Course UI fix completed."
