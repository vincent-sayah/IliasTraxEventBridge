# Documentation — IliasTraxEventBridge

Cette page centralise la documentation à jour du plugin **IliasTraxEventBridge**.

Version stable actuelle : **v0.5.5**.

## État stable v0.5.5

La version **v0.5.5** clôture la série V0.5 et devient la version stable courante.

Fonctionnalités validées :

- captation des événements ILIAS 10 via le plugin EventHook ;
- journalisation des événements bruts ;
- génération locale de statements xAPI ;
- stockage dans une outbox locale ;
- envoi manuel vers TRAX ;
- envoi automatique par tâche cron ILIAS ;
- retry configurable avec `retry_count`, `max_retry` et `last_attempt_at` ;
- réinitialisation manuelle des statements `failed` ;
- diagnostics du dernier test TRAX, du dernier envoi manuel et du dernier cron ;
- filtre métier : seuls les objets contenus dans un **cours** peuvent générer des statements xAPI ;
- exclusion des objets placés directement dans une catégorie, un dossier hors cours ou un autre contexte non cours ;
- suivi de l'exploitation réelle des objets de dépôt via `read_event` ;
- table anti-doublon locale `evnt_evhk_itxeb_read` ;
- suppression des traces parasites `Tracking:updateStatus` génériques sur `crs` ou `root`.

## Cron ILIAS

L'option **Activer le cron plugin** dans la configuration du plugin autorise le plugin à envoyer l'outbox, mais elle ne suffit pas à planifier l'exécution.

Il faut aussi activer le job cron dans ILIAS :

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

Les actions d'administration restent journalisées mais ne doivent pas être envoyées comme traces xAPI d'apprentissage.

## Roadmap

- [Roadmap V0.5 / V0.6](ROADMAP.md)
- [Plan V0.5](V0.5_PLAN.md)
- [Plan V0.6](V0.6_PLAN.md)

## Remarque sur les dossiers `doc` et `docs`

Le dépôt contient aussi un dossier `docs` utilisé par certaines pages Markdown existantes. Le dossier `doc` est conservé pour centraliser les documents visibles depuis l'URL historique `tree/main/doc`.
