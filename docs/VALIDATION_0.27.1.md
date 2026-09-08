# Validation V0.27.1 — tableau de bord pédagogique amélioré

## Versions attendues

- Plugin principal : `0.27.1-dev`
- Plugin compagnon UI : `0.8.47`
- Commit fonctionnel : `de90cb1`

## Contrôle code

```bash
grep -n "0.27.1-dev\|0.8.47\|ITXEB V0.27.1 dashboard command center\|dashboardDisplayMode\|renderDashboardCommandCenter\|renderCourseSuccessGauge\|renderLearnerFunnel\|renderRecommendedActions\|renderResourceSignalMatrix\|__mode_compact\|🎓" \
plugin.php \
companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php
```

## Contrôle PHP

```bash
php -l plugin.php
php -l classes/class.ilIliasTraxEventBridgeCourseTrackingRepository.php
php -l classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php
php -l companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
php -l companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
php -l /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php
php -l /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php
```

## Validation navigateur

### Tableau de bord

Vérifier la présence des blocs :

- État global du cours ;
- Réussite du cours avec icône 🎓 ;
- Entonnoir pédagogique ;
- Actions recommandées ;
- Matrice ressources ;
- Synthèse pédagogique ;
- Activité dans le temps ;
- Top ressources.

### Analyse

Vérifier la présence des blocs :

- Actions recommandées ;
- Synthèse pédagogique ;
- Matrice ressources ;
- Apprenants en difficulté ;
- Questions à fort taux d'échec lorsque le contexte s'y prête ;
- MediaCast.

### Configuration

Vérifier :

- mode `Compact` ;
- mode `Standard` ;
- mode `Complet` ;
- cases de sélection des blocs du tableau de bord ;
- cases de sélection des cartes de synthèse pédagogique.

### Expert

Vérifier :

- colonne `Apprenant` entre `User ID` et `Verbe` ;
- export CSV avec `learner_identity`.

## Résultat attendu

Le tableau de bord doit être plus lisible et utilisable comme vue de décision formateur, sans perdre les données techniques déjà présentes dans les autres onglets.
