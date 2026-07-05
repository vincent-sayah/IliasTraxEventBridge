#!/usr/bin/env bash
set -euo pipefail

ILIAS_ROOT="${1:-/var/www/ilias}"
OUT="${2:-/var/www/logs/itxeb_ilias10_plugin_api.txt}"

mkdir -p "$(dirname "$OUT")"
: > "$OUT"

log() {
  printf '%s\n' "$*" | tee -a "$OUT"
}

log "==== ILIAS root ===="
log "$ILIAS_ROOT"
log ""

log "==== PHP version ===="
php -v | tee -a "$OUT" || true
log ""

log "==== ILIAS version markers ===="
find "$ILIAS_ROOT" -maxdepth 4 \( -name "class.ilias.php" -o -name "composer.json" -o -name "package.json" -o -name "ilias.ini.php" \) -print | tee -a "$OUT" || true
log ""

log "==== RepositoryObject plugin API classes ===="
grep -R "class ilRepositoryObjectPlugin" -n "$ILIAS_ROOT/components" "$ILIAS_ROOT/Services" 2>/dev/null | tee -a "$OUT" || true
grep -R "class ilObjectPluginGUI" -n "$ILIAS_ROOT/components" "$ILIAS_ROOT/Services" 2>/dev/null | tee -a "$OUT" || true
grep -R "class ilObjectPlugin" -n "$ILIAS_ROOT/components" "$ILIAS_ROOT/Services" 2>/dev/null | tee -a "$OUT" || true
log ""

log "==== RepositoryObject plugin method signatures ===="
for pattern in "class ilRepositoryObjectPlugin" "class ilObjectPluginGUI" "class ilObjectPlugin"; do
  file=$(grep -R -l "$pattern" "$ILIAS_ROOT/components" "$ILIAS_ROOT/Services" 2>/dev/null | head -n 1 || true)
  if [[ -n "$file" ]]; then
    log "---- $pattern :: $file ----"
    grep -nE "function (getPluginName|getType|initType|performCommand|setTabs|getStandardCmd|getAfterCreationCmd|doCreate|doRead|doUpdate|doDelete|doCloneObject|executeCommand|addTab|activateTab|setSubTabs|addSubTab|activateSubTab)" "$file" | tee -a "$OUT" || true
    log ""
  fi
done

log "==== Existing RepositoryObject plugins under Customizing ===="
find "$ILIAS_ROOT/public/Customizing/global/plugins" "$ILIAS_ROOT/Customizing/global/plugins" \
  -path "*/RepositoryObject/*" -maxdepth 9 -type f \( -name "plugin.php" -o -name "class.il*Plugin.php" -o -name "class.ilObj*GUI.php" -o -name "class.ilObj*.php" \) -print 2>/dev/null | tee -a "$OUT" || true
log ""

log "==== Sample method signatures from installed RepositoryObject plugins ===="
while IFS= read -r f; do
  log "---- $f ----"
  grep -nE "class |function (getPluginName|getType|initType|performCommand|setTabs|getStandardCmd|getAfterCreationCmd|doCreate|doRead|doUpdate|doDelete|doCloneObject|executeCommand)" "$f" | head -n 80 | tee -a "$OUT" || true
  log ""
done < <(find "$ILIAS_ROOT/public/Customizing/global/plugins" "$ILIAS_ROOT/Customizing/global/plugins" \
  -path "*/RepositoryObject/*" -type f \( -name "class.il*Plugin.php" -o -name "class.ilObj*GUI.php" -o -name "class.ilObj*.php" \) 2>/dev/null | head -n 20)

log "==== ilTabsGUI signatures ===="
grep -R "class ilTabsGUI" -n "$ILIAS_ROOT/components" "$ILIAS_ROOT/Services" 2>/dev/null | tee -a "$OUT" || true
file=$(grep -R -l "class ilTabsGUI" "$ILIAS_ROOT/components" "$ILIAS_ROOT/Services" 2>/dev/null | head -n 1 || true)
if [[ -n "$file" ]]; then
  log "---- $file ----"
  grep -nE "function (addTab|activateTab|setTabActive|addSubTab|activateSubTab|setSubTabActive|clearTargets|removeTab)" "$file" | tee -a "$OUT" || true
fi
log ""

log "==== Output ===="
log "$OUT"
