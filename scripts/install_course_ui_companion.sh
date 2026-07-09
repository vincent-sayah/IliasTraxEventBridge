#!/usr/bin/env bash
set -euo pipefail

HTTPD_USER="${HTTPD_USER:-apache}"
PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="$PLUGIN_ROOT/companion/IliasTraxEventBridgeCourseUI"

# ILIAS_ROOT can be explicitly provided by the administrator.
# If not provided, infer it from the installed EventHook path when possible.
if [[ -n "${ILIAS_ROOT:-}" ]]; then
  ILIAS_ROOT="$ILIAS_ROOT"
else
  EVENTHOOK_SUFFIX="/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge"
  if [[ "$PLUGIN_ROOT" == *"$EVENTHOOK_SUFFIX" ]]; then
    ILIAS_ROOT="${PLUGIN_ROOT%$EVENTHOOK_SUFFIX}"
  else
    ILIAS_ROOT="/var/www/ilias"
  fi
fi

TARGET_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI"

if [[ ! -d "$SOURCE_DIR" ]]; then
  echo "Source companion directory not found: $SOURCE_DIR" >&2
  exit 1
fi

if [[ ! -d "$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook" ]]; then
  echo "ILIAS UIHook plugin slot not found under: $ILIAS_ROOT" >&2
  echo "Set ILIAS_ROOT explicitly, for example:" >&2
  echo "  export ILIAS_ROOT=/chemin/vers/ilias" >&2
  exit 1
fi

echo "Installing IliasTraxEventBridgeCourseUI companion"
echo "ILIAS : $ILIAS_ROOT"
echo "Source: $SOURCE_DIR"
echo "Target: $TARGET_DIR"
echo "Mode  : V0.21.2 consolidated templates"

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
