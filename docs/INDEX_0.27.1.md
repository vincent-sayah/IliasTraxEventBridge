# Index V0.27.1 — tableau de bord pédagogique amélioré

## Statut

| Élément | Valeur |
|---|---|
| Version | V0.27.1 |
| Plugin principal | `0.27.1-dev` |
| Plugin compagnon UI | `0.8.47` |
| Branche validée | `v0.27-dashboard-command-center-validated` |
| Commit fonctionnel | `de90cb1` |
| Promotion | `main` |

## Objectif

La V0.27.1 transforme le tableau de bord du cours en centre de décision pédagogique lisible pour le formateur.

## Fonctions ajoutées

- État global du cours.
- Jauge de réussite du cours avec icône diplôme 🎓.
- Entonnoir pédagogique : inscrits, actifs, tentatives, réussites.
- Actions recommandées.
- Matrice ressources : activité, réussite, signal pédagogique.
- Modes du tableau de bord : Compact, Standard, Complet.
- Sélection des blocs visibles depuis l'onglet Configuration.

## Données utilisées

| Bloc | Source |
|---|---|
| Activité, traces, ressources, questions, MediaCast | TRAX/LRS |
| Réussite du cours | Progression ILIAS |
| Login apprenant | ILIAS `usr_data.login` |
| Préférences d'affichage | Configuration locale du cours |

## Fichiers principaux

- `classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php`
- `classes/class.ilIliasTraxEventBridgeCourseTrackingRepository.php`
- `companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl`
- `plugin.php`
- `companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl`
- `scripts/apply_v0261_course_progress_success_rate.py`
- `scripts/apply_v0262_synthesis_card_configuration.py`
- `scripts/apply_v0271_dashboard_command_center.py`

## Documentation associée

- `docs/RELEASE_0.27.1.md`
- `docs/VALIDATION_0.27.1.md`
- `docs/INSTALLATION.md`
- `README.md`
- `README_TECHNIQUE.md`
- `CHANGELOG.md`
