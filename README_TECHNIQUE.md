# README technique — IliasTraxEventBridge

## Version technique courante

| Élément | Valeur |
|---|---|
| Version plugin principal | `0.27.1-dev` |
| Version plugin compagnon UI | `0.8.47` |
| Branche stable | `main` |
| Commit fonctionnel validé | `de90cb1` |

## Architecture

```text
ILIAS 10
  ├─ EventHook IliasTraxEventBridge
  │    ├─ capte les événements ILIAS
  │    ├─ génère les statements xAPI
  │    ├─ génère les traces question par question
  │    ├─ génère les traces MediaCast client
  │    └─ alimente l'outbox locale technique
  │
  ├─ Cron ILIAS
  │    └─ envoie l'outbox vers TRAX/LRS
  │
  └─ UIHook IliasTraxEventBridgeCourseUI
       ├─ affiche Pilotage xAPI dans le cours
       ├─ lit TRAX/LRS pour les indicateurs xAPI
       ├─ lit la progression ILIAS pour la réussite du cours
       └─ affiche Tableau de bord, Analyse, Analyse IA, Expert, Configuration
```

## Sources de données

| Donnée | Source |
|---|---|
| Statements xAPI | TRAX/LRS |
| Activité, ressources, verbes, questions, MediaCast | TRAX/LRS |
| Apprenants actifs xAPI | TRAX/LRS |
| Login apprenant affiché | ILIAS `usr_data.login` après résolution `ilias-user-ID` |
| Taux de réussite du cours | Progression ILIAS (`ut_lp_settings`, `ut_lp_marks`, participants cours) |
| Préférences tableau de bord | Table locale de configuration cours `evnt_evhk_itxeb_ccfg` |
| Préférences synthèse pédagogique | Table locale de configuration cours `evnt_evhk_itxeb_ccfg` |
| Outbox | Tables locales du plugin EventHook |

## V0.27.1 — centre de décision pédagogique

La V0.27.1 ajoute une couche de restitution formateur au-dessus des indicateurs existants.

### Nouveaux rendus

- `renderDashboardCommandCenter()` : état global du cours.
- `renderCourseSuccessGauge()` : jauge graphique de réussite avec icône 🎓.
- `renderLearnerFunnel()` : entonnoir pédagogique.
- `renderRecommendedActions()` : actions recommandées.
- `renderResourceSignalMatrix()` : matrice activité / réussite / signal.
- `dashboardDisplayMode()` : mode `Compact`, `Standard`, `Complet`.

### Configuration

Les préférences sont stockées par cours via les préférences du tableau de bord.

Les modes utilisent des clés techniques internes :

```text
__mode_compact
__mode_standard
__mode_full
```

Les blocs restent sélectionnables depuis l'onglet `Configuration`.

## V0.26 intégrée

### Progression ILIAS

La réussite du cours est calculée localement dans ILIAS lorsque la progression du cours est paramétrée.

Tables et mécanismes utilisés :

```text
ut_lp_settings.u_mode
ut_lp_marks.status
crs_members ou ilCourseParticipants
ilLPStatus::LP_STATUS_COMPLETED_NUM si disponible
```

La donnée est exposée dans le résumé LRS enrichi sous :

```text
course_progress
summary.course_success_rate
summary.course_progress_configured
```

### Synthèse pédagogique configurable

Les cartes de synthèse sont configurables par cours. Le stockage utilise :

```text
synthesis_cards_json
synthesis_cards_updated_at
synthesis_cards_updated_by
```

## V0.25.6 intégrée

La résolution nominative applique l'ordre suivant :

1. lecture `actor.account.name` ;
2. détection du format `ilias-user-ID` ;
3. recherche dans ILIAS via `ilObjUser::_lookupLogin` ;
4. fallback SQL `usr_data.login` ;
5. fallback sur l'identité brute TRAX/LRS si aucun login n'est trouvé.

## Contrôles techniques

```bash
php -l plugin.php
php -l classes/class.ilIliasTraxEventBridgeCourseTrackingRepository.php
php -l classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php
php -l companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
php -l companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
```

## Installation du compagnon UI

```bash
export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```
