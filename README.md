# IliasTraxEventBridge

Plugin ILIAS 10 EventHook pour transformer certains événements ILIAS en statements xAPI et les envoyer vers TRAX 3 LRS via une outbox locale.

Version stable actuelle : **v0.5.5**. Branche de développement avancée : **v0.6** avec plugin version **0.6.0** en pré-stabilisation.

## État des branches

| Branche | Rôle | État |
|---|---|---|
| `main` | Stable publiée | Alignée sur la série v0.5.5 |
| `v0.5` | Maintenance stable V0.5 | Stable v0.5.5 |
| `v0.6` | Développement / pré-stabilisation | Enrichissement xAPI, diagnostics, supervision admin |

La branche `v0.6` ne doit pas encore remplacer `main` tant que le dernier cycle de validation serveur n'est pas terminé.

## Fonctionnalités stables v0.5.5

- Captation d'événements ILIAS via EventHook.
- Journal brut des événements reçus dans `evnt_evhk_itxeb_log`.
- Génération locale de statements xAPI.
- Outbox locale avec statuts `generated`, `sending`, `sent`, `failed`.
- Envoi manuel vers TRAX.
- Envoi automatique par job cron ILIAS `itxeb_send_outbox_to_trax`.
- Retry configurable avec `retry_count`, `max_retry` et `last_attempt_at`.
- Bouton de réinitialisation des statements en échec.
- Filtre métier : seuls les objets contenus dans un **cours** peuvent générer des statements xAPI.
- Exclusion des objets placés directement dans une catégorie, un dossier hors cours ou un autre contexte non cours.
- Suivi de l'exploitation réelle des objets de dépôt via la table ILIAS `read_event`.
- Table anti-doublon locale `evnt_evhk_itxeb_read`.
- Suppression des traces parasites `Tracking:updateStatus` génériques sur `crs` ou `root`.

## Apports V0.6 en pré-stabilisation

La V0.6 conserve le périmètre métier de la V0.5.5 et enrichit les statements ainsi que l'exploitation opérationnelle.

- Titres ILIAS dans les statements : `object_title`, `course_title`.
- URLs ILIAS : `object_url`, `course_url`.
- Cours parent dans `context.contextActivities.parent`.
- Métriques `read_event` : `read_count`, `spent_seconds`, `read_event_first_access`, `read_event_last_access`.
- Durée xAPI `result.duration` quand `spent_seconds > 0`.
- Verbes et libellés plus précis par famille d'objet.
- Extensions d'analyse : `statement_family`, `interaction_type`, `repository_object_family`.
- Extensions de diagnostic : `outbox_id`, `outbox_table`, `event_log_id`, `statement_uuid`, `event_record_source`, `source_table`, `deduplication_key`.
- Descriptions bilingues `fr-FR` / `en-US` pour les activités xAPI.
- Écran admin `Supervision V0.6` avec compteurs, familles, sources, diagnostics et erreurs.
- Guide d'exploitation `docs/OPERATIONS.md`.
- Plan de validation complet `docs/VALIDATION.md`.

## Objets couverts en V0.6

| Action ILIAS | Source | `event_type` SQL | Verbe xAPI / famille |
|---|---|---|---|
| Démarrage d'un test dans un cours | `Tracking:updateStatus` test | `test_tracking_status` | `attempted` / `test_tracking` |
| Test réussi dans un cours | `Tracking:updateStatus` test | `test_tracking_status` | `passed` / `test_tracking` |
| Test échoué dans un cours | `Tracking:updateStatus` test | `test_tracking_status` | `failed` / `test_tracking` |
| Téléchargement d'un fichier dans un cours | EventHook `sendfile` | `file_downloaded` | `downloaded` / `file_download` |
| Consultation blog dans un cours | `read_event` | `repository_object_access` | `read` / `repository_blog_access` |
| Consultation forum dans un cours | `read_event` | `repository_object_access` | `interacted` / `repository_forum_access` |
| Consultation lien web dans un cours | `read_event` | `repository_object_access` | `visited` / `repository_web_link_access` |
| Consultation mediacast dans un cours | `read_event` | `repository_object_access` | `viewed` / `repository_media_access` |
| Consultation wiki dans un cours | `read_event` | `repository_object_access` | `read` / `repository_wiki_access` |
| Consultation module HTML dans un cours | `read_event` | `repository_object_access` | `read` / `repository_html_module_access` |
| Consultation module web dans un cours | `read_event` | `repository_object_access` | `read` / `repository_learning_module_access` |
| Consultation module SCORM dans un cours | `read_event` | `repository_object_access` | `launched` / `repository_scorm_access` |

Les actions d'administration, comme la suppression des résultats de test, sont journalisées mais ne sont pas envoyées dans l'outbox xAPI.

## Installation stable depuis GitHub

Exemple avec une racine ILIAS située dans `/var/www/ilias` :

```bash
sudo -i

export ILIAS_ROOT="/var/www/ilias"
export EVENTHOOK_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook"
export PLUGIN_NAME="IliasTraxEventBridge"

mkdir -p "$EVENTHOOK_DIR"
cd "$EVENTHOOK_DIR"

git clone -b main --single-branch https://github.com/vincent-sayah/IliasTraxEventBridge.git "$PLUGIN_NAME"

cd "$PLUGIN_NAME"
grep -n '\$version' plugin.php

chown -R apache:apache "$EVENTHOOK_DIR/$PLUGIN_NAME"
find "$EVENTHOOK_DIR/$PLUGIN_NAME" -type d -exec chmod 755 {} \;
find "$EVENTHOOK_DIR/$PLUGIN_NAME" -type f -exec chmod 644 {} \;
find "$EVENTHOOK_DIR/$PLUGIN_NAME" -name "*.php" -print0 | xargs -0 -n1 php -l

cd "$ILIAS_ROOT"
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
```

Résultat attendu sur `main` tant que V0.6 n'est pas promue :

```text
$version = "0.5.5";
```

## Installation ou test de la branche V0.6

Pour installer explicitement la branche de développement V0.6 :

```bash
sudo -i

export ILIAS_ROOT="/var/www/ilias"
export EVENTHOOK_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook"
export PLUGIN_NAME="IliasTraxEventBridge"

cd "$EVENTHOOK_DIR"
git clone -b v0.6 --single-branch https://github.com/vincent-sayah/IliasTraxEventBridge.git "$PLUGIN_NAME"

cd "$PLUGIN_NAME"
grep -n '\$version' plugin.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l

cd "$ILIAS_ROOT"
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
```

Résultat attendu sur `v0.6` :

```text
$version = "0.6.0";
```

## Mise à jour d'une installation existante vers V0.6

```bash
sudo -i

cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git config remote.origin.fetch "+refs/heads/*:refs/remotes/origin/*"
git fetch origin --prune --tags
git switch v0.6
git pull --ff-only origin v0.6

git status --short
git branch --show-current
grep -n '\$version' plugin.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l

cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
```

## Configuration TRAX

Dans l'écran de configuration du plugin :

| Champ | Description |
|---|---|
| Activer génération xAPI locale | Autorise la création de statements dans l'outbox. |
| Activer envoi manuel | Autorise le bouton d'envoi manuel. |
| Activer le cron plugin | Autorise le job cron du plugin à générer les consultations `read_event` et à envoyer l'outbox. |
| Endpoint xAPI TRAX | Endpoint xAPI racine ou endpoint complet `/statements`. |
| Identifiant client TRAX | Client xAPI TRAX. |
| Secret client TRAX | Secret associé au client xAPI. |
| Version xAPI | Recommandé : `1.0.3`. |
| Timeout HTTP | Timeout d'appel HTTP. |
| Taille batch | Nombre maximum de statements envoyés par batch manuel ou cron. |
| Max retry | Nombre maximum de tentatives par statement. |
| Base URL ILIAS forcée | Utilisée pour les IRIs xAPI et `actor.account.homePage`. |

Le plugin ajoute automatiquement `/statements` si l'endpoint fourni ne se termine pas déjà par `/statements`.

## Cron ILIAS

L'option **Activer le cron plugin** autorise le plugin à travailler lorsque le cron ILIAS s'exécute, mais elle ne planifie pas l'exécution à elle seule.

Il faut aussi activer le job dans ILIAS :

```text
Administration > Paramètres système et maintenance > Tâches cron
```

Job à activer :

```text
IliasTraxEventBridge — envoi outbox vers TRAX
```

Identifiant technique :

```text
itxeb_send_outbox_to_trax
```

Le cron doit être actif dans ILIAS et le cron système/CLI d'ILIAS doit tourner régulièrement sur le serveur. Sans cela, les consultations détectées dans `read_event` ne sont transformées en statements xAPI qu'au prochain passage du cron.

## Supervision V0.6

Dans ILIAS :

```text
Administration > Plugins > IliasTraxEventBridge > Configurer
```

La section `Supervision V0.6` affiche :

- les compteurs d'exploitation : total, 24h, 7j, `sent`, `generated`, `failed`, retry épuisé ;
- les répartitions sur les dernières lignes outbox : statuts, événements, objets, familles xAPI, interactions et sources ;
- les dernières clés de diagnostic ;
- les dernières erreurs.

## Vérifications SQL utiles

Outbox récente :

```sql
SELECT id, event_log_id, event_type, obj_type, ref_id, obj_id,
       user_id, status, retry_count, created_at, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 20;
```

Table anti-doublon `read_event` :

```sql
SELECT *
FROM evnt_evhk_itxeb_read
ORDER BY processed_at DESC
LIMIT 20;
```

Inspection JSON V0.6 :

```sql
SELECT id, event_type, obj_type, status, statement_json
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 1\G
```

Vérifier qu'aucun statement parasite `root` ou `crs` n'est créé :

```sql
SELECT id, event_log_id, event_type, obj_type, ref_id, obj_id,
       user_id, status, created_at, sent_at
FROM evnt_evhk_itxeb_out
WHERE obj_type IN ('root', 'crs')
ORDER BY id DESC
LIMIT 20;
```

## Documentation complémentaire

- [README technique](README_TECHNIQUE.md)
- [Changelog](CHANGELOG.md)
- [Guide d'exploitation](docs/OPERATIONS.md)
- [Plan de validation](docs/VALIDATION.md)
- [Plan de stabilisation V0.6](docs/V0.6_STABILISATION.md)
- [Guide d'import GitHub](GITHUB_IMPORT.md)
- [Documentation centrale](doc/README.md)
