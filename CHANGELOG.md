# Changelog — IliasTraxEventBridge

Toutes les évolutions notables du plugin sont listées ici.

## v0.5.5 — développement

### Changé

- Nettoyage du périmètre xAPI V0.5 : les événements `Tracking:updateStatus` génériques non-test ne génèrent plus de statements xAPI.
- Les traces d'exploitation des objets de dépôt restent générées via `read_event` avec `event_type = repository_object_access`.
- Les traces de progression de test restent conservées via `Tracking:updateStatus` lorsqu'elles concernent réellement un test.

### Corrigé

- Suppression de la pollution outbox par des statements `learning_tracking_status` sur des objets de type `crs` ou `root`.
- Le tracking utile est recentré sur les consultations réelles d'objets contenus dans un cours : blog, forum, lien web, mediacast, wiki, module HTML, module web et SCORM.

## v0.5.4 — développement

### Ajouté

- Ajout d'un tracker d'exploitation basé sur la table ILIAS `read_event`.
- Ajout de la table anti-doublon `evnt_evhk_itxeb_read` pour mémoriser le dernier `last_access` et le dernier `read_count` traités par couple `obj_id` / `usr_id`.
- Génération de statements xAPI `repository_object_access` avec le verbe `experienced` / `a consulté` pour les objets de dépôt contenus dans un cours.

### Couverture

- Blog : `blog`.
- Lien web : `webr`.
- Mediacast : `mcst`.
- Forum : `frm`.
- Wiki : `wiki`.
- Module HTML : `htlm`.
- Module web : `lm`.
- Module SCORM : `sahs`.

## v0.5.3 — développement

### Corrigé

- Correction du contexte cours pour les événements de création d'objet dans un cours : ILIAS peut transmettre le `ref_id` du cours conteneur pendant `create`, `insertNode` ou `putObjectInTree`, et non le `ref_id` final de l'objet créé.
- Le resolver accepte maintenant le cas où le `ref_id` reçu est lui-même un cours, et il tente aussi de retrouver les références de l'`obj_id` avant de se rabattre sur le `ref_id` de l'événement.
- Cette correction cible notamment la création de blog, lien web et mediacast dans un cours, qui pouvait apparaître dans le journal brut sans générer de ligne outbox.

## v0.5.2 — développement

### Corrigé

- Assouplissement de la génération xAPI pour les objets de dépôt contenus dans un cours : les événements `create` et `update` des types supportés sont acceptés quel que soit le composant ILIAS émetteur.
- Conservation prioritaire du traitement spécifique `Tracking:updateStatus` afin de ne pas remplacer les traces de progression par des interactions génériques.

### Note de test

- Cette correction cible notamment blog, lien web et mediacast lorsque ILIAS les journalise avec un composant différent de `components/ILIAS/ILIASObject`.
- La table outbox réelle est `evnt_evhk_itxeb_out`.

## v0.5.1 — développement

### Corrigé

- Détection renforcée du type d'objet ILIAS lorsque `obj_type` est vide dans l'événement reçu : le routeur utilise maintenant le `ref_id` ou l'`obj_id` via `ilObject::_lookupType()`.
- Ajout des mappings de classes GUI pour les objets blog, lien web et mediacast.
- Génération de statements xAPI pour les objets de dépôt contenus dans un cours : blog, lien web, mediacast, forum, wiki, module HTML, module web et module SCORM.
- Classification outbox des nouveaux statements avec le type `repository_object_update`.

## v0.5.0 — développement

### Ajouté

- Nouveau filtre métier : seuls les objets contenus dans un objet **cours** peuvent générer un statement xAPI.
- Nouveau service `ilIliasTraxEventBridgeCourseContextResolver` pour retrouver le cours parent à partir du `ref_id` ou, en secours, des références de l'`obj_id`.
- Ajout des extensions xAPI `course_ref_id` et `course_obj_id` dans les statements générés.

### Changé

- Les événements bruts restent journalisés dans `evnt_evhk_itxeb_log`, mais les objets hors cours ne sont plus ajoutés à l'outbox xAPI.
- Le routeur enrichit le record avec le contexte cours avant d'appeler la factory xAPI.

## v0.4.3 — stable

### Stabilisé

- Validation fonctionnelle de la V0.4 après tests serveur : génération outbox, envoi manuel, envoi automatique par cron ILIAS, retry, reset des `failed` et diagnostics.
- Version stable recommandée avant ouverture de la V0.5.

### Corrigé

- Amélioration de l’affichage des tableaux dans la configuration du plugin.
- Colonne **Verb** élargie afin d’afficher les verbes xAPI en entier.
- Colonne **URI** replacée avant le payload dans le journal des événements et élargie pour une lecture plus confortable.
- Payloads conservés dans des blocs repliables afin d’éviter de repousser les colonnes importantes hors écran.

## v0.4.2

### Corrigé

- Compatibilité ILIAS 10 : utilisation de l’interface globale `ilCronJobProvider` au lieu de `ILIAS\Cron\Job\JobProvider`, non disponible sur certaines installations ILIAS 10.
- Le plugin EventHook peut maintenant être reconnu par le dépôt cron ILIAS 10 via `instanceof ilCronJobProvider`.

## v0.4.1

### Corrigé

- Déclaration explicite du plugin comme fournisseur de jobs cron ILIAS via `ILIAS\Cron\Job\JobProvider`.
- Correction de la méthode `getCronJobInstance()` pour respecter le comportement attendu par ILIAS : retour du job connu ou exception `OutOfBoundsException`.
- Le job `itxeb_send_outbox_to_trax` peut maintenant apparaître dans la liste des tâches cron ILIAS.

## v0.4.0

### Ajouté

- Envoi automatique des statements xAPI vers TRAX via un job cron ILIAS.
- Nouveau service partagé `ilIliasTraxEventBridgeOutboxSender` utilisé par l’envoi manuel et le cron.
- Paramètre d’administration **Activer le cron plugin**.
- Paramètre **Max retry** pour limiter les tentatives d’envoi.
- Colonnes outbox : `retry_count`, `max_retry`, `last_attempt_at`.
- Bouton **Réinitialiser les failed** pour remettre les statements en échec au statut `generated` avec `retry_count = 0`.
- Diagnostic persistant du dernier passage cron : date, succès, HTTP, message.
- Compteur des statements dont le retry est épuisé.

### Changé

- L’envoi manuel et l’envoi cron utilisent la même logique de batch.
- Les statements `failed` ne sont retentés que si `retry_count < max_retry`.
- Les statements JSON invalides sont marqués en erreur sans bloquer tout le batch.

## v0.3.1
