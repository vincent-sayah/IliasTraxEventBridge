# Changelog — IliasTraxEventBridge

Toutes les évolutions notables du plugin sont listées ici.

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

### Ajouté

- Diagnostic persistant du bouton **Tester connexion TRAX**.
- Affichage du dernier test de connexion dans la configuration : date, succès, code HTTP, message retourné.
- Affichage du dernier envoi manuel vers TRAX.

### Corrigé

- Le bouton de test peut maintenant être diagnostiqué même si les messages flash ILIAS ne s’affichent pas.

## v0.3.0

### Ajouté

- Configuration TRAX dans l’écran du plugin.
- Endpoint xAPI, identifiant client, secret client, version xAPI, timeout HTTP, taille de batch.
- Boutons **Tester connexion TRAX** et **Envoyer les statements générés vers TRAX**.
- Client HTTP xAPI.
- Statuts outbox : `generated`, `sending`, `sent`, `failed`.

### Validé

- Envoi manuel de statements vers TRAX.
- Passage des lignes outbox au statut `sent`.
- Gestion de l’erreur HTTP 401 avec passage au statut `failed`.

## v0.2.1

### Corrigé

- Exclusion des événements d’administration liés à la suppression des résultats de test.
- Les événements contenant `cmdClass=ilTestParticipantsGUI`, `pt_action=delete_results` ou `cmd=executeTableAction` restent dans le journal brut mais ne sont plus transformés en xAPI.
- Les événements `Tracking:updateStatus` ambigus avec `obj_type` vide sont ignorés pour l’outbox s’ils ne viennent pas clairement du player de test.

## v0.2.0

### Ajouté

- Table outbox locale `evnt_evhk_itxeb_out`.
- Génération locale de statements xAPI.
- Mapping fichier téléchargé vers `experienced`.
- Mapping début de test vers `attempted`.
- Mapping test réussi vers `passed`.
- Mapping test échoué vers `failed`.
- Affichage de l’outbox dans la configuration du plugin.

## v0.1.5

### Amélioré

- Tableau de configuration plus lisible.
- Colonnes regroupées.
- Retour à la ligne dans les cellules.
- URI dans bloc scrollable.
- Payload JSON formaté et dépliable.
- Compteurs d’affichage : total, affichés, limite.

## v0.1.4

### Ajouté

- Affichage du payload JSON dans la configuration.
- Pré-classification des événements candidats xAPI.
- Récupération du `ref_id` depuis l’URI si absent du payload.
- Détection indicative du type d’objet via `cmdClass`.

## v0.1.3

### Corrigé

- Remplacement de `includeClass()` par `require_once`.
- Correction de l’erreur ILIAS 10 : `Call to undefined method ilIliasTraxEventBridgePlugin::includeClass()`.

## v0.1.2

### Corrigé

- Correction de l’écran de configuration du plugin.
- Ajout de la directive ilCtrl : `@ilCtrl_IsCalledBy ilIliasTraxEventBridgeConfigGUI: ilObjComponentSettingsGUI`.
- Utilisation correcte de `ilPluginConfigGUI`.

## v0.1.1

### Corrigé

- Signature ILIAS 10 de `handleEvent`.

## v0.1.0

### Ajouté

- Squelette initial du plugin.
- Installation EventHook.
- Table debug `evnt_evhk_itxeb_log`.
- Journalisation des événements ILIAS.
- Écran minimal de configuration.
