#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# V0.13 compatibility wrapper.
# Historical standalone fixes and V0.12 screen patchers are now consolidated
# into the companion templates. Keep this entry point for existing procedures.
bash "$SCRIPT_DIR/install_course_ui_companion.sh"
