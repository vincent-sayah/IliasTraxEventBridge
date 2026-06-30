# Changelog — IliasTraxEventBridge

Toutes les évolutions notables du plugin sont listées ici.

## v0.11.0 — diagnostic et durcissement exploitation

### Statut

- Branche concernée : `v0.11-diagnostic-exploitation`.
- Base : `main` / `v0.10.1`.
- Version cible envisagée : `0.11.0`.
- Type : durcissement exploitation, diagnostic et rollback.
- Source fonctionnelle du suivi xAPI : TRAX/LRS.
- Rôle de l'outbox locale : file technique d'envoi uniquement.

### Objectif

La V0.11 prépare une version plus robuste pour l'exploitation : installation plus contrôlable, diagnostic plus simple, procédures de retour arrière documentées, et préparation d'une future page de santé du plugin.

### Ajouts documentaires

- `docs/V0.11_DIAGNOSTIC_EXPLOITATION.md` : cadrage V0.11.
- `docs/DIAGNOSTIC.md` : procédure de diagnostic exploitation.
- `docs/ROLLBACK.md` : procédure de retour arrière.
- `docs/README.md` mis à jour pour rendre les nouveaux documents visibles.

### Pistes techniques V0.11

- Ajouter `scripts/diagnostic_itxeb.sh`.
- Ajouter une page ou section `Santé / Diagnostic` dans l'administration du plugin.
- Vérifier la présence du plugin compagnon UIHook.
- Vérifier les tables SQL `evnt_evhk_itxeb_*`.
- Vérifier la configuration TRAX/LRS.
- Vérifier l'état de l'outbox.
- Vérifier le cron ILIAS.
- Préparer les tests de lecture et d'écriture TRAX/LRS.

## Documentation main — README, docs et roadmap IA

### Statut

- Branche concernée : `main`.
- Version plugin inchangée : `0.10.1`.
- Type : mise à jour documentaire post-promotion.

### Corrections documentaires

- Mise à jour du `README.md` racine pour indiquer que la branche stable officielle est maintenant `main`.
- Ajout d'un index documentaire dans `docs/README.md`.
- Mise à jour de `docs/ROADMAP.md`, qui contenait encore une roadmap obsolète V0.5.5.
- Ajout de `docs/IA_ANALYSE_TRACES.md` pour cadrer l'analyse future des traces xAPI par IA avec clé API IA configurable.
- Mise à jour de `companion/IliasTraxEventBridgeCourseUI/README.md` pour refléter la V0.10.1 et l'accès `Cours > Suivi xAPI`.

### Roadmap ajoutée

La roadmap couvre désormais :

- V0.11 : durcissement exploitation et packaging ;
- V0.12 : enrichissement pédagogique du tableau de bord ;
- V0.13 : analyse IA optionnelle des traces xAPI ;
- V0.14 : historisation durable et gouvernance ;
- V0.15 : connecteurs et interopérabilité.

## v0.10.1 — release stable corrective et documentation complète

### Statut

- Branche de développement concernée : `v0.10-lrs-direct-read`.
- Branche stable officielle après promotion : `main`.
- Tag stable : `v0.10.1`.
- Version plugin principal : `0.10.1`.
- Compatibilité : ILIAS 10.x.
- Type : version stable corrective après V0.10.0.
- Source fonctionnelle du suivi xAPI : TRAX/LRS.
- Rôle de l'outbox locale : file technique d'envoi uniquement.

### Correction principale

- Correction du fichier `sql/dbupdate.php` pour ajouter le marqueur d'étape ILIAS `<#1>` au début du fichier.
- Correction destinée à éviter l'erreur d'installation de type `Undefined array key ...` observée lors de l'installation du plugin après désinstallation d'une ancienne version.
- Stabilisation du scénario d'installation depuis zéro et du scénario de mise à jour depuis une ancienne V0.6/V0.9.

### Documentation ajoutée / mise à jour

- `README.md` transformé en page d'accueil stable V0.10.1.
- `docs/INSTALLATION.md` ajouté.
- `docs/FONCTIONNEL.md` ajouté.
- `docs/TECHNIQUE.md` ajouté.
- `docs/EXPLOITATION.md` ajouté.
- `docs/DEVELOPPEUR.md` ajouté.
- `docs/RELEASE_0.10.1.md` ajouté.
- `docs/V0.10_LRS_DIRECT_READ.md` mis en cohérence avec la V0.10.1.

### Contrôles attendus

```bash
grep -n '\$version' plugin.php
head -5 sql/dbupdate.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

Résultats attendus :

```text
$version = '0.10.1';
<#1>
<?php
aucune erreur PHP
```

## v0.10.0 — suivi xAPI alimenté par TRAX/LRS

### Statut

- Branche concernée : `v0.10-lrs-direct-read`.
- Base : V0.9.1 feedback dashboard.
- Version plugin principal : `0.10.0`.
- Source fonctionnelle du suivi xAPI : TRAX/LRS.
- Rôle de l'outbox locale : file technique d'envoi uniquement.
- Statut : validée fonctionnellement avant tag `v0.10.0`.

### Décision d'architecture

La V0.10.0 stabilise la séparation suivante :

```text
Outbox locale = file technique d'envoi
TRAX/LRS      = source officielle du suivi xAPI pédagogique
```

L'outbox locale `evnt_evhk_itxeb_out` reste utilisée pour générer, envoyer, rejouer et superviser les statements xAPI. Elle n'est plus utilisée comme source fonctionnelle des vues pédagogiques, car elle peut être purgée en exploitation.

### Nouveautés principales

- Lecture directe des statements depuis TRAX/LRS via `GET /statements`.
- Tableau de bord alimenté par TRAX/LRS.
- Analyse des ressources alimentée par TRAX/LRS.
- Vue Expert alimentée par TRAX/LRS.
- Export CSV Expert alimenté par TRAX/LRS.
- Comparaison entre périodes calculée depuis TRAX/LRS.
- Export PDF du tableau de bord basé sur TRAX/LRS.
