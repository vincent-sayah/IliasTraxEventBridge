# IliasTraxEventBridgeCourseUI

Plugin compagnon UIHook pour `IliasTraxEventBridge`.

## Objectif

Ce plugin est destiné à exposer la configuration TRAX / xAPI directement depuis l'objet cours ILIAS.

Le plugin principal `IliasTraxEventBridge` reste responsable de :

- la captation EventHook ;
- l'outbox ;
- le cron ;
- l'envoi TRAX ;
- les tables `evnt_evhk_itxeb_ccfg` et `evnt_evhk_itxeb_rcfg` ;
- le filtrage avant outbox.

Ce plugin compagnon est uniquement responsable de l'entrée UI dans le cours.

## État Lot 2

Ce lot fournit un squelette non invasif :

- `plugin.php` du plugin compagnon ;
- classe plugin `ilIliasTraxEventBridgeCourseUIPlugin` ;
- bridge `ilIliasTraxEventBridgeCourseUIBridge` ;
- classe UIHook `ilIliasTraxEventBridgeCourseUIUIHookGUI`.

Aucune entrée n'est encore injectée dans l'interface cours à ce stade.

## Chemin d'installation cible

Le dossier `IliasTraxEventBridgeCourseUI` doit être installé ici :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

Le plugin principal doit rester ici :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

## Installation serveur provisoire

Depuis le serveur ILIAS, avec le dépôt principal déjà présent :

```bash
sudo -i

export ILIAS_ROOT="/var/www/ilias"
export SOURCE_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge/companion/IliasTraxEventBridgeCourseUI"
export TARGET_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI"

mkdir -p "$(dirname "$TARGET_DIR")"
rm -rf "$TARGET_DIR"
cp -a "$SOURCE_DIR" "$TARGET_DIR"

chown -R apache:apache "$TARGET_DIR"
find "$TARGET_DIR" -type d -exec chmod 755 {} \;
find "$TARGET_DIR" -type f -exec chmod 644 {} \;
find "$TARGET_DIR" -name "*.php" -print0 | xargs -0 -n1 php -l

cd "$ILIAS_ROOT"
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
```

Ensuite, vérifier dans l'administration des plugins ILIAS que `IliasTraxEventBridgeCourseUI` apparaît comme plugin UIHook.

## Validation Lot 2

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

find companion/IliasTraxEventBridgeCourseUI -name "*.php" -print0 | xargs -0 -n1 php -l
```

Résultat attendu : aucune erreur de syntaxe PHP.

## Suite

Lot 3 : détection fiable du contexte cours et préparation de l'URL de configuration xAPI contextualisée.
