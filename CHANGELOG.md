# Changelog — IliasTraxEventBridge

Toutes les évolutions notables du plugin sont listées ici.

## v0.23.8 — MediaCast validé pour promotion dans main

### Statut

- Branche stable cible : `main`.
- Branche de développement : `v0.23-mediacast-media-tracking`.
- Commit de gel fonctionnel : `630ad8e` — `V0.23.8 validate MediaCast analysis view and external titles`.
- Version plugin principal : `0.23.8-dev`.
- Version plugin compagnon UI : `0.8.19`.
- Type : version fonctionnelle validée, prête pour promotion.
- Compatibilité : ILIAS 10.x.

### Objectif

La V0.23.8 ajoute le suivi MediaCast au pilotage xAPI du cours : ouverture de MediaCast, lancement de vidéo interne, ouverture de média externe et affichage formateur des médias vus dans l'onglet Analyse.

### Ajouts et corrections principales

- Détection des objets MediaCast `mcst`.
- Génération de statements xAPI lors du lancement d'une vidéo interne MediaCast.
- Génération de statements xAPI lors de l'ouverture d'un média externe MediaCast, par exemple YouTube/Vimeo.
- Déduplication des beacons MediaCast côté client et côté serveur.
- Ajout des verbes `played-media` et `opened-external-media`.
- Ajout des extensions xAPI MediaCast : `media_title`, `media_provider`, `media_mime`, `media_url`, `media_client_event`, `mediacast_ref_id`, `mediacast_obj_id`.
- Lecture des traces MediaCast depuis TRAX/LRS pour les vues pédagogiques.
- Ajout du bloc `Médias MediaCast vus` dans l'onglet Analyse uniquement.
- Retrait du bloc MediaCast de l'onglet Tableau de bord.
- Affichage du titre réel d'un média externe lorsque le titre est fourni par la playlist MediaCast ILIAS.
- Mise à jour du companion UI en `0.8.19`.
- Mise à jour du plugin principal en `0.23.8-dev`.

### Règle métier conservée

```text
TRAX = toutes les questions de test ILIAS sont tracées.
Tableau de bord / Analyse = seules les questions problématiques sont remontées.
Analyse IA = seules les questions problématiques sont intégrées au payload IA.
Expert = vision technique complète.
Analyse = détail MediaCast des vidéos internes lues et médias externes ouverts.
```

### Validation

- Ouverture MediaCast visible dans Expert : OK.
- Vidéo interne `vid5.mp4` tracée avec `played-media` : OK.
- Média externe YouTube tracé avec `opened-external-media` : OK.
- Envoi outbox vers TRAX/LRS avec statut `sent` : OK.
- Traces visibles dans Expert : OK.
- Bloc `Médias MediaCast vus` visible uniquement dans Analyse : OK.
- Titre réel du média externe affiché après nouvelle trace : OK.
- Serveur `ilias10`, poste Windows et GitHub réalignés sur la branche V0.23 : OK.

## v0.22.4 — version stable précédente promue dans main

### Statut

- Branche stable : `main`.
- Branche de développement : `v0.22-dashboard-activity-timeline`.
- Commit de gel fonctionnel : `b4fdf9a` — `V0.22.4 validate dashboard layout and AI tab fixes`.
- Version plugin principal : `0.22.4-dev`.
- Version plugin compagnon UI : `0.8.10`.
- Type : version fonctionnelle validée et promue.
- Compatibilité : ILIAS 10.x.

### Objectif

La V0.22.4 améliore l'ergonomie du tableau de bord, de l'analyse et de l'analyse IA sans changer la règle métier xAPI validée en V0.21.2.

### Ajouts et corrections principales

- Remplacement de la liste longue `Activité par jour` par un bloc `Activité dans le temps` compact.
- Ajout du choix d'affichage : `7 jours`, `14 jours`, `30 jours`, `Par semaine`, `Détail complet`.
- Ajout d'une synthèse d'activité : périodes actives, périodes sans activité, pic, moyenne.
- Présentation des blocs de tableau de bord et d'analyse selon un modèle proche des formulaires ILIAS : libellé/fonctionnalité à gauche, données à droite.
- Correction de l'alignement de la `Synthèse pédagogique` dans Tableau de bord et Analyse.
- Correction de l'onglet actif après retrait d'une analyse IA historisée : l'utilisateur reste sur `Analyse IA`.
- Mise à jour du companion UI en `0.8.10`.
- Mise à jour du plugin principal en `0.22.4-dev`.
- Documentation V0.22 ajoutée et alignée avec `main`.

### Règle métier conservée

```text
TRAX = toutes les questions de test ILIAS sont tracées.
Tableau de bord / Analyse = seules les questions problématiques sont remontées.
Analyse IA = seules les questions problématiques sont intégrées au payload IA.
Expert = vision technique complète.
```

### Validation

- Bloc `Activité dans le temps` validé visuellement.
- Présentation type ILIAS validée dans Tableau de bord et Analyse.
- Synthèse pédagogique alignée titre/données validée.
- Analyse IA validée après retrait d'une analyse historisée.
- Serveur `ilias10`, poste Windows et GitHub réalignés : OK.

## v0.21.2 — version stable précédente promue dans main

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

Voir les documents historiques V0.15.2/V0.16.

## v0.15.2-dev — analyse IA formateur validée

Voir les documents historiques V0.15.2.

## v0.12.1 — consolidation technique du compagnon UI

Voir les documents historiques V0.12.1.

## v0.12.0 — enrichissement pédagogique du tableau de bord

Voir les documents historiques V0.12.

## v0.11.0 — diagnostic et durcissement exploitation

Voir les documents historiques V0.11.
