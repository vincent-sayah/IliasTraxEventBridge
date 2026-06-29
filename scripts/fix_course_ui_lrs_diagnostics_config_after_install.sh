#!/usr/bin/env bash
set -euo pipefail

ILIAS_ROOT="${ILIAS_ROOT:-/var/www/ilias}"
PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_SCREEN="$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
PATCHER="$PLUGIN_ROOT/scripts/patch_course_ui_lrs_diagnostics_config.php"

if [[ ! -f "$TARGET_SCREEN" ]]; then
  echo "Generated Course UI screen not found: $TARGET_SCREEN" >&2
  exit 1
fi

if [[ ! -f "$PATCHER" ]]; then
  echo "LRS diagnostics config patcher not found: $PATCHER" >&2
  exit 1
fi

php "$PATCHER" "$TARGET_SCREEN"
php -l "$TARGET_SCREEN"

if ! grep -q "renderLrsDirectSummary(\$course) . \$this->renderBulkActions" "$TARGET_SCREEN"; then
  echo "LRS diagnostics block was not found in Configuration after patching: $TARGET_SCREEN" >&2
  exit 1
fi

echo "LRS diagnostics Configuration fix completed."
