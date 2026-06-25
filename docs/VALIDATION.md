# Plan de validation — IliasTraxEventBridge v0.7

Version de stabilisation actuelle : **v0.7.0**.

Ce plan valide la branche V0.7 : configuration xAPI par cours et par ressource, interface d'accès admin, filtrage avant outbox, conservation des enrichissements V0.6, envoi TRAX et absence de traces parasites `root` / `crs`.

La branche stable publiée reste `main` / `v0.6` tant que la V0.7 n'est pas taguée et promue.

## Test 1 — État Git et version plugin

Dans le dossier du plugin :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git status --short
git branch --show-current
git log --oneline --decorate -10
grep -n '\$version' plugin.php
```

Résultat attendu :

```text
git status --short : vide
branche courante : v0.7
plugin.php : 0.7.0
```

## Test 2 — Build ILIAS et syntaxe PHP

Depuis le dossier du plugin :

```bash
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

Depuis la racine ILIAS :

```bash
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
```

Résultat attendu : aucun défaut de syntaxe PHP et build ILIAS terminé sans erreur.

## Test 3 — Tables attendues

```sql
SHOW TABLES LIKE 'evnt_evhk_itxeb_log';
SHOW TABLES LIKE 'evnt_evhk_itxeb_out';
SHOW TABLES LIKE 'evnt_evhk_itxeb_read';
SHOW TABLES LIKE 'evnt_evhk_itxeb_%cfg';
SHOW TABLES LIKE 'read_event';
```

Résultat attendu :

```text
evnt_evhk_itxeb_log
evnt_evhk_itxeb_out
evnt_evhk_itxeb_read
evnt_evhk_itxeb_ccfg
evnt_evhk_itxeb_rcfg
read_event
```

Structure V0.7 :

```sql
DESCRIBE evnt_evhk_itxeb_ccfg;
DESCRIBE evnt_evhk_itxeb_rcfg;
SHOW INDEX FROM evnt_evhk_itxeb_ccfg;
SHOW INDEX FROM evnt_evhk_itxeb_rcfg;
```

## Test 4 — Accès écran configuration xAPI par cours

Dans ILIAS :

```text
Administration > Plugins > IliasTraxEventBridge > Configurer
```

Vérifier la présence de la section :

```text
Configuration xAPI par cours
```

Saisir le `course_ref_id` d'un cours, par exemple :

```text
194
```

Résultat attendu : l'écran `TRAX / xAPI — configuration du cours` s'ouvre sans erreur `The requested page could not be found`.

## Test 5 — Enregistrement configuration cours / ressources

Depuis l'écran V0.7 :

1. cocher `Activer les traces xAPI pour ce cours` ;
2. cocher uniquement quelques ressources, par exemple `file` et `htlm` ;
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

Exemple validé :

```text
course_ref_id 194 / course_obj_id 629 / enabled 1
file ref_id 196 / obj_id 636 / enabled 1
htlm ref_id 207 / obj_id 655 / enabled 1
```

## Test 6 — Filtrage ressource activée

Noter le dernier identifiant outbox :

```sql
SELECT COALESCE(MAX(id), 0) AS max_outbox_id
FROM evnt_evhk_itxeb_out;
```

Consulter ensuite une ressource activée, par exemple :

```text
file ref_id 196
htlm ref_id 207
```

Lancer le cron si nécessaire :

```bash
cd /var/www/ilias
sudo -u apache php cron/cron.php run itxeb_send_outbox_to_trax
```

Vérifier :

```sql
SELECT id, event_log_id, event_type, obj_type, ref_id, obj_id,
       user_id, status, created_at, sent_at
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 20;
```

Résultat attendu :

```text
file activé -> ligne outbox file_downloaded -> sent
htlm activé -> ligne outbox repository_object_access -> sent
```

Exemple validé :

```text
file ref_id 196 -> file_downloaded -> sent
htlm ref_id 207 -> repository_object_access -> sent
```

## Test 7 — Filtrage ressource désactivée

Noter le dernier identifiant outbox :

```sql
SELECT MAX(id) AS max_outbox_id
FROM evnt_evhk_itxeb_out;
```

Consulter une ressource désactivée, par exemple :

```text
wiki ref_id 199
test ref_id 200
webr ref_id 204 ou 209
```

Lancer le cron si nécessaire :

```bash
cd /var/www/ilias
sudo -u apache php cron/cron.php run itxeb_send_outbox_to_trax
```

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

Cela confirme que le refus V0.7 a lieu avant insertion dans `evnt_evhk_itxeb_out`.

## Test 8 — Consultation réelle via read_event

Avant ou après le cron, vérifier que `read_event` est alimentée :

```sql
SELECT r.obj_id, r.usr_id, r.read_count, r.spent_seconds,
       r.first_access, r.last_access,
       od.type, od.title
FROM read_event r
JOIN object_data od ON od.obj_id = r.obj_id
ORDER BY r.last_access DESC
LIMIT 20;
```

Après le cron, vérifier la table anti-doublon :

```sql
SELECT *
FROM evnt_evhk_itxeb_read
ORDER BY processed_at DESC
LIMIT 20;
```

Résultat attendu :

- une ressource activée peut générer une ligne outbox ;
- une ressource désactivée ne génère pas d'outbox ;
- une consultation refusée est quand même marquée traitée dans `evnt_evhk_itxeb_read` pour éviter une boucle cron.

## Test 9 — Enrichissement xAPI conservé

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

Résultat attendu : l'objet et le cours parent sont identifiables par titre et URL comme en V0.6.

## Test 10 — Familles de statements et verbes

```sql
SELECT id, event_type, obj_type, verb_id, status, statement_json
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 10\G
```

Dans `statement_json`, vérifier les extensions :

```text
statement_family
interaction_type
repository_object_family
```

Résultat attendu par type :

```text
blog -> statement_family = repository_blog_access        ; interaction_type = read
wiki -> statement_family = repository_wiki_access        ; interaction_type = read
htlm -> statement_family = repository_html_module_access ; interaction_type = read
webr -> statement_family = repository_web_link_access    ; interaction_type = visit
mcst -> statement_family = repository_media_access       ; interaction_type = view
file -> statement_family = file_download                 ; interaction_type = download
tst  -> statement_family = test_tracking                 ; interaction_type = assessment_progress
```

## Test 11 — Métriques read_event et durée xAPI

Pour une consultation issue de `read_event`, vérifier :

```text
result.extensions.read_count
result.extensions.spent_seconds
result.extensions.read_event_last_access
result.extensions.read_event_first_access
context.extensions.read_count
context.extensions.spent_seconds
context.extensions.read_event_last_access
context.extensions.read_event_first_access
```

Si `spent_seconds > 0`, vérifier aussi :

```text
result.duration = PT...S
```

## Test 12 — Diagnostics outbox dans le JSON xAPI

Inspecter un statement récent :

```sql
SELECT id, event_type, obj_type, status, statement_json
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 1\G
```

Vérifier dans `context.extensions` :

```text
outbox_id
outbox_table
event_log_id
statement_uuid
event_record_source
source_table
deduplication_key
```

Résultat attendu pour une trace `read_event` :

```text
outbox_id = id de la ligne evnt_evhk_itxeb_out
outbox_table = evnt_evhk_itxeb_out
event_log_id = 0
event_record_source = read_event_tracker
source_table = read_event
deduplication_key = read_event:{obj_id}:{user_id}:{read_event_last_access}:{read_count}
```

Résultat attendu pour un EventHook journalisé, par exemple fichier ou test :

```text
event_log_id > 0
event_record_source = event_hook_log
source_table = evnt_evhk_itxeb_log
deduplication_key = event_log:{event_log_id}
```

## Test 13 — Wording bilingue

Dans `object.definition.description`, vérifier que `fr-FR` et `en-US` sont distincts.

Exemple attendu pour un module HTML :

```json
"description": {
  "fr-FR": "Consultation d’un module HTML ILIAS dans un cours",
  "en-US": "Consultation of an ILIAS HTML learning module in a course"
}
```

Exemple attendu pour le cours parent :

```json
"description": {
  "fr-FR": "Cours parent ILIAS",
  "en-US": "Parent ILIAS course"
}
```

## Test 14 — Objet hors cours

Créer ou consulter un objet directement dans une catégorie ou dans un contexte hors cours.

Vérification utile :

```sql
SELECT id, component, event_name, obj_type, ref_id, obj_id, request_uri, created_at
FROM evnt_evhk_itxeb_log
ORDER BY id DESC
LIMIT 50;
```

Résultat attendu :

```text
événement brut éventuellement présent dans evnt_evhk_itxeb_log
aucune ligne correspondante dans evnt_evhk_itxeb_out
aucun envoi vers TRAX
```

## Test 15 — Absence de pollution root / crs

Noter le dernier identifiant outbox avant les tests, puis remplacer `<ID_DE_DEPART>` :

```sql
SELECT id, event_log_id, event_type, obj_type, ref_id, obj_id,
       user_id, status, created_at, sent_at
FROM evnt_evhk_itxeb_out
WHERE id > <ID_DE_DEPART>
  AND obj_type IN ('root', 'crs')
ORDER BY id DESC;
```

Résultat attendu :

```text
Empty set
```

## Test 16 — Envoi TRAX

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

## Test 17 — Authentification TRAX invalide

Configurer volontairement un mauvais secret TRAX, puis envoyer l'outbox.

Résultat attendu :

```text
status = failed
last_error contient une erreur HTTP ou réseau
retry_count augmente
```

Reconfigurer ensuite le bon client xAPI TRAX et relancer l'envoi.

## Test 18 — Requêtes de synthèse V0.7

Répartition par type :

```sql
SELECT event_type, obj_type, verb_id, status, COUNT(*) AS nb
FROM evnt_evhk_itxeb_out
GROUP BY event_type, obj_type, verb_id, status
ORDER BY event_type, obj_type, verb_id, status;
```

Configuration par cours :

```sql
SELECT course_ref_id, course_obj_id, enabled, updated_at, updated_by
FROM evnt_evhk_itxeb_ccfg
ORDER BY updated_at DESC;
```

Configuration par ressource :

```sql
SELECT course_ref_id, ref_id, obj_id, obj_type, enabled, updated_at, updated_by
FROM evnt_evhk_itxeb_rcfg
ORDER BY course_ref_id, ref_id;
```

Dernières erreurs :

```sql
SELECT id, event_type, obj_type, status, retry_count, last_attempt_at, last_error
FROM evnt_evhk_itxeb_out
WHERE status = 'failed' OR last_error <> ''
ORDER BY updated_at DESC
LIMIT 20;
```
