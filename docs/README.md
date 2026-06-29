# Documentation — IliasTraxEventBridge

Ce dossier regroupe toute la documentation de la version stable **V0.10.1** du plugin `IliasTraxEventBridge`.

## Version documentée

| Élément | Valeur |
|---|---|
| Version stable | `0.10.1` |
| Branche stable | `main` |
| Tag stable | `v0.10.1` |
| Plugin principal | `IliasTraxEventBridge` |
| Plugin compagnon | `IliasTraxEventBridgeCourseUI` |
| Source pédagogique du suivi xAPI | TRAX/LRS |
| Rôle de l'outbox locale | File technique d'envoi uniquement |

## Lecture rapide

| Besoin | Document à lire |
|---|---|
| Installer le plugin sur ILIAS | [`INSTALLATION.md`](INSTALLATION.md) |
| Comprendre ce que fait le plugin | [`FONCTIONNEL.md`](FONCTIONNEL.md) |
| Comprendre l'architecture technique | [`TECHNIQUE.md`](TECHNIQUE.md) |
| Exploiter et dépanner en production | [`EXPLOITATION.md`](EXPLOITATION.md) |
| Développer ou modifier le plugin | [`DEVELOPPEUR.md`](DEVELOPPEUR.md) |
| Préparer la suite du projet | [`ROADMAP.md`](ROADMAP.md) |
| Cadrer l'analyse IA des traces | [`IA_ANALYSE_TRACES.md`](IA_ANALYSE_TRACES.md) |
| Lire la note de release stable | [`RELEASE_0.10.1.md`](RELEASE_0.10.1.md) |
| Comprendre la lecture directe TRAX/LRS | [`V0.10_LRS_DIRECT_READ.md`](V0.10_LRS_DIRECT_READ.md) |
| Voir la checklist de validation | [`FINAL_RELEASE_CHECKLIST_0.10.1.md`](FINAL_RELEASE_CHECKLIST_0.10.1.md) |

## Documents principaux

### Installation

[`INSTALLATION.md`](INSTALLATION.md) décrit :

- les prérequis ILIAS ;
- les prérequis TRAX/LRS ;
- l'installation depuis `main` ;
- l'installation depuis le tag `v0.10.1` ;
- l'installation du plugin compagnon UIHook ;
- la reconstruction ILIAS ;
- les contrôles post-installation ;
- les cas de dépannage.

### Documentation fonctionnelle

[`FONCTIONNEL.md`](FONCTIONNEL.md) décrit :

- l'objectif du plugin ;
- les profils utilisateurs concernés ;
- le parcours d'activation d'un cours ;
- les vues `Tableau de bord`, `Analyse`, `Expert`, `Configuration` ;
- les objets ILIAS couverts ;
- les limites fonctionnelles.

### Documentation technique

[`TECHNIQUE.md`](TECHNIQUE.md) décrit :

- l'architecture EventHook / UIHook ;
- les classes principales ;
- les tables SQL ;
- le flux d'envoi xAPI ;
- le flux de lecture TRAX/LRS ;
- la pagination LRS ;
- l'export PDF ;
- les règles de robustesse.

### Exploitation

[`EXPLOITATION.md`](EXPLOITATION.md) décrit :

- la supervision de l'outbox ;
- le cron ILIAS ;
- les requêtes SQL utiles ;
- les purges ;
- les contrôles TRAX/LRS ;
- les incidents fréquents et leur diagnostic.

### Développeur

[`DEVELOPPEUR.md`](DEVELOPPEUR.md) décrit :

- la structure du dépôt ;
- la gestion des versions ;
- les règles de migration SQL ILIAS ;
- les conventions de développement ;
- l'ajout de nouveaux événements ;
- l'ajout de nouveaux indicateurs ;
- les contrôles avant commit et release.

## Roadmap et IA

La roadmap est dans [`ROADMAP.md`](ROADMAP.md).

Elle prévoit notamment un axe futur d'analyse des traces xAPI par IA :

- configuration d'une clé API IA dans l'administration du plugin ;
- choix d'un fournisseur IA ;
- anonymisation / pseudonymisation avant envoi ;
- génération de synthèses pédagogiques ;
- détection de ressources problématiques ;
- aide à l'identification des apprenants en difficulté ;
- recommandations d'amélioration de cours ;
- gouvernance et audit des appels IA.

Le cadrage détaillé est dans [`IA_ANALYSE_TRACES.md`](IA_ANALYSE_TRACES.md).

## Décision d'architecture V0.10.1

```text
Outbox locale = file technique d'envoi
TRAX/LRS      = source officielle du suivi xAPI pédagogique
```

L'outbox locale peut être purgée. Les vues pédagogiques ne doivent donc pas dépendre de cette table locale.

## Images

Les captures d'écran sont stockées dans :

```text
docs/images/
```

Images disponibles :

- `suivi_xapi_configuration.png` ;
- `suivi_xapi_tableau_bord.png` ;
- `suivi_xapi_analyse.png` ;
- `suivi_xapi_expert.png`.

## Fichiers de release

| Fichier | Rôle |
|---|---|
| [`RELEASE_0.10.0.md`](RELEASE_0.10.0.md) | Note de release initiale V0.10.0. |
| [`RELEASE_0.10.1.md`](RELEASE_0.10.1.md) | Note de release corrective stable V0.10.1. |
| [`RELEASE_TAG_COMMANDS_0.10.1.md`](RELEASE_TAG_COMMANDS_0.10.1.md) | Commandes de tag V0.10.1. |
| [`STABLE_0.10.1.md`](STABLE_0.10.1.md) | Marqueur documentaire de stabilisation V0.10.1. |
| [`FINAL_RELEASE_CHECKLIST_0.10.1.md`](FINAL_RELEASE_CHECKLIST_0.10.1.md) | Checklist finale de validation. |

## Commandes de contrôle rapides

```bash
grep -n '\$version' plugin.php
head -5 sql/dbupdate.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

Résultat attendu :

```text
$version = '0.10.1';
<#1>
<?php
aucune erreur PHP
```
