#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ILIAS_ROOT="${ILIAS_ROOT:-/var/www/ilias}"
TARGET_FILE="$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
ALERT_PATCHER="$SCRIPT_DIR/patch_course_ui_struggling""_learners.php"
CSV_PATCHER="$SCRIPT_DIR/patch_course_ui_expert_csv_pedagogy.php"

bash "$SCRIPT_DIR/install_course_ui_companion.sh"
bash "$SCRIPT_DIR/fix_course_ui_delos_navigation_after_install.sh"
bash "$SCRIPT_DIR/fix_course_ui_delos_info_route_after_install.sh"
bash "$SCRIPT_DIR/fix_course_ui_lrs_direct_after_install.sh"
bash "$SCRIPT_DIR/fix_course_ui_lrs_primary_after_install.sh"
bash "$SCRIPT_DIR/fix_course_ui_outbox_technical_config_after_install.sh"

if [[ -f "$SCRIPT_DIR/patch_course_ui_pedagogical_dashboard.php" && -f "$TARGET_FILE" ]]; then
  php "$SCRIPT_DIR/patch_course_ui_pedagogical_dashboard.php" "$TARGET_FILE"
fi

if [[ -f "$ALERT_PATCHER" && -f "$TARGET_FILE" ]]; then
  php "$ALERT_PATCHER" "$TARGET_FILE"
fi

if [[ -f "$CSV_PATCHER" && -f "$TARGET_FILE" ]]; then
  php "$CSV_PATCHER" "$TARGET_FILE"
fi

find "$(dirname "$(dirname "$TARGET_FILE")")" -name '*.php' -print0 | xargs -0 -n1 php -l
