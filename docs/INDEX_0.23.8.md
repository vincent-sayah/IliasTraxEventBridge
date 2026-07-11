# Index documentaire V0.23.8 — IliasTraxEventBridge

Cette page est l'index de référence pour la version V0.23.8.

## Documents V0.23.8

| Document | Usage |
|---|---|
| [`RELEASE_0.23.8.md`](RELEASE_0.23.8.md) | Note de release et périmètre validé. |
| [`VALIDATION_0.23.8.md`](VALIDATION_0.23.8.md) | Checklist de validation MediaCast. |
| [`V0.23_MEDIACAST.md`](V0.23_MEDIACAST.md) | Cadrage fonctionnel et technique du suivi MediaCast. |
| [`INSTALLATION.md`](INSTALLATION.md) | Installation et mise à jour depuis `main`. |
| [`RELEASE_0.22.4.md`](RELEASE_0.22.4.md) | Release stable précédente conservée pour historique. |
| [`INDEX_0.22.4.md`](INDEX_0.22.4.md) | Index documentaire de la V0.22.4 précédente. |

## Version validée

```text
plugin principal : 0.23.8-dev
companion UI     : 0.8.19
commit validé    : 630ad8e
branche dev      : v0.23-mediacast-media-tracking
branche stable   : main après promotion
```

## Périmètre fonctionnel

```text
MediaCast ouvert                         OK
Vidéo interne lue                        OK
Média externe ouvert                     OK
Titre réel vidéo externe dans Analyse    OK
Bloc MediaCast uniquement dans Analyse   OK
```

## Vues impactées

| Vue | Évolution V0.23.8 |
|---|---|
| Tableau de bord | Aucun bloc MediaCast dédié ; la vue reste synthétique. |
| Analyse | Ajout du bloc `Médias MediaCast vus`. |
| Expert | Affichage technique des statements `played-media` et `opened-external-media`. |
| Configuration | Inchangée. |
| Analyse IA | Inchangée pour V0.23.8. |

## Règle métier

```text
TRAX = source principale de lecture pédagogique.
Outbox = file technique d'envoi.
MediaCast = les médias vus sont exploités dans Analyse.
```

## Contrôle rapide serveur

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

grep -n '\$version' plugin.php companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
php -l plugin.php
php -l classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php
php -l companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
php -l companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl
php -l /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php
php -l /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php

grep -n "Médias MediaCast vus\|played-media\|opened-external-media\|ITXEB V0.23.8 external playlist title" \
plugin.php \
classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php \
classes/class.ilIliasTraxEventBridgeStatementFactory.php \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php
```
