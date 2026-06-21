# Documentation — IliasTraxEventBridge

Cette page centralise la documentation à jour du plugin **IliasTraxEventBridge**.

Version stable actuelle : **v0.4.3**.

## État stable v0.4.3

La version **v0.4.3** clôture la série V0.4.

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
- affichage amélioré des tableaux de configuration, notamment les colonnes **Verb** et **URI**.

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

## Objets couverts en v0.4.3

| Action ILIAS | Statement xAPI |
|---|---|
| Démarrage d'un test | `attempted` |
| Test réussi | `passed` |
| Test échoué | `failed` |
| Téléchargement d'un fichier | `experienced` |

Les actions d'administration restent journalisées mais ne doivent pas être envoyées comme traces xAPI d'apprentissage.

## Roadmap

- [Roadmap V0.5 / V0.6](ROADMAP.md)
- [Plan V0.5](V0.5_PLAN.md)
- [Plan V0.6](V0.6_PLAN.md)

## Remarque sur les dossiers `doc` et `docs`

Le dépôt contient aussi un dossier `docs` utilisé par certaines pages Markdown existantes. Le dossier `doc` est conservé pour centraliser les documents visibles depuis l'URL historique `tree/main/doc`.
