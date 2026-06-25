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

## État Lot 3

Le lot 3 ajoute la détection contextualisée du cours courant.

Le bridge prépare maintenant :

- `course_ref_id` ;
- `course_obj_id` ;
- `course_title` ;
- `can_manage` ;
- `main_plugin_available` ;
- `course_tracking_classes_available` ;
- `configuration_url` ;
- `detection_candidates`.

La détection peut exploiter :

- `ref_id` ;
- `course_ref_id` ;
- `target_ref_id` ;
- `itxeb_course_ref_id` ;
- `target=crs_<id>` ;
- `REQUEST_URI`, notamment `/goto.php/crs/<id>` et `/crs/<id>`.

## État Lot 4

Le lot 4 ajoute une entrée visible non destructive dans l'objet cours.

Si le contexte est un cours et si l'utilisateur peut gérer le cours, le plugin compagnon injecte un bouton flottant :

```text
TRAX / xAPI
```

Ce bouton pointe vers l'URL contextualisée préparée par le lot 3 :

```text
itxeb_cui_cmd=showCourseTracking
itxeb_course_ref_id=<course_ref_id>
```

Le bouton est volontairement limité aux utilisateurs qui ont au moins un des droits suivants :

```text
write
edit_permission
manage_members
```

Le lot 4 ne câble pas encore l'écran complet. Le clic peut donc seulement recharger la page avec les paramètres contextualisés. Le rendu de l'écran complet est prévu au lot 5.

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
systemctl restart httpd
```

Ensuite, vérifier dans l'administration des plugins ILIAS que `IliasTraxEventBridgeCourseUI` apparaît comme plugin UIHook et qu'il est actif.

## Validation syntaxe

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

find companion/IliasTraxEventBridgeCourseUI -name "*.php" -print0 | xargs -0 -n1 php -l
```

Résultat attendu : aucune erreur de syntaxe PHP.

## Validation rapide du bridge

Après installation provisoire dans le dossier UIHook, vérifier le chemin vers le plugin principal :

```bash
php -r 'require "/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIBridge.php"; $b = new ilIliasTraxEventBridgeCourseUIBridge(); echo $b->getMainPluginPath(), PHP_EOL; echo $b->isMainPluginAvailable() ? "main plugin OK\n" : "main plugin missing\n";'
```

Résultat attendu :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
main plugin OK
```

## Validation visuelle Lot 4

1. Activer le plugin compagnon dans l'administration des plugins ILIAS.
2. Ouvrir un cours avec un utilisateur qui peut gérer le cours.
3. Vérifier la présence d'un bouton flottant `TRAX / xAPI` en bas à droite.
4. Ouvrir le même cours avec un utilisateur sans droits de gestion.
5. Vérifier que le bouton n'apparaît pas.

## Suite

Lot 5 : router l'URL contextualisée vers l'écran complet `ilIliasTraxEventBridgeCourseTrackingGUI` sans saisie manuelle du `course_ref_id`.
