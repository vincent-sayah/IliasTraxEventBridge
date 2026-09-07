# Changelog — IliasTraxEventBridge

Toutes les évolutions notables du plugin sont listées ici.

## v0.25.6 — affichage login apprenant validé

### Statut

- Branche stable cible : `main`.
- Branche de développement : `v0.25-learner-identity-display`.
- Commit de gel fonctionnel : `8d97685` — `V0.25.6 validate learner login display`.
- Version plugin principal : `0.25.6-dev`.
- Version plugin compagnon UI : `0.8.44`.
- Type : version fonctionnelle validée, prête pour promotion.
- Compatibilité : ILIAS 10.x.

### Objectif

La V0.25.6 finalise l'affichage nominatif des apprenants dans les vues de pilotage du cours. Les formateurs voient le login ILIAS dans le tableau `Apprenants en difficulté` et dans une nouvelle colonne `Apprenant` de la vue Expert.

### Ajouts et corrections principales

- Ajout de `learner_identity` dans les lignes Expert lues depuis TRAX/LRS.
- Résolution de l'acteur xAPI `ilias-user-ID` vers le login ILIAS.
- Recherche du login via `ilObjUser::_lookupLogin()`, puis via `$DIC->database()`, puis via `$ilDB` et `usr_data.login`.
- Ajout d'un cache local de résolution des logins pendant la requête.
- Remplacement de l'ancien pseudonyme `Apprenant xxxxxxxx` dans `Analyse > Apprenants en difficulté`.
- Ajout de la colonne `Apprenant` entre `User ID` et `Verbe` dans la vue Expert.
- Ajout de la colonne `learner_identity` dans l'export CSV Expert.
- Conservation de `User ID` comme identifiant technique pseudonymisé.
- Mise à jour du compagnon UI en `0.8.44`.
- Mise à jour du plugin principal en `0.25.6-dev`.

### Périmètre inchangé

- Génération des statements xAPI inchangée.
- Outbox locale inchangée.
- Envoi TRAX/LRS inchangé.
- Règle d'activation cours/ressource inchangée.
- Tableau de bord V0.24.17 conservé.
- Suivi MediaCast V0.23.8 conservé.
- Analyse IA inchangée.
- Filtrage des questions problématiques inchangé.

### Règle métier conservée

```text
TRAX = toutes les questions de test ILIAS sont tracées.
Tableau de bord / Analyse = seules les questions problématiques sont remontées.
Analyse IA = seules les questions problématiques sont intégrées au payload IA.
Expert = vision technique complète.
Analyse = détail MediaCast des vidéos internes lues et médias externes ouverts.
Analyse / Expert = affichage du login ILIAS lorsque le login est résolu.
```

### Validation

- V0.25.5 appliquée avec préflight Python 3.6 : OK.
- Affichage initial de type `ilias-user-6` / `ilias-user-401` constaté : OK.
- Correctif V0.25.6 appliqué pour résoudre `ilias-user-ID` vers le login ILIAS : OK.
- `php -l` sur les fichiers critiques : OK.
- Redémarrage `php-fpm` et `httpd` : OK.
- Onglet Analyse fonctionnel avec affichage du login : OK.
- Onglet Expert fonctionnel avec colonne `Apprenant` : OK.
- Export CSV Expert enrichi avec `learner_identity` : OK.
- Bundle serveur importé puis poussé via Git Bash Windows : OK.

## v0.24.17 — tableau de bord pédagogique validé

### Statut

- Branche stable cible : `main`.
- Branche de développement : `v0.24-dashboard-synthesis-layout`.
- Commit de gel fonctionnel : `3a05d77` — `V0.24.17 validate dashboard synthesis and activity layout`.
- Version plugin principal : `0.24.17-dev`.
- Version plugin compagnon UI : `0.8.37`.
- Type : version fonctionnelle validée, prête pour promotion.
- Compatibilité : ILIAS 10.x.

### Objectif

La V0.24.17 améliore la lisibilité du `Tableau de bord` du pilotage xAPI : synthèse pédagogique regroupée, suppression des tuiles doublonnées, graphique d'activité linéaire et alignement visuel du bloc `Activité dans le temps` avec `Top ressources`.

### Ajouts et corrections principales

- Intégration des indicateurs pédagogiques dans le bloc `Synthèse pédagogique`.
- Suppression des doublons visuels des tuiles `ressources sans activité`, `critique` et `à surveiller`.
- Ajout d'icônes dans les cartes KPI.
- Filtrage du bloc `Questions à fort taux d’échec` selon le contexte : vue globale, tous types, type test ou ressource test.
- Masquage du bloc `Questions à fort taux d’échec` lorsqu'une ressource non-test est sélectionnée.
- Remplacement de l'ancien graphique horizontal d'activité par un graphique linéaire SVG.
- Conservation des modes d'affichage de l'activité : 7 jours, 14 jours, 30 jours, par semaine, détail complet.
- Alignement du titre `Activité dans le temps` avec les autres titres de sections ILIAS.
- Alignement de `Top ressources` sur la même ligne que la carte `Progression de l’activité`.
- Mise à jour du companion UI en `0.8.37`.
- Mise à jour du plugin principal en `0.24.17-dev`.

### Périmètre inchangé

- Génération des statements xAPI inchangée.
- Outbox locale inchangée.
- Envoi TRAX/LRS inchangé.
- Suivi MediaCast V0.23.8 conservé.
- Analyse IA inchangée hors présentation des données déjà disponibles.
- Vue Expert inchangée.
- Configuration inchangée.

### Règle métier conservée

```text
TRAX = toutes les questions de test ILIAS sont tracées.
Tableau de bord / Analyse = seules les questions problématiques sont remontées.
Analyse IA = seules les questions problématiques sont intégrées au payload IA.
Expert = vision technique complète.
Analyse = détail MediaCast des vidéos internes lues et médias externes ouverts.
```

### Validation

- Restauration depuis `main` puis réapplication des scripts sûrs : OK.
- `private $repository`, `__construct()` et `handle()` présents dans la classe écran : OK.
- `php -l` sur les fichiers critiques : OK.
- Redémarrage `php-fpm` et `httpd` : OK.
- Tableau de bord accessible dans le navigateur : OK.
- Graphique linéaire `Progression de l’activité` affiché : OK.
- `Top ressources` aligné avec la carte du graphique : OK.
- GitHub réaligné sur le commit validé `3a05d77` : OK.

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

Voir `docs/RELEASE_0.22.4.md`.

## v0.21.2 — version stable précédente promue dans main

Voir `docs/FONCTIONNEL_0.21.2.md`, `docs/TECHNIQUE_0.21.2.md`, `docs/GUIDE_DEVELOPPEUR_0.21.2.md` et `docs/EXPLOITATION_0.21.2.md`.

## v0.16 — consolidation post V0.15.2

Voir les documents historiques V0.15.2/V0.16.

## v0.15.2-dev — analyse IA formateur validée

Voir les documents historiques V0.15.2.

## v0.12.1 — consolidation technique du compagnon UI

Voir les documents historiques V0.12.1.
