# Guide d'import GitHub

Ce document explique comment maintenir ou réimporter le dépôt **IliasTraxEventBridge** sur GitHub.

Version stable actuelle après promotion : **V0.25.6**.

## État attendu du dépôt GitHub

Branches principales :

```text
main                             -> version stable courante V0.25.6
v0.25-learner-identity-display   -> branche de validation V0.25.6, alignée fonctionnellement avant promotion
v0.24-dashboard-synthesis-layout -> archive de validation V0.24.17
v0.23-mediacast-media-tracking   -> archive de validation V0.23.8
```

Commit fonctionnel V0.25.6 :

```text
8d97685 V0.25.6 validate learner login display
```

Versions attendues :

```text
plugin.php                                         -> 0.25.6-dev
companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl -> 0.8.44
```

Le dépôt conserve aussi les anciennes branches historiques V0.1 à V0.22 pour traçabilité.

## Vérifier l'état local

Depuis Git Bash Windows :

```bash
git fetch origin --tags

git branch -vv
git log --oneline -5
git tag --points-at HEAD
```

État attendu après stabilisation V0.25.6 :

```text
main pointe sur le dernier commit documentaire V0.25.6
le commit 8d97685 est présent dans l'historique de main
plugin.php contient $version = '0.25.6-dev';
```

## Mettre à jour `main` depuis GitHub

```bash
git fetch origin --tags
git checkout main
git pull --ff-only origin main

grep -n '\$version' plugin.php
grep -n '\$version' companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
```

Résultat attendu après promotion :

```text
$version = '0.25.6-dev';
$version = '0.8.44';
```

## Import d'un commit validé depuis un serveur sans authentification GitHub

Lorsque le serveur ILIAS ne peut pas pousser directement vers GitHub, créer un bundle serveur :

```bash
git bundle create /tmp/v0256_validated.bundle origin/v0.25-learner-identity-display..HEAD
git bundle verify /tmp/v0256_validated.bundle
```

Copier le bundle vers Windows :

```bash
scp root@<serveur>:/tmp/v0256_validated.bundle /c/Users/vincent/Downloads/v0256_validated.bundle
```

Importer puis pousser depuis Git Bash Windows :

```bash
cd ~/Downloads/IliasTraxEventBridge_github_ready_package/package/IliasTraxEventBridge

git fetch origin
git checkout v0.25-learner-identity-display
git pull --ff-only origin v0.25-learner-identity-display

git bundle verify /c/Users/vincent/Downloads/v0256_validated.bundle
git pull /c/Users/vincent/Downloads/v0256_validated.bundle HEAD

git push origin v0.25-learner-identity-display
```

## Installation depuis GitHub après promotion

Sur le serveur ILIAS :

```bash
sudo -i

export ILIAS_ROOT="/var/www/ilias"
export EVENTHOOK_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook"
export PLUGIN_NAME="IliasTraxEventBridge"

mkdir -p "$EVENTHOOK_DIR"
cd "$EVENTHOOK_DIR"

git clone -b main --single-branch https://github.com/vincent-sayah/IliasTraxEventBridge.git "$PLUGIN_NAME"
cd "$PLUGIN_NAME"

grep -n '\$version' plugin.php
grep -n '\$version' companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl

export HTTPD_USER="apache"
bash scripts/install_course_ui_companion_with_standalone_fix.sh

chown -R apache:apache "$EVENTHOOK_DIR/$PLUGIN_NAME"
find "$EVENTHOOK_DIR/$PLUGIN_NAME" -type d -exec chmod 755 {} \;
find "$EVENTHOOK_DIR/$PLUGIN_NAME" -type f -exec chmod 644 {} \;

cd "$ILIAS_ROOT"
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
systemctl restart php-fpm
```

Puis dans ILIAS :

```text
Administration > Plugins > EventHook > IliasTraxEventBridge > Installer / Activer / Configurer
Administration > Plugins > UIHook > IliasTraxEventBridgeCourseUI > Installer / Activer
```

## Documentation à jour pour V0.25.6

```text
README.md
README_TECHNIQUE.md
CHANGELOG.md
GITHUB_IMPORT.md
companion/IliasTraxEventBridgeCourseUI/README.md
docs/INSTALLATION.md
docs/INDEX_0.25.6.md
docs/RELEASE_0.25.6.md
docs/VALIDATION_0.25.6.md
```

## Tag stable recommandé

Après promotion de V0.25.6 dans `main`, créer le tag si nécessaire :

```bash
git checkout main
git pull --ff-only origin main
git tag -a v0.25.6 -m "IliasTraxEventBridge v0.25.6 stable"
git push origin v0.25.6
```
