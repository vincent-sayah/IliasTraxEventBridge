# Import GitHub / promotion — IliasTraxEventBridge

## Version courante

| Élément | Valeur |
|---|---|
| Version validée | V0.28.5 |
| Plugin principal | `0.28.5-dev` |
| Plugin compagnon UI | `0.8.50` |
| Branche de validation | `v0.28-dashboard-analysis-config-ai-prompt-validated` |
| Commit validé serveur | `eabc786` |
| Branche stable cible | `main` |

## Principe retenu

Le serveur ILIAS ne pousse pas directement vers GitHub.

Le flux de promotion reste :

```text
Serveur ILIAS
  -> git commit local validé
  -> git bundle /tmp/v0285_validated.bundle
  -> copie SCP vers Windows
  -> import bundle dans dépôt Windows
  -> push vers GitHub sur branche de validation
  -> mise à jour documentation .md
  -> promotion de main
```

## Commandes utilisées pour V0.28.5

### Serveur ILIAS

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

rm -rf scripts/__pycache__

git status -sb
git diff --stat

git add \
plugin.php \
classes/class.ilIliasTraxEventBridgeConfig.php \
classes/class.ilIliasTraxEventBridgeConfigGUI.php \
classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php \
companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIBridge.php.tpl \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl \
scripts/apply_v0281_dashboard_analysis_config_ai_prompt.py \
scripts/apply_v0283_pilotage_button_course_access_fix.py \
scripts/apply_v0284_config_plugin_layout.py \
scripts/apply_v0285_config_purge_buttons_layout.py

git commit -m "V0.28.5 validate plugin configuration layout"

git fetch origin
git bundle create /tmp/v0285_validated.bundle origin/main..HEAD
git bundle verify /tmp/v0285_validated.bundle
ls -lh /tmp/v0285_validated.bundle
```

### Windows Git Bash

```bash
scp root@192.168.56.50:/tmp/v0285_validated.bundle /c/Users/vincent/Downloads/v0285_validated.bundle

cd ~/Downloads/IliasTraxEventBridge_github_ready_package/package/IliasTraxEventBridge

git fetch origin
git checkout -B v0.28-dashboard-analysis-config-ai-prompt-validated origin/main

git bundle verify /c/Users/vincent/Downloads/v0285_validated.bundle
git pull /c/Users/vincent/Downloads/v0285_validated.bundle HEAD

git push -u origin v0.28-dashboard-analysis-config-ai-prompt-validated
```

## Résultat validé

```text
eabc786 V0.28.5 validate plugin configuration layout
```

Le bundle validé contenait `eabc786` et nécessitait `f7f8611`, commit stable V0.27.1.

## Après promotion main

```bash
git fetch origin
git checkout -f main
git reset --hard origin/main

export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"
bash scripts/install_course_ui_companion_with_standalone_fix.sh

systemctl restart php-fpm
systemctl restart httpd
```
