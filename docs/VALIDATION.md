# Plan de validation — IliasTraxEventBridge v0.8.0

Version stable publiée : **v0.8.0** sur `main`.

Ce plan valide la release V0.8.0 : configuration xAPI par cours et par ressource, accès depuis l'objet cours, filtrage avant outbox, outbox, envoi LRS/TRAX, diagnostic des traces refusées, purge et packaging propre du plugin compagnon UIHook.

## Test 1 — État Git et version plugin

Dans le dossier du plugin principal :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git status --short
git log --oneline --decorate -5
grep -n '\$version' plugin.php
grep -n '\$version' companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
```

Résultat attendu :

```text
git status --short : vide
HEAD sur tag v0.8.0 ou branche main à jour
plugin.php : 0.8.0
plugin.php.tpl companion : 0.1.1
```

## Test 2 — Syntaxe PHP et packaging companion

Depuis le dossier du plugin principal :

```bash
find . -name "*.php" -print0 | xargs -0 -n1 php -l
find companion/IliasTraxEventBridgeCourseUI -name "*.php" -print
```

Résultat attendu :

```text
aucune erreur de syntaxe PHP
aucun fichier .php dans companion/IliasTraxEventBridgeCourseUI
```

Installer ou régénérer le companion :

```bash
bash scripts/install_course_ui_companion.sh
```

Contrôler le slot UIHook actif :

```bash
find /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI -name "*.php" -print
```

Résultat attendu :

```text
plugin.php
classes/class.ilIliasTraxEventBridgeCourseUIPlugin.php
classes/class.ilIliasTraxEventBridgeCourseUIBridge.php
classes/class.ilIliasTraxEventBridgeCourseUIScreen.php
classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php
```

## Test 3 — Build ILIAS

Depuis la racine ILIAS :

```bash
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
```

Résultat attendu :

```text
build terminé sans erreur bloquante
plus aucun warning Composer Ambiguous class resolution sur IliasTraxEventBridgeCourseUI
```

Des warnings ILIAS indépendants, par exemple sur `scripts/PHP-CS-Fixer/example`, peuvent rester.

## Test 4 — Tables attendues

```sql
SHOW TABLES LIKE 'evnt_evhk_itxeb_log';
SHOW TABLES LIKE 'evnt_evhk_itxeb_out';
SHOW TABLES LIKE 'evnt_evhk_itxeb_read';
SHOW TABLES LIKE 'evnt_evhk_itxeb_ccfg';
SHOW TABLES LIKE 'evnt_evhk_itxeb_rcfg';
SHOW TABLES LIKE 'evnt_evhk_itxeb_dlog';
```

Résultat attendu :

```text
evnt_evhk_itxeb_log
evnt_evhk_itxeb_out
evnt_evhk_itxeb_read
evnt_evhk_itxeb_ccfg
evnt_evhk_itxeb_rcfg
evnt_evhk_itxeb_dlog
```

Structures utiles :

```sql
DESCRIBE evnt_evhk_itxeb_ccfg;
DESCRIBE evnt_evhk_itxeb_rcfg;
DESCRIBE evnt_evhk_itxeb_dlog;
```

## Test 5 — Accès depuis l'objet cours

Dans ILIAS :

```text
Cours > Paramètres > Suivi xAPI
```

Résultat attendu :

```text
le sous-onglet Suivi xAPI est visible
l'écran s'ouvre dans le contenu central ILIAS
les onglets du cours restent visibles
```

## Test 6 — Enregistrement configuration cours / ressources

Depuis l'écran `Suivi xAPI` du cours :

1. cocher `Activer les traces xAPI pour ce cours` ;
2. cocher quelques ressources, par exemple `file` et `htlm` ;
3. cliquer sur `Enregistrer la configuration xAPI`.

Vérifier ensuite :

```sql
SELECT *
FROM evnt_evhk_itxeb_ccfg
WHERE course_ref_id = 194;

SELECT course_ref_id, ref_id, obj_id, obj_type, enabled, updated_at, updated_by
FROM evnt_evhk_itxeb_rcfg
WHERE course_ref_id = 194
ORDER BY ref_id;
```

Résultat attendu :

```text
evnt_evhk_itxeb_ccfg.enabled = 1 pour le cours
ressources cochées : enabled = 1
ressources non cochées : enabled = 0
updated_by renseigné
```

Exemple déjà validé :

```text
course_ref_id 194 / course_obj_id 629 / enabled 1
file ref_id 196 / enabled 1
htlm ref_id 207 / enabled 1
autres ressources / enabled 0
```

## Test 7 — Filtrage ressource activée

Noter le dernier identifiant outbox :

```sql
SELECT COALESCE(MAX(id), 0) AS max_outbox_id
FROM evnt_evhk_itxeb_out;
```

Consulter une ressource activée, puis lancer le cron si nécessaire :

```bash
cd /var/www/ilias
sudo -u apache php cron/cron.php run itxeb_send_outbox_to_trax
```

Vérifier :

```sql
SELECT id, event_log_id, event_type, obj_type, ref_id, obj_id,
       user_id, status, created_at, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 20;
```

Résultat attendu :

```text
ressource activée -> ligne outbox generated puis sent
last_error vide après envoi OK
```

## Test 8 — Filtrage ressource désactivée

Noter le dernier identifiant outbox :

```sql
SELECT COALESCE(MAX(id), 0) AS max_outbox_id
FROM evnt_evhk_itxeb_out;
```

Consulter une ressource désactivée, puis lancer le cron si nécessaire.

Vérifier :

```sql
SELECT id, event_log_id, event_type, obj_type, ref_id, obj_id,
       user_id, status, created_at, sent_at
FROM evnt_evhk_itxeb_out
WHERE id > <MAX_ID_AVANT_TEST>
ORDER BY id ASC;
```

Résultat attendu :

```text
Empty set
```

Cela confirme que le refus a lieu avant insertion dans `evnt_evhk_itxeb_out`.

## Test 9 — Diagnostic des traces refusées désactivé

Dans l'administration du plugin, décocher :

```text
Activer le diagnostic des traces refusées
```

Noter le compteur :

```sql
SELECT COUNT(*) AS total
FROM evnt_evhk_itxeb_dlog;
```

Consulter une ressource désactivée, lancer le cron si nécessaire, puis recompter.

Résultat attendu :

```text
le total ne doit pas augmenter
```

## Test 10 — Diagnostic des traces refusées activé

Dans l'administration du plugin, cocher :

```text
Activer le diagnostic des traces refusées
```

Consulter une ressource désactivée, lancer le cron si nécessaire, puis vérifier :

```sql
SELECT id, created_at, reason, event_type, user_id, course_ref_id,
       ref_id, obj_id, obj_type, source_table, source_id
FROM evnt_evhk_itxeb_dlog
ORDER BY id DESC
LIMIT 30;

SELECT reason, COUNT(*) AS total
FROM evnt_evhk_itxeb_dlog
GROUP BY reason
ORDER BY total DESC, reason ASC;
```

Résultat attendu :

```text
reason = resource_disabled pour les ressources désactivées
source_table = read_event ou evnt_evhk_itxeb_log selon la source
```

## Test 11 — Purge du diagnostic des refus

Dans l'administration du plugin :

```text
Diagnostic des traces refusées V0.8 > Purger le diagnostic des traces refusées
```

Vérifier :

```sql
SELECT COUNT(*) AS total
FROM evnt_evhk_itxeb_dlog;
```

Résultat attendu :

```text
total = 0
```

## Test 12 — Enrichissement xAPI conservé

Inspecter un statement récent :

```sql
SELECT id, event_type, obj_type, status, statement_json
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 1\G
```

Dans le JSON, vérifier :

```text
object.definition.name
object.definition.moreInfo
context.contextActivities.parent
context.extensions.object_title
context.extensions.object_url
context.extensions.course_title
context.extensions.course_url
```

## Test 13 — Diagnostics outbox dans le JSON xAPI

Dans `context.extensions`, vérifier :

```text
outbox_id
outbox_table
event_log_id
statement_uuid
event_record_source
source_table
deduplication_key
statement_family
interaction_type
repository_object_family
```

## Test 14 — Absence de pollution root / crs

```sql
SELECT id, event_log_id, event_type, obj_type, ref_id, obj_id,
       user_id, status, created_at, sent_at
FROM evnt_evhk_itxeb_out
WHERE obj_type IN ('root', 'crs')
ORDER BY id DESC
LIMIT 20;
```

Résultat attendu :

```text
Empty set
```

## Test 15 — Envoi LRS/TRAX

```sql
SELECT id, event_type, obj_type, status, retry_count, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 20;
```

Résultat attendu :

```text
status = sent
retry_count = 0
sent_at renseigné
last_error vide
```

## Test 16 — Synthèse finale

```sql
SELECT event_type, obj_type, verb_id, status, COUNT(*) AS nb
FROM evnt_evhk_itxeb_out
GROUP BY event_type, obj_type, verb_id, status
ORDER BY event_type, obj_type, verb_id, status;

SELECT course_ref_id, course_obj_id, enabled, updated_at, updated_by
FROM evnt_evhk_itxeb_ccfg
ORDER BY updated_at DESC;

SELECT course_ref_id, ref_id, obj_id, obj_type, enabled, updated_at, updated_by
FROM evnt_evhk_itxeb_rcfg
ORDER BY course_ref_id, ref_id;

SELECT reason, COUNT(*) AS total
FROM evnt_evhk_itxeb_dlog
GROUP BY reason
ORDER BY total DESC, reason ASC;
```

## Validation réalisée V0.8.0

```text
Tag v0.8.0 publié : OK
main promu sur v0.8.0 : OK
plugin principal 0.8.0 : OK
companion 0.1.1 : OK
php -l plugin principal : OK
installation companion par templates : OK
composer du sans warnings Ambiguous class resolution CourseUI : OK
activation/désactivation diagnostic refus : OK
purge diagnostic refus : OK
Suivi xAPI dans l'objet cours : OK
```
