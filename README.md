# IliasTraxEventBridge V0.1

Plugin ILIAS 10 de type `Services/EventHandling/EventHook`.

## But de cette version

Cette première version sert uniquement à valider les événements réellement émis par ILIAS 10 lors de la navigation dans les cours, objets et tests.

Elle ne pousse pas encore de statements xAPI vers TRAX 3.

## Installation

Chemin standard :

```bash
Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

Chemin possible sur certaines installations ILIAS 10 :

```bash
public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

Dans ILIAS :

1. Administration > Plugins
2. Update
3. Activate
4. Refresh Languages
5. Configure

## Table créée

```text
evnt_evhk_itxeb_log
```

## Commande SQL de contrôle

```sql
SELECT id, created_at, component, event_name, user_id, ref_id, obj_id, obj_type, param_keys
FROM evnt_evhk_itxeb_log
ORDER BY id DESC
LIMIT 100;
```

## Étape suivante

Après observation des événements réels :

1. choisir les événements utiles ;
2. créer le mapping ILIAS -> xAPI ;
3. ajouter l'outbox ;
4. connecter TRAX 3.
