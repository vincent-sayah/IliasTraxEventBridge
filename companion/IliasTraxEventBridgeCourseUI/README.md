# IliasTraxEventBridgeCourseUI

Plugin compagnon UIHook pour `IliasTraxEventBridge`.

## Objectif

Ce plugin expose la configuration xAPI directement depuis l'objet cours ILIAS.

Le plugin principal `IliasTraxEventBridge` reste responsable de :

- la captation EventHook ;
- l'outbox ;
- le cron ;
- l'envoi vers le LRS configuré ;
- les tables `evnt_evhk_itxeb_ccfg` et `evnt_evhk_itxeb_rcfg` ;
- le filtrage avant outbox.

Ce plugin compagnon est uniquement responsable de l'entrée UI dans le cours.

## État V0.7.1

V0.7.1 validée fonctionnellement.

L'accès visible final n'est plus un bouton flottant. Il est intégré comme sous-onglet du cours :

```text
Cours > Paramètres > Suivi xAPI
```

Le libellé `Suivi xAPI` est volontairement indépendant du LRS utilisé.

L'écran de configuration s'affiche dans le contenu central ILIAS. Il ne s'affiche plus dans une fenêtre flottante et ne contient plus de bouton `Fermer`.

Les onglets ILIAS restent visibles :

- fil d'Ariane ;
- titre du cours ;
- onglets principaux ;
- sous-onglets de l'onglet `Paramètres`.

## Conditions d'affichage

Le sous-onglet `Suivi xAPI` est affiché si les conditions suivantes sont réunies :

- cours détecté ;
- utilisateur autorisé à gérer le cours ;
- plugin principal disponible ;
- classes de configuration V0.7 disponibles ;
- URL contextualisée disponible.

## URL du sous-onglet

Le sous-onglet conserve le contexte de l'onglet `Paramètres` du cours, notamment :

```text
cmd=edit
cmdClass=ilObjCourseGUI
ref_id=<course_ref_id>
```

Il ajoute les paramètres spécifiques au plugin compagnon :

```text
itxeb_cui_cmd=showCourseTracking
itxeb_course_ref_id=<course_ref_id>
```

Cette construction permet à ILIAS de reconstruire normalement la page du cours, puis au plugin compagnon de remplacer uniquement le contenu central.

## Écran affiché

L'écran affiche :

- le résumé du cours ;
- l'état d'activation xAPI du cours ;
- la liste des ressources traçables ;
- les cases à cocher par ressource ;
- le bouton `Enregistrer la configuration xAPI` ;
- les actions `Tout activer`, `Tout désactiver`, `Réinitialiser ce cours`.

Les écritures sont faites dans les tables V0.7 existantes :

```text
evnt_evhk_itxeb_ccfg
evnt_evhk_itxeb_rcfg
```

La règle de filtrage reste celle du plugin principal :

```text
statement xAPI autorisé = cours activé ET ressource activée
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

## Installation serveur

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

## Validation visuelle

1. Ouvrir un cours avec un utilisateur qui peut gérer le cours.
2. Ouvrir l'onglet `Paramètres` du cours.
3. Vérifier la présence du sous-onglet `Suivi xAPI`.
4. Cliquer sur `Suivi xAPI`.
5. Vérifier que le contenu s'affiche dans la zone centrale ILIAS.
6. Vérifier que les onglets du cours restent visibles.
7. Cocher `Activer les traces xAPI pour ce cours`.
8. Cocher une ou plusieurs ressources.
9. Cliquer sur `Enregistrer la configuration xAPI`.
10. Vérifier le message de succès.
11. Vérifier en SQL que les tables sont modifiées.

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

## Validation outbox réalisée

Configuration validée sur le cours `194` :

```text
Cours activé : oui
Ressources activées : file ref_id 196, htlm ref_id 207
Autres ressources : désactivées
```

Outbox validée :

```text
file_downloaded / file / ref_id 196 / status sent
repository_object_access / htlm / ref_id 207 / status sent
```

## Version

Plugin compagnon : `0.1.1`.

Cette version accompagne `IliasTraxEventBridge` `0.7.1`.
