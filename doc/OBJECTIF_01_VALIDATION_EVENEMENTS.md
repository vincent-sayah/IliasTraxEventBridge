# Objectif 01 — Validation des événements ILIAS 10

Cette V0.1 du plugin ne communique pas encore avec TRAX.
Elle sert uniquement à écouter les événements ILIAS reçus par le slot EventHook et à les stocker dans la table `evnt_evhk_itxeb_log`.

## Installation

Depuis la racine ILIAS :

```bash
mkdir -p Customizing/global/plugins/Services/EventHandling/EventHook
cp -R IliasTraxEventBridge Customizing/global/plugins/Services/EventHandling/EventHook/
```

Sur certaines installations ILIAS 10, le répertoire `Customizing` est sous `public/` :

```bash
mkdir -p public/Customizing/global/plugins/Services/EventHandling/EventHook
cp -R IliasTraxEventBridge public/Customizing/global/plugins/Services/EventHandling/EventHook/
```

Ensuite, dans ILIAS :

1. Administration > Plugins
2. Chercher `IliasTraxEventBridge`
3. Action > Update
4. Action > Activate
5. Action > Refresh Languages
6. Action > Configure

## Test fonctionnel attendu

Avec le mode debug activé :

1. se connecter avec un utilisateur membre d'un cours ;
2. entrer dans un cours ;
3. ouvrir un fichier ou un objet du cours ;
4. ouvrir un test ;
5. démarrer une tentative ;
6. terminer la tentative ;
7. retourner dans l'administration du plugin ;
8. consulter les 100 derniers événements.

## Requête SQL utile

```sql
SELECT id, created_at, component, event_name, user_id, ref_id, obj_id, obj_type, param_keys
FROM evnt_evhk_itxeb_log
ORDER BY id DESC
LIMIT 100;
```

## Ce que l'on doit relever

Pour chaque action métier, relever :

- `component`
- `event_name`
- `param_keys`
- `ref_id`
- `obj_id`
- `obj_type`
- URL affichée dans `request_uri`

Ce relevé permettra de produire le mapping V0.2 : événements ILIAS 10 vers statements xAPI.
