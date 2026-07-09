# Documentation fonctionnelle V0.21.2 — Pilotage pédagogique xAPI

## 1. Objectif fonctionnel

`IliasTraxEventBridge` permet à un formateur de piloter un cours ILIAS à partir des traces xAPI envoyées vers TRAX.

La V0.21.2 ajoute un suivi détaillé des questions de tests ILIAS : toutes les questions sont tracées dans TRAX, mais les vues pédagogiques ne remontent que les questions à fort taux d'échec.

## 2. Parcours utilisateur

### Accès depuis un cours

Dans un cours ILIAS, le formateur accède au suivi avec le bouton :

```text
Pilotage xAPI
```

L'écran affiche :

```text
Tableau de bord | Analyse | Analyse IA | Expert | Configuration | Retour contenu du cours
```

Le titre affiché est :

```text
Pilotage pédagogique — <nom du cours>
```

## 3. Tableau de bord

Le tableau de bord donne une synthèse rapide :

- volume de données d'apprentissage ;
- apprenants actifs ;
- ressources utilisées ;
- score moyen ;
- activité par jour ;
- actions principales ;
- ressources critiques ou à surveiller ;
- questions à fort taux d'échec.

### Bloc Questions à fort taux d'échec

Le bloc affiche uniquement les questions problématiques.

Critères d'affichage :

```text
taux d'échec / non-réponse >= 50 %
ou
score moyen < 50 %
```

Colonnes affichées :

| Colonne | Description |
|---|---|
| Priorité | `Critique` ou `À surveiller` |
| Question | Titre de la question et identifiant technique court |
| Test | Test ILIAS concerné |
| Réponses | Nombre de réponses prises en compte |
| Échecs / non-réponses | Nombre de réponses échouées ou non répondues |
| Taux d'échec | Pourcentage calculé |
| Score moyen | Score moyen de la question |
| Dernière trace | Date de dernière activité connue |

## 4. Analyse

L'onglet `Analyse` reste centré sur l'aide au formateur.

Il affiche :

- les ressources critiques ;
- les ressources à surveiller ;
- les ressources sans activité ;
- les questions à fort taux d'échec ;
- les signaux faibles pédagogiques.

Les libellés hors Expert sont volontairement simplifiés : l'utilisateur voit des termes pédagogiques plutôt que des termes techniques TRAX/xAPI.

## 5. Analyse IA

L'onglet `Analyse IA` est séparé de l'analyse classique.

Il permet :

- de générer une synthèse pédagogique ;
- de relire les analyses IA historisées ;
- de comparer deux analyses IA ;
- de retirer une analyse de l'historique visible.

### Données envoyées à l'IA

L'IA reçoit uniquement des données agrégées et anonymisées.

Pour les tests, l'IA reçoit uniquement :

```text
question_failure_analysis
```

Cette section contient les questions problématiques, pas toutes les questions.

## 6. Expert

L'onglet `Expert` conserve la vision technique complète.

Il permet de voir les traces détaillées retournées ou produites, les verbes xAPI, les identifiants techniques, les statuts d'envoi et les détails nécessaires au diagnostic.

## 7. Configuration

L'onglet `Configuration` permet :

- d'activer le suivi du cours ;
- d'activer ou désactiver les ressources ;
- de contrôler la source TRAX/LRS ;
- de superviser l'outbox technique ;
- de vérifier l'état d'envoi des statements.

## 8. Règles de confidentialité

- Aucun nom d'apprenant n'est nécessaire dans l'analyse pédagogique.
- Les acteurs xAPI sont pseudonymisés selon la configuration.
- L'analyse IA ne reçoit pas les traces brutes complètes.
- L'analyse IA ne reçoit pas tous les résultats de questions, seulement les agrégats problématiques.

## 9. Règle métier principale V0.21.2

```text
Toutes les questions sont tracées pour conserver la granularité xAPI.
Seules les questions problématiques sont affichées au formateur.
Seules les questions problématiques sont envoyées à l'IA.
```
