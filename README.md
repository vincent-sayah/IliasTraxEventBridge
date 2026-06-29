# IliasTraxEventBridge

Plugin ILIAS 10 EventHook permettant de transformer certains événements ILIAS en statements xAPI, de les envoyer vers un LRS xAPI comme TRAX 3, puis d'afficher un suivi xAPI de cours alimenté directement par TRAX/LRS.

## Version stable officielle

| Élément | Valeur |
|---|---|
| Version stable | `0.10.1` |
| Branche stable officielle | `main` |
| Tag stable | `v0.10.1` |
| Branche de développement V0.10 | `v0.10-lrs-direct-read` |
| Compatibilité ILIAS | `10.0.0` à `10.999.999` |
| Plugin principal | `IliasTraxEventBridge` |
| Type plugin principal | `EventHook` |
| Plugin compagnon | `IliasTraxEventBridgeCourseUI` |
| Type plugin compagnon | `UIHook` |
| Source pédagogique du suivi xAPI | TRAX/LRS |
| Rôle de l'outbox locale | File technique d'envoi uniquement |

La V0.10.1 est maintenant promue sur `main`. Pour une installation stable, utiliser `main` ou le tag `v0.10.1`.

Correction importante V0.10.1 : le fichier `sql/dbupdate.php` commence par le marqueur ILIAS `<#1>`, ce qui sécurise l'installation depuis une ancienne version ou après désinstallation.

## Documentation complète

Le dossier `docs/` contient maintenant un index dédié : [`docs/README.md`](docs/README.md).

| Document | Rôle |
|---|---|
| [`docs/README.md`](docs/README.md) | Index général de toute la documentation. |
| [`docs/INSTALLATION.md`](docs/INSTALLATION.md) | Installation complète, mise à jour, reconstruction ILIAS, plugin compagnon, contrôles et dépannage. |
| [`docs/FONCTIONNEL.md`](docs/FONCTIONNEL.md) | Documentation fonctionnelle : objectifs, utilisateurs, parcours cours, vues Tableau de bord / Analyse / Expert / Configuration. |
| [`docs/TECHNIQUE.md`](docs/TECHNIQUE.md) | Documentation technique : architecture, EventHook, UIHook, outbox, TRAX/LRS, tables SQL, flux de lecture et d'envoi. |
| [`docs/EXPLOITATION.md`](docs/EXPLOITATION.md) | Exploitation : supervision, cron, tests LRS, requêtes SQL utiles, purge et analyse d'incident. |
| [`docs/DEVELOPPEUR.md`](docs/DEVELOPPEUR.md) | Documentation développeur : classes principales, conventions, migrations, contrôles avant livraison. |
| [`docs/ROADMAP.md`](docs/ROADMAP.md) | Roadmap à jour : V0.11, V0.12, IA d'analyse des traces, API keys IA, sécurité et gouvernance. |
| [`docs/IA_ANALYSE_TRACES.md`](docs/IA_ANALYSE_TRACES.md) | Cadrage détaillé de l'analyse des traces xAPI par IA. |
| [`docs/RELEASE_0.10.1.md`](docs/RELEASE_0.10.1.md) | Note de version stable V0.10.1. |
| [`docs/V0.10_LRS_DIRECT_READ.md`](docs/V0.10_LRS_DIRECT_READ.md) | Décision d'architecture V0.10 : lecture directe TRAX/LRS. |
| [`CHANGELOG.md`](CHANGELOG.md) | Historique des versions. |

## Principe d'architecture V0.10.1

```text
ILIAS 10
  ├─ EventHook IliasTraxEventBridge
  │    ├─ capte les événements ILIAS
  │    ├─ génère des statements xAPI
  │    └─ alimente l'outbox locale technique
  │
  ├─ Cron ILIAS
  │    └─ envoie l'outbox vers TRAX/LRS
  │
  └─ UIHook IliasTraxEventBridgeCourseUI
       └─ affiche l'écran Suivi xAPI dans le cours

TRAX / LRS
  ├─ reçoit les statements xAPI
  └─ devient la source officielle des vues pédagogiques
```

Décision centrale :

```text
Outbox locale = file technique d'envoi
TRAX/LRS      = source officielle du suivi xAPI pédagogique
```

L'outbox locale `evnt_evhk_itxeb_out` peut être purgée en exploitation. Elle ne doit donc pas être utilisée comme source fonctionnelle du tableau de bord pédagogique.

## Fonctionnalités principales

- Captation d'événements ILIAS via EventHook.
- Génération locale de statements xAPI.
- Envoi vers TRAX/LRS via outbox locale.
- Retry technique avec `retry_count`, `max_retry` et `last_attempt_at`.
- Activation stricte par cours et par ressource.
- Accès `Suivi xAPI` depuis l'objet cours via le plugin compagnon UIHook.
- Tableau de bord pédagogique alimenté par TRAX/LRS.
- Analyse des ressources alimentée par TRAX/LRS.
- Vue Expert alimentée par TRAX/LRS.
- Export CSV Expert.
- Export PDF du tableau de bord.
- Diagnostic TRAX/LRS dans l'onglet Configuration.
- Supervision technique de l'outbox dans l'onglet Configuration.

## Vues du suivi xAPI

L'écran de cours contient quatre vues :

```text
Tableau de bord | Analyse | Expert | Configuration
```

| Vue | Rôle |
|---|---|
| Tableau de bord | Synthèse pédagogique : statements TRAX, apprenants actifs, ressources utilisées, score moyen, activité par jour, actions xAPI, top ressources, export PDF. |
| Analyse | Analyse par ressource, ressources sans trace, verbes retournés par TRAX, ressources retournées par TRAX, apprenants en difficulté anonymisés. |
| Expert | Liste détaillée des statements retournés par TRAX/LRS avec export CSV. |
| Configuration | Activation du cours, activation des ressources, préférences dashboard, diagnostic LRS, supervision technique de l'outbox. |

## Écrans

### Configuration du suivi xAPI

![Écran de configuration du suivi xAPI](docs/images/suivi_xapi_configuration.png)

### Tableau de bord du suivi xAPI

![Écran tableau de bord du suivi xAPI](docs/images/suivi_xapi_tableau_bord.png)

### Analyse du suivi xAPI

![Écran analyse du suivi xAPI](docs/images/suivi_xapi_analyse.png)

### Vue Expert du suivi xAPI

![Écran expert du suivi xAPI](docs/images/suivi_xapi_expert.png)

## Installation rapide depuis main

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
head -5 sql/dbupdate.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

Résultat attendu :

```text
$version = '0.10.1';
<#1>
<?php
aucune erreur de syntaxe PHP
```

Installer le plugin compagnon UIHook :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Reconstruire ILIAS :

```bash
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
# si php-fpm est utilisé :
systemctl restart php-fpm
```

Puis installer ou mettre à jour les plugins dans ILIAS :

```text
Administration > Plugins > IliasTraxEventBridge > Installer ou Mettre à jour
Administration > Plugins > IliasTraxEventBridgeCourseUI > Installer ou Mettre à jour
```

La procédure complète est disponible dans [`docs/INSTALLATION.md`](docs/INSTALLATION.md).

## Configuration LRS / TRAX

Dans l'administration du plugin principal :

```text
Administration > Plugins > IliasTraxEventBridge > Configurer
```

Champs principaux :

| Champ | Description |
|---|---|
| Endpoint xAPI TRAX | Endpoint xAPI racine ou URL complète `/statements`. |
| Identifiant client TRAX | Compte xAPI autorisé à écrire et lire les statements. |
| Secret client TRAX | Secret du compte xAPI. |
| Version xAPI | Recommandé : `1.0.3`. |
| Timeout HTTP | Timeout des appels HTTP vers TRAX/LRS. |
| Taille batch | Nombre maximum de statements envoyés par batch. |
| Max retry | Nombre maximum de tentatives par statement. |
| Base URL ILIAS forcée | Optionnelle, utilisée pour construire les IRIs xAPI. |
| Activer le cron plugin | Autorise le traitement automatique via le cron ILIAS. |
| Activer le diagnostic des traces refusées | À activer uniquement pour analyse ciblée. |

Le plugin ajoute automatiquement `/statements` si l'endpoint fourni ne se termine pas déjà par `/statements`.

## Configuration xAPI par cours

Dans un cours ILIAS :

```text
Cours > Suivi xAPI > Configuration
```

Procédure :

1. cocher `Activer les traces xAPI pour ce cours` ;
2. cocher les ressources à tracer ;
3. enregistrer la configuration xAPI ;
4. générer de l'activité sur les ressources ;
5. vérifier les vues `Tableau de bord`, `Analyse` et `Expert`.

Règle métier :

```text
statement xAPI autorisé = cours activé ET ressource activée
```

## Cron ILIAS

L'option `Activer le cron plugin` autorise le plugin à travailler lorsque le cron ILIAS s'exécute. Il faut aussi activer le job dans ILIAS :

```text
Administration > Paramètres système et maintenance > Tâches cron
```

Job :

```text
IliasTraxEventBridge — envoi outbox vers TRAX
```

Identifiant technique :

```text
itxeb_send_outbox_to_trax
```

## Tables principales

| Table | Rôle |
|---|---|
| `evnt_evhk_itxeb_log` | Journal brut des événements ILIAS reçus. |
| `evnt_evhk_itxeb_out` | Outbox locale technique des statements xAPI à envoyer. |
| `evnt_evhk_itxeb_read` | Anti-doublon local pour les consultations `read_event`. |
| `evnt_evhk_itxeb_ccfg` | Configuration xAPI par cours et préférences dashboard. |
| `evnt_evhk_itxeb_rcfg` | Configuration xAPI par ressource dans un cours. |
| `evnt_evhk_itxeb_dlog` | Diagnostic des traces refusées. |

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

## Roadmap

La roadmap à jour est disponible ici : [`docs/ROADMAP.md`](docs/ROADMAP.md).

Axes principaux envisagés :

- V0.11 : durcissement exploitation, diagnostics et packaging.
- V0.12 : enrichissement pédagogique des tableaux de bord.
- V0.13 : analyse IA optionnelle des traces xAPI avec clé API IA configurable.
- V0.14 : gouvernance, anonymisation avancée et historisation durable.

Le cadrage détaillé de l'analyse IA est disponible ici : [`docs/IA_ANALYSE_TRACES.md`](docs/IA_ANALYSE_TRACES.md).

## Requêtes SQL utiles

Outbox récente :

```sql
SELECT id, event_log_id, event_type, obj_type, ref_id, obj_id,
       user_id, status, retry_count, created_at, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 20;
```

Configuration d'un cours :

```sql
SELECT *
FROM evnt_evhk_itxeb_ccfg
WHERE course_ref_id = 210;

SELECT course_ref_id, ref_id, obj_id, obj_type, enabled, updated_at, updated_by
FROM evnt_evhk_itxeb_rcfg
WHERE course_ref_id = 210
ORDER BY ref_id;
```

Diagnostic des refus :

```sql
SELECT id, created_at, reason, event_type, user_id, course_ref_id,
       ref_id, obj_id, obj_type, source_table, source_id
FROM evnt_evhk_itxeb_dlog
ORDER BY id DESC
LIMIT 30;
```

## Contrôles de livraison stable

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

grep -n '\$version' plugin.php
head -5 sql/dbupdate.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
git status
git log --oneline -10
```

Résultat attendu :

```text
$version = '0.10.1';
sql/dbupdate.php commence par <#1>
aucune erreur PHP
working tree clean
```
