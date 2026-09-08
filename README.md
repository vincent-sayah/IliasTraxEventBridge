# IliasTraxEventBridge

Plugin ILIAS 10 EventHook permettant de transformer des événements ILIAS en statements xAPI, de les envoyer vers TRAX/LRS, puis d'afficher un pilotage pédagogique directement dans le cours ILIAS via un plugin compagnon UIHook.

## Version courante validée

| Élément | Valeur |
|---|---|
| Branche stable officielle | `main` après promotion V0.27.1 |
| Version plugin principal | `0.27.1-dev` |
| Version plugin compagnon UI | `0.8.47` |
| Branche de validation | `v0.27-dashboard-command-center-validated` |
| Commit fonctionnel validé | `de90cb1` — `V0.27.1 validate dashboard command center` |
| Compatibilité ILIAS | `10.0.0` à `10.999.999` |

Installation stable courante :

```bash
git clone -b main --single-branch https://github.com/vincent-sayah/IliasTraxEventBridge.git IliasTraxEventBridge
```

## Règle métier

```text
TRAX/LRS = destination xAPI et source principale de suivi pédagogique xAPI.
Outbox locale = file technique d'envoi.
Progression ILIAS = source du taux de réussite du cours quand la progression du cours est paramétrée.
MediaCast = suivi des ouvertures, vidéos internes lues et médias externes sélectionnés.
```

## Fonctionnalités principales

- Captation d'événements ILIAS via EventHook.
- Génération locale de statements xAPI.
- Envoi vers TRAX/LRS via outbox locale.
- Activation stricte par cours et par ressource.
- Accès `Pilotage xAPI` depuis l'objet cours via le plugin compagnon UIHook.
- Tableau de bord pédagogique enrichi.
- Mode d'affichage du tableau de bord : `Compact`, `Standard`, `Complet`.
- État global du cours : situation stable, à surveiller ou critique.
- Jauge `Réussite du cours` avec icône diplôme 🎓, calculée depuis la progression ILIAS.
- Entonnoir pédagogique : inscrits, actifs, tentatives, réussites.
- Bloc `Actions recommandées` pour guider le formateur.
- Matrice ressources : activité, réussite, signal pédagogique.
- Synthèse pédagogique configurable par cours.
- Activité dans le temps avec graphique linéaire SVG.
- Top ressources aligné avec le graphique en vue large.
- Questions à fort taux d'échec filtrées selon le contexte.
- Bloc `Apprenants en difficulté` avec affichage du login ILIAS.
- Suivi MediaCast : vidéos internes lues et médias externes ouverts.
- Onglet `Analyse IA` avec génération, historique, comparaison et retrait d'analyses.
- Vue Expert technique avec colonne `Apprenant` entre `User ID` et `Verbe`.
- Export CSV Expert avec colonne `learner_identity`.
- Export PDF du tableau de bord.
- Diagnostic TRAX/LRS dans l'onglet Configuration.
- Supervision technique de l'outbox.
- Traces question par question pour les tests ILIAS.

## Vues du pilotage xAPI

```text
Tableau de bord | Analyse | Analyse IA | Expert | Configuration | Retour contenu du cours
```

| Vue | Rôle |
|---|---|
| Tableau de bord | Décision rapide : état global, réussite, entonnoir, actions, synthèse, activité et ressources. |
| Analyse | Lecture formateur détaillée : priorités, actions, matrice ressources, apprenants en difficulté, MediaCast et questions. |
| Analyse IA | Génération, historique, comparaison et retrait d'analyses IA. |
| Expert | Vue technique détaillée des statements, colonne `Apprenant`, export CSV. |
| Configuration | Activation cours/ressources, personnalisation du tableau de bord, synthèse pédagogique, diagnostic LRS et outbox. |

## V0.27.1 — tableau de bord pédagogique amélioré

La V0.27.1 transforme le tableau de bord en centre de décision formateur.

### Nouveaux blocs

| Bloc | Description |
|---|---|
| État global du cours | Statut synthétique : stable, à surveiller ou priorité formateur. |
| Jauge réussite du cours | Barre de progression et icône 🎓 lorsque la progression ILIAS du cours est paramétrée. |
| Entonnoir pédagogique | Visualisation `Inscrits > Actifs > Tentatives > Réussites`. |
| Actions recommandées | Liste courte des priorités pédagogiques à traiter. |
| Matrice ressources | Vue compacte par ressource : activité, réussite, signal et dernière trace. |

### Configuration

L'onglet `Configuration` permet de choisir le mode du tableau de bord :

```text
Compact  = décision rapide
Standard = suivi formateur recommandé
Complet  = tous les blocs disponibles
```

La synthèse pédagogique reste configurable par carte, par cours.

## V0.26 conservée

- V0.26.1 : ajout du taux de réussite du cours depuis la progression ILIAS.
- V0.26.2 : icône diplôme 🎓 et sélection des cartes de `Synthèse pédagogique`.

## V0.25.6 conservée

La V0.25.6 ajoute l'affichage du login ILIAS dans `Analyse` et `Expert`.

```text
Date | User ID | Apprenant | Verbe | Ressource | Type | Score | Completion | Success | Source | Statement ID
```

`User ID` reste un identifiant technique pseudonymisé. `Apprenant` contient le login ILIAS résolu.

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

Contrôle des versions après promotion V0.27.1 :

```bash
grep -n "0.27.1-dev\|0.8.47\|renderDashboardCommandCenter\|renderCourseSuccessGauge\|renderLearnerFunnel\|renderRecommendedActions\|renderResourceSignalMatrix\|🎓" \
plugin.php \
companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
```

## Documentation de référence

| Document | Rôle |
|---|---|
| [`docs/INDEX_0.27.1.md`](docs/INDEX_0.27.1.md) | Index de référence V0.27.1. |
| [`docs/RELEASE_0.27.1.md`](docs/RELEASE_0.27.1.md) | Note de release V0.27.1. |
| [`docs/VALIDATION_0.27.1.md`](docs/VALIDATION_0.27.1.md) | Checklist de validation V0.27.1. |
| [`docs/INDEX_0.25.6.md`](docs/INDEX_0.25.6.md) | Index V0.25.6. |
| [`docs/RELEASE_0.25.6.md`](docs/RELEASE_0.25.6.md) | Note de release V0.25.6. |
| [`docs/VALIDATION_0.25.6.md`](docs/VALIDATION_0.25.6.md) | Checklist V0.25.6. |
| [`docs/INSTALLATION.md`](docs/INSTALLATION.md) | Installation et mise à jour depuis `main`. |
| [`README_TECHNIQUE.md`](README_TECHNIQUE.md) | Architecture technique courante. |
| [`CHANGELOG.md`](CHANGELOG.md) | Historique des versions. |

Les documents des versions précédentes sont conservés pour historique.
