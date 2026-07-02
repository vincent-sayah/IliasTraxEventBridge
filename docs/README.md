# Documentation — IliasTraxEventBridge

Ce dossier regroupe toute la documentation du plugin `IliasTraxEventBridge`.

## Version stable

| Élément | Valeur |
|---|---|
| Version stable sur `main` | `0.12.0` |
| Branche stable | `main` |
| Dernier tag publié | `v0.11.0` |
| Tag V0.12 | `v0.12.0` à créer après validation finale de la promotion |
| Ancienne stable | `0.11.0` |
| Ancienne branche de développement V0.12 | `v0.12-dashboard-pedagogique` |
| Objectif V0.12 | Dashboard pédagogique, analyse enrichie et export CSV pédagogique |
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
| Diagnostiquer une installation | [`DIAGNOSTIC.md`](DIAGNOSTIC.md) |
| Préparer un retour arrière | [`ROLLBACK.md`](ROLLBACK.md) |
| Cadrer la V0.12 | [`V0.12_DASHBOARD_PEDAGOGIQUE.md`](V0.12_DASHBOARD_PEDAGOGIQUE.md) |
| Utiliser le dashboard pédagogique V0.12 | [`V0.12_GUIDE_UTILISATION.md`](V0.12_GUIDE_UTILISATION.md) |
| Valider la V0.12 | [`VALIDATION_0.12.md`](VALIDATION_0.12.md) |
| Lire la note de release V0.11 historique | [`RELEASE_0.11.0.md`](RELEASE_0.11.0.md) |
| Valider la V0.11 historique | [`VALIDATION_0.11.md`](VALIDATION_0.11.md) |
| Développer ou modifier le plugin | [`DEVELOPPEUR.md`](DEVELOPPEUR.md) |
| Préparer la suite du projet | [`ROADMAP.md`](ROADMAP.md) |
| Cadrer l'analyse IA des traces | [`IA_ANALYSE_TRACES.md`](IA_ANALYSE_TRACES.md) |
| Comprendre la lecture directe TRAX/LRS | [`V0.10_LRS_DIRECT_READ.md`](V0.10_LRS_DIRECT_READ.md) |
| Voir la checklist de validation V0.10.1 | [`FINAL_RELEASE_CHECKLIST_0.10.1.md`](FINAL_RELEASE_CHECKLIST_0.10.1.md) |

## Documents V0.12

### Cadrage V0.12

[`V0.12_DASHBOARD_PEDAGOGIQUE.md`](V0.12_DASHBOARD_PEDAGOGIQUE.md) décrit :

- le périmètre de la V0.12 ;
- les objectifs pédagogiques ;
- les indicateurs attendus ;
- les critères d'acceptation ;
- les hors périmètre, notamment l'analyse IA.

### Guide d’utilisation V0.12

[`V0.12_GUIDE_UTILISATION.md`](V0.12_GUIDE_UTILISATION.md) décrit :

- le tableau de bord pédagogique ;
- la synthèse pédagogique ;
- les statuts `OK`, `À surveiller`, `Critique`, `Désactivée` ;
- l'onglet Analyse enrichi ;
- le bloc `Apprenants en difficulté` ;
- l'export CSV Expert enrichi ;
- les colonnes pédagogiques ajoutées au CSV.

### Validation V0.12

[`VALIDATION_0.12.md`](VALIDATION_0.12.md) décrit :

- la mise à jour du plugin sur VM ILIAS ;
- l'installation complète du compagnon UI ;
- les contrôles fichiers installés ;
- le rebuild ILIAS ;
- la validation du tableau de bord ;
- la validation de l'analyse ;
- la validation de l'export PDF ;
- la validation de l'export CSV Expert enrichi ;
- la validation des filtres ;
- la conservation des diagnostics V0.11 ;
- les critères d'acceptation finale.

## Documents V0.11 historiques

### Cadrage V0.11

[`V0.11_DIAGNOSTIC_EXPLOITATION.md`](V0.11_DIAGNOSTIC_EXPLOITATION.md) décrit :

- le périmètre de la V0.11 ;
- les objectifs d'exploitation ;
- les contrôles attendus ;
- les critères d'acceptation ;
- le lien avec la future analyse IA.

### Diagnostic

[`DIAGNOSTIC.md`](DIAGNOSTIC.md) décrit :

- les chemins attendus du plugin principal et du plugin compagnon ;
- les commandes de contrôle serveur ;
- les contrôles SQL ;
- l'analyse de l'outbox ;
- la vérification du cron ;
- la vérification TRAX/LRS ;
- les tests lecture et écriture TRAX/LRS ;
- les symptômes fréquents.

### Rollback

[`ROLLBACK.md`](ROLLBACK.md) décrit :

- les sauvegardes avant mise à jour ;
- le rollback par Git ;
- le rollback depuis archive `tar.gz` ;
- le rollback du plugin compagnon ;
- les précautions SQL ;
- les contrôles après retour arrière.

### Validation V0.11

[`VALIDATION_0.11.md`](VALIDATION_0.11.md) décrit :

- les commandes à lancer côté Git Bash ;
- les commandes à lancer côté VM ILIAS ;
- le rebuild ILIAS ;
- les contrôles de la page `Santé / Diagnostic V0.11` ;
- le test de connexion TRAX ;
- le test de lecture TRAX/LRS ;
- le test d'écriture TRAX/LRS ;
- les critères d'acceptation de la V0.11.

### Release V0.11.0

[`RELEASE_0.11.0.md`](RELEASE_0.11.0.md) récapitule :

- la version stable historique `0.11.0` ;
- les nouveautés de diagnostic ;
- les validations attendues ;
- les points de vigilance.

## Documents principaux

### Installation

[`INSTALLATION.md`](INSTALLATION.md) décrit :

- les prérequis ILIAS ;
- les prérequis TRAX/LRS ;
- l'installation depuis `main` ;
- l'installation depuis un tag stable ;
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
