# Documentation — IliasTraxEventBridge

Cette page centralise la documentation à jour du plugin **IliasTraxEventBridge**.

Version stable actuelle : **v0.8.0**.

Branche stable par défaut : **main**.

Tag stable : **v0.8.0**.

## État stable v0.8.0

La version **v0.8.0** devient la version stable courante du plugin.

Fonctionnalités validées :

- captation des événements ILIAS 10 via le plugin EventHook ;
- journalisation des événements bruts dans `evnt_evhk_itxeb_log` ;
- génération locale de statements xAPI ;
- stockage dans une outbox locale `evnt_evhk_itxeb_out` ;
- envoi manuel vers le LRS configuré ;
- envoi automatique par tâche cron ILIAS ;
- filtre métier : seuls les objets contenus dans un cours peuvent générer des statements xAPI ;
- configuration stricte par cours et par ressource ;
- accès dans le cours via `Paramètres > Suivi xAPI` ;
- suivi de l'exploitation réelle des objets de dépôt via `read_event` ;
- table anti-doublon locale `evnt_evhk_itxeb_read` ;
- diagnostic V0.8 des traces refusées dans `evnt_evhk_itxeb_dlog` ;
- activation/désactivation du diagnostic des refus à la demande ;
- purge du diagnostic des refus ;
- packaging propre du plugin compagnon UIHook par templates `.php.tpl` ;
- suppression des warnings Composer `Ambiguous class resolution` liés au companion.

## Installation

La procédure d'installation complète est disponible dans :

- [README principal](../README.md#installation-stable-v080)
- [README technique](../README_TECHNIQUE.md#7-installation-technique-v080)
- [Release v0.8.0](../docs/RELEASE_0.8.0.md)

Résumé :

```bash
sudo -i

export ILIAS_ROOT="/var/www/ilias"
export EVENTHOOK_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook"
export PLUGIN_NAME="IliasTraxEventBridge"

mkdir -p "$EVENTHOOK_DIR"
cd "$EVENTHOOK_DIR"

git clone -b main --single-branch https://github.com/vincent-sayah/IliasTraxEventBridge.git "$PLUGIN_NAME"
cd "$PLUGIN_NAME"
git checkout v0.8.0

find . -name "*.php" -print0 | xargs -0 -n1 php -l
bash scripts/install_course_ui_companion.sh

cd "$ILIAS_ROOT"
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
```

## Cron ILIAS

Le job cron ILIAS à activer est :

```text
IliasTraxEventBridge — envoi outbox vers TRAX
```

Identifiant technique :

```text
itxeb_send_outbox_to_trax
```

Chemin ILIAS :

```text
Administration > Paramètres système et maintenance > Tâches cron
```

## Configuration xAPI par cours

Chemin recommandé :

```text
Cours > Paramètres > Suivi xAPI
```

Règle métier :

```text
statement xAPI autorisé = cours activé ET ressource activée
```

## Diagnostic des traces refusées

Chemin :

```text
Administration > Plugins > IliasTraxEventBridge > Configurer
```

Option :

```text
Activer le diagnostic des traces refusées
```

La table utilisée est :

```text
evnt_evhk_itxeb_dlog
```

Le diagnostic doit rester désactivé en exploitation courante et être activé uniquement pendant une phase de debug.

## Objets couverts en v0.8.0

| Action ILIAS | Source | Statement xAPI |
|---|---|---|
| Démarrage d'un test dans un cours | `Tracking:updateStatus` test | `attempted` |
| Test réussi dans un cours | `Tracking:updateStatus` test | `passed` |
| Test échoué dans un cours | `Tracking:updateStatus` test | `failed` |
| Téléchargement d'un fichier dans un cours | EventHook `sendfile` | `downloaded` |
| Consultation blog dans un cours | `read_event` | `repository_object_access` / `read` |
| Consultation forum dans un cours | `read_event` | `repository_object_access` / `interacted` |
| Consultation lien web dans un cours | `read_event` | `repository_object_access` / `visited` |
| Consultation mediacast dans un cours | `read_event` | `repository_object_access` / `viewed` |
| Consultation wiki dans un cours | `read_event` | `repository_object_access` / `read` |
| Consultation module HTML dans un cours | `read_event` | `repository_object_access` / `read` |
| Consultation module web dans un cours | `read_event` | `repository_object_access` / `read` |
| Consultation module SCORM dans un cours | `read_event` | `repository_object_access` / `launched` |

## Documents utiles

- [README principal](../README.md)
- [README technique](../README_TECHNIQUE.md)
- [Changelog](../CHANGELOG.md)
- [Release v0.8.0](../docs/RELEASE_0.8.0.md)
- [Plan de validation](../docs/VALIDATION.md)
- [Guide d'exploitation](../docs/OPERATIONS.md)
- [V0.8 lot 1 — journal des refus](../docs/V0.8_LOT1_DENY_LOG.md)
- [V0.8 lot 2 — supervision des refus](../docs/V0.8_LOT2_DENY_SUPERVISION.md)
- [V0.8 lot 3 — packaging companion](../docs/V0.8_LOT3_COMPANION_PACKAGING.md)
- [Guide d'import GitHub](../GITHUB_IMPORT.md)

## Remarque sur les dossiers `doc` et `docs`

Le dépôt contient deux dossiers historiques :

```text
doc/
docs/
```

Le dossier `docs/` contient la documentation technique détaillée. Le dossier `doc/` conserve cette page centrale pour compatibilité avec l'URL historique `tree/main/doc`.
