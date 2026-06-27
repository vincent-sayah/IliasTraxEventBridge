# Changelog — IliasTraxEventBridge

Toutes les évolutions notables du plugin sont listées ici.

## v0.9.1 — feedback cours, dashboard pédagogique et navigation Delos

### Statut

- Branche concernée : `v0.9-feedback-dashboard`.
- Base : `main` stable `v0.8.0`.
- Version plugin principal : `0.9.1`.
- Version plugin compagnon UIHook : `0.2.1`.
- Statut : validée fonctionnellement avant merge `main` et tag `v0.9.1`.

### Objectif

La V0.9.1 ajoute un feedback pédagogique et technique directement dans l'objet cours ILIAS.

Elle complète la configuration xAPI par cours avec des vues d'analyse exploitables par un formateur, un pilote de cours ou un administrateur.

### Navigation ILIAS 10.8 / Delos

- Déplacement de l'accès `Suivi xAPI` dans la barre principale du cours.
- Compatibilité validée avec ILIAS 10.8 et le thème par défaut Delos.
- Correction de la route du lien `Suivi xAPI` : utilisation de la route support officielle `Info / showSummary`, puis remplacement du contenu central par l'écran xAPI.
- Correction des liens internes `Tableau de bord`, `Analyse`, `Expert`, `Configuration` afin de rester dans l'écran xAPI.
- Suppression des essais non retenus basés sur un pilotage fragile des sous-onglets natifs.

### Vues ajoutées

L'écran `Suivi xAPI` contient désormais :

```text
Tableau de bord | Analyse | Expert | Configuration
```

- `Tableau de bord` : synthèse de l'activité xAPI du cours.
- `Analyse` : vue pédagogique par ressource.
- `Expert` : traces locales détaillées et export CSV.
- `Configuration` : activation du cours, activation des ressources et personnalisation du dashboard.

### Tableau de bord

- Compteurs principaux : traces, traces envoyées, erreurs, apprenants actifs, ressources tracées, tests, score moyen.
- Comparaison avec la période précédente.
- Activité par jour.
- Répartition des actions xAPI.
- Top ressources.
- Ressources activées sans trace.
- État technique local de l'outbox.
- Personnalisation des widgets visibles par cours.
- Persistance des préférences dashboard dans `evnt_evhk_itxeb_ccfg`.

### Analyse pédagogique

- Filtre période : 7, 30, 90, 365 jours.
- Filtre par ressource.
- Filtre par type d'objet.
- Le filtre type est désactivé et ignoré lorsqu'une ressource précise est sélectionnée.
- Taux réussite / échec par test.
- Signal `à surveiller` lorsque le taux d'échec atteint le seuil d'alerte.
- Signal `échecs fréquents` lorsque le taux d'échec est élevé.
- Code couleur : orange pour `à surveiller`, rouge pour `échecs fréquents`.
- Bloc `Apprenants en difficulté`, anonymisé.

### Vue Expert

- Table détaillée des traces locales.
- Export CSV UTF-8 avec BOM et séparateur `;`.
- Export tenant compte des filtres période, ressource et type.
- Ajout des colonnes de contexte : `course_ref_id`, `filter_ref_id`, `filter_obj_type`, `verb`, ressource, score, completion, success, statut outbox, UUID statement et dernière erreur.

### Données et limites

- La V0.9.1 exploite l'outbox locale `evnt_evhk_itxeb_out` et les statements JSON déjà générés.
- La V0.9.1 ne requête pas encore directement TRAX/LRS.
- L'interrogation directe TRAX/LRS est prévue après merge/tag V0.9.1.
- L'export PDF est prévu après l'interrogation directe TRAX/LRS.

### Installation compagnon V0.9.1

Pour ILIAS 10.8 / Delos :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Le nom du wrapper est conservé pour compatibilité historique, mais son contenu applique désormais les correctifs de navigation Delos stabilisés.

## v0.8.1 — documentation stable main et guide d’exploitation V0.8

### Statut

- Branche concernée : `main`.
- Base : tag stable `v0.8.0`.
- Type de release : documentation / exploitation uniquement.
- Version plugin principal : inchangée, `0.8.0`.
- Version plugin compagnon UIHook : inchangée, `0.1.1`.
- Changement PHP : aucun.
- Changement SQL : aucun.
- Migration ILIAS : aucune nouvelle étape.

### Objectif

La V0.8.1 fige le rattrapage documentaire réalisé après la promotion de `v0.8.0` sur `main`.

Elle rend cohérents tous les documents principaux avec l’état publié :

```text
main = stable publiée v0.8.0
plugin principal = 0.8.0
plugin compagnon = 0.1.1
```

### Documentation mise à jour

- `README.md` : indication claire que `main` est la stable publiée V0.8.0.
- `README_TECHNIQUE.md` : mise à jour complète de l’architecture technique V0.8.
- `docs/VALIDATION.md` : remplacement du plan V0.7 par le plan de validation V0.8.0.
- `docs/OPERATIONS.md` : mise à jour du guide d’exploitation avec outbox, diagnostic des refus, purge et companion.
- `doc/README.md` : mise à jour de la documentation centrale historique.
- `docs/RELEASE_0.8.0.md` : release marquée comme publiée et promue sur `main`.
- `CHANGELOG.md` : ajout de cette entrée V0.8.1.

### Contenu couvert

- Installation stable depuis `main`.
- Verrouillage possible sur le tag `v0.8.0`.
- Installation du plugin compagnon via `scripts/install_course_ui_companion.sh`.
- Contrôles Composer pour vérifier l’absence des warnings `Ambiguous class resolution` liés à `IliasTraxEventBridgeCourseUI`.
- Plan de validation V0.8 complet.
- Guide d’exploitation V0.8 complet.
- Requêtes SQL utiles pour l’outbox, la configuration cours/ressources et le diagnostic des refus.

### Compatibilité

Cette release documentaire ne modifie pas le comportement applicatif.

Une installation déjà validée en `v0.8.0` peut rester sur le tag `v0.8.0` pour le code applicatif, ou suivre `main` pour bénéficier de la documentation corrigée.

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
- Ajout de `docs/V0.7_COURSE_TRACKING_CONFIG.md`.

### Jalon 3 — résolution cours / ressources

- Ajout de `ilIliasTraxEventBridgeCourseResourceResolver`.
- Le resolver liste les ressources traçables contenues dans un cours à partir d'un `course_ref_id`.
- Types préparés : `file`, `tst`, `blog`, `wiki`, `webr`, `mcst`, `frm`, `htlm`, `lm`, `sahs`.
- Pour chaque ressource, le resolver prépare `ref_id`, `obj_id`, `obj_type`, famille, titre, chemin, état `configured` et état `enabled`.
- Ajout de `docs/V0.7_COURSE_RESOURCE_RESOLVER.md`.

### Jalon 4 — interface de configuration cours

- Ajout de `ilIliasTraxEventBridgeCourseTrackingGUI`.
- L'interface permet d'afficher le résumé d'un cours, l'état d'activation xAPI du cours et les ressources traçables retournées par le resolver.
- Actions disponibles : `show`, `save`, `enableAll`, `disableAll`, `resetCourse`.
- Les choix sont enregistrés dans `evnt_evhk_itxeb_ccfg` et `evnt_evhk_itxeb_rcfg`.
- Les droits cours sont vérifiés avant écriture via les permissions `write`, `edit_permission` ou `manage_members`.
- Ajout de `docs/V0.7_COURSE_TRACKING_UI.md`.

### Jalon 4b — accès visible depuis l'administration du plugin

- `ilIliasTraxEventBridgeCourseTrackingGUI` peut désormais être embarqué depuis un autre contrôleur GUI.
- Ajout d'une section visible `Configuration xAPI par cours` dans l'écran d'administration du plugin.
- L'administrateur peut saisir un `course_ref_id`, ouvrir l'écran de configuration xAPI du cours, enregistrer les choix, tout activer, tout désactiver ou réinitialiser le cours.
- Ajout de `docs/V0.7_COURSE_TRACKING_ACCESS.md`.

### Jalon 5 — filtrage avant outbox

- Ajout du filtrage effectif avant insertion dans `evnt_evhk_itxeb_out`.
- Les événements EventHook ne génèrent un statement que si le cours est activé dans `evnt_evhk_itxeb_ccfg` et si la ressource est activée dans `evnt_evhk_itxeb_rcfg`.
- Les consultations issues de `read_event` appliquent la même règle avant génération outbox.
- Les consultations `read_event` refusées par la configuration sont marquées traitées dans `evnt_evhk_itxeb_read` afin d'éviter une boucle cron.
- La configuration est strictement opt-in : sans configuration explicite du cours et de la ressource, aucun statement xAPI n'est généré.
- Ajout de `docs/V0.7_OUTBOX_FILTERING.md`.

### Jalon 6 — stabilisation documentaire V0.7

- Alignement de `README.md` avec l'état V0.7.
- Alignement de `README_TECHNIQUE.md` avec l'architecture V0.7.
- Mise à jour de `docs/VALIDATION.md` pour remplacer le plan V0.6 par un plan V0.7 complet.
- Ajout des critères de validation réels : cours activé, ressources activées envoyées, ressources désactivées refusées avant outbox.

## v0.6.0 — stable

### Statut

- Version stable V0.6.0 taguée après validation serveur et Windows.
- Branches `main` et `v0.6` alignées sur la stable V0.6.0.
