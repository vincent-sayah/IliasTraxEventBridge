# Changelog — IliasTraxEventBridge

Toutes les évolutions notables du plugin sont listées ici.

## v0.8.0 — supervision outbox et diagnostic des refus

### Statut

- Branche de développement : `v0.8-outbox-supervision`.
- Base : tag stable `v0.7.1`.
- Version plugin principal : `0.8.0`.
- Version plugin compagnon UIHook : `0.1.1`.
- Objectif : ajouter des outils d'exploitation pour comprendre pourquoi une trace xAPI n'est pas générée, sans activer ce diagnostic en permanence sur une plateforme volumineuse.

### Lot 1 — journal SQL des refus

- Ajout de la table `evnt_evhk_itxeb_dlog` via l'étape SQL `<#6>`.
- Ajout du repository `ilIliasTraxEventBridgeDenyLogRepository`.
- Journalisation non bloquante des refus issus de `EventHook`.
- Journalisation non bloquante des refus issus de `read_event`.
- Motifs pris en charge : `not_in_course`, `missing_course_context`, `missing_resource_context`, `course_not_configured`, `course_disabled`, `resource_not_configured`, `resource_disabled`, `unsupported_object_type`.
- Validation réalisée sur les ressources désactivées d'un cours : les refus sont bien journalisés avec `reason = resource_disabled`.

### Lot 2 — supervision admin des refus

- Ajout d'une section `Diagnostic des traces refusées V0.8` dans l'écran de configuration du plugin.
- Affichage du total des refus, de la synthèse par motif, par source et par type d'événement.
- Affichage des 50 derniers refus avec contexte utilisateur, cours, ressource, source technique et payload JSON.
- Ajout de la case `Activer le diagnostic des traces refusées`.
- Par défaut, le diagnostic des refus est désactivé pour éviter une croissance inutile de `evnt_evhk_itxeb_dlog`.
- Ajout du bouton `Purger le diagnostic des traces refusées`, qui vide uniquement `evnt_evhk_itxeb_dlog`.
- Validation réalisée : activation/désactivation fonctionnelle et purge fonctionnelle.

### Lot 3 — packaging propre du plugin compagnon UIHook

- Remplacement des fichiers PHP source du compagnon par des templates `.php.tpl`.
- Ajout du script `scripts/install_course_ui_companion.sh` pour générer les vrais fichiers PHP uniquement dans le slot actif `UserInterfaceHook`.
- Suppression des warnings Composer `Ambiguous class resolution` liés à `IliasTraxEventBridgeCourseUI`.
- Mise à jour de la documentation d'installation du plugin compagnon.
- Validation réalisée : le sous-onglet `Paramètres > Suivi xAPI` reste fonctionnel après installation par templates.

### Documentation

- Ajout de `docs/V0.8_LOT1_DENY_LOG.md`.
- Ajout de `docs/V0.8_LOT2_DENY_SUPERVISION.md`.
- Ajout de `docs/V0.8_LOT3_COMPANION_PACKAGING.md`.
- Ajout de `docs/RELEASE_0.8.0.md`.

## v0.7.1 — développement course object UI

### Statut

- Branche de développement : `v0.7.1-course-object-ui`.
- Base : tag stable `v0.7.0` / branche `v0.7`.
- Objectif : permettre la sélection des ressources xAPI depuis l'objet cours, par un administrateur de cours, et non plus principalement depuis la configuration globale du plugin.

### Lot 1 — cadrage

- Ajout de `docs/V0.7.1_COURSE_OBJECT_UI.md`.
- Décision d'architecture : conserver `IliasTraxEventBridge` comme plugin principal EventHook et ajouter un plugin compagnon UIHook léger.

### Lot 2 — squelette plugin compagnon

- Ajout du dossier `companion/IliasTraxEventBridgeCourseUI`.
- Ajout du `plugin.php` du plugin compagnon avec l'identifiant `itxebcui` et la version `0.1.0`.
- Ajout de `ilIliasTraxEventBridgeCourseUIPlugin`, classe plugin UIHook.
- Ajout de `ilIliasTraxEventBridgeCourseUIBridge`, bridge de découverte du plugin principal, chargement des classes V0.7 et vérification des droits cours.
- Ajout de `ilIliasTraxEventBridgeCourseUIUIHookGUI`, classe UIHook non invasive pour le lot 2.
- Ajout de `companion/IliasTraxEventBridgeCourseUI/README.md` avec le chemin d'installation cible et les commandes de validation.

### Lot 3 — détection contexte cours

- Le bridge détecte le cours courant depuis `ref_id`, `course_ref_id`, `target_ref_id`, `itxeb_course_ref_id`, `target=crs_<id>` ou l'URL courante.
- Le bridge prépare `course_ref_id`, `course_obj_id`, `course_title`, les droits de gestion, la disponibilité du plugin principal et l'URL contextualisée.
- Compatibilité ILIAS 10 : les superglobales remplacées par `SuperGlobalDropInReplacement` sont prises en charge.

### Lot 4 — entrée visible dans l'objet cours

- Ajout d'un bouton flottant `TRAX / xAPI` dans l'objet cours.
- Le bouton apparaît uniquement si un cours est détecté et si l'utilisateur a le droit de gérer le cours.
- Le bouton pointe vers l'URL contextualisée du cours.

### Lot 5 — écran complet depuis l'objet cours

- Ajout de `ilIliasTraxEventBridgeCourseUIScreen` dans le plugin compagnon.
- Le bouton `TRAX / xAPI` ouvre maintenant un panneau complet de configuration depuis l'objet cours.
- L'écran affiche le résumé du cours, l'activation xAPI du cours et les ressources traçables.
- Actions prises en charge : `showCourseTracking`, `saveCourseTracking`, `enableAllCourseTracking`, `disableAllCourseTracking`, `resetCourseTracking`.
- Les choix sont enregistrés dans les tables V0.7 existantes `evnt_evhk_itxeb_ccfg` et `evnt_evhk_itxeb_rcfg`.
- La saisie manuelle du `course_ref_id` n'est plus nécessaire depuis l'objet cours.

## v0.7.0 — stable

### Statut

- Branche `v0.7` créée depuis `main` / `v0.6.0` stable.
- Version plugin portée à `0.7.0` pour ouvrir la série V0.7.
- Objectif principal : permettre le pilotage des traces xAPI par cours et par ressource.
- `main` et `v0.6` restent les références stables V0.6.0.
- Tag stable : `v0.7.0`.

### Objectif fonctionnel

- Permettre à un administrateur ou responsable de cours d'activer ou désactiver les traces xAPI pour son cours.
- Permettre de choisir quelles ressources du cours génèrent des statements xAPI.
- Filtrer les événements avant insertion dans l'outbox quand le cours ou la ressource n'est pas autorisé.
- Conserver les statements enrichis V0.6 pour les ressources explicitement activées.

### Jalon 1 — ouverture V0.7

- Création de la branche `v0.7`.
- Ouverture de la version plugin `0.7.0`.
- Ajout de `docs/V0.7_DEV_PLAN.md` pour cadrer le modèle de données, l'interface cours, les permissions et les règles de filtrage.

### Jalon 2 — tables de configuration cours / ressources

- Ajout de la migration SQL `#5` dans `sql/dbupdate.php`.
- Création de la table `evnt_evhk_itxeb_ccfg` pour stocker l'activation xAPI par cours.
- Création de la table `evnt_evhk_itxeb_rcfg` pour stocker l'activation xAPI par ressource dans un cours.
- Les noms de tables ont été raccourcis pour respecter la limite ILIAS de 22 caractères.
- Ajout du repository `ilIliasTraxEventBridgeCourseTrackingRepository` pour lire et écrire ces configurations.
- Ajout de `docs/V0.7_COURSE_TRACKING_CONFIG.md` avec les requêtes de vérification et les tests SQL manuels.
- Aucun filtrage xAPI n'est encore appliqué dans ce jalon : le comportement V0.6 reste inchangé jusqu'au lot de filtrage.

### Jalon 3 — résolution cours / ressources

- Ajout de `ilIliasTraxEventBridgeCourseResourceResolver`.
- Le resolver liste les ressources traçables contenues dans un cours à partir d'un `course_ref_id`.
- Types préparés : `file`, `tst`, `blog`, `wiki`, `webr`, `mcst`, `frm`, `htlm`, `lm`, `sahs`.
- Pour chaque ressource, le resolver prépare `ref_id`, `obj_id`, `obj_type`, famille, titre, chemin, état `configured` et état `enabled`.
- Les états sont joints depuis `evnt_evhk_itxeb_rcfg` quand une configuration existe.
- Ajout de `docs/V0.7_COURSE_RESOURCE_RESOLVER.md`.
- Aucun écran cours et aucun filtrage xAPI ne sont encore appliqués dans ce jalon.

### Jalon 4 — interface de configuration cours

- Ajout de `ilIliasTraxEventBridgeCourseTrackingGUI`.
- L'interface permet d'afficher le résumé d'un cours, l'état d'activation xAPI du cours et les ressources traçables retournées par le resolver.
- Actions disponibles : `show`, `save`, `enableAll`, `disableAll`, `resetCourse`.
- Les choix sont enregistrés dans `evnt_evhk_itxeb_ccfg` et `evnt_evhk_itxeb_rcfg`.
- Les droits cours sont vérifiés avant écriture via les permissions `write`, `edit_permission` ou `manage_members`.
- Ajout de `docs/V0.7_COURSE_TRACKING_UI.md`.
- Le filtrage xAPI avant outbox reste prévu pour le jalon suivant.

### Jalon 4b — accès visible depuis l'administration du plugin

- `ilIliasTraxEventBridgeCourseTrackingGUI` peut désormais être embarqué depuis un autre contrôleur GUI.
- Ajout d'une section visible `Configuration xAPI par cours` dans l'écran d'administration du plugin.
- L'administrateur peut saisir un `course_ref_id`, ouvrir l'écran de configuration xAPI du cours, enregistrer les choix, tout activer, tout désactiver ou réinitialiser le cours.
- Les données sont toujours enregistrées dans `evnt_evhk_itxeb_ccfg` et `evnt_evhk_itxeb_rcfg`.
- Cette étape rend l'écran testable dans ILIAS sans dépendre immédiatement d'une injection dans les paramètres natifs du cours.
- L'intégration directe dans l'objet cours reste l'objectif fonctionnel, mais dépend des points d'extension disponibles pour un plugin EventHook sur ILIAS 10.
- Ajout de `docs/V0.7_COURSE_TRACKING_ACCESS.md`.
- Les formulaires d'accès cours utilisent `POST` pour préserver les paramètres `ilCtrl`.

### Jalon 5 — filtrage avant outbox

- Ajout du filtrage effectif avant insertion dans `evnt_evhk_itxeb_out`.
- Les événements EventHook ne génèrent un statement que si le cours est activé dans `evnt_evhk_itxeb_ccfg` et si la ressource est activée dans `evnt_evhk_itxeb_rcfg`.
- Les consultations issues de `read_event` appliquent la même règle avant génération outbox.
- Les consultations `read_event` refusées par la configuration sont marquées traitées dans `evnt_evhk_itxeb_read` afin d'éviter une boucle cron ; une consultation ultérieure pourra être traitée si `last_access` ou `read_count` évolue.
- La configuration est strictement opt-in : sans configuration explicite du cours et de la ressource, aucun statement xAPI n'est généré.
- Ajout de `docs/V0.7_OUTBOX_FILTERING.md`.

### Jalon 6 — stabilisation documentaire V0.7

- Alignement de `README.md` avec l'état V0.7 candidate : `main` reste stable V0.6.0, `v0.7` porte la candidate `0.7.0`.
- Alignement de `README_TECHNIQUE.md` avec l'architecture V0.7 : nouvelles classes, tables `ccfg` / `rcfg`, flux de filtrage EventHook et `read_event`.
- Mise à jour de `docs/VALIDATION.md` pour remplacer le plan V0.6 par un plan V0.7 complet.
- Ajout des critères de validation réels : cours activé, ressources activées envoyées, ressources désactivées refusées avant outbox.
- Préparation de la suite : validation finale serveur/Windows, puis tag `v0.7.0` et promotion éventuelle de `main`.

## v0.6.0 — stable

### Statut

- Version stable V0.6.0 taguée après validation serveur et Windows.
- Branches `main` et `v0.6` alignées sur la stable V0.6.0.
- Branche `v0.5` conservée pour maintenance historique V0.5.5.
- Version plugin : `0.6.0`.

### Ajouté

- Enrichissement des statements xAPI avec `object_title`, `object_url`, `course_title` et `course_url` quand les informations ILIAS sont disponibles.
- Ajout du cours parent dans `context.contextActivities.parent` pour relier les consultations, fichiers et tests au cours ILIAS.
- Ajout de `read_event_first_access` dans les records xAPI issus de `read_event`, en complément de `read_count`, `spent_seconds` et `read_event_last_access`.
- Classification V0.6 des statements via les extensions `statement_family`, `interaction_type` et `repository_object_family` pour faciliter les analyses TRAX.
- Ajout de `result.duration` au format ISO 8601 lorsque `spent_seconds` est disponible et supérieur à zéro.
- Ajout de descriptions xAPI sur les activités objet/cours pour rendre les statements plus lisibles dans TRAX.
- Ajout d'extensions de diagnostic outbox dans `context.extensions` : `outbox_id`, `outbox_table`, `event_log_id`, `statement_uuid`, `event_record_source`, `source_table` et `deduplication_key`.
- Ajout d'une section admin `Supervision V0.6` dans l'écran de configuration du plugin, calculée sur les 200 dernières lignes outbox : statuts, événements, objets, familles xAPI, types d'interaction, sources techniques, dernières clés de diagnostic et dernières erreurs.
- Ajout d'un bloc admin `Exploitation / maintenance` avec compteurs total, 24h, 7j, `sent`, `generated`, `failed`, erreurs à inspecter et retry épuisé.
- Ajout du guide `docs/OPERATIONS.md` pour l'exploitation SQL et les diagnostics serveur.
- Ajout du guide `docs/V0.6_STABILISATION.md` pour préparer et rejouer la stabilisation V0.6.
- Mise à jour du plan de validation V0.6 avec les contrôles SQL complets : familles xAPI, métriques `read_event`, diagnostics outbox, wording bilingue, envoi TRAX et absence de traces parasites.

### Changé

- Les consultations issues de `read_event` utilisent désormais des verbes plus précis selon le type d'objet : lecture de blog/wiki/module, visite de lien web, visionnage de mediacast, interaction forum, lancement SCORM.
- Le téléchargement de fichier utilise un verbe xAPI dédié `downloaded` au lieu du libellé générique `experienced`.
- Les statements de test conservent les verbes `attempted`, `passed` et `failed`, mais avec un wording plus explicite (`a commencé le test`, `a réussi le test`, `a échoué au test`).
- Le contexte des tests utilise désormais `source_event = test_tracking_status` dans le JSON xAPI, en cohérence avec l'outbox.
- Les statements sont enrichis au moment de l'insertion outbox afin d'y inclure l'identifiant technique local `outbox_id` sans modifier le schéma SQL.
- Les descriptions xAPI `en-US` sont maintenant réellement anglophones, distinctes des descriptions `fr-FR`.
- L'écran d'administration affiche désormais la série V0.6 et expose une vue de supervision opérationnelle sans requête SQL manuelle.
