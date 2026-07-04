# Validation V0.13 — analyse IA optionnelle des traces xAPI

## Objectif

Valider que la V0.13 introduit une analyse IA optionnelle, désactivée par défaut, sans casser les fonctionnalités V0.12.1.

## Référence de version

```text
Branche : v0.13-ai-xapi-analysis
Version : 0.13.0-dev
```

## Validation déjà réalisée

```text
86a4854 Add V0.13 AI configuration UI
5369f7f Add V0.13 AI connection test client
db0e00e Persist V0.13 AI test diagnostics
```

## Points validés

- Le formulaire de configuration IA est visible dans la configuration du plugin.
- L’analyse IA est désactivée par défaut.
- Le test IA affiche un diagnostic persistant dans la page de configuration.
- Aucun statement xAPI réel n’est envoyé pendant le test de connexion IA.
- La valeur de la clé IA n’est pas stockée dans Git.

## Prochaine validation

- Configurer l’URL API IA et le modèle.
- Fournir la clé côté serveur via variable d’environnement.
- Activer l’analyse IA.
- Cliquer sur Tester configuration IA.
- Vérifier la ligne Dernier test IA dans Diagnostics TRAX / cron.
