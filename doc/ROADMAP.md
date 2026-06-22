# Roadmap — IliasTraxEventBridge

## Version stable actuelle

La version stable actuelle est **v0.5.5**.

La V0.5.5 clôture la série V0.5 avec :

- envoi manuel vers TRAX ;
- envoi automatique par job cron ILIAS ;
- retry configurable ;
- reset des statements en échec ;
- diagnostics TRAX, envoi manuel et cron ;
- affichage amélioré des tableaux d'administration ;
- filtre cours pour éviter l'envoi de traces hors contexte cours ;
- consultations d'objets suivies via `read_event` ;
- nettoyage des traces génériques `crs` et `root`.

## Cible v0.5 — périmètre cours et activation par cours

La V0.5 empêche l'envoi de traces hors contexte cours et prépare le contrôle à l'administrateur du cours.

### Règle de périmètre

Les traces xAPI ne doivent concerner que les objets contenus dans un objet **cours**.

Conséquences attendues :

- un test placé directement dans une catégorie ne doit pas produire de statement xAPI envoyé vers TRAX ;
- un fichier placé directement dans une catégorie ne doit pas produire de statement xAPI envoyé vers TRAX ;
- seuls les objets dont le chemin parent contient un cours ILIAS doivent être éligibles ;
- le journal brut peut continuer à enregistrer les événements, mais la génération outbox doit être bloquée si aucun cours parent n'est trouvé.

### Activation par administrateur du cours

Ce point est reporté après la stabilisation V0.5.5 :

- activer ou désactiver l'envoi xAPI vers TRAX pour un cours ;
- choisir les types d'objets autorisés pour l'envoi de traces ;
- appliquer ces choix avant la création du statement dans l'outbox.

### Types d'objets couverts

La V0.5.5 couvre les tests, les fichiers et les consultations d'objets via `read_event` :

- blog ;
- forum ;
- lien web ;
- mediacast ;
- wiki ;
- module web ;
- module SCORM.

## Cible v0.6 — enrichissement xAPI et exploitation

La V0.6 portera sur l'amélioration qualitative des statements et sur les outils d'exploitation.

Objectifs :

1. Améliorer les verbes xAPI selon les types d'événements ILIAS.
2. Générer des statements plus riches pour cours, tests, fichiers, modules CMI/xAPI et autres objets couverts.
3. Ajouter des filtres dans la configuration globale du plugin.
4. Ajouter une page de diagnostic TRAX.
5. Ajouter une purge configurable des anciens événements et de l'outbox.
