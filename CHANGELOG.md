# Changelog — IliasTraxEventBridge

Toutes les évolutions notables du plugin sont listées ici.

## v0.5.5 — stable

### Statut

- Version stable actuelle du dépôt.
- Branche `main` alignée sur la série stable V0.5.
- Branche `v0.5` conservée comme branche stable V0.5.
- Prochaine cible de développement : `v0.6`.

### Changé

- Nettoyage du périmètre xAPI V0.5 : les événements `Tracking:updateStatus` génériques non-test ne génèrent plus de statements xAPI.
- Les traces d'exploitation des objets de dépôt restent générées via `read_event` avec `event_type = repository_object_access`.
- Les traces de progression de test restent conservées via `Tracking:updateStatus` lorsqu'elles concernent réellement un test.
- Documentation stabilisée : README, README technique, guide d'import GitHub, plan de validation, dossiers `doc/` et `docs/`.
- Ajout d'une procédure d'installation et de mise à jour directement dans le README principal.

### Corrigé

- Suppression de la pollution outbox par des statements `learning_tracking_status` sur des objets de type `crs` ou `root`.
- Le tracking utile est recentré sur les consultations réelles d'objets contenus dans un cours : blog, forum, lien web, mediacast, wiki, module HTML, module web et SCORM.
- Les documents ne présentent plus `v0.4.3` comme version stable courante ; `v0.4.3` est conservée uniquement comme archive de la série V0.4.

## v0.5.4 — développement

- Ajout d'un tracker d'exploitation basé sur la table ILIAS `read_event`.
- Ajout de la table anti-doublon `evnt_evhk_itxeb_read` pour mémoriser le dernier `last_access` et le dernier `read_count` traités par couple `obj_id` / `usr_id`.
- Génération de statements xAPI `repository_object_access` avec le verbe `experienced` / `a consulté` pour les objets de dépôt contenus dans un cours.
- Couverture : blog, lien web, mediacast, forum, wiki, module HTML, module web et module SCORM.

## v0.5.3 — développement

- Correction du contexte cours pour les événements de création d'objet dans un cours : ILIAS peut transmettre le `ref_id` du cours conteneur pendant `create`, `insertNode` ou `putObjectInTree`, et non le `ref_id` final de l'objet créé.
- Le resolver accepte maintenant le cas où le `ref_id` reçu est lui-même un cours, et il tente aussi de retrouver les références de l'`obj_id` avant de se rabattre sur le `ref_id` de l'événement.

## v0.5.2 — développement

- Assouplissement de la génération xAPI pour les objets de dépôt contenus dans un cours : les événements `create` et `update` des types supportés sont acceptés quel que soit le composant ILIAS émetteur.
- Conservation prioritaire du traitement spécifique `Tracking:updateStatus` afin de ne pas remplacer les traces de progression par des interactions génériques.

## v0.5.1 — développement

- Détection renforcée du type d'objet ILIAS lorsque `obj_type` est vide dans l'événement reçu.
- Ajout des mappings de classes GUI pour les objets blog, lien web et mediacast.
- Génération de statements xAPI pour les objets de dépôt contenus dans un cours : blog, lien web, mediacast, forum, wiki, module HTML, module web et module SCORM.

## v0.5.0 — développement

- Nouveau filtre métier : seuls les objets contenus dans un objet **cours** peuvent générer un statement xAPI.
- Nouveau service `ilIliasTraxEventBridgeCourseContextResolver` pour retrouver le cours parent.
- Ajout des extensions xAPI `course_ref_id` et `course_obj_id` dans les statements générés.

## v0.4.3 — stable archivée

- Version stable de clôture de la série V0.4.
- Envoi manuel et cron vers TRAX.
- Retry avec `retry_count` et `max_retry`.
- Diagnostics du dernier cron.
- Tableaux d'administration améliorés.
