# Objectif 02 — Outbox xAPI locale

## But

Valider le mapping ILIAS 10 vers xAPI sans envoyer encore de données à TRAX.

## Événements transformés

### Fichier téléchargé

Déclencheur :

```text
component = components/ILIAS/ILIASObject
event     = update
obj_type  = file
URI       contient cmd=sendfile
```

Statement généré :

```text
verb = http://adlnet.gov/expapi/verbs/experienced
event_type = file_downloaded
```

### Progression / test

Déclencheur :

```text
component = components/ILIAS/Tracking
event     = updateStatus
```

Statement généré :

```text
obj_type=tst + status=2 ou percentage=100 -> passed
obj_type=tst + status=3                   -> failed
obj_type=tst autre statut                 -> attempted
autre objet status=2 ou percentage=100    -> completed
autre objet autre statut                  -> progressed
```

## Tests attendus

1. Vider le journal debug et l'outbox.
2. Télécharger un fichier.
3. Terminer un test.
4. Vérifier dans la configuration du plugin :
   - apparition d'événements dans le journal debug ;
   - apparition de statements dans l'outbox locale.
5. Vérifier en SQL :

```sql
SELECT id, event_log_id, event_type, verb_id, user_id, ref_id, obj_id, obj_type, status
FROM evnt_evhk_itxeb_out
ORDER BY id DESC;
```
