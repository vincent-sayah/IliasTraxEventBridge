# Changelog — IliasTraxEventBridge

Toutes les évolutions notables du plugin sont listées ici.

## v0.21.2 — version stable courante promue dans main

### Statut

- Branche stable : `main`.
- Commit de gel fonctionnel : `fad4c28` — `Freeze V0.21.2 validated implementation`.
- Version plugin principal : `0.21.2-dev`.
- Version plugin compagnon UI : `0.8.5`.
- Type : version fonctionnelle validée et promue.
- Compatibilité : ILIAS 10.x.

### Objectif

La V0.21.2 finalise le pilotage pédagogique xAPI avec suivi des tests ILIAS question par question et intégration des questions problématiques dans les vues formateur et l'Analyse IA.

### Règle métier validée

```text
TRAX = toutes les questions de test ILIAS sont tracées.
Tableau de bord / Analyse = seules les questions problématiques sont remontées.
Analyse IA = seules les questions problématiques sont intégrées au payload IA.
Expert = vision technique complète.
```

### Ajouts principaux

- Génération de plusieurs statements depuis un même événement ILIAS de test.
- Ajout de `classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php`.
- Lecture des résultats question dans les tables ILIAS `tst_tests`, `tst_active`, `tst_test_result`, `qpl_questions`.
- Ajout de `classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php`.
- Calcul des questions à fort taux d'échec depuis les statements question disponibles dans l'outbox locale.
- Ajout du bloc `Questions à fort taux d’échec` dans `Tableau de bord` et `Analyse`.
- Ajout de `question_failure_analysis` dans le payload de l'Analyse IA.
- Mise à jour du companion UI en `0.8.5`.
- Documentation V0.21.2 complète : release, fonctionnel, technique, exploitation, validation, guide développeur.
- Correction du script compagnon pour supporter les installations ILIAS hors `/var/www/ilias` via `ILIAS_ROOT`.
- Correction de `docs/INSTALLATION.md` : installation depuis `main` et non plus depuis l'ancienne branche `v0.10-lrs-direct-read`.

### Validation

- Génération de statements par question : OK.
- Statements contenant `question_id` : OK.
- Correction `source_event = test_question_result` : OK.
- Bloc `Questions à fort taux d’échec` visible : OK.
- Intégration Analyse IA : OK.
- `php -l` sur les classes critiques : OK.
- Serveur `ilias10`, poste Windows et GitHub `main` réalignés : OK.

## v0.16 — consolidation post V0.15.2

### Statut

- Branche concernée : `v0.13-ai-xapi-analysis`.
- Base : `0.15.2-dev` validée.
- Type : consolidation documentaire et nettoyage technique.
- Source fonctionnelle du suivi xAPI : TRAX/LRS.
- Rôle de l'outbox locale : file technique d'envoi uniquement.

### Objectif

La V0.16 consolide l'état validé de la V0.15.2 afin de repartir sur une base propre pour les évolutions suivantes.

### Changements réalisés

- Suppression du script temporaire `scripts/patch_v0151_ai_screen_markdown_history_pdf.php`.
- Suppression du script temporaire `scripts/patch_v0152_ai_screen_template_and_live.php`.
- Ajout de la note de release `docs/RELEASE_0.15.2.md`.
- Mise à jour du `README.md` pour rendre visibles les fonctionnalités IA validées.
- Mise à jour du présent `CHANGELOG.md`.

### Suite préparée

- Consultation détaillée d'une analyse IA historisée.
- Suppression contrôlée d'une analyse IA historisée.
- Comparaison de deux analyses IA.
- Documentation plus explicite du déploiement du plugin companion UI.

## v0.15.2-dev — analyse IA formateur validée

### Statut

- Branche concernée : `v0.13-ai-xapi-analysis`.
- Version plugin principal : `0.15.2-dev`.
- Version companion UI : `0.4.2`.
- Type : évolution fonctionnelle IA et consolidation d'écran cours.
- Source fonctionnelle du suivi xAPI : TRAX/LRS.
- Rôle de l'outbox locale : file technique d'envoi uniquement.

### Objectif

La V0.15.2 rend l'analyse IA exploitable directement par le formateur dans l'onglet `Analyse` du suivi xAPI cours.

Elle transforme le retour IA brut en page lisible, conserve un historique local des analyses et enrichit l'export PDF avec la dernière analyse IA historisée.

### Ajouts réalisés

- Ajout de `classes/class.ilIliasTraxEventBridgeAiAnalysisHistory.php`.
- Ajout du stockage runtime local `var/ai_analysis_history`.
- Ajout de `/var/` dans `.gitignore`.
- Mise à jour de l'écran `Analyse` en page `Analyse formateur`.
- Ajout du rendu Markdown/HTML de la réponse IA.
- Ajout du bloc `Historique des analyses IA`.
- Sauvegarde automatique des analyses IA réussies.
- Intégration de la dernière analyse IA historisée dans l'export PDF.
- Passage du plugin principal en `0.15.2-dev`.
- Passage du companion UI en `0.4.2`.

### Validation

- Analyse IA formatée : OK.
- Historique IA visible : OK.
- Fichiers JSON d'historique générés : OK.
- Export PDF avec analyse IA historisée : OK.
- PHP lint sur les fichiers principaux : OK.
- Plugin principal, poste Windows et serveur ILIAS réalignés sur GitHub : OK.

## v0.12.1 — consolidation technique du compagnon UI

Voir les documents historiques V0.12.1.

## v0.12.0 — enrichissement pédagogique du tableau de bord

Voir les documents historiques V0.12.

## v0.11.0 — diagnostic et durcissement exploitation

Voir les documents historiques V0.11.

## Documentation main — README, docs et roadmap IA

La documentation de référence est désormais la documentation V0.21.2.
