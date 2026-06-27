#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

bash "$SCRIPT_DIR/install_course_ui_companion.sh"
bash "$SCRIPT_DIR/fix_course_ui_standalone_after_install.sh"
