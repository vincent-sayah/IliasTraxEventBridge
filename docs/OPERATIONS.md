# Exploitation — IliasTraxEventBridge v0.6

Ce document regroupe les contrôles et procédures d'exploitation utiles pour suivre l'outbox xAPI locale, les envois TRAX et les diagnostics V0.6.

## 1. Écran admin

Dans ILIAS :

```text
Administration > Plugins > IliasTraxEventBridge > Configurer
```

La section `Supervision V0.6` affiche :

- les compteurs d'exploitation : total outbox, créés 24h, créés 7j, sent, generated, failed, retry épuisé ;
- les compteurs sur les 200 dernières lignes : statuts, types d'événements SQL, types d'objets, familles xAPI, interactions, sources techniques ;
- les dernières clés de diagnostic ;
- les dernières erreurs.

Les compteurs de période utilisent la colonne `created_ts` de `evnt_evhk_itxeb_out`.

## 2. Contrôle rapide de l'état outbox

```sql
SELECT status, COUNT(*) AS total
FROM evnt_evhk_itxeb_out
GROUP BY status
ORDER BY total DESC;
```

```sql
SELECT id, event_type, obj_type, verb_id, status, retry_count, max_retry,
       created_at, sent_at, last_attempt_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 20;
```

## 3. Volumes par période

Dernières 24 heures :

```sql
SELECT status, COUNT(*) AS total
FROM evnt_evhk_itxeb_out
WHERE created_ts >= UNIX_TIMESTAMP() - 86400
GROUP BY status
ORDER BY total DESC;
```

Derniers 7 jours :

```sql
SELECT status, COUNT(*) AS total
FROM evnt_evhk_itxeb_out
WHERE created_ts >= UNIX_TIMESTAMP() - (7 * 86400)
GROUP BY status
ORDER BY total DESC;
```

## 4. Éléments à surveiller

### Statements en attente

```sql
SELECT id, event_type, obj_type, status, retry_count, max_retry, created_at
FROM evnt_evhk_itxeb_out
WHERE status = 'generated'
ORDER BY id ASC
LIMIT 50;
```

Si ce volume augmente, vérifier que le cron ILIAS `itxeb_send_outbox_to_trax` est actif et exécuté.

### Statements en erreur

```sql
SELECT id, event_type, obj_type, status, retry_count, max_retry,
       last_attempt_at, last_error
FROM evnt_evhk_itxeb_out
WHERE status = 'failed'
   OR last_error <> ''
ORDER BY id DESC
LIMIT 50;
```

### Retry épuisé

```sql
SELECT id, event_type, obj_type, status, retry_count, max_retry,
       last_attempt_at, last_error
FROM evnt_evhk_itxeb_out
WHERE status = 'failed'
  AND retry_count >= max_retry
ORDER BY id DESC;
```

## 5. Diagnostic xAPI V0.6

Les statements V0.6 contiennent des extensions de diagnostic dans `context.extensions` :

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

Pour inspecter un statement récent :

```sql
SELECT id, event_type, obj_type, status, statement_json
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 1\G
```

## 6. Corrélation read_event

Pour les consultations issues de `read_event`, `event_log_id = 0` et le JSON contient :

```text
event_record_source = read_event_tracker
source_table = read_event
deduplication_key = read_event:{obj_id}:{user_id}:{last_access}:{read_count}
```

Contrôle des dernières consultations ILIAS :

```sql
SELECT r.obj_id, r.usr_id, r.read_count, r.spent_seconds,
       r.first_access, r.last_access,
       od.type, od.title
FROM read_event r
JOIN object_data od ON od.obj_id = r.obj_id
ORDER BY r.last_access DESC
LIMIT 20;
```

## 7. Corrélation EventHook

Pour les événements issus du journal brut plugin, le JSON contient :

```text
event_record_source = event_hook_log
source_table = evnt_evhk_itxeb_log
deduplication_key = event_log:{event_log_id}
```

Requête de corrélation :

```sql
SELECT o.id AS outbox_id, o.event_log_id, o.event_type, o.obj_type,
       o.status, o.retry_count, l.component, l.event_name, l.request_uri
FROM evnt_evhk_itxeb_out o
LEFT JOIN evnt_evhk_itxeb_log l ON l.id = o.event_log_id
WHERE o.event_log_id > 0
ORDER BY o.id DESC
LIMIT 20;
```

## 8. Réinitialiser les failed

Depuis l'écran admin, utiliser le bouton :

```text
Réinitialiser les failed
```

Cela remet les lignes `failed` en `generated`, remet `retry_count` à 0 et permet un nouvel envoi.

## 9. Purge manuelle prudente

Ne purger que les lignes `sent`, et conserver une fenêtre suffisante pour le diagnostic.

Exemple : conserver les 30 derniers jours de lignes envoyées :

```sql
DELETE FROM evnt_evhk_itxeb_out
WHERE status = 'sent'
  AND created_ts < UNIX_TIMESTAMP() - (30 * 86400);
```

Ne pas purger les lignes `failed` tant qu'elles n'ont pas été analysées.

## 10. Contrôle anti-parasites root/crs

```sql
SELECT id, event_type, obj_type, ref_id, obj_id, user_id, status, created_at, sent_at
FROM evnt_evhk_itxeb_out
WHERE obj_type IN ('root', 'crs')
ORDER BY id DESC
LIMIT 20;
```

Résultat attendu après V0.5.5 / V0.6 : aucune nouvelle ligne parasite `root` ou `crs`.
