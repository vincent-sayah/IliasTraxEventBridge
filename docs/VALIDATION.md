# Plan de validation

## Test 1 — Installation

```bash
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
```

Dans ILIAS :

```text
Administration > Plugins > Update > Activate > Configure
```

Résultat attendu : écran de configuration accessible.

## Test 2 — Journal brut

```sql
DELETE FROM evnt_evhk_itxeb_log;
DELETE FROM evnt_evhk_itxeb_out;
```

Actions :

1. démarrer un test ;
2. terminer un test ;
3. télécharger un fichier.

Vérification :

```sql
SELECT id, component, event_name, user_id, ref_id, obj_id, obj_type, request_uri
FROM evnt_evhk_itxeb_log
ORDER BY id DESC;
```

## Test 3 — Outbox xAPI

```sql
SELECT id, event_log_id, event_type, verb_id, user_id, ref_id, obj_id, obj_type, status
FROM evnt_evhk_itxeb_out
ORDER BY id DESC;
```

Résultat attendu :

```text
startTest  -> attempted
finishTest -> passed ou failed
sendfile   -> experienced
```

## Test 4 — Exclusion des actions admin

Supprimer les résultats d’un participant depuis l’administration du test.

Résultat attendu :

- événement visible dans `evnt_evhk_itxeb_log` ;
- aucun statement généré dans `evnt_evhk_itxeb_out`.

## Test 5 — Authentification TRAX invalide

Configurer volontairement un mauvais secret TRAX.

Résultat attendu :

```text
status = failed
last_error contient HTTP 401
```

## Test 6 — Authentification TRAX valide

Configurer le bon client xAPI TRAX.

Cliquer sur :

```text
Envoyer les statements générés vers TRAX
```

Résultat attendu :

```text
status = sent
sent_at renseigné
last_error vide
```
