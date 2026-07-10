# Release V0.22.4 — Tableau de bord compact et présentation type ILIAS

## Statut

| Élément | Valeur |
|---|---|
| Version plugin principal | `0.22.4-dev` |
| Version plugin compagnon UI | `0.8.10` |
| Branche de développement | `v0.22-dashboard-activity-timeline` |
| Branche promue | `main` |
| Commit de gel fonctionnel | `b4fdf9a` |
| Compatibilité ILIAS | ILIAS 10.x |

## Objectif

La V0.22.4 améliore la lisibilité du pilotage pédagogique sans modifier la logique xAPI validée en V0.21.2.

Elle répond à deux besoins :

1. éviter que la liste `Activité par jour` occupe une grande partie de la page ;
2. rapprocher les écrans Tableau de bord / Analyse / Analyse IA / Configuration d'une présentation ILIAS classique avec intitulé à gauche et données à droite.

## Nouveautés validées

### Activité dans le temps

Le bloc `Activité par jour` est remplacé par :

```text
Activité dans le temps
```

Affichages disponibles :

```text
7 jours | 14 jours | 30 jours | Par semaine | Détail complet
```

Le détail complet est repliable afin de ne pas saturer la page.

### Présentation type formulaire ILIAS

Les blocs principaux sont présentés selon le modèle :

```text
Titre / fonctionnalité à gauche
Données / formulaire / tableau à droite
```

Ce principe est appliqué aux onglets :

- Tableau de bord ;
- Analyse ;
- Analyse IA ;
- Configuration.

### Synthèse pédagogique alignée

La `Synthèse pédagogique` est alignée comme les autres blocs :

```text
Synthèse pédagogique     cartes et constats à droite
```

### Correction Analyse IA

Après retrait d'une analyse IA historisée :

```text
Retirer > confirmer > valider
```

l'utilisateur reste sur l'onglet :

```text
Analyse IA
```

et non plus sur l'onglet `Tableau de bord`.

## Règle métier conservée

```text
TRAX = toutes les questions de test ILIAS sont tracées.
Tableau de bord / Analyse = questions problématiques uniquement.
Analyse IA = questions problématiques uniquement.
Expert = vision technique complète.
```

## Fichiers principaux modifiés

```text
plugin.php
companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
```

## Scripts de développement conservés

```text
scripts/apply_v022_activity_timeline.php
scripts/apply_v0221_ilias_like_dashboard_layout.php
scripts/apply_v0223_layout_fixes_ai_tab.php
scripts/apply_v0224_alignment_and_ai_archive_tab.php
```

Ces scripts documentent l'historique de construction de la V0.22.4. La version promue dans `main` contient le résultat final validé.

## Validation réalisée

- `Activité dans le temps` validé.
- Sélecteur d'affichage validé.
- Présentation type ILIAS validée.
- Synthèse pédagogique alignée dans Tableau de bord et Analyse.
- Analyse IA : retrait d'une analyse historisée avec onglet IA actif validé.
- Serveur `ilias10`, poste Windows et GitHub réalignés.
