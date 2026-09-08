# CHANGELOG

## v0.27.1 — tableau de bord pédagogique amélioré

### Statut

Version validée fonctionnellement sur serveur ILIAS 10 puis intégrée dans GitHub.

- Plugin principal : `0.27.1-dev`
- Plugin compagnon UI : `0.8.47`
- Branche de validation : `v0.27-dashboard-command-center-validated`
- Commit fonctionnel validé : `de90cb1`
- Promotion cible : `main`

### Changements fonctionnels

- Ajout d'un centre de décision pédagogique dans l'onglet `Tableau de bord`.
- Ajout d'une carte `État global du cours` avec statut stable, à surveiller ou critique.
- Ajout d'une jauge `Réussite du cours` avec icône diplôme 🎓.
- Ajout d'un entonnoir pédagogique : inscrits, actifs, tentatives, réussites.
- Ajout d'un bloc `Actions recommandées` pour prioriser les interventions formateur.
- Ajout d'une matrice ressources indiquant activité, réussite, signal pédagogique et dernière trace.
- Ajout d'un mode d'affichage du tableau de bord : `Compact`, `Standard`, `Complet`.
- Ajout des blocs V0.27 dans la personnalisation du tableau de bord.
- Reprise des actions recommandées et de la matrice ressources dans l'onglet `Analyse`.

### Changements hérités intégrés

- V0.26.1 : calcul du taux de réussite du cours depuis la progression ILIAS.
- V0.26.2 : icône diplôme 🎓 et configuration des cartes de `Synthèse pédagogique`.
- V0.25.6 : affichage du login ILIAS dans `Analyse` et `Expert`.

### Validation

- Script V0.27.1 exécuté avec préflight complet.
- Lint PHP OK sur les fichiers modifiés.
- Redémarrage `php-fpm` et `httpd` OK.
- Validation navigateur réalisée par l'utilisateur.

## v0.25.6 — affichage login apprenant validé

### Statut

Version validée fonctionnellement sur serveur ILIAS 10.

- Plugin principal : `0.25.6-dev`
- Plugin compagnon UI : `0.8.44`
- Branche : `v0.25-learner-identity-display`
- Commit fonctionnel validé : `8d97685`

### Changements

- Affichage du login ILIAS dans `Apprenants en difficulté`.
- Ajout de la colonne `Apprenant` dans la vue Expert entre `User ID` et `Verbe`.
- Ajout de `learner_identity` dans l'export CSV Expert.
- Résolution de `ilias-user-ID` vers `usr_data.login`.
- Conservation de `User ID` comme identifiant technique pseudonymisé.

## v0.24.17 — tableau de bord stabilisé

- Alignement du graphique d'activité et de `Top ressources`.
- Correction de régressions de rendu du tableau de bord.
- Conservation de la synthèse pédagogique enrichie.

## v0.23.8 — suivi MediaCast stabilisé

- Suivi des vidéos internes MediaCast lancées.
- Suivi des médias externes sélectionnés.
- Vue MediaCast dans l'onglet Analyse.

## Historique précédent

Les versions précédentes sont conservées dans les branches et documents historiques du dépôt.
