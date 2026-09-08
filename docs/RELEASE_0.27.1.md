# Release V0.27.1 — tableau de bord pédagogique amélioré

## Résumé

La V0.27.1 enrichit le pilotage xAPI avec une vue de décision formateur plus lisible. Elle ajoute des blocs graphiques et synthétiques au tableau de bord, tout en conservant les vues détaillées `Analyse`, `Analyse IA` et `Expert`.

## Nouveautés

### État global du cours

Une carte principale indique rapidement si le cours est :

```text
Situation stable
À surveiller
Priorité formateur
```

Le statut est calculé à partir des signaux pédagogiques déjà présents : ressources critiques, ressources à surveiller, ressources sans activité, tests échoués et taux de réussite ILIAS.

### Jauge réussite du cours

La carte `Réussite du cours` affiche une jauge visuelle avec l'icône diplôme 🎓.

Cette donnée vient de la progression ILIAS du cours, pas de TRAX/xAPI.

### Entonnoir pédagogique

L'entonnoir affiche :

```text
Inscrits > Actifs > Tentatives > Réussites
```

Il permet d'identifier rapidement où le parcours se bloque.

### Actions recommandées

Le plugin affiche des priorités courtes :

- traiter les ressources critiques ;
- relancer les ressources sans activité ;
- surveiller les taux d'échec ;
- exploiter les questions problématiques ;
- suivre les apprenants en difficulté.

### Matrice ressources

La matrice offre une lecture compacte par ressource :

```text
Ressource | Activité | Réussite | Signal | Dernière trace
```

Elle complète les tableaux détaillés sans les remplacer.

### Modes du tableau de bord

L'onglet `Configuration` permet de choisir :

```text
Compact  = décision rapide
Standard = suivi formateur recommandé
Complet  = tous les blocs disponibles
```

## Fichiers modifiés

- `classes/class.ilIliasTraxEventBridgeCourseTrackingRepository.php`
- `classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php`
- `companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl`
- `plugin.php`
- `companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl`

## Scripts ajoutés

- `scripts/apply_v0261_course_progress_success_rate.py`
- `scripts/apply_v0262_synthesis_card_configuration.py`
- `scripts/apply_v0271_dashboard_command_center.py`

## Validation

Version validée fonctionnellement sur serveur ILIAS 10.

- Préflight script OK.
- Lint PHP OK.
- Redémarrage `php-fpm` OK.
- Redémarrage `httpd` OK.
- Validation navigateur OK.
