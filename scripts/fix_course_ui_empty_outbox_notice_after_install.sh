#!/usr/bin/env bash
set -euo pipefail

ILIAS_ROOT="${ILIAS_ROOT:-/var/www/ilias}"
PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_SCREEN="$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
PATCHER="$PLUGIN_ROOT/scripts/patch_course_ui_empty_outbox_local_notice.php"

if [[ ! -f "$TARGET_SCREEN" ]]; then
  echo "Generated Course UI screen not found: $TARGET_SCREEN" >&2
  exit 1
fi

if [[ ! -f "$PATCHER" ]]; then
  echo "Empty outbox notice patcher not found: $PATCHER" >&2
  exit 1
fi

php "$PATCHER" "$TARGET_SCREEN"
php -l "$TARGET_SCREEN"

if ! grep -q "renderLocalOutboxEmptyNotice" "$TARGET_SCREEN"; then
  echo "Empty outbox notice block was not found after patching: $TARGET_SCREEN" >&2
  exit 1
fi

echo "Empty outbox local view fix completed."
