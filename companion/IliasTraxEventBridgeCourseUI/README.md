# IliasTraxEventBridgeCourseUI

Plugin compagnon UIHook pour `IliasTraxEventBridge`.

## Objectif

Ce plugin expose la configuration xAPI directement depuis l'objet cours ILIAS :

```text
Cours > Paramètres > Suivi xAPI
```

Le plugin principal `IliasTraxEventBridge` reste responsable de :

- la captation EventHook ;
- l'outbox ;
- le cron ;
- l'envoi vers le LRS configuré ;
- les tables `evnt_evhk_itxeb_ccfg` et `evnt_evhk_itxeb_rcfg` ;
- le filtrage avant outbox.

Ce plugin compagnon est uniquement responsable de l'entrée UI dans le cours.

## Packaging V0.8

À partir de la V0.8, les fichiers PHP du compagnon ne sont plus stockés directement dans le dossier source `companion/`.

Ils sont stockés sous forme de templates :

```text
plugin.php.tpl
classes/*.php.tpl
```

Objectif : éviter que Composer voie deux copies des mêmes classes lorsque :

```text
1. le dépôt principal est cloné dans EventHandling/EventHook/IliasTraxEventBridge ;
2. le compagnon est installé dans UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI.
```

Avant la V0.8, cela produisait des warnings du type :

```text
Ambiguous class resolution, "ilIliasTraxEventBridgeCourseUIPlugin" was found in both ...
```

Le script d'installation matérialise les templates `.php.tpl` en vrais fichiers `.php` uniquement dans le slot actif `UserInterfaceHook`.

## Installation serveur

Depuis le serveur ILIAS, avec le dépôt principal déjà présent :

```bash
sudo -i
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

bash scripts/install_course_ui_companion.sh

cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
```

Variables optionnelles :

```bash
ILIAS_ROOT=/var/www/ilias
HTTPD_USER=apache
```

Exemple avec variables explicites :

```bash
ILIAS_ROOT=/var/www/ilias HTTPD_USER=apache bash scripts/install_course_ui_companion.sh
```

## Chemin d'installation cible

Le script installe le compagnon ici :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

Le plugin principal reste ici :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

## Validation Composer

Après installation par script, cette commande ne doit plus afficher de warning `Ambiguous class resolution` concernant `IliasTraxEventBridgeCourseUI` :

```bash
cd /var/www/ilias
sudo -u apache composer du
```

Les éventuels warnings ILIAS génériques sur `scripts/PHP-CS-Fixer/example` sont indépendants du plugin.

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

Cette version accompagne `IliasTraxEventBridge` `0.8.0`.
