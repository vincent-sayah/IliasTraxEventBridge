# Validation V0.24.17 — Tableau de bord pédagogique

Version : `0.24.17-dev`  
Plugin compagnon UI : `0.8.37`  
Branche : `v0.24-dashboard-synthesis-layout`  
Commit fonctionnel validé : `3a05d77` — `V0.24.17 validate dashboard synthesis and activity layout`

## Pré-requis

- ILIAS 10 opérationnel.
- Plugin principal `IliasTraxEventBridge` installé.
- Plugin compagnon `IliasTraxEventBridgeCourseUI` installé.
- Branche de travail : `v0.24-dashboard-synthesis-layout`.
- Version appliquée : `0.24.17-dev` / `0.8.37`.

## Contrôles Git

```bash
git fetch origin
git checkout v0.24-dashboard-synthesis-layout
git status -sb
```

Résultat attendu :

```text
## v0.24-dashboard-synthesis-layout...origin/v0.24-dashboard-synthesis-layout
```

## Contrôles fichiers critiques

```bash
grep -n "private \$repository\|public function __construct\|public function handle" \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php
```

Résultat attendu :

```text
private $repository;
public function __construct(...)
public function handle()
```

## Contrôles syntaxe PHP

```bash
php -l /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge/plugin.php
php -l /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
php -l /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
php -l /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php
php -l /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php
```

Résultat attendu pour chaque fichier :

```text
No syntax errors detected
```

## Contrôle versions

```bash
grep -n "0.24.17-dev\|0.8.37\|ITXEB V0.24.17 top resources aligned with graph card safe\|itxeb-dashboard-activity-chart-row\|itxeb-dashboard-top-card" \
plugin.php \
companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php
```

Résultat attendu :

- `plugin.php` en `0.24.17-dev` ;
- plugin compagnon en `0.8.37` ;
- marqueur V0.24.17 présent ;
- classes CSS `itxeb-dashboard-activity-chart-row` et `itxeb-dashboard-top-card` présentes.

## Contrôle absence de doublons

```bash
grep -n "private function renderDashboardActivityTopLayout\|renderDashboardChartsRow\|itxeb-dashboard-charts-row" \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php
```

Résultat attendu :

```text
1 seule méthode renderDashboardActivityTopLayout dans le template
1 seule méthode renderDashboardActivityTopLayout dans le live
aucun renderDashboardChartsRow
aucun itxeb-dashboard-charts-row
```

## Redémarrage

```bash
systemctl restart php-fpm
systemctl restart httpd
```

## Validation navigateur

Dans ILIAS :

```text
Cours > Pilotage xAPI > Tableau de bord
```

Valider visuellement :

```text
Synthèse pédagogique
Activité dans le temps
  intro + boutons + KPI
  Progression de l’activité  |  Top ressources
```

Résultat attendu :

- la page s'ouvre sans erreur `Erreur Suivi xAPI` ;
- le titre `Activité dans le temps` est aligné comme les autres titres ;
- le graphique est une courbe et non plus un histogramme horizontal ;
- `Top ressources` démarre sur la même ligne que la carte `Progression de l’activité` ;
- le bloc `Questions à fort taux d’échec` ne s'affiche pas pour une ressource non-test.

## Critères d'acceptation

La V0.24.17 est validée si :

- les contrôles PHP sont OK ;
- le navigateur ne remonte pas d'erreur ;
- l'affichage du tableau de bord est conforme ;
- les règles métier xAPI précédentes sont conservées ;
- MediaCast reste disponible dans l'onglet `Analyse`.
