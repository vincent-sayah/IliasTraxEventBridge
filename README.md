# IliasTraxEventBridge

Plugin ILIAS 10 EventHook pour transformer certains événements ILIAS en statements xAPI et les envoyer vers un LRS xAPI, notamment TRAX 3, via une outbox locale.

Version stable publiée : **v0.8.0** sur `main`, plugin version **0.8.0**.

Plugin compagnon UIHook : **IliasTraxEventBridgeCourseUI 0.1.1**.

## État des branches

| Branche | Rôle | État |
|---|---|---|
| `main` | Stable publiée par défaut | Stable `v0.8.0` |
| `v0.8-outbox-supervision` | Branche de développement V0.8 | Clôturée après tag `v0.8.0` et promotion sur `main` |
| `v0.7.1-course-object-ui` | Maintenance V0.7.1 | Stable taguée `v0.7.1` |
| `v0.7` | Maintenance V0.7 | Stable taguée `v0.7.0` |
| `v0.6` | Maintenance V0.6 | Stable taguée `v0.6.0` |

## Fonctionnalités principales

- Captation d'événements ILIAS via EventHook.
- Journal brut des événements reçus dans `evnt_evhk_itxeb_log`.
- Génération locale de statements xAPI.
- Outbox locale avec statuts `generated`, `sending`, `sent`, `failed`.
- Envoi manuel vers le LRS configuré.
- Envoi automatique par job cron ILIAS `itxeb_send_outbox_to_trax`.
- Retry configurable avec `retry_count`, `max_retry` et `last_attempt_at`.
- Filtre métier : seuls les objets contenus dans un cours peuvent générer des statements xAPI.
- Pilotage strict opt-in : un statement est généré seulement si le cours et la ressource sont activés.
- Accès dans l'objet cours : `Paramètres > Suivi xAPI` via le plugin compagnon `IliasTraxEventBridgeCourseUI`.
- Supervision outbox dans l'administration du plugin.
- Diagnostic V0.8 des traces refusées, activable à la demande et purgeable.

## Règle métier V0.7/V0.8

```text
statement xAPI autorisé = cours activé ET ressource activée
```

Sans configuration explicite du cours et de la ressource, aucune trace xAPI n'est générée.

## Tables principales

| Table | Rôle |
|---|---|
| `evnt_evhk_itxeb_log` | Journal brut des événements ILIAS reçus |
| `evnt_evhk_itxeb_out` | Outbox locale des statements xAPI |
| `evnt_evhk_itxeb_read` | Anti-doublon local pour les consultations `read_event` |
| `evnt_evhk_itxeb_ccfg` | Configuration xAPI par cours |
| `evnt_evhk_itxeb_rcfg` | Configuration xAPI par ressource dans un cours |
| `evnt_evhk_itxeb_dlog` | Diagnostic V0.8 des traces refusées |

## Objets couverts

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

## Installation stable V0.8.0

### Plugin principal EventHook

```bash
sudo -i

export ILIAS_ROOT="/var/www/ilias"
export EVENTHOOK_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook"
export PLUGIN_NAME="IliasTraxEventBridge"

mkdir -p "$EVENTHOOK_DIR"
cd "$EVENTHOOK_DIR"

# Installation neuve depuis la stable main
git clone -b main --single-branch https://github.com/vincent-sayah/IliasTraxEventBridge.git "$PLUGIN_NAME"
cd "$PLUGIN_NAME"

# Pour verrouiller exactement la release stable
git checkout v0.8.0

grep -n '\$version' plugin.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

Résultat attendu :

```text
$version = "0.8.0";
```

### Plugin compagnon UIHook

Le compagnon est fourni sous forme de templates `.php.tpl` dans le dossier `companion/` afin d'éviter les warnings Composer `Ambiguous class resolution`.

Installation/régénération :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
bash scripts/install_course_ui_companion.sh
```

Chemin cible généré :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

### Rebuild ILIAS

```bash
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
```

Dans ILIAS, mettre à jour les plugins si proposé :

```text
Administration > Plugins > IliasTraxEventBridge > Mettre à jour
Administration > Plugins > IliasTraxEventBridgeCourseUI > Mettre à jour
```

## Configuration LRS / TRAX

Dans l'écran de configuration du plugin :

| Champ | Description |
|---|---|
| Activer le cron plugin | Autorise le job cron du plugin à générer les consultations `read_event` et à envoyer l'outbox |
| Activer le diagnostic des traces refusées | Active l'écriture dans `evnt_evhk_itxeb_dlog`, uniquement à la demande |
| Endpoint xAPI TRAX | Endpoint xAPI racine ou endpoint complet `/statements` |
| Identifiant client TRAX | Client xAPI autorisé à écrire |
| Secret client TRAX | Secret associé au client |
| Version xAPI | Recommandé : `1.0.3` |
| Timeout HTTP | Timeout d'appel HTTP |
| Taille batch | Nombre maximum de statements envoyés par batch manuel ou cron |
| Max retry | Nombre maximum de tentatives par statement |
| Base URL ILIAS forcée | Utilisée pour les IRIs xAPI et `actor.account.homePage` |

Le plugin ajoute automatiquement `/statements` si l'endpoint fourni ne se termine pas déjà par `/statements`.

## Configuration xAPI par cours

Dans ILIAS :

```text
Cours > Paramètres > Suivi xAPI
```

Procédure :

1. cocher `Activer les traces xAPI pour ce cours` ;
2. cocher les ressources à tracer ;
3. cliquer sur `Enregistrer la configuration xAPI`.

L'accès admin historique reste disponible :

```text
Administration > Plugins > IliasTraxEventBridge > Configurer > Configuration xAPI par cours
```

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

## Supervision V0.8

Dans ILIAS :

```text
Administration > Plugins > IliasTraxEventBridge > Configurer
```

La page affiche :

- l'état de configuration du plugin ;
- les diagnostics des derniers tests / envois / cron ;
- les compteurs d'exploitation outbox ;
- les dernières erreurs outbox ;
- le diagnostic des traces refusées V0.8 ;
- les dernières lignes outbox ;
- les derniers événements ILIAS reçus.

Le diagnostic des traces refusées est désactivé par défaut. À activer uniquement pendant une phase d'analyse ciblée.

## Vérifications SQL utiles

Configuration d'un cours :

```sql
SELECT *
FROM evnt_evhk_itxeb_ccfg
WHERE course_ref_id = 194;

SELECT course_ref_id, ref_id, obj_id, obj_type, enabled, updated_at, updated_by
FROM evnt_evhk_itxeb_rcfg
WHERE course_ref_id = 194
ORDER BY ref_id;
```

Outbox récente :

```sql
SELECT id, event_log_id, event_type, obj_type, ref_id, obj_id,
       user_id, status, retry_count, created_at, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 20;
```

Diagnostic des refus :

```sql
SELECT id, created_at, reason, event_type, user_id, course_ref_id,
       ref_id, obj_id, obj_type, source_table, source_id
FROM evnt_evhk_itxeb_dlog
ORDER BY id DESC
LIMIT 30;

SELECT reason, COUNT(*) AS total
FROM evnt_evhk_itxeb_dlog
GROUP BY reason
ORDER BY total DESC, reason ASC;
```

Purge du diagnostic :

```sql
SELECT COUNT(*) AS total
FROM evnt_evhk_itxeb_dlog;
```

## Documentation complémentaire

- [Changelog](CHANGELOG.md)
- [Release v0.8.0](docs/RELEASE_0.8.0.md)
- [V0.8 lot 1 — journal des refus](docs/V0.8_LOT1_DENY_LOG.md)
- [V0.8 lot 2 — supervision des refus](docs/V0.8_LOT2_DENY_SUPERVISION.md)
- [V0.8 lot 3 — packaging companion](docs/V0.8_LOT3_COMPANION_PACKAGING.md)
- [README technique](README_TECHNIQUE.md)
- [Guide d'exploitation](docs/OPERATIONS.md)
- [Plan de validation](docs/VALIDATION.md)
- [Documentation centrale](doc/README.md)
