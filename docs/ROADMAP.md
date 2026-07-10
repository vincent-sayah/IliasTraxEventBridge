# Roadmap — IliasTraxEventBridge

Cette roadmap décrit les évolutions possibles après la version stable courante **V0.22.4** promue dans `main`.

Elle n'est pas un engagement de livraison. Elle sert à cadrer les priorités fonctionnelles, techniques et pédagogiques du projet.

## État actuel — V0.22.4 stable dans main

Fonctions disponibles :

- captation d'événements ILIAS via EventHook ;
- génération de statements xAPI ;
- outbox locale technique ;
- envoi vers TRAX/LRS ;
- activation du suivi par cours et par ressource ;
- écran `Pilotage xAPI` dans le cours ;
- tableau de bord pédagogique ;
- bloc `Activité dans le temps` compact ;
- choix de vue d'activité : 7 jours, 14 jours, 30 jours, par semaine, détail complet ;
- présentation type formulaire ILIAS avec titre à gauche et données à droite ;
- analyse formateur ;
- analyse IA optionnelle ;
- historique local des analyses IA ;
- comparaison de deux analyses IA ;
- retrait contrôlé d'une analyse IA historisée ;
- correction de l'onglet actif après retrait IA ;
- vue Expert ;
- export CSV ;
- export PDF ;
- diagnostic TRAX/LRS ;
- suivi des tests ILIAS question par question ;
- bloc `Questions à fort taux d’échec` ;
- intégration des questions problématiques dans le payload IA.

Décision d'architecture maintenue :

```text
Outbox locale = file technique d'envoi.
TRAX/LRS = cible xAPI et source principale du suivi pédagogique.
Exception validée = calcul des questions problématiques depuis les statements question présents dans l'outbox locale.
```

## V0.23 — Consolidation post-promotion V0.22.4

### Objectif

Stabiliser la version promue dans `main` et réduire la dette des scripts historiques.

### Pistes

- Supprimer ou archiver les scripts de patch devenus historiques.
- Consolider les scripts V0.22 en un script unique idempotent.
- Ajouter un script d'audit unique V0.22.4.
- Ajouter une page de diagnostic dédiée aux traces de questions.
- Ajouter un contrôle automatique du companion installé.
- Ajouter une commande de validation serveur unique.
- Vérifier l'idempotence complète de l'installation du companion avec `ILIAS_ROOT` personnalisé.

## V0.24 — Amélioration du suivi des questions

### Objectif

Rendre le diagnostic des questions plus exploitable pour le formateur.

### Pistes

- Ajouter une vue détaillée d'une question problématique.
- Ajouter l'évolution du taux d'échec dans le temps.
- Ajouter un filtre par test dans le bloc questions.
- Ajouter une exportation CSV des questions problématiques.
- Ajouter des seuils configurables : `failure_rate`, `avg_score`, criticité.
- Ajouter un lien direct vers le test ILIAS concerné.

## V0.25 — Durcissement IA

### Objectif

Renforcer la gouvernance de l'Analyse IA.

### Pistes

- Ajouter une prévisualisation du payload anonymisé avant appel IA.
- Ajouter un journal technique des appels IA sans contenu sensible.
- Ajouter des politiques d'anonymisation plus explicites.
- Ajouter des seuils de volume minimum avant recommandation IA.
- Ajouter une mention visible des limites de l'analyse.

## V0.26 — Exploitation et supervision

### Objectif

Faciliter le maintien en condition opérationnelle.

### Pistes

- Dashboard administrateur de santé plugin.
- Contrôle des tables `evnt_evhk_itxeb_*`.
- Contrôle de la présence du companion UIHook.
- Contrôle du cron ILIAS.
- Contrôle de l'envoi TRAX/LRS.
- Contrôle du nombre de statements question générés.
- Commande d'export diagnostic anonymisé.

## Points de vigilance permanents

- Ne pas bloquer la navigation ILIAS si le plugin rencontre une erreur.
- Préserver l'opt-in cours/ressource.
- Ne pas exposer les clés API IA, secrets TRAX ou mots de passe.
- Ne pas envoyer de données nominatives apprenant à l'IA en mode strict.
- Conserver une séparation claire entre vues pédagogiques et diagnostic Expert.
- Maintenir la compatibilité ILIAS 10.
