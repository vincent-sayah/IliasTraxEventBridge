#!/usr/bin/env bash
set -euo pipefail

ILIAS_ROOT="${ILIAS_ROOT:-/var/www/ilias}"
HTTPD_USER="${HTTPD_USER:-apache}"
PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="$PLUGIN_ROOT/companion/IliasTraxEventBridgeCourseUI"
TARGET_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI"
PDF_ROUTE_PATCHER="$PLUGIN_ROOT/scripts/patch_course_ui_pdf_route.php"

if [[ ! -d "$SOURCE_DIR" ]]; then
  echo "Source companion directory not found: $SOURCE_DIR" >&2
  exit 1
fi

if [[ ! -d "$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook" ]]; then
  echo "ILIAS UIHook plugin slot not found under: $ILIAS_ROOT" >&2
  exit 1
fi

echo "Installing IliasTraxEventBridgeCourseUI companion"
echo "Source: $SOURCE_DIR"
echo "Target: $TARGET_DIR"
echo "Mode  : V0.13 consolidated templates"

rm -rf "$TARGET_DIR"
mkdir -p "$TARGET_DIR/classes"

if [[ -f "$SOURCE_DIR/README.md" ]]; then
  cp "$SOURCE_DIR/README.md" "$TARGET_DIR/README.md"
fi

cp "$SOURCE_DIR/plugin.php.tpl" "$TARGET_DIR/plugin.php"

while IFS= read -r -d '' template; do
  rel="${template#$SOURCE_DIR/}"
  target_rel="${rel%.tpl}"
  mkdir -p "$TARGET_DIR/$(dirname "$target_rel")"
  cp "$template" "$TARGET_DIR/$target_rel"
done < <(find "$SOURCE_DIR/classes" -type f -name '*.php.tpl' -print0)

# V0.13: the CourseUIScreen template now directly contains the stable V0.12 UI blocks.
# Do not replay the historical screen patchers here. Only keep the PDF route patcher
# until the UIHookGUI template is consolidated as a dedicated follow-up.
if [[ -f "$PDF_ROUTE_PATCHER" ]]; then
  php "$PDF_ROUTE_PATCHER" "$TARGET_DIR/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php"
else
  echo "PDF route patcher not found, skipping: $PDF_ROUTE_PATCHER"
fi

find "$TARGET_DIR" -type d -exec chmod 755 {} \;
find "$TARGET_DIR" -type f -exec chmod 644 {} \;

if id "$HTTPD_USER" >/dev/null 2>&1; then
  chown -R "$HTTPD_USER:$HTTPD_USER" "$TARGET_DIR"
else
  echo "User $HTTPD_USER not found, ownership unchanged."
fi

echo "PHP syntax check"
find "$TARGET_DIR" -name '*.php' -print0 | xargs -0 -n1 php -l

echo "Companion installation completed."
