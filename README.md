# IliasTraxEventBridge

Plugin ILIAS 10 EventHook permettant de transformer certains événements ILIAS en statements xAPI, de les envoyer vers un LRS xAPI comme TRAX 3, puis d'afficher un suivi xAPI de cours alimenté directement par TRAX/LRS.

## État des versions

| Élément | Valeur |
|---|---|
| Version stable sur `main` | `0.12.0` |
| Branche stable officielle | `main` |
| Tag stable | `v0.12.0` |
| Version de développement validée | `0.15.2-dev` |
| Branche de développement IA | `v0.13-ai-xapi-analysis` |
| État V0.15.2 | validée fonctionnellement |
| Consolidation en cours | `V0.16` |
| Compatibilité ILIAS | `10.0.0` à `10.999.999` |
| Plugin principal | `IliasTraxEventBridge` |
| Type plugin principal | `EventHook` |
| Plugin compagnon | `IliasTraxEventBridgeCourseUI` |
| Type plugin compagnon | `UIHook` |
| Source pédagogique du suivi xAPI | TRAX/LRS |
| Rôle de l'outbox locale | File technique d'envoi uniquement |

Pour une installation stable courante, utiliser `main` ou le tag `v0.12.0`.

La branche `v0.13-ai-xapi-analysis` porte les évolutions IA validées en environnement de test : analyse IA formateur, rendu Markdown/HTML, historisation locale des analyses et export PDF enrichi.

## Documentation complète

Le dossier `docs/` contient un index dédié : [`docs/README.md`](docs/README.md).

| Document | Rôle |
|---|---|
| [`docs/README.md`](docs/README.md) | Index général de toute la documentation. |
| [`docs/RELEASE_0.15.2.md`](docs/RELEASE_0.15.2.md) | Note de release de la V0.15.2-dev validée : analyse IA, historique, PDF enrichi. |
| [`docs/INSTALLATION.md`](docs/INSTALLATION.md) | Installation complète, mise à jour, reconstruction ILIAS, plugin compagnon, contrôles et dépannage. |
| [`docs/FONCTIONNEL.md`](docs/FONCTIONNEL.md) | Documentation fonctionnelle : objectifs, utilisateurs, parcours cours, vues Tableau de bord / Analyse / Expert / Configuration. |
| [`docs/TECHNIQUE.md`](docs/TECHNIQUE.md) | Documentation technique : architecture, EventHook, UIHook, outbox, TRAX/LRS, tables SQL, flux de lecture et d'envoi. |
| [`docs/EXPLOITATION.md`](docs/EXPLOITATION.md) | Exploitation : supervision, cron, tests LRS, requêtes SQL utiles, purge et analyse d'incident. |
| [`docs/DIAGNOSTIC.md`](docs/DIAGNOSTIC.md) | Diagnostic exploitation : santé plugin, tables, outbox, TRAX/LRS et cron. |
| [`docs/ROLLBACK.md`](docs/ROLLBACK.md) | Procédure de retour arrière. |
| [`docs/VALIDATION_0.12.md`](docs/VALIDATION_0.12.md) | Procédure complète de validation V0.12. |
| [`docs/V0.12_DASHBOARD_PEDAGOGIQUE.md`](docs/V0.12_DASHBOARD_PEDAGOGIQUE.md) | Cadrage fonctionnel et technique du dashboard pédagogique V0.12. |
| [`docs/V0.12_GUIDE_UTILISATION.md`](docs/V0.12_GUIDE_UTILISATION.md) | Guide utilisateur du dashboard pédagogique V0.12. |
| [`docs/VALIDATION_0.11.md`](docs/VALIDATION_0.11.md) | Procédure de validation V0.11 conservée pour historique. |
| [`docs/DEVELOPPEUR.md`](docs/DEVELOPPEUR.md) | Documentation développeur : classes principales, conventions, migrations, contrôles avant livraison. |
| [`docs/ROADMAP.md`](docs/ROADMAP.md) | Roadmap : IA d'analyse des traces, API keys IA, sécurité et gouvernance. |
| [`docs/IA_ANALYSE_TRACES.md`](docs/IA_ANALYSE_TRACES.md) | Cadrage détaillé de l'analyse des traces xAPI par IA. |
| [`docs/RELEASE_0.11.0.md`](docs/RELEASE_0.11.0.md) | Note de version stable V0.11.0, conservée pour historique. |
| [`docs/V0.10_LRS_DIRECT_READ.md`](docs/V0.10_LRS_DIRECT_READ.md) | Décision d'architecture V0.10/V0.11 : lecture directe TRAX/LRS. |
| [`CHANGELOG.md`](CHANGELOG.md) | Historique des versions. |

## Principe d'architecture

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

## Nouveautés V0.15.2-dev — Analyse IA formateur

La V0.15.2-dev enrichit l'onglet `Analyse` du suivi xAPI avec une aide pédagogique générée par IA à partir des données xAPI agrégées de TRAX/LRS.

Fonctionnalités validées :

- page `Analyse formateur` plus lisible ;
- rendu Markdown/HTML de la réponse IA ;
- historisation locale des analyses IA réussies ;
- export PDF du tableau de bord incluant la dernière analyse IA historisée ;
- stockage runtime hors Git dans `var/ai_analysis_history` ;
- anonymisation stricte : aucune identité nominative apprenant, aucun courriel et aucun UUID brut de statement ne doit être envoyé ou stocké dans l'historique IA.

## Nouveautés V0.12.0 — Dashboard pédagogique stable

- Dashboard pédagogique enrichi dans `Cours > Suivi xAPI > Tableau de bord`.
- Synthèse pédagogique visible dans `Tableau de bord` et `Analyse`.
- Statuts pédagogiques des ressources : `OK`, `À surveiller`, `Critique`, `Désactivée`.
- Code couleur renforcé : `À surveiller` en orange, `Critique` en rouge.
- Analyse des ressources enrichie avec statut, raison, score moyen, réussites, échecs et taux d'échec.
- Bloc anonymisé `Apprenants en difficulté` conservé dans l'onglet Analyse.
- Export CSV Expert enrichi avec les champs pédagogiques V0.12.
- Export PDF repositionné dans l'en-tête du tableau de bord.
- Agencement visuel amélioré : titres renforcés, blocs mieux encadrés, lisibilité améliorée.
- Documentation V0.12 : cadrage, guide utilisateur et checklist de validation.

## Héritage V0.11.0 conservé

- Section `Santé / Diagnostic V0.11` dans l'administration du plugin.
- Script serveur non destructif `scripts/diagnostic_itxeb.sh`.
- Test de connexion TRAX.
- Test de lecture TRAX/LRS via `GET /statements?limit=1`, sans création de trace.
- Test d'écriture TRAX/LRS avec création volontaire d'un statement de diagnostic identifiable.
- Persistance des résultats lecture/écriture dans `Diagnostics TRAX / cron`.
- Documentation diagnostic, rollback et validation.

## Fonctionnalités principales

- Captation d'événements ILIAS via EventHook.
- Génération locale de statements xAPI.
- Envoi vers TRAX/LRS via outbox locale.
- Retry technique avec `retry_count`, `max_retry` et `last_attempt_at`.
- Activation stricte par cours et par ressource.
- Accès `Suivi xAPI` depuis l'objet cours via le plugin compagnon UIHook.
- Tableau de bord pédagogique alimenté par TRAX/LRS.
- Analyse des ressources alimentée par TRAX/LRS.
- Analyse IA formateur optionnelle sur données agrégées/anonymisées.
- Historique local des analyses IA.
- Vue Expert alimentée par TRAX/LRS.
- Export CSV Expert enrichi avec les colonnes pédagogiques V0.12.
- Export PDF du tableau de bord avec analyse IA historisée si disponible.
- Diagnostic TRAX/LRS dans l'onglet Configuration.
- Supervision technique de l'outbox dans l'onglet Configuration.

## Vues du suivi xAPI

L'écran de cours contient quatre vues :

```text
Tableau de bord | Analyse | Expert | Configuration
```

| Vue | Rôle |
|---|---|
| Tableau de bord | Synthèse pédagogique : statements TRAX, apprenants actifs, ressources utilisées, score moyen, activité par jour, actions xAPI, top ressources, compteurs OK / À surveiller / Critique / Sans trace, export PDF. |
| Analyse | Analyse par ressource avec statut pédagogique, raison, score moyen, tests, apprenants en difficulté anonymisés, analyse IA formatée et historique IA. |
| Expert | Liste détaillée des statements retournés par TRAX/LRS avec export CSV enrichi des champs pédagogiques. |
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
