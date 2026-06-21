# Objectif 03 — Envoi manuel vers TRAX

## But

Valider l'envoi réel vers TRAX 3 avant d'ajouter un cron automatique.

## Fonctionnalités

- Configuration TRAX dans l'écran plugin.
- Test de connexion via `GET /statements?limit=1`.
- Envoi manuel via `POST /statements`.
- Envoi par batch.
- Mise à jour des statuts outbox.

## Statuts outbox

```text
generated : statement généré localement, jamais envoyé
sending   : envoi en cours
sent      : envoyé avec succès HTTP 2xx
failed    : échec HTTP, réseau, authentification ou payload
```

## Requête xAPI utilisée

```http
POST <endpoint-xapi>/statements
Authorization: Basic <client:secret>
Content-Type: application/json
Accept: application/json
X-Experience-API-Version: 1.0.3
```

Payload :

```json
[
  {
    "actor": {},
    "verb": {},
    "object": {}
  }
]
```

## Test recommandé

1. Vider l'outbox.
2. Faire un test ILIAS et télécharger un fichier.
3. Vérifier que des statements apparaissent au statut `generated`.
4. Renseigner la configuration TRAX.
5. Cliquer sur `Tester connexion TRAX`.
6. Cliquer sur `Envoyer les statements générés vers TRAX`.
7. Vérifier que les lignes passent à `sent`.
