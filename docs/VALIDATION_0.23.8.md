# Validation V0.23.8 — MediaCast

## Objectif de validation

Valider que la V0.23.8 trace correctement les médias MediaCast et que l'affichage formateur est conforme.

## Préparation

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin
git checkout v0.23-mediacast-media-tracking
git pull --ff-only origin v0.23-mediacast-media-tracking
```

Installer / réaligner le plugin companion :

```bash
export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"
bash scripts/install_course_ui_companion_with_standalone_fix.sh

systemctl restart php-fpm
systemctl restart httpd
```

## Contrôle des versions

```bash
grep -n "0.23.8-dev\|0.8.19" \
plugin.php \
companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php
```

Résultat attendu :

```text
plugin.php:4:$version = '0.23.8-dev';
companion/.../plugin.php.tpl:4:$version = '0.8.19';
live companion plugin.php:4:$version = '0.8.19';
```

## Contrôle syntaxe PHP

```bash
php -l plugin.php
php -l classes/class.ilIliasTraxEventBridgeStatementFactory.php
php -l classes/class.ilIliasTraxEventBridgeOutboxRepository.php
php -l classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php
php -l companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
php -l companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl
php -l /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php
php -l /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php
```

## Contrôle des marqueurs MediaCast

```bash
grep -n "played-media\|opened-external-media\|by_mediacast_media\|Médias MediaCast vus\|ITXEB V0.23.8 external playlist title" \
classes/class.ilIliasTraxEventBridgeStatementFactory.php \
classes/class.ilIliasTraxEventBridgeOutboxRepository.php \
classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php
```

## Test fonctionnel navigateur

Dans un cours contenant un objet MediaCast :

```text
1. Ouvrir le MediaCast.
2. Lancer une vidéo interne.
3. Sélectionner un média externe, par exemple YouTube.
4. Attendre le passage du cron ou du job d'envoi.
5. Ouvrir Pilotage xAPI > Expert.
6. Ouvrir Pilotage xAPI > Analyse.
```

## Contrôle outbox

```sql
SELECT id, event_type, verb_id, status, sent_at, last_error
FROM evnt_evhk_itxeb_out
WHERE event_type = 'mediacast_media_client_event'
ORDER BY id DESC
LIMIT 20;
```

Résultat attendu :

```text
played-media          | sent | last_error vide
opened-external-media | sent | last_error vide
```

## Contrôle du titre externe

```sql
SELECT id, verb_id,
       SUBSTRING(statement_json, LOCATE('media_title', statement_json), 300) AS media_title_preview,
       created_at
FROM evnt_evhk_itxeb_out
WHERE event_type = 'mediacast_media_client_event'
  AND verb_id LIKE '%opened-external-media%'
ORDER BY id DESC
LIMIT 5;
```

Résultat attendu : le titre réel du média externe apparaît dans `media_title`, par exemple :

```text
Hold On to the Light
```

## Contrôle des vues

| Vue | Résultat attendu |
|---|---|
| Tableau de bord | Aucun bloc `Médias MediaCast vus`. |
| Analyse | Bloc `Médias MediaCast vus` visible. |
| Expert | Statements MediaCast visibles. |

## Validation finale

```text
MediaCast ouvert                         OK
Vidéo interne lue                        OK
Média externe ouvert                     OK
Titre réel vidéo externe dans Analyse    OK
Bloc MediaCast uniquement dans Analyse   OK
Outbox envoyée vers TRAX/LRS             OK
```
