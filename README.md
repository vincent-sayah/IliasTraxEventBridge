# IliasTraxEventBridge

Plugin ILIAS 10 EventHook permettant de transformer certains événements ILIAS en statements xAPI, de les envoyer vers un LRS xAPI comme TRAX 3, puis d'afficher un pilotage pédagogique de cours directement dans ILIAS.

## Version courante validée

| Élément | Valeur |
|---|---|
| Branche stable officielle | `main` après promotion V0.25.6 |
| Version stable précédente | `0.24.17-dev` |
| Branche de développement V0.25 | `v0.25-learner-identity-display` |
| Version V0.25 validée | `0.25.6-dev` |
| Commit de validation V0.25.6 | `8d97685` — `V0.25.6 validate learner login display` |
| Plugin principal | `IliasTraxEventBridge` |
| Type plugin principal | `EventHook` |
| Plugin compagnon | `IliasTraxEventBridgeCourseUI` |
| Type plugin compagnon | `UIHook` |
| Version plugin compagnon V0.25.6 | `0.8.44` |
| Compatibilité ILIAS | `10.0.0` à `10.999.999` |

Installation stable courante :

```bash
git clone -b main --single-branch https://github.com/vincent-sayah/IliasTraxEventBridge.git IliasTraxEventBridge
```

Ne plus utiliser les anciennes branches d'installation comme `v0.10-lrs-direct-read` pour une nouvelle installation.

## Règle métier conservée

```text
TRAX/LRS = destination xAPI et source principale de suivi pédagogique.
Outbox locale = file technique d'envoi.
MediaCast = suivi des ouvertures, vidéos internes lues et médias externes sélectionnés.
```

Règle fonctionnelle validée :

```text
TRAX = toutes les questions de test ILIAS sont tracées.
Tableau de bord / Analyse = seules les questions problématiques sont remontées.
Analyse IA = seules les questions problématiques sont intégrées au payload IA.
Expert = vision technique complète.
Analyse = vue MediaCast des vidéos internes lues et médias externes ouverts.
Analyse / Expert = affichage du login ILIAS pour les apprenants lorsque le login est résolu.
```

## Fonctionnalités principales

- Captation d'événements ILIAS via EventHook.
- Génération locale de statements xAPI.
- Envoi vers TRAX/LRS via outbox locale.
- Retry technique avec `retry_count`, `max_retry` et `last_attempt_at`.
- Activation stricte par cours et par ressource.
- Accès `Pilotage xAPI` depuis l'objet cours via le plugin compagnon UIHook.
- Tableau de bord pédagogique.
- Synthèse pédagogique enrichie et regroupée dans le tableau de bord.
- Cartes KPI avec icônes et suppression des doublons visuels.
- Activité dans le temps avec graphique linéaire SVG.
- Alignement du bloc `Activité dans le temps` et de `Top ressources` sur une même ligne en vue large.
- Filtrage contextuel du bloc `Questions à fort taux d’échec`.
- Analyse formateur.
- Bloc `Apprenants en difficulté` avec affichage du login ILIAS.
- Vue `Médias MediaCast vus` dans l'onglet Analyse uniquement.
- Suivi des vidéos internes MediaCast lancées.
- Suivi des médias externes MediaCast sélectionnés, dont YouTube/Vimeo.
- Affichage du titre réel des médias externes lorsque le titre est disponible dans la playlist MediaCast ILIAS.
- Onglet `Analyse IA` séparé.
- Historique local des analyses IA.
- Comparaison d'analyses IA historisées.
- Retrait contrôlé d'analyses IA historisées.
- Vue Expert technique avec colonne `Apprenant` entre `User ID` et `Verbe`.
- Export CSV Expert avec colonne `learner_identity`.
- Export PDF du tableau de bord.
- Diagnostic TRAX/LRS dans l'onglet Configuration.
- Supervision technique de l'outbox.
- Traces question par question pour les tests ILIAS.
- Intégration des questions problématiques dans le payload IA.

## Vues du pilotage xAPI

```text
Tableau de bord | Analyse | Analyse IA | Expert | Configuration | Retour contenu du cours
```

| Vue | Rôle |
|---|---|
| Tableau de bord | Synthèse pédagogique du cours, activité dans le temps, ressources, tests, questions problématiques, export PDF. |
| Analyse | Lecture formateur des ressources, priorités, questions à surveiller, apprenants en difficulté et médias MediaCast vus. |
| Analyse IA | Génération, historique, comparaison et retrait d'analyses IA. |
| Expert | Vue technique détaillée des statements, colonne `Apprenant`, export CSV. |
| Configuration | Activation cours/ressources, préférences, diagnostic LRS, supervision outbox. |

## V0.25.6 — affichage du login apprenant

La V0.25.6 ajoute l'affichage du login ILIAS dans les vues `Analyse` et `Expert`.

### Analyse

Le bloc `Apprenants en difficulté` n'affiche plus de pseudonyme de type :

```text
Apprenant xxxxxxxx
```

Il n'affiche plus non plus l'acteur brut lorsque le login existe :

```text
ilias-user-6
ilias-user-401
```

Il affiche le login ILIAS résolu depuis `usr_data.login`.

### Expert

La vue Expert affiche désormais :

```text
Date | User ID | Apprenant | Verbe | Ressource | Type | Score | Completion | Success | Source | Statement ID
```

`User ID` reste un identifiant technique pseudonymisé. `Apprenant` contient le login ILIAS.

### Export CSV Expert

L'export CSV Expert contient la colonne :

```text
learner_identity
```

## Tableau de bord V0.24.17 conservé

La V0.24.17 améliore la lisibilité du tableau de bord sans changer les règles de captation xAPI.

| Zone | Description |
|---|---|
| Synthèse pédagogique | Indicateurs regroupés et cartes KPI avec icônes. |
| Questions à fort taux d'échec | Bloc affiché uniquement en contexte test ou global. |
| Activité dans le temps | Graphique linéaire SVG avec choix 7 jours, 14 jours, 30 jours, semaine, détail complet. |
| Top ressources | Aligné avec le graphique en vue large. |

## Suivi MediaCast V0.23.8 conservé

La V0.23.8 ajoute un suivi pédagogique MediaCast sans modifier le fonctionnement des tests ILIAS.

| Action utilisateur | Verbe xAPI | Vue concernée |
|---|---|---|
| Ouverture d'un objet MediaCast | verbe de consultation existant | Expert / activité générale |
| Lancement d'une vidéo interne | `played-media` | Expert et Analyse |
| Sélection d'un média externe, par exemple YouTube | `opened-external-media` | Expert et Analyse |

Dans l'onglet `Analyse`, le bloc `Médias MediaCast vus` affiche le média, son type, le nombre d'actions, le nombre d'apprenants, le MediaCast parent et la dernière trace.

## Architecture synthétique

```text
ILIAS 10
  ├─ EventHook IliasTraxEventBridge
  │    ├─ capte les événements ILIAS
  │    ├─ génère les statements xAPI globaux
  │    ├─ génère les statements question par question
  │    ├─ génère les statements MediaCast client
  │    └─ alimente l'outbox locale technique
  │
  ├─ Cron ILIAS
  │    └─ envoie l'outbox vers TRAX/LRS
  │
  └─ UIHook IliasTraxEventBridgeCourseUI
       ├─ affiche Pilotage xAPI dans le cours
       ├─ injecte le suivi MediaCast côté navigateur
       └─ affiche les vues Tableau de bord, Analyse, Analyse IA, Expert et Configuration

TRAX / LRS
  ├─ reçoit les statements xAPI
  └─ reste la cible xAPI officielle et la source de lecture pédagogique
```

## Installation / mise à jour rapide

Depuis le dossier plugin EventHook :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin
git checkout main
git pull --ff-only origin main

export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"
bash scripts/install_course_ui_companion_with_standalone_fix.sh

systemctl restart php-fpm
systemctl restart httpd
```

Contrôle des versions après promotion V0.25.6 :

```bash
grep -n "0.25.6-dev\|0.8.44\|ITXEB V0.25.6 learner login resolution\|lookupIliasLogin\|usr_data\|learner_identity" \
plugin.php \
classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php \
companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php
```

## Documentation de référence

| Document | Rôle |
|---|---|
| [`docs/INDEX_0.25.6.md`](docs/INDEX_0.25.6.md) | Index de référence de la V0.25.6. |
| [`docs/RELEASE_0.25.6.md`](docs/RELEASE_0.25.6.md) | Note de release V0.25.6. |
| [`docs/VALIDATION_0.25.6.md`](docs/VALIDATION_0.25.6.md) | Checklist de validation V0.25.6. |
| [`docs/INDEX_0.24.17.md`](docs/INDEX_0.24.17.md) | Index de référence de la V0.24.17. |
| [`docs/RELEASE_0.24.17.md`](docs/RELEASE_0.24.17.md) | Note de release V0.24.17. |
| [`docs/VALIDATION_0.24.17.md`](docs/VALIDATION_0.24.17.md) | Checklist de validation V0.24.17. |
| [`docs/INDEX_0.23.8.md`](docs/INDEX_0.23.8.md) | Index de référence de la V0.23.8. |
| [`docs/RELEASE_0.23.8.md`](docs/RELEASE_0.23.8.md) | Note de release V0.23.8. |
| [`docs/VALIDATION_0.23.8.md`](docs/VALIDATION_0.23.8.md) | Checklist de validation V0.23.8. |
| [`docs/V0.23_MEDIACAST.md`](docs/V0.23_MEDIACAST.md) | Cadrage fonctionnel et technique du suivi MediaCast. |
| [`docs/INSTALLATION.md`](docs/INSTALLATION.md) | Installation et mise à jour depuis `main`. |
| [`README_TECHNIQUE.md`](README_TECHNIQUE.md) | Architecture technique courante. |
| [`CHANGELOG.md`](CHANGELOG.md) | Historique des versions. |

Les documents des versions précédentes sont conservés pour historique et continuité.

## Copie écran

![Tableau de bord](docs/images/1.png)

![Tableau de bord](docs/images/2.png)

![Analyse](docs/images/3.png)

![Analyse](docs/images/4.png)

![Analyse](docs/images/5.png)

![Analyse IA](docs/images/6.png)

![Analyse IA](docs/images/7.png)

![Expert](docs/images/8.png)

![Configuration](docs/images/9.png)
