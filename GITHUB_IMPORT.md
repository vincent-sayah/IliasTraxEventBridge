# Guide d'import GitHub

Ce document explique comment maintenir ou réimporter le dépôt **IliasTraxEventBridge** sur GitHub.

Version stable actuelle : **v0.5.5**.

## État attendu du dépôt GitHub

Branches principales :

```text
main   -> version stable courante v0.5.5
v0.5   -> branche stable de la série V0.5, alignée sur main
v0.4   -> archive de la série V0.4
```

Tags importants :

```text
v0.5.5 -> tag stable de la version V0.5.5
v0.4.3 -> tag stable de la version V0.4.3 si présent
```

Le dépôt peut conserver les anciennes branches d'import historique :

```text
v0.1.0
v0.1.1
v0.1.2
v0.1.3
v0.1.4
v0.1.5
v0.2.0
v0.2.1
v0.3.0
v0.3.1
```

## Vérifier l'état local

Depuis Git Bash Windows :

```bash
git fetch origin --tags

git branch -vv
git log --oneline -1
git tag --points-at HEAD
```

État attendu après stabilisation V0.5.5 :

```text
main et v0.5 pointent sur le même commit stable/documentaire
plugin.php contient $version = "0.5.5";
```

## Mettre à jour `main` depuis GitHub

```bash
git fetch origin --tags
git checkout main
git pull --ff-only origin main

grep -n '\$version' plugin.php
grep -n "Version stable actuelle" README.md
```

Résultat attendu :

```text
$version = "0.5.5";
Version stable actuelle : **v0.5.5**
```

## Aligner la branche `v0.5` sur `main`

À utiliser après une correction documentaire stable sur `main` :

```bash
git checkout v0.5
git reset --hard origin/main
git push --force-with-lease origin v0.5
```

Vérification :

```bash
git log --oneline -1
git branch -vv
```

`main` et `v0.5` doivent pointer sur le même commit.

## Créer le tag stable V0.5.5

À faire uniquement si le tag n'existe pas déjà :

```bash
git checkout v0.5
git pull --ff-only origin v0.5

grep -n '\$version' plugin.php

git tag -a v0.5.5 -m "IliasTraxEventBridge v0.5.5 stable"
git push origin v0.5.5
```

Vérification :

```bash
git ls-remote --tags origin v0.5.5
git show --stat v0.5.5
git branch --contains v0.5.5
```

## Import dans un nouveau repository GitHub

Créer d'abord un repository vide sur GitHub nommé :

```text
IliasTraxEventBridge
```

Puis, depuis le dossier local du dépôt :

```bash
git remote add origin https://github.com/<organisation-ou-user>/IliasTraxEventBridge.git

git push -u origin main
git push origin v0.5
git push origin v0.4

git push origin --tags
```

Si vous voulez aussi conserver toutes les branches historiques :

```bash
git push origin --all
git push origin --tags
```

## Installation depuis GitHub après import

Sur le serveur ILIAS :

```bash
sudo -i

export ILIAS_ROOT="/var/www/ilias"
export EVENTHOOK_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook"
export PLUGIN_NAME="IliasTraxEventBridge"

mkdir -p "$EVENTHOOK_DIR"
cd "$EVENTHOOK_DIR"

git clone -b main --single-branch https://github.com/<organisation-ou-user>/IliasTraxEventBridge.git "$PLUGIN_NAME"

cd "$PLUGIN_NAME"
grep -n '\$version' plugin.php

chown -R apache:apache "$EVENTHOOK_DIR/$PLUGIN_NAME"
find "$EVENTHOOK_DIR/$PLUGIN_NAME" -type d -exec chmod 755 {} \;
find "$EVENTHOOK_DIR/$PLUGIN_NAME" -type f -exec chmod 644 {} \;

cd "$ILIAS_ROOT"
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
```

Puis dans ILIAS :

```text
Administration > Plugins > EventHook > IliasTraxEventBridge > Installer / Activer / Configurer
```
