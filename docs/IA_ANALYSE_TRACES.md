# Analyse IA des traces xAPI — état V0.21.2

## 1. Statut

L'analyse IA n'est plus seulement un cadrage futur : elle est disponible dans la version stable courante V0.21.2 promue dans `main`.

Elle reste optionnelle et désactivable.

## 2. Objectif

L'objectif est d'aider un formateur, un pilote de cours ou un administrateur de cours à comprendre plus rapidement l'activité xAPI.

L'IA produit une aide à l'interprétation : synthèse pédagogique, signaux faibles, ressources à surveiller et recommandations d'amélioration.

L'IA ne décide pas à la place du formateur.

## 3. Principe général

```text
TRAX/LRS et outbox locale question
  ↓ lecture / agrégation
IliasTraxEventBridge
  ↓ anonymisation / limitation
Payload IA agrégé
  ↓ appel IA optionnel
Synthèse pédagogique affichée dans ILIAS
  ↓ historisation locale si succès
Historique des analyses IA
```

## 4. Activation optionnelle

L'analyse IA doit être activée explicitement dans la configuration du plugin.

Paramètres principaux :

| Paramètre | Description |
|---|---|
| Activer l'analyse IA | Active ou désactive toute la fonctionnalité IA. |
| Fournisseur IA | Fournisseur ou service IA interne. |
| URL API IA | Endpoint HTTP de l'API IA. |
| Clé API IA | Clé d'authentification, masquée en interface. |
| Modèle IA | Nom du modèle utilisé. |
| Timeout IA | Timeout des requêtes IA. |
| Mode anonymisation | `strict`, `pseudonymized` ou `none`. |
| Limite de traces IA | Nombre maximum d'éléments agrégés utilisés pour une analyse. |

La clé API ne doit jamais être réaffichée en clair.

## 5. Données envoyées à l'IA en V0.21.2

L'IA reçoit un payload agrégé. Les statements bruts complets ne sont pas envoyés si ce n'est pas nécessaire.

Sections principales :

- informations de cours ;
- période analysée ;
- indicateurs globaux ;
- indicateurs pédagogiques déterministes ;
- activité par jour ;
- répartition des verbes ;
- analyse des ressources ;
- agrégat de risques apprenants anonymisé ;
- `question_failure_analysis`.

## 6. Questions problématiques dans l'IA

Depuis V0.21.2, le payload contient :

```text
question_failure_analysis
```

Cette section contient uniquement les questions problématiques, pas toutes les questions du test.

Champs transmis :

| Champ | Description |
|---|---|
| `question_id` | Identifiant technique de question. |
| `question_title` | Titre de la question. |
| `test_title` | Test concerné. |
| `ref_id` | Référence ILIAS du test. |
| `attempts` | Nombre de réponses prises en compte. |
| `failed` | Nombre d'échecs. |
| `unanswered` | Nombre de non-réponses. |
| `failure_rate` | Taux d'échec / non-réponse. |
| `avg_score` | Score moyen. |
| `risk_label` | `Critique` ou `À surveiller`. |
| `risk_reason` | Explication synthétique du risque. |

## 7. Données interdites ou à éviter

Ne pas envoyer :

- mots de passe ;
- secrets TRAX ;
- clés API ;
- jetons techniques ;
- données personnelles directes si non nécessaires ;
- noms complets des apprenants si l'anonymisation est activée ;
- logs serveur bruts ;
- données hors périmètre du cours ;
- statements bruts complets si les agrégats suffisent.

## 8. Classes concernées

```text
classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php
classes/class.ilIliasTraxEventBridgeAiClient.php
classes/class.ilIliasTraxEventBridgeAiAnalysisHistory.php
classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php
```

## 9. Contrôles techniques

```bash
php -l classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php
php -l classes/class.ilIliasTraxEventBridgeAiClient.php
php -l classes/class.ilIliasTraxEventBridgeAiAnalysisHistory.php
php -l classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php

grep -n "question_failure_analysis\|buildQuestionFailureAnalysis\|filterQuestionRisks" \
classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php
```

## 10. Documents liés

| Document | Rôle |
|---|---|
| [`FONCTIONNEL_0.21.2.md`](FONCTIONNEL_0.21.2.md) | Fonctionnement formateur. |
| [`TECHNIQUE_0.21.2.md`](TECHNIQUE_0.21.2.md) | Architecture technique. |
| [`GUIDE_DEVELOPPEUR_0.21.2.md`](GUIDE_DEVELOPPEUR_0.21.2.md) | Classes, tables et flux. |
| [`VALIDATION_0.21.2.md`](VALIDATION_0.21.2.md) | Validation de l'intégration IA. |
