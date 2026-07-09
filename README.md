# IliasTraxEventBridge

Plugin ILIAS 10 EventHook permettant de transformer certains événements ILIAS en statements xAPI, de les envoyer vers un LRS xAPI comme TRAX 3, puis d'afficher un pilotage pédagogique de cours dans ILIAS.

## Version stable actuelle

| Élément | Valeur |
|---|---|
| Branche stable officielle | `main` |
| Version stable courante | `0.21.2-dev` validée et promue dans `main` |
| Commit de gel fonctionnel | `fad4c28` — `Freeze V0.21.2 validated implementation` |
| Plugin principal | `IliasTraxEventBridge` |
| Type plugin principal | `EventHook` |
| Version plugin compagnon | `0.8.5` |
| Plugin compagnon | `IliasTraxEventBridgeCourseUI` |
| Type plugin compagnon | `UIHook` |
| Compatibilité ILIAS | `10.0.0` à `10.999.999` |
| Branche historique IA | `v0.13-ai-xapi-analysis` |
| Anciennes branches/tags | conservés pour historique uniquement |

Pour une installation stable courante, utiliser `main` :

```bash
git clone -b main --single-branch https://github.com/vincent-sayah/IliasTraxEventBridge.git IliasTraxEventBridge
```

Ne plus utiliser les anciennes branches d'installation comme `v0.10-lrs-direct-read` pour une nouvelle installation.

## Règle métier V0.21.2

```text
TRAX/LRS = destination xAPI et source principale de suivi pédagogique.
Outbox locale = file technique d'envoi.
Exception V0.21.2 = calcul robuste des questions problématiques depuis les statements question disponibles dans l'outbox locale.
```

Règle fonctionnelle validée :

```text
TRAX = toutes les questions de test ILIAS sont tracées.
Tableau de bord / Analyse = seules les questions problématiques sont remontées.
Analyse IA = seules les questions problématiques sont intégrées au payload IA.
Expert = vision technique complète.
```

## Fonctionnalités principales

- Captation d'événements ILIAS via EventHook.
- Génération locale de statements xAPI.
- Envoi vers TRAX/LRS via outbox locale.
- Retry technique avec `retry_count`, `max_retry` et `last_attempt_at`.
- Activation stricte par cours et par ressource.
- Accès `Pilotage xAPI` depuis l'objet cours via le plugin compagnon UIHook.
- Tableau de bord pédagogique.
- Analyse formateur.
- Onglet `Analyse IA` séparé.
- Historique local des analyses IA.
- Comparaison d'analyses IA historisées.
- Retrait contrôlé d'analyses IA historisées.
- Vue Expert technique.
- Export CSV Expert.
- Export PDF du tableau de bord.
- Diagnostic TRAX/LRS dans l'onglet Configuration.
- Supervision technique de l'outbox.
- Traces question par question pour les tests ILIAS.
- Bloc `Questions à fort taux d’échec` dans Tableau de bord et Analyse.
- Intégration des questions problématiques dans le payload IA.

## Vues du pilotage xAPI

```text
Tableau de bord | Analyse | Analyse IA | Expert | Configuration | Retour contenu du cours
```

| Vue | Rôle |
|---|---|
| Tableau de bord | Synthèse pédagogique du cours, activité, ressources, tests, questions problématiques, export PDF. |
| Analyse | Lecture formateur des ressources et questions à surveiller. |
| Analyse IA | Génération, historique, comparaison et retrait d'analyses IA. |
| Expert | Vue technique détaillée des statements et export CSV. |
| Configuration | Activation cours/ressources, préférences, diagnostic LRS, supervision outbox. |

## Architecture synthétique

```text
ILIAS 10
  ├─ EventHook IliasTraxEventBridge
  │    ├─ capte les événements ILIAS
  │    ├─ génère les statements xAPI globaux
  │    ├─ génère les statements question par question
  │    └─ alimente l'outbox locale technique
  │
  ├─ Cron ILIAS
  │    └─ envoie l'outbox vers TRAX/LRS
  │
  └─ UIHook IliasTraxEventBridgeCourseUI
       └─ affiche Pilotage xAPI dans le cours

TRAX / LRS
  ├─ reçoit les statements xAPI
  └─ reste la cible xAPI officielle
```

## Documentation de référence

| Document | Rôle |
|---|---|
| [`docs/INDEX_0.21.2.md`](docs/INDEX_0.21.2.md) | Index de référence de la V0.21.2. |
| [`docs/INSTALLATION.md`](docs/INSTALLATION.md) | Installation et mise à jour depuis `main`, avec `ILIAS_ROOT` personnalisable. |
| [`docs/RELEASE_0.21.2.md`](docs/RELEASE_0.21.2.md) | Note de release V0.21.2. |
| [`docs/FONCTIONNEL_0.21.2.md`](docs/FONCTIONNEL_0.21.2.md) | Documentation fonctionnelle actuelle. |
| [`docs/TECHNIQUE_0.21.2.md`](docs/TECHNIQUE_0.21.2.md) | Architecture technique actuelle. |
| [`docs/GUIDE_DEVELOPPEUR_0.21.2.md`](docs/GUIDE_DEVELOPPEUR_0.21.2.md) | Guide développeur actuel : classes, tables, flux. |
| [`docs/EXPLOITATION_0.21.2.md`](docs/EXPLOITATION_0.21.2.md) | Exploitation et diagnostic courant. |
| [`docs/VALIDATION_0.21.2.md`](docs/VALIDATION_0.21.2.md) | Checklist de validation. |
| [`CHANGELOG.md`](CHANGELOG.md) | Historique des versions. |

Les documents `V0.10`, `V0.11`, `V0.12`, `V0.13` et `RELEASE_0.15.2` sont conservés pour historique. Pour une installation ou une maintenance courante, utiliser les documents V0.21.2.

## Copie écran
![Tableau de bord](images/1.png)
![Tableau de bord](images/2.png)
![Analyse](images/3.png)
![Analyse](images/4.png)
![Analyse](images/5.png)
![Analyse IA](images/6.png)
![Analyse IA](images/7.png)
![Expert](images/8.png)
![Configuration](images/9.png)


