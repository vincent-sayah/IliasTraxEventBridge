# Exploitation — IliasTraxEventBridge v0.8.0

Ce document regroupe les contrôles et procédures d'exploitation utiles pour suivre l'outbox xAPI locale, les envois LRS/TRAX, la configuration par cours/ressource et le diagnostic V0.8 des traces refusées.

## 1. Écran admin

Dans ILIAS :

```text
Administration > Plugins > IliasTraxEventBridge > Configurer
```

La page V0.8 affiche :

- l'état de configuration du plugin ;
- les derniers diagnostics de test connexion / envoi manuel / cron ;
- les compteurs outbox : total, 24h, 7j, `sent`, `generated`, `failed`, retry épuisé ;
- les répartitions sur les dernières lignes outbox : statuts, types d'événements, types d'objets, familles xAPI, interactions, sources techniques ;
- les dernières clés de diagnostic outbox ;
- les dernières erreurs outbox ;
- la section `Diagnostic des traces refusées V0.8` ;
- les derniers événements ILIAS reçus.

## 2. Configuration cours / ressource

Écran recommandé depuis l'objet cours :

```text
Cours > Paramètres > Suivi xAPI
```

Règle d'exploitation :

```text
statement xAPI autorisé = cours activé ET ressource activée
```

Contrôle SQL :

```sql
SELECT course_ref_id, course_obj_id, enabled, updated_at, updated_by
FROM evnt_evhk_itxeb_ccfg
ORDER BY updated_at DESC;

SELECT course_ref_id, ref_id, obj_id, obj_type, enabled, updated_at, updated_by
FROM evnt_evhk_itxeb_rcfg
ORDER BY course_ref_id, ref_id;
```

## 3. Contrôle rapide de l'outbox

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

## 4. Volumes par période

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

## 5. Éléments outbox à surveiller

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

Depuis l'écran admin, le bouton `Réinitialiser les failed` remet les lignes `failed` en `generated`, remet `retry_count` à 0 et permet un nouvel envoi.

## 6. Cron ILIAS

Job à activer :

```text
IliasTraxEventBridge — envoi outbox vers TRAX
```

Identifiant technique :

```text
itxeb_send_outbox_to_trax
```

Exécution manuelle utile en test :

```bash
cd /var/www/ilias
sudo -u apache php cron/cron.php run itxeb_send_outbox_to_trax
```

Le cron :

1. traite les consultations `read_event` ;
2. applique la règle cours/ressource ;
3. insère les statements autorisés dans l'outbox ;
4. journalise les refus uniquement si le diagnostic est activé ;
5. envoie l'outbox vers le LRS configuré.

## 7. Diagnostic des traces refusées V0.8

Option admin :

```text
Activer le diagnostic des traces refusées
```

Règle d'exploitation recommandée :

```text
laisser désactivé en exploitation courante
activer uniquement pendant une phase de diagnostic ciblée
purger après analyse
```

Contrôle rapide :

```sql
SELECT COUNT(*) AS total
FROM evnt_evhk_itxeb_dlog;

SELECT reason, COUNT(*) AS total
FROM evnt_evhk_itxeb_dlog
GROUP BY reason
ORDER BY total DESC, reason ASC;
```

Derniers refus :

```sql
SELECT id, created_at, reason, event_type, user_id, course_ref_id,
       ref_id, obj_id, obj_type, source_table, source_id
FROM evnt_evhk_itxeb_dlog
ORDER BY id DESC
LIMIT 50;
```

Motifs principaux :

```text
not_in_course
missing_course_context
missing_resource_context
course_not_configured
course_disabled
resource_not_configured
resource_disabled
unsupported_object_type
```

## 8. Purge du diagnostic des refus

Depuis l'écran admin :

```text
Diagnostic des traces refusées V0.8 > Purger le diagnostic des traces refusées
```

Contrôle :

```sql
SELECT COUNT(*) AS total
FROM evnt_evhk_itxeb_dlog;
```

Après purge :

```text
total = 0
```

Purge SQL équivalente si nécessaire :

```sql
DELETE FROM evnt_evhk_itxeb_dlog;
```

À utiliser avec prudence et uniquement si l'action admin n'est pas disponible.

## 9. Diagnostic xAPI dans l'outbox

Les statements contiennent des extensions de diagnostic dans `context.extensions` :

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

## 10. Corrélation read_event

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

Table anti-doublon plugin :

```sql
SELECT *
FROM evnt_evhk_itxeb_read
ORDER BY processed_at DESC
LIMIT 20;
```

## 11. Corrélation EventHook

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

## 12. Purge prudente de l'outbox

Ne purger que les lignes `sent`, et conserver une fenêtre suffisante pour le diagnostic.

Exemple : conserver les 30 derniers jours de lignes envoyées :

```sql
DELETE FROM evnt_evhk_itxeb_out
WHERE status = 'sent'
  AND created_ts < UNIX_TIMESTAMP() - (30 * 86400);
```

Ne pas purger les lignes `failed` tant qu'elles n'ont pas été analysées.

## 13. Contrôle anti-parasites root/crs

```sql
SELECT id, event_type, obj_type, ref_id, obj_id, user_id, status, created_at, sent_at
FROM evnt_evhk_itxeb_out
WHERE obj_type IN ('root', 'crs')
ORDER BY id DESC
LIMIT 20;
```

Résultat attendu : aucune nouvelle ligne parasite `root` ou `crs`.

## 14. Contrôle packaging companion

Dans le plugin principal :

```bash
find companion/IliasTraxEventBridgeCourseUI -name "*.php" -print
```

Résultat attendu : aucune ligne.

Dans le slot UIHook actif :

```bash
find /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI -name "*.php" -print
```

Résultat attendu : fichiers PHP actifs du companion.
