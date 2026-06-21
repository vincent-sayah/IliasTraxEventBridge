# Changelog — IliasTraxEventBridge

Toutes les évolutions notables du plugin sont listées ici.

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
