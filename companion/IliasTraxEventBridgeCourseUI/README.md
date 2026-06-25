# IliasTraxEventBridgeCourseUI

Plugin compagnon UIHook pour `IliasTraxEventBridge`.

## Objectif

Ce plugin expose la configuration TRAX / xAPI directement depuis l'objet cours ILIAS.

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

## État Lot 5

Le lot 5 affiche l'écran complet de configuration depuis l'objet cours.

Commandes UIHook prises en charge :

```text
showCourseTracking
enableAllCourseTracking
disableAllCourseTracking
resetCourseTracking
saveCourseTracking
```

L'écran affiche :

- le résumé du cours ;
- l'état d'activation xAPI du cours ;
- la liste des ressources traçables ;
- les cases à cocher par ressource ;
- le bouton Enregistrer ;
- les actions Tout activer, Tout désactiver, Réinitialiser.

Les écritures sont faites dans les tables V0.7 existantes :

```text
evnt_evhk_itxeb_ccfg
evnt_evhk_itxeb_rcfg
```

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

## Validation visuelle Lot 5

1. Ouvrir un cours avec un utilisateur qui peut gérer le cours.
2. Cliquer sur le bouton flottant `TRAX / xAPI`.
3. Vérifier que le panneau de configuration s'ouvre.
4. Cocher `Activer les traces xAPI pour ce cours`.
5. Cocher une ou plusieurs ressources.
6. Cliquer sur `Enregistrer la configuration xAPI`.
7. Vérifier le message de succès.
8. Vérifier en SQL que les tables sont modifiées.

SQL de contrôle :

```sql
SELECT *
FROM evnt_evhk_itxeb_ccfg
WHERE course_ref_id = 194;

SELECT course_ref_id, ref_id, obj_id, obj_type, enabled, updated_at, updated_by
FROM evnt_evhk_itxeb_rcfg
WHERE course_ref_id = 194
ORDER BY ref_id;
```

## Suite

Lot 6 : stabilisation documentaire, validation non-régression outbox et préparation du tag `v0.7.1`.
