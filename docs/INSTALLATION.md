# Installation et mise à jour — IliasTraxEventBridge

## Version stable courante

| Élément | Valeur |
|---|---|
| Branche stable | `main` |
| Version plugin principal | `0.27.1-dev` |
| Version plugin compagnon UI | `0.8.47` |
| Commit fonctionnel validé | `de90cb1` |

## Mise à jour depuis `main`

Depuis le serveur ILIAS :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin
git checkout -f main
git reset --hard origin/main

git update-index --assume-unchanged scripts/apply_v0243_dashboard_dual_chart_row.php || true

export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"
bash scripts/install_course_ui_companion_with_standalone_fix.sh

systemctl restart php-fpm
systemctl restart httpd
```

## Contrôle des versions

```bash
grep -n "0.27.1-dev\|0.8.47\|renderDashboardCommandCenter\|renderCourseSuccessGauge\|renderLearnerFunnel\|renderRecommendedActions\|renderResourceSignalMatrix\|🎓" \
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

Dans un cours ILIAS avec `Pilotage xAPI` :

- onglet `Tableau de bord` : vérifier l'état global, la jauge de réussite, l'entonnoir, les actions et la matrice ;
- onglet `Analyse` : vérifier les actions recommandées, la matrice et la synthèse ;
- onglet `Configuration` : vérifier le mode Compact / Standard / Complet et la sélection des blocs ;
- onglet `Expert` : vérifier la colonne `Apprenant`.

## Remarques

Le taux de réussite du cours est lu depuis la progression ILIAS lorsque celle-ci est paramétrée. Il ne provient pas des statements TRAX/xAPI.
