# IliasTraxEventBridge

Plugin ILIAS 10 EventHook pour transformer certains événements ILIAS en statements xAPI et les envoyer vers TRAX 3 LRS.

Version stable actuelle : **v0.5.5**. Branche de développement en cours : **aucune**. Prochaine cible : **v0.6**.

## Fonctionnalités v0.5.5 stable

- Captation d'événements ILIAS via EventHook.
- Journal brut des événements reçus dans `evnt_evhk_itxeb_log`.
- Génération locale de statements xAPI.
- Outbox locale avec statuts `generated`, `sending`, `sent`, `failed`.
- Envoi manuel vers TRAX.
- Envoi automatique par job cron ILIAS `itxeb_send_outbox_to_trax`.
- Retry configurable avec `retry_count`, `max_retry` et `last_attempt_at`.
- Bouton de réinitialisation des statements en échec.
- Diagnostics du dernier test TRAX, du dernier envoi manuel et du dernier cron.
- Filtre métier : seuls les objets contenus dans un **cours** peuvent générer des statements xAPI.
- Exclusion des objets placés directement dans une catégorie, un dossier hors cours ou un autre contexte non cours.
- Suivi de l'exploitation réelle des objets de dépôt via la table ILIAS `read_event`.
- Table anti-doublon locale `evnt_evhk_itxeb_read` pour éviter de renvoyer plusieurs fois les mêmes consultations.
- Suppression des traces parasites `Tracking:updateStatus` génériques sur `crs` ou `root`.

## Périmètre stable v0.5.5

La version **v0.5.5** stabilise le filtre métier suivant : un statement xAPI n'est généré que si l'objet ILIAS concerné est contenu dans un objet **cours**.

Comportement :

- l'événement brut reste journalisé dans `evnt_evhk_itxeb_log` ;
- si aucun cours parent n'est trouvé, aucun statement n'est ajouté dans `evnt_evhk_itxeb_out` ;
- les objets placés directement dans une catégorie, un dossier hors cours ou un autre contexte non cours sont exclus de l'outbox xAPI ;
- quand le cours parent est identifié, les extensions xAPI contiennent `course_ref_id` et `course_obj_id` ;
- l'exploitation réelle des objets de dépôt est suivie via `read_event` et produit des statements `repository_object_access`.

Depuis **v0.5.4**, l'exploitation réelle des objets de dépôt est suivie via la table ILIAS `read_event`, avec une table anti-doublon locale `evnt_evhk_itxeb_read`.

Depuis **v0.5.5**, les événements génériques `Tracking:updateStatus` non-test, notamment sur `crs` ou `root`, ne génèrent plus de statements xAPI. Les consultations utiles passent par `repository_object_access`.

## Objets couverts en v0.5.5

| Action ILIAS | Source | Statement xAPI |
|---|---|---|
| Démarrage d'un test dans un cours | `Tracking:updateStatus` test | `attempted` |
| Test réussi dans un cours | `Tracking:updateStatus` test | `passed` |
| Test échoué dans un cours | `Tracking:updateStatus` test | `failed` |
| Téléchargement d'un fichier dans un cours | EventHook `sendfile` | `experienced` |
| Consultation blog dans un cours | `read_event` | `repository_object_access` / `experienced` |
| Consultation forum dans un cours | `read_event` | `repository_object_access` / `experienced` |
| Consultation lien web dans un cours | `read_event` | `repository_object_access` / `experienced` |
| Consultation mediacast dans un cours | `read_event` | `repository_object_access` / `experienced` |
| Consultation wiki dans un cours | `read_event` | `repository_object_access` / `experienced` |
| Consultation module HTML dans un cours | `read_event` | `repository_object_access` / `experienced` |
| Consultation module web dans un cours | `read_event` | `repository_object_access` / `experienced` |
| Consultation module SCORM dans un cours | `read_event` | `repository_object_access` / `experienced` |

Les actions d'administration comme la suppression des résultats de test sont journalisées mais ne sont pas envoyées dans l'outbox xAPI.

## Installation dans ILIAS 10

### Prérequis

- ILIAS 10 déjà installé et fonctionnel.
- Accès shell au serveur ILIAS.
- Git installé sur le serveur.
- Accès à la base ILIAS pour vérifier les tables si nécessaire.
- Utilisateur web ILIAS habituel : `apache` sur AlmaLinux/RHEL, à adapter si votre serveur utilise `www-data`.

### Installation depuis GitHub

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

Résultat attendu :

```text
$version = "0.5.5";
```

Puis dans ILIAS :

```text
Administration > Plugins > EventHook > IliasTraxEventBridge > Mettre à jour / Installer > Activer > Configurer
```

### Mise à jour d'une installation existante

Depuis le serveur ILIAS :

```bash
sudo -i

cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin
git checkout main
git pull --ff-only origin main

grep -n '\$version' plugin.php

cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
```

Puis dans ILIAS :

```text
Administration > Plugins > EventHook > IliasTraxEventBridge > Mettre à jour
```

### Attention aux sauvegardes locales

Ne laissez pas de dossiers de sauvegarde du plugin dans le dossier `EventHook`, par exemple `IliasTraxEventBridge.bak.*`. ILIAS tente de scanner tous les sous-dossiers de plugins, ce qui peut casser le build.

Déplacer les sauvegardes hors de `EventHook` :

```bash
sudo -i

export EVENTHOOK_DIR="/var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook"
mkdir -p /root/ilias-plugin-backups
mv "$EVENTHOOK_DIR"/IliasTraxEventBridge.bak.* /root/ilias-plugin-backups/ 2>/dev/null || true
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

## Vérifications SQL utiles

Outbox xAPI :

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

Vérifier qu'aucun statement parasite `root` ou `crs` n'est créé :

```sql
SELECT id, event_log_id, event_type, obj_type, ref_id, obj_id,
       user_id, status, created_at, sent_at
FROM evnt_evhk_itxeb_out
WHERE obj_type IN ('root', 'crs')
ORDER BY id DESC
LIMIT 20;
```

## Roadmap

### V0.5 — stabilisée

La V0.5 limite le périmètre métier aux objets contenus dans un objet cours et trace l'exploitation réelle des objets de dépôt via `read_event`.

Objectifs réalisés :

- [x] n'envoyer des traces xAPI que pour les objets contenus dans un objet **cours** ;
- [x] exclure les objets placés directement dans une catégorie, un dossier hors cours ou un autre contexte non cours ;
- [x] tracer l'exploitation réelle des objets de dépôt via `read_event` ;
- [x] étendre la couverture aux objets suivants : blog, forum, lien web, mediacast, wiki, module HTML, module web et module SCORM ;
- [x] supprimer les statements parasites `crs` / `root` issus de `Tracking:updateStatus` génériques.

### Cible v0.6

La V0.6 portera sur l'enrichissement xAPI et l'exploitation opérationnelle.

Objectifs :

- améliorer les verbes xAPI selon les types d'événements ILIAS ;
- générer des statements plus riches pour cours, tests, fichiers, modules CMI/xAPI et autres objets couverts ;
- ajouter des filtres dans la configuration globale du plugin ;
- ajouter une page de diagnostic TRAX ;
- ajouter une purge configurable des anciens événements et de l'outbox ;
- étudier l'activation/désactivation par cours et par type d'objet.

## Documentation complémentaire

- [README technique](README_TECHNIQUE.md)
- [Changelog](CHANGELOG.md)
- [Guide d'import GitHub](GITHUB_IMPORT.md)
- [Plan de validation](docs/VALIDATION.md)
- [Documentation centrale](doc/README.md)
- [Roadmap](doc/ROADMAP.md)
