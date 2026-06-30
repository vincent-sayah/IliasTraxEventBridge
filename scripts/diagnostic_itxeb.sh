#!/usr/bin/env bash
set -u

# Diagnostic non destructif pour le plugin IliasTraxEventBridge.
# Ce script ne modifie ni les fichiers, ni la base de donnees.

ILIAS_ROOT="${ILIAS_ROOT:-/var/www/ilias}"
HTTPD_USER="${HTTPD_USER:-apache}"
PLUGIN_NAME="IliasTraxEventBridge"
COMPANION_NAME="IliasTraxEventBridgeCourseUI"

EVENTHOOK_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook"
UIHOOK_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook"
PLUGIN_DIR="$EVENTHOOK_DIR/$PLUGIN_NAME"
COMPANION_DIR="$UIHOOK_DIR/$COMPANION_NAME"

ok() { printf '[OK] %s\n' "$*"; }
warn() { printf '[WARN] %s\n' "$*"; }
err() { printf '[ERROR] %s\n' "$*"; }
info() { printf '[INFO] %s\n' "$*"; }
section() { printf '\n==== %s ====\n' "$*"; }

check_path() {
    local path="$1"
    local label="$2"
    if [ -e "$path" ]; then
        ok "$label : $path"
        return 0
    fi
    err "$label introuvable : $path"
    return 1
}

section "Diagnostic IliasTraxEventBridge"
info "ILIAS_ROOT=$ILIAS_ROOT"
info "HTTPD_USER=$HTTPD_USER"
info "PLUGIN_DIR=$PLUGIN_DIR"
info "COMPANION_DIR=$COMPANION_DIR"

section "Controle chemins"
check_path "$ILIAS_ROOT" "Racine ILIAS" || true
check_path "$PLUGIN_DIR" "Plugin principal" || true
check_path "$COMPANION_DIR" "Plugin compagnon UIHook" || true

section "Controle Git"
if [ -d "$PLUGIN_DIR/.git" ]; then
    (
        cd "$PLUGIN_DIR" || exit 0
        info "Branche : $(git branch --show-current 2>/dev/null || echo 'inconnue')"
        info "Dernier commit : $(git log --oneline -1 2>/dev/null || echo 'inconnu')"
        if git diff --quiet 2>/dev/null; then
            ok "Working tree sans modification locale"
        else
            warn "Working tree avec modifications locales"
            git status --short 2>/dev/null || true
        fi
    )
else
    warn "Le plugin principal ne semble pas etre un depot Git"
fi

section "Controle plugin.php"
if [ -f "$PLUGIN_DIR/plugin.php" ]; then
    ok "plugin.php present"
    grep -n '\$id\|\$version\|\$ilias_min_version\|\$ilias_max_version' "$PLUGIN_DIR/plugin.php" || true
else
    err "plugin.php absent"
fi

section "Controle dbupdate.php"
if [ -f "$PLUGIN_DIR/sql/dbupdate.php" ]; then
    ok "sql/dbupdate.php present"
    head -5 "$PLUGIN_DIR/sql/dbupdate.php"
    if head -1 "$PLUGIN_DIR/sql/dbupdate.php" | grep -q '^<#1>'; then
        ok "dbupdate.php commence par <#1>"
    else
        err "dbupdate.php ne commence pas par <#1>"
    fi
else
    err "sql/dbupdate.php absent"
fi

section "Controle syntaxe PHP"
if command -v php >/dev/null 2>&1; then
    info "Version PHP : $(php -v | head -1)"
    if [ -d "$PLUGIN_DIR" ]; then
        php_errors=0
        while IFS= read -r -d '' file; do
            if ! php -l "$file" >/dev/null; then
                err "Erreur PHP : $file"
                php_errors=$((php_errors + 1))
            fi
        done < <(find "$PLUGIN_DIR" -name '*.php' -print0)
        if [ "$php_errors" -eq 0 ]; then
            ok "Aucune erreur de syntaxe PHP detectee dans le plugin principal"
        else
            err "$php_errors erreur(s) PHP detectee(s)"
        fi
    fi
else
    warn "Commande php introuvable"
fi

section "Controle scripts importants"
for f in \
    "$PLUGIN_DIR/scripts/install_course_ui_companion_with_standalone_fix.sh" \
    "$PLUGIN_DIR/scripts/install_course_ui_companion.sh" \
    "$PLUGIN_DIR/scripts/diagnostic_itxeb.sh"; do
    if [ -f "$f" ]; then
        ok "Script present : $f"
    else
        warn "Script absent : $f"
    fi
done

section "Controle plugin compagnon"
if [ -d "$COMPANION_DIR" ]; then
    ok "Dossier compagnon present"
    if [ -f "$COMPANION_DIR/plugin.php" ]; then
        ok "plugin.php compagnon present"
        grep -n '\$id\|\$version\|\$ilias_min_version\|\$ilias_max_version' "$COMPANION_DIR/plugin.php" || true
    else
        warn "plugin.php compagnon absent"
    fi
    info "Fichiers compagnon :"
    find "$COMPANION_DIR" -maxdepth 3 -type f | sort | sed 's#^#  - #' | head -80
else
    warn "Dossier compagnon absent. Executer le script d'installation compagnon si necessaire."
fi

section "Controle droits fichiers"
if [ -d "$PLUGIN_DIR" ]; then
    info "Proprietaire plugin principal : $(stat -c '%U:%G %a %n' "$PLUGIN_DIR" 2>/dev/null || echo 'stat indisponible')"
fi
if [ -d "$COMPANION_DIR" ]; then
    info "Proprietaire plugin compagnon : $(stat -c '%U:%G %a %n' "$COMPANION_DIR" 2>/dev/null || echo 'stat indisponible')"
fi

section "Aide controle SQL"
cat <<'SQL'
Executer dans la base ILIAS :

SHOW TABLES LIKE 'evnt_evhk_itxeb%';

SELECT id, event_type, ref_id, obj_id, obj_type, user_id,
       status, retry_count, created_at, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 30;

SELECT COUNT(*) AS pending_count
FROM evnt_evhk_itxeb_out
WHERE status IN ('pending', 'retry');
SQL

section "Aide controle cron ILIAS"
cat <<'TXT'
Dans ILIAS, verifier :
Administration > Parametres systeme et maintenance > Taches cron

Job attendu : IliasTraxEventBridge — envoi outbox vers TRAX
Identifiant technique : itxeb_send_outbox_to_trax
TXT

section "Aide rebuild ILIAS"
cat <<TXT
Commandes recommandees apres installation ou correction :

cd $ILIAS_ROOT
sudo -u $HTTPD_USER composer du
sudo -u $HTTPD_USER php cli/setup.php build --yes
systemctl restart httpd
systemctl restart php-fpm
TXT

section "Aide logs"
cat <<'TXT'
Commandes utiles :

journalctl -u httpd -n 200 --no-pager
journalctl -u php-fpm -n 200 --no-pager
find /var/www/ilias -type f -iname "*.log" | sort
grep -RniE "IliasTraxEventBridge|itxeb|xapi|TRAX|LRS|exception|error" /var/www/ilias 2>/dev/null | tail -100
TXT

section "Fin diagnostic"
ok "Diagnostic termine. Aucun changement n'a ete effectue."
