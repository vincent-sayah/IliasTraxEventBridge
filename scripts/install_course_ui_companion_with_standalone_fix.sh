#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

bash "$SCRIPT_DIR/install_course_ui_companion.sh"
bash "$SCRIPT_DIR/fix_course_ui_delos_navigation_after_install.sh"
bash "$SCRIPT_DIR/fix_course_ui_delos_info_route_after_install.sh"
bash "$SCRIPT_DIR/fix_course_ui_lrs_direct_after_install.sh"
bash "$SCRIPT_DIR/fix_course_ui_empty_outbox_notice_after_install.sh"
