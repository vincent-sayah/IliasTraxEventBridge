# Index documentaire V0.22.4 — IliasTraxEventBridge

Cette page est l'index de référence pour la version stable V0.22.4.

## Documents V0.22.4

| Document | Usage |
|---|---|
| [`RELEASE_0.22.4.md`](RELEASE_0.22.4.md) | Note de release et périmètre validé. |
| [`INSTALLATION.md`](INSTALLATION.md) | Installation et mise à jour depuis `main`. |
| [`VALIDATION_0.22.4.md`](VALIDATION_0.22.4.md) | Checklist de validation de l'ergonomie V0.22.4. |
| [`V0.22_ACTIVITY_TIMELINE.md`](V0.22_ACTIVITY_TIMELINE.md) | Cadrage du bloc Activité dans le temps. |
| [`V0.22.1_ILIAS_LIKE_DASHBOARD_LAYOUT.md`](V0.22.1_ILIAS_LIKE_DASHBOARD_LAYOUT.md) | Cadrage de la présentation type formulaire ILIAS. |
| [`FONCTIONNEL_0.21.2.md`](FONCTIONNEL_0.21.2.md) | Base fonctionnelle conservée, complétée par la release V0.22.4. |
| [`TECHNIQUE_0.21.2.md`](TECHNIQUE_0.21.2.md) | Base technique conservée, complétée par la release V0.22.4. |
| [`GUIDE_DEVELOPPEUR_0.21.2.md`](GUIDE_DEVELOPPEUR_0.21.2.md) | Guide développeur : classes, tables, flux. |
| [`EXPLOITATION_0.21.2.md`](EXPLOITATION_0.21.2.md) | Exploitation et diagnostic courant. |

## Version stable

```text
plugin principal : 0.22.4-dev
companion UI     : 0.8.10
commit validé    : b4fdf9a
branche stable   : main
```

## Règle métier

```text
TRAX = toutes les questions de test ILIAS sont tracées.
Tableau de bord / Analyse = questions problématiques uniquement.
Analyse IA = questions problématiques uniquement.
Expert = vision technique complète.
```

## Nouveautés ergonomiques

```text
Activité dans le temps
Présentation titre à gauche / données à droite
Synthèse pédagogique alignée
Retour correct sur l'onglet Analyse IA après retrait d'une analyse
```

## Contrôle rapide serveur

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

grep -n '\$version' plugin.php companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
php -l plugin.php
php -l companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
php -l /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php

grep -n "Activité dans le temps\|V0.22.4 alignment\|showCourseAiAnalysis" \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php
```
