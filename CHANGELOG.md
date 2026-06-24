# Changelog — IliasTraxEventBridge

Toutes les évolutions notables du plugin sont listées ici.

## v0.6.0 — stable

### Statut

- Version stable V0.6.0 taguée après validation serveur et Windows.
- Branche `v0.6` conservée comme branche stable V0.6.
- `main` et `v0.5` restent alignées sur la série stable V0.5.5 tant que la promotion de `main` vers V0.6 n'est pas décidée explicitement.
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
- `README.md`, `README_TECHNIQUE.md`, `docs/VALIDATION.md`, `docs/OPERATIONS.md` et `docs/V0.6_STABILISATION.md` documentent désormais la série V0.6.

### Validation stable observée

- Génération et envoi `sent` validés pour fichier, test, blog, wiki, lien web, mediacast et module HTML.
- Vérification des extensions V0.6 dans le JSON xAPI : titres, URLs, contexte cours, familles, durée, métriques `read_event`, diagnostics outbox et clé de déduplication.
- Vérification du wording bilingue `fr-FR` / `en-US`.
- Vérification de la branche serveur et Windows en `v0.6` propre.
- Vérification anti-parasites : aucune nouvelle ligne outbox `root` ou `crs`.

### Après tag

- Le tag `v0.6.0` doit pointer sur le commit documentaire stable final de la branche `v0.6`.
- La promotion éventuelle de `main` vers V0.6 reste une décision séparée.

## v0.5.5 — stable

### Statut

- Version stable V0.5 conservée pour maintenance.
- Branche `main` encore alignée sur la série stable V0.5 tant que la promotion V0.6 n'est pas décidée.
- Branche `v0.5` conservée comme branche stable V0.5.

### Changé

- Nettoyage du périmètre xAPI V0.5 : les événements `Tracking:updateStatus` génériques non-test ne génèrent plus de statements xAPI.
- Les traces d'exploitation des objets de dépôt restent générées via `read_event` avec `event_type = repository_object_access`.
- Les traces de progression de test restent conservées via `Tracking:updateStatus` lorsqu'elles concernent réellement un test.
