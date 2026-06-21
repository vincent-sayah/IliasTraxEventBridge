# IliasTraxEventBridge v0.3.1

Plugin EventHook ILIAS 10 pour observer les événements ILIAS, générer des statements xAPI et les envoyer manuellement vers TRAX 3.

## Objectif V0.3.1

Cette version ajoute :

- configuration TRAX / xAPI depuis l'écran du plugin ;
- test de connexion TRAX ;
- client HTTP xAPI ;
- envoi manuel des statements de l'outbox locale ;
- statuts outbox : `generated`, `sending`, `sent`, `failed`.

Il n'y a pas encore de cron automatique en V0.3.

## Événements actuellement transformés

- téléchargement de fichier :
  `components/ILIAS/ILIASObject:update` avec `obj_type=file` et `cmd=sendfile`
- début de test :
  `components/ILIAS/Tracking:updateStatus` avec `cmd=startTest` -> `attempted`
- fin de test réussie :
  `components/ILIAS/Tracking:updateStatus` avec `status=2` ou `percentage=100` -> `passed`
- fin de test échouée :
  `components/ILIAS/Tracking:updateStatus` avec `status=3` -> `failed`

Les actions d'administration du test comme `delete_results` restent dans le journal debug mais ne sont pas générées dans l'outbox.

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

## Configuration TRAX

Dans la configuration du plugin, renseigner :

- Endpoint xAPI TRAX ;
- Identifiant client TRAX ;
- Secret client TRAX ;
- Version xAPI, par défaut `1.0.3` ;
- Timeout HTTP ;
- Taille du batch manuel.

L'endpoint peut être :

```text
https://trax.example.com/.../xapi
```

ou directement :

```text
https://trax.example.com/.../xapi/statements
```

Le plugin ajoute `/statements` si nécessaire.

## Vérification SQL

```sql
SELECT id, created_at, event_log_id, event_type, verb_id, user_id, ref_id, obj_id, obj_type, status, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC;
```

Voir le dernier statement :

```sql
SELECT statement_json
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 1;
```

## Envoi manuel

Depuis l'écran de configuration :

```text
Envoyer les statements générés vers TRAX
```

Le plugin envoie les statements au statut :

```text
generated
failed
```

Les statements `sent` ne sont pas renvoyés.


## Correctif 0.3.1

Le bouton **Tester connexion TRAX** enregistre désormais systématiquement son résultat dans les settings du plugin et l'écran affiche un bloc **Derniers diagnostics TRAX**.

Vérification SQL :

```sql
SELECT keyword, value
FROM settings
WHERE module = 'itxeb'
AND keyword LIKE 'last_trax_%'
ORDER BY keyword;
```
