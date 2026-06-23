# Changelog — IliasTraxEventBridge

Toutes les évolutions notables du plugin sont listées ici.

## v0.6.0 — développement

### Statut

- Branche de développement `v0.6` créée à partir de `main` / `v0.5.5` stable.
- Version plugin portée à `0.6.0` pour ouvrir la série V0.6.
- `main` et `v0.5` restent les références stables V0.5.5 tant que la V0.6 n'est pas validée.

### Ajouté

- Enrichissement des statements xAPI avec `object_title`, `object_url`, `course_title` et `course_url` quand les informations ILIAS sont disponibles.
- Ajout du cours parent dans `context.contextActivities.parent` pour relier les consultations, fichiers et tests au cours ILIAS.
- Ajout de `read_event_first_access` dans les records xAPI issus de `read_event`, en complément de `read_count`, `spent_seconds` et `read_event_last_access`.
- Classification V0.6 des statements via les extensions `statement_family`, `interaction_type` et `repository_object_family` pour faciliter les analyses TRAX.
- Ajout de `result.duration` au format ISO 8601 lorsque `spent_seconds` est disponible et supérieur à zéro.
- Ajout de descriptions xAPI sur les activités objet/cours pour rendre les statements plus lisibles dans TRAX.
- Ajout d'extensions de diagnostic outbox dans `context.extensions` : `outbox_id`, `outbox_table`, `event_log_id`, `statement_uuid`, `event_record_source`, `source_table` et `deduplication_key`.
- Ajout d'une section admin `Supervision V0.6` dans l'écran de configuration du plugin, calculée sur les 200 dernières lignes outbox : statuts, événements, objets, familles xAPI, types d'interaction, sources techniques, dernières clés de diagnostic et dernières erreurs.
- Mise à jour du plan de validation V0.6 avec les contrôles SQL complets : familles xAPI, métriques `read_event`, diagnostics outbox, wording bilingue, envoi TRAX et absence de traces parasites.

### Changé

- Les consultations issues de `read_event` utilisent désormais des verbes plus précis selon le type d'objet : lecture de blog/wiki/module, visite de lien web, visionnage de mediacast, interaction forum, lancement SCORM.
- Le téléchargement de fichier utilise un verbe xAPI dédié `downloaded` au lieu du libellé générique `experienced`.
- Les statements de test conservent les verbes `attempted`, `passed` et `failed`, mais avec un wording plus explicite (`a commencé le test`, `a réussi le test`, `a échoué au test`).
- Le contexte des tests utilise désormais `source_event = test_tracking_status` dans le JSON xAPI, en cohérence avec l'outbox.
- Les statements sont enrichis au moment de l'insertion outbox afin d'y inclure l'identifiant technique local `outbox_id` sans modifier le schéma SQL.
- Les descriptions xAPI `en-US` sont maintenant réellement anglophones, distinctes des descriptions `fr-FR`.
- L'écran d'administration affiche désormais la série V0.6 et expose une vue de supervision opérationnelle sans requête SQL manuelle.

### Cible fonctionnelle

- Enrichir les statements xAPI avec les titres ILIAS utiles : cours, objet et contexte.
- Ajouter les URL ILIAS exploitables dans les statements et/ou extensions xAPI.
- Exploiter plus explicitement `read_count`, `spent_seconds`, `first_access` et `last_access` issus de `read_event`.
- Affiner les verbes, activity types et familles de statements selon les types d'objets : consultation, test, fichier, forum, wiki, module, SCORM.
- Ajouter des extensions xAPI plus riches pour faciliter l'analyse dans TRAX.
- Préparer les futurs filtres de configuration, diagnostics TRAX et purge configurable.

### Premier jalon

- Initialisation documentaire et technique de la branche V0.6.
- Ajout d'un plan de reprise V0.6 dans `docs/V0.6_DEV_PLAN.md`.

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
