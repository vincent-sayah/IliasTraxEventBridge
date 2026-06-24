# README technique — IliasTraxEventBridge

Version stable actuelle : **v0.5.5**. Branche de développement en pré-stabilisation : **v0.6**, plugin version **0.6.0**.

## Type de plugin

Le plugin est un plugin ILIAS de type :

```text
Services/EventHandling/EventHook
```

Chemin d'installation attendu :

```text
public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

Classe principale :

```text
classes/class.ilIliasTraxEventBridgePlugin.php
```

Méthode appelée par ILIAS 10 :

```php
public function handleEvent(string $a_component, string $a_event, array $a_parameter): void
```

## Versions

### v0.5.5 stable

La V0.5.5 stabilise :

- le filtrage métier « objet contenu dans un cours uniquement » ;
- la génération de statements xAPI pour tests et fichiers dans un cours ;
- la détection des consultations réelles d'objets via `read_event` ;
- l'envoi manuel et l'envoi cron vers TRAX ;
- l'anti-doublon de consultation via `evnt_evhk_itxeb_read` ;
- l'exclusion des statements parasites `root` et `crs` issus de `Tracking:updateStatus` génériques.

### v0.6 en pré-stabilisation

La V0.6 conserve le périmètre V0.5.5 et ajoute :

- enrichissement objet/cours : titres, URLs, activité parent cours ;
- métriques `read_event` : `read_count`, `spent_seconds`, `first_access`, `last_access` ;
- `result.duration` xAPI quand une durée utile est disponible ;
- familles analytiques : `statement_family`, `interaction_type`, `repository_object_family` ;
- verbes et libellés xAPI plus précis selon le type d'objet ;
- descriptions bilingues `fr-FR` / `en-US` ;
- diagnostics outbox dans le JSON xAPI : `outbox_id`, `statement_uuid`, `source_table`, `deduplication_key` ;
- supervision admin V0.6 avec compteurs opérationnels et dernières erreurs ;
- documentation d'exploitation dans `docs/OPERATIONS.md`.

## Organisation des classes

| Classe | Rôle |
|---|---|
| `ilIliasTraxEventBridgePlugin` | Point d'entrée EventHook ILIAS |
| `ilIliasTraxEventBridgeConfigGUI` | Écran de configuration, actions manuelles, supervision V0.6 |
| `ilIliasTraxEventBridgeConfig` | Lecture/écriture des paramètres via `ilSetting` |
| `ilIliasTraxEventBridgeEventRouter` | Normalisation, filtrage cours et routage des événements ILIAS |
| `ilIliasTraxEventBridgeCourseContextResolver` | Résolution du cours parent d'un objet ILIAS |
| `ilIliasTraxEventBridgeEventDebugRepository` | Persistance du journal brut |
| `ilIliasTraxEventBridgeStatementFactory` | Mapping événement ILIAS ou consultation `read_event` vers statement xAPI |
| `ilIliasTraxEventBridgeOutboxRepository` | Stockage, statut d'envoi, diagnostics et compteurs outbox |
| `ilIliasTraxEventBridgeOutboxSender` | Service d'envoi partagé par action manuelle et cron |
| `ilIliasTraxEventBridgeCron` | Job cron ILIAS d'envoi outbox vers TRAX et génération des consultations `read_event` |
| `ilIliasTraxEventBridgeReadEventTracker` | Détection des consultations réelles d'objets via la table ILIAS `read_event` |
| `ilIliasTraxEventBridgeTraxClient` | Client HTTP xAPI/TRAX |
| `ilIliasTraxEventBridgeHttpResult` | Objet résultat HTTP |

## Installation technique V0.6

Exemple avec ILIAS dans `/var/www/ilias` :

```bash
sudo -i

export ILIAS_ROOT="/var/www/ilias"
export EVENTHOOK_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook"
export PLUGIN_NAME="IliasTraxEventBridge"

mkdir -p "$EVENTHOOK_DIR"
cd "$EVENTHOOK_DIR"

git clone -b v0.6 --single-branch https://github.com/vincent-sayah/IliasTraxEventBridge.git "$PLUGIN_NAME"

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

Résultat attendu sur `v0.6` :

```text
$version = "0.6.0";
```

## Mise à jour technique V0.6

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

## Flux interne V0.6

```mermaid
flowchart TD
    H[handleEvent] --> C{Plugin actif ?}
    C -->|non| END[return]
    C -->|oui| R[EventRouter]

    R --> N[Normalisation record]
    N --> L[Insert evnt_evhk_itxeb_log]
    L --> G{Génération xAPI locale active ?}
    G -->|non| END
    G -->|oui| CRS[CourseContextResolver]
    CRS -->|pas de cours parent| END
    CRS -->|cours parent trouvé| F[StatementFactory]

    F -->|événement ignoré| END
    F -->|statement généré| O[OutboxRepository.enqueue]
    O --> DX[Ajout diagnostics outbox]
    DX --> DB[(evnt_evhk_itxeb_out)]

    CRON[Cron ILIAS] --> READ[ReadEventTracker]
    READ --> READDB[(read_event)]
    READ --> DEDUP[(evnt_evhk_itxeb_read)]
    READ --> OUT[Statements repository_object_access enrichis]
    OUT --> O
    CRON --> SEND[OutboxSender]
    SEND --> TRAX[TRAX 3 LRS]

    ADMIN[ConfigGUI] --> SUP[Supervision V0.6]
    SUP --> DB
```

Le journal brut reste alimenté même si l'objet n'est pas dans un cours. Le filtre agit uniquement avant la génération xAPI et l'ajout dans l'outbox.

## Filtre “objet contenu dans un cours uniquement”

Le service `ilIliasTraxEventBridgeCourseContextResolver` tente de confirmer un cours parent de façon conservative :

1. utiliser le `ref_id` détecté dans l'événement ou dans l'URI ;
2. si le `ref_id` est absent, tenter de retrouver les références de l'`obj_id` via `ilObject::_getAllReferences()` ;
3. lire le chemin complet du repository avec `$tree->getPathFull($ref_id)` ;
4. accepter le cas où le `ref_id` reçu est lui-même un cours, notamment lors de la création d'un objet dans un cours ;
5. à défaut, remonter les parents avec `$tree->getParentId()` et vérifier les types via `ilObject::_lookupType()`.

Un statement xAPI n'est généré que si un contexte cours est trouvé. Les objets directement placés en catégorie, dans un dossier hors cours ou dans un autre contexte non cours sont donc exclus de l'outbox.

Quand le cours parent est identifié, le record est enrichi avec :

```text
course_ref_id
course_obj_id
course_title
course_url
```

Ces valeurs sont ajoutées dans les extensions du statement xAPI, et le cours parent est ajouté dans `context.contextActivities.parent`.

## Tracking `read_event`

La table ILIAS `read_event` est utilisée pour détecter l'exploitation réelle des objets de dépôt. Le cron lit les consultations, vérifie que l'objet est contenu dans un cours, puis ajoute un statement `repository_object_access` dans l'outbox.

La table locale suivante évite les doublons :

```text
evnt_evhk_itxeb_read
```

Elle mémorise :

```text
obj_id
usr_id
last_access
read_count
processed_at
```

Le plugin génère une nouvelle trace si `last_access` ou `read_count` a évolué depuis le dernier traitement.

Les records issus de `read_event` enrichissent les statements avec :

```text
read_count
spent_seconds
read_event_first_access
read_event_last_access
```

## Normalisation des événements

Le routeur tente de récupérer :

- `user_id` depuis `usr_id`, `user_id`, utilisateur global ILIAS ;
- `ref_id` depuis les paramètres ou depuis `REQUEST_URI` ;
- `obj_id` depuis les paramètres ;
- `obj_type` depuis les paramètres, l'URI, `cmdClass`, ou en secours via les méthodes ILIAS de lookup.

Exemples de correspondance `cmdClass` :

| `cmdClass` | `obj_type` |
|---|---|
| `ilObjFileGUI` | `file` |
| `ilTestPlayerFixedQuestionSetGUI` | `tst` |
| `ilObjCourseGUI` | `crs` |
| `ilObjWikiGUI` | `wiki` |
| `ilObjFileBasedLMGUI` | `htlm` |
| `ilObjBlogGUI` | `blog` |
| `ilObjWebResourceGUI` | `webr` |
| `ilObjMediaCastGUI` | `mcst` |

## Mapping xAPI V0.6

| Source | Condition | `event_type` | Famille V0.6 |
|---|---|---|---|
| EventHook test | `Tracking:updateStatus` sur un objet `tst` dans un cours | `test_tracking_status` | `test_tracking` |
| EventHook fichier | `cmd=sendfile` sur un objet `file` dans un cours | `file_downloaded` | `file_download` |
| `read_event` blog | consultation blog dans un cours | `repository_object_access` | `repository_blog_access` |
| `read_event` forum | consultation forum dans un cours | `repository_object_access` | `repository_forum_access` |
| `read_event` lien web | consultation lien web dans un cours | `repository_object_access` | `repository_web_link_access` |
| `read_event` mediacast | consultation mediacast dans un cours | `repository_object_access` | `repository_media_access` |
| `read_event` wiki | consultation wiki dans un cours | `repository_object_access` | `repository_wiki_access` |
| `read_event` module HTML | consultation module HTML dans un cours | `repository_object_access` | `repository_html_module_access` |
| `read_event` module web | consultation module web dans un cours | `repository_object_access` | `repository_learning_module_access` |
| `read_event` SCORM | consultation module SCORM dans un cours | `repository_object_access` | `repository_scorm_access` |

Les événements `Tracking:updateStatus` génériques sur `crs` ou `root` ne génèrent plus de statements xAPI depuis v0.5.5.

## Extensions xAPI V0.6

Extensions fonctionnelles :

```text
source_event
statement_family
interaction_type
repository_object_family
ref_id
obj_id
obj_type
course_ref_id
course_obj_id
request_uri
object_title
object_url
course_title
course_url
read_count
spent_seconds
read_event_last_access
read_event_first_access
```

Extensions de diagnostic outbox :

```text
outbox_id
outbox_table
event_log_id
statement_uuid
event_record_source
source_table
deduplication_key
```

Sources techniques attendues :

| Source record | `source_table` | Cas |
|---|---|---|
| `read_event_tracker` | `read_event` | Consultations d'objets de dépôt |
| `event_hook_log` | `evnt_evhk_itxeb_log` | Fichiers et tests issus de l'EventHook |
| `synthetic` | vide | Cas synthétiques éventuels |

## Tables principales

| Table | Rôle |
|---|---|
| `evnt_evhk_itxeb_log` | Journal brut des événements EventHook reçus |
| `evnt_evhk_itxeb_out` | Outbox locale des statements xAPI |
| `evnt_evhk_itxeb_read` | Pointeur anti-doublon pour les consultations `read_event` |
| `read_event` | Table ILIAS utilisée comme source de consultation réelle |

## Supervision admin V0.6

L'écran de configuration affiche une section `Supervision V0.6` avec :

- compteurs d'exploitation : total, 24h, 7j, `sent`, `generated`, `failed`, retry épuisé ;
- répartitions sur les dernières lignes outbox ;
- familles xAPI, interactions et sources techniques ;
- dernières clés de diagnostic ;
- dernières erreurs.

Ces données sont issues de `ilIliasTraxEventBridgeOutboxRepository` et du JSON xAPI stocké dans `statement_json`.

## Vérifications SQL

Outbox récente :

```sql
SELECT id, event_log_id, event_type, obj_type, ref_id, obj_id,
       user_id, status, retry_count, created_at, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 20;
```

Consultations déjà traitées :

```sql
SELECT *
FROM evnt_evhk_itxeb_read
ORDER BY processed_at DESC
LIMIT 20;
```

Inspection JSON enrichi :

```sql
SELECT id, event_type, obj_type, status, statement_json
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 1\G
```

Absence de pollution `root` / `crs` :

```sql
SELECT id, event_log_id, event_type, obj_type, ref_id, obj_id,
       user_id, status, created_at, sent_at
FROM evnt_evhk_itxeb_out
WHERE obj_type IN ('root', 'crs')
ORDER BY id DESC
LIMIT 20;
```

## Documents liés

- `README.md` : présentation et installation.
- `docs/VALIDATION.md` : plan de validation V0.6 complet.
- `docs/OPERATIONS.md` : exploitation et maintenance.
- `docs/V0.6_STABILISATION.md` : checklist de stabilisation avant tag.
