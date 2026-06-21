# Branches du dépôt

Le dépôt est organisé avec une branche par version.

| Branche | Description |
|---|---|
| `main` | Dernière version documentée |
| `v0.1.0` | Squelette initial et journal debug |
| `v0.1.1` | Correction signature `handleEvent` ILIAS 10 |
| `v0.1.2` | Correction configuration `ilCtrl` |
| `v0.1.3` | Correction `includeClass()` |
| `v0.1.4` | Payload, classification, récupération `ref_id` |
| `v0.1.5` | Amélioration affichage |
| `v0.2.0` | Outbox xAPI locale |
| `v0.2.1` | Nettoyage mapping / exclusion actions admin |
| `v0.3.0` | Envoi manuel vers TRAX |
| `v0.3.1` | Diagnostic visible du test connexion TRAX |

## Commandes utiles

Changer de version :

```bash
git checkout v0.2.1
```

Revenir à la dernière version :

```bash
git checkout main
```

Voir l’historique :

```bash
git log --oneline --decorate --graph --all
```
