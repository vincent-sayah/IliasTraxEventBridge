# Import GitHub / promotion — IliasTraxEventBridge

## Version courante

| Élément | Valeur |
|---|---|
| Version validée | V0.27.1 |
| Plugin principal | `0.27.1-dev` |
| Plugin compagnon UI | `0.8.47` |
| Branche de validation | `v0.27-dashboard-command-center-validated` |
| Commit validé serveur | `de90cb1` |
| Branche stable cible | `main` |

## Principe retenu

Le serveur ILIAS ne pousse pas directement vers GitHub.

Le flux de promotion reste :

```text
Serveur ILIAS -> git bundle -> Git Bash Windows -> branche GitHub -> documentation GitHub -> promotion main
```

## Étapes utilisées pour V0.27.1

### Serveur

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git add plugin.php \
classes/class.ilIliasTraxEventBridgeCourseTrackingRepository.php \
classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php \
companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl \
scripts/apply_v0261_course_progress_success_rate.py \
scripts/apply_v0262_synthesis_card_configuration.py \
scripts/apply_v0271_dashboard_command_center.py

git commit -m "V0.27.1 validate dashboard command center"

git bundle create /tmp/v0271_validated.bundle origin/main..HEAD
git bundle verify /tmp/v0271_validated.bundle
```

### Windows / Git Bash

```bash
scp root@192.168.56.50:/tmp/v0271_validated.bundle /c/Users/vincent/Downloads/v0271_validated.bundle

cd ~/Downloads/IliasTraxEventBridge_github_ready_package/package/IliasTraxEventBridge

git fetch origin
git checkout -B v0.27-dashboard-command-center-validated origin/main

git bundle verify /c/Users/vincent/Downloads/v0271_validated.bundle
git pull /c/Users/vincent/Downloads/v0271_validated.bundle HEAD

git push origin HEAD:v0.27-dashboard-command-center-validated
```

## Promotion finale

La promotion finale est faite côté GitHub après mise à jour des fichiers `.md`.

## Contrôle après promotion

```bash
git fetch origin
git checkout -f main
git reset --hard origin/main

git status -sb
git log --oneline -8
```

Résultat attendu : `main` pointe sur la documentation V0.27.1, avec `de90cb1` dans l'historique.
