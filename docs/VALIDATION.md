# Plan de validation — IliasTraxEventBridge v0.6

Version de développement actuelle : **v0.6.0**.

Ce plan valide la branche V0.6 : enrichissement xAPI, familles de statements, métriques `read_event`, diagnostics outbox, wording bilingue, envoi TRAX et absence de traces parasites `root` / `crs`.

La branche stable reste `main` / `v0.5` tant que la V0.6 n'est pas promue.

## Test 1 — État Git et version plugin

Dans le dossier du plugin :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git status --short
git branch --show-current
git log --oneline --decorate -5
grep -n '\$version' plugin.php
```

Résultat attendu :

```text
git status --short : vide
branche courante : v0.6
plugin.php : 0.6.0
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
SHOW TABLES LIKE 'read_event';
```

Résultat attendu : les quatre tables existent.

## Test 4 — Génération des traces dans un cours

Dans un cours ILIAS, effectuer les actions suivantes avec un utilisateur apprenant :

- télécharger un fichier ;
- terminer ou échouer un test ;
- consulter un blog ;
- consulter un wiki ;
- ouvrir un lien web ;
- consulter un mediacast ;
- consulter un module HTML ;
- consulter un forum, module d'apprentissage ou SCORM si disponibles.

Puis exécuter le cron ILIAS du plugin :

```text
itxeb_send_outbox_to_trax
```

Vérification outbox :

```sql
SELECT id, event_type, obj_type, verb_id, status, retry_count, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 30;
```

Résultat attendu :

```text
file -> file_downloaded          -> downloaded -> sent
tst  -> test_tracking_status     -> attempted / passed / failed -> sent
blog -> repository_object_access -> read -> sent
wiki -> repository_object_access -> read -> sent
htlm -> repository_object_access -> read -> sent
webr -> repository_object_access -> visited -> sent
mcst -> repository_object_access -> viewed -> sent
frm  -> repository_object_access -> interacted -> sent
lm   -> repository_object_access -> read -> sent
sahs -> repository_object_access -> launched -> sent
```

## Test 5 — Consultation réelle via read_event

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

Résultat attendu : `last_access` et `read_count` correspondent à la consultation traitée.

## Test 6 — Enrichissement xAPI de base

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

Résultat attendu : l'objet et le cours parent sont identifiables par titre et URL.

## Test 7 — Familles de statements et verbes V0.6

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

## Test 8 — Métriques read_event et durée xAPI

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

Exemple attendu :

```text
spent_seconds = 130
result.duration = PT130S
```

## Test 9 — Diagnostics outbox dans le JSON xAPI

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

## Test 10 — Wording bilingue

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

## Test 11 — Objet hors cours

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

## Test 12 — Absence de pollution root / crs

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

## Test 13 — Envoi TRAX

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

## Test 14 — Authentification TRAX invalide

Configurer volontairement un mauvais secret TRAX, puis envoyer l'outbox.

Résultat attendu :

```text
status = failed
last_error contient une erreur HTTP ou réseau
retry_count augmente
```

Reconfigurer ensuite le bon client xAPI TRAX et relancer l'envoi.

## Test 15 — Requêtes de synthèse V0.6

Répartition par type :

```sql
SELECT event_type, obj_type, verb_id, status, COUNT(*) AS nb
FROM evnt_evhk_itxeb_out
GROUP BY event_type, obj_type, verb_id, status
ORDER BY event_type, obj_type, verb_id, status;
```

Dernières erreurs :

```sql
SELECT id, event_type, obj_type, status, retry_count, last_attempt_at, last_error
FROM evnt_evhk_itxeb_out
WHERE status <> 'sent'
ORDER BY id DESC
LIMIT 20;
```

Dernières traces `read_event` traitées :

```sql
SELECT *
FROM evnt_evhk_itxeb_read
ORDER BY processed_at DESC
LIMIT 20;
```

## Critère de validation globale V0.6

La V0.6 est validée si :

- le serveur et Windows sont bien sur la branche `v0.6` ;
- le plugin est en version `0.6.0` ;
- les objets dans un cours produisent des statements enrichis ;
- les objets hors cours ne produisent pas d'outbox xAPI ;
- les consultations réelles sont générées depuis `read_event` au passage cron ;
- les fichiers et tests issus des EventHooks restent générés ;
- les familles `statement_family`, `interaction_type` et `repository_object_family` sont présentes ;
- les diagnostics `outbox_id`, `statement_uuid`, `source_table` et `deduplication_key` sont présents ;
- les descriptions `fr-FR` et `en-US` sont distinctes ;
- les statements sont envoyés vers TRAX ;
- aucune nouvelle trace parasite `root` ou `crs` n'est produite.
