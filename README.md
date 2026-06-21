# IliasTraxEventBridge v0.2.0

Plugin EventHook ILIAS 10 pour observer les événements ILIAS et générer localement des statements xAPI.

## Objectif V0.2

Cette version ne contacte pas encore TRAX.

Elle ajoute une table outbox locale contenant les statements xAPI générés à partir des événements ILIAS fiables déjà observés :

- téléchargement de fichier :
  `components/ILIAS/ILIASObject:update` avec `obj_type=file` et `cmd=sendfile`
- progression / test :
  `components/ILIAS/Tracking:updateStatus`

## Tables

- `evnt_evhk_itxeb_log` : journal brut des événements ILIAS reçus.
- `evnt_evhk_itxeb_out` : outbox locale des statements xAPI générés.

## Installation / mise à jour

Copier le dossier dans :

```bash
public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

Puis exécuter depuis la racine ILIAS :

```bash
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
```

Ensuite dans ILIAS :

```text
Administration > Plugins > Update
```

L'étape SQL #2 crée la table `evnt_evhk_itxeb_out`.

## Vérification SQL

```sql
SELECT id, created_at, component, event_name, user_id, ref_id, obj_id, obj_type
FROM evnt_evhk_itxeb_log
ORDER BY id DESC
LIMIT 20;
```

```sql
SELECT id, created_at, event_log_id, event_type, verb_id, user_id, ref_id, obj_id, obj_type, status
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 20;
```

Pour voir un statement :

```sql
SELECT statement_json
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 1;
```

## Notes de mapping V0.2

- `file_downloaded` génère un statement `experienced`.
- `test_tracking_status` génère :
  - `passed` si `status = 2` ou `percentage >= 100`,
  - `failed` si `status = 3`,
  - `attempted` sinon.
- `learning_tracking_status` génère :
  - `completed` si `status = 2` ou `percentage >= 100`,
  - `progressed` sinon.

Ces règles sont volontairement simples et devront être confirmées sur tes données ILIAS 10 avant l'envoi vers TRAX.
