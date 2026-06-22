# Plan de validation — IliasTraxEventBridge v0.5.5

Version stable actuelle : **v0.5.5**.

Ce plan valide la version stable V0.5.5 : installation, génération xAPI, filtre cours, tracking `read_event`, cron TRAX et absence de traces parasites `root` / `crs`.

## Test 1 — Installation / mise à jour

Depuis la racine ILIAS :

```bash
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
```

Dans ILIAS :

```text
Administration > Plugins > EventHook > IliasTraxEventBridge > Installer / Mettre à jour > Activer > Configurer
```

Résultat attendu :

```text
écran de configuration accessible
plugin actif
version plugin.php = 0.5.5
```

## Test 2 — Tables attendues

```sql
SHOW TABLES LIKE 'evnt_evhk_itxeb_log';
SHOW TABLES LIKE 'evnt_evhk_itxeb_out';
SHOW TABLES LIKE 'evnt_evhk_itxeb_read';
SHOW TABLES LIKE 'read_event';
```

Résultat attendu : les quatre tables existent.

## Test 3 — Objet dans un cours

Créer ou consulter les objets suivants dans un cours :

- fichier ;
- test ;
- blog ;
- forum ;
- lien web ;
- mediacast ;
- wiki ;
- module HTML / module web / SCORM si disponibles.

Vérification outbox :

```sql
SELECT id, event_log_id, event_type, obj_type, ref_id, obj_id,
       user_id, status, created_at, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 30;
```

Résultat attendu :

```text
tst  -> test_tracking_status
file -> file_downloaded
blog -> repository_object_access
frm  -> repository_object_access
webr -> repository_object_access
mcst -> repository_object_access
wiki -> repository_object_access
```

## Test 4 — Objet hors cours

Créer ou consulter un objet directement dans une catégorie ou dans un contexte hors cours.

Résultat attendu :

```text
événement brut éventuellement présent dans evnt_evhk_itxeb_log
aucune ligne correspondante dans evnt_evhk_itxeb_out
aucun envoi vers TRAX
```

Requête utile :

```sql
SELECT id, component, event_name, obj_type, ref_id, obj_id, request_uri, created_at
FROM evnt_evhk_itxeb_log
ORDER BY id DESC
LIMIT 50;
```

## Test 5 — Tracking réel via read_event

Consulter un blog, un lien web, un mediacast ou un wiki avec un utilisateur apprenant.

Avant le passage cron, vérifier `read_event` :

```sql
SELECT obj_id, usr_id, last_access, read_count, spent_seconds, first_access
FROM read_event
ORDER BY last_access DESC
LIMIT 20;
```

Après le passage cron, vérifier la table anti-doublon :

```sql
SELECT *
FROM evnt_evhk_itxeb_read
ORDER BY processed_at DESC
LIMIT 20;
```

Résultat attendu :

```text
une ligne obj_id / usr_id est mémorisée
last_access et read_count correspondent à la consultation traitée
```

## Test 6 — Cron xAPI

Activer le job dans ILIAS :

```text
Administration > Paramètres système et maintenance > Tâches cron
```

Job :

```text
IliasTraxEventBridge — envoi outbox vers TRAX
```

Identifiant technique :

```text
itxeb_send_outbox_to_trax
```

Après exécution du cron :

```sql
SELECT id, event_type, obj_type, user_id, status, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 20;
```

Résultat attendu :

```text
status = sent
sent_at renseigné
last_error vide
```

## Test 7 — Absence de pollution root / crs

Après plusieurs consultations et passages cron :

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

## Test 8 — Authentification TRAX invalide

Configurer volontairement un mauvais secret TRAX, puis envoyer l'outbox.

Résultat attendu :

```text
status = failed
last_error contient une erreur HTTP ou réseau
retry_count augmente
```

## Test 9 — Authentification TRAX valide

Configurer le bon client xAPI TRAX, puis relancer l'envoi manuel ou le cron.

Résultat attendu :

```text
status = sent
sent_at renseigné
last_error vide
```

## Critère de validation globale

La V0.5.5 est validée si :

- les objets dans un cours produisent des statements utiles ;
- les objets hors cours ne produisent pas d'outbox xAPI ;
- les consultations réelles sont générées depuis `read_event` au passage cron ;
- les statements sont envoyés vers TRAX ;
- aucune nouvelle trace parasite `root` ou `crs` n'est produite.
