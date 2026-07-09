# Documentation technique V0.21.2 — Architecture et flux

## 1. Vue d'ensemble

```text
ILIAS 10
  ├─ EventHook IliasTraxEventBridge
  │  ├─ capte les événements ILIAS
  │  ├─ détecte les événements de test
  │  ├─ lit les résultats détaillés des questions
  │  ├─ génère les statements xAPI
  │  └─ écrit l'outbox locale
  │
  ├─ Cron ILIAS
  │  └─ envoie l'outbox vers TRAX/LRS
  │
  └─ UIHook IliasTraxEventBridgeCourseUI
     ├─ Tableau de bord
     ├─ Analyse
     ├─ Analyse IA
     ├─ Expert
     └─ Configuration

TRAX/LRS
  ├─ reçoit les statements globaux de test
  ├─ reçoit les statements question par question
  └─ sert de source xAPI cible
```

## 2. Flux test ILIAS

### 2.1 Événement ILIAS capté

Le plugin réagit notamment à :

```text
component = components/ILIAS/Tracking
event     = updateStatus
obj_type  = tst
```

### 2.2 Statement global du test

Un statement global est généré pour le test :

- progression ;
- réussite / échec ;
- score global ;
- activité test ILIAS.

### 2.3 Statements par question

En V0.21.1, le routeur peut produire plusieurs statements pour un seul événement ILIAS.

Principe :

```text
1 événement updateStatus de test
  -> 1 statement global test
  -> N statements question
```

Méthodes concernées :

```text
classes/class.ilIliasTraxEventBridgeEventRouter.php
classes/class.ilIliasTraxEventBridgeStatementFactory.php
```

## 3. Extraction des questions

Classe :

```text
classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php
```

Tables ILIAS utilisées :

| Table | Rôle |
|---|---|
| `tst_tests` | Relie l'objet ILIAS test à l'identifiant interne de test. |
| `tst_active` | Identifie la tentative active d'un utilisateur pour un test. |
| `tst_test_result` | Contient les résultats par question, par tentative et par passage. |
| `qpl_questions` | Contient les métadonnées des questions : titre, points max, type. |

Colonnes principales :

```text
tst_tests.test_id
tst_tests.obj_fi
tst_active.active_id
tst_active.user_fi
tst_active.test_fi
tst_active.last_finished_pass
tst_test_result.active_fi
tst_test_result.question_fi
tst_test_result.points
tst_test_result.pass
tst_test_result.answered
tst_test_result.test_result_id
qpl_questions.question_id
qpl_questions.title
qpl_questions.points
qpl_questions.question_type_fi
```

## 4. Structure du statement question

Verbe :

```text
http://adlnet.gov/expapi/verbs/answered
```

Objet :

```text
http(s)://<ilias>/xapi/activity/tst/ref/<ref_id>/question/<question_id>
```

Type :

```text
http://adlnet.gov/expapi/activities/cmi.interaction
```

Extensions principales :

| Extension | Description |
|---|---|
| `/question_id` | Identifiant ILIAS de la question. |
| `/question_title` | Titre de la question. |
| `/question_type` | Type interne ILIAS. |
| `/question_points` | Points obtenus. |
| `/question_max_points` | Points maximum. |
| `/question_score_percent` | Score de la question en pourcentage. |
| `/question_answered` | Indique si la question a été répondue. |
| `/test_id` | Identifiant interne du test. |
| `/test_active_id` | Tentative active ILIAS. |
| `/test_pass` | Passage/tentative. |
| `/test_result_id` | Identifiant de ligne résultat. |
| `/question_result_source` | Table source, actuellement `tst_test_result`. |

## 5. Calcul des questions problématiques

Classe :

```text
classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php
```

Cette classe agrège les statements question déjà présents dans l'outbox locale.

Raison : les statements question sont générés localement avant l'envoi TRAX et sont immédiatement disponibles pour l'interface cours.

Critères :

```text
failure_rate >= 50 %
ou
avg_score < 50 %
```

Agrégats produits :

- `question_id` ;
- `question_title` ;
- `test_title` ;
- `ref_id` ;
- `attempts` ;
- `failed` ;
- `unanswered` ;
- `failure_rate` ;
- `avg_score` ;
- `risk_label` ;
- `risk_reason`.

## 6. UI cours

Plugin compagnon :

```text
companion/IliasTraxEventBridgeCourseUI
```

Classe écran :

```text
class.ilIliasTraxEventBridgeCourseUIScreen.php
```

Méthode ajoutée :

```text
renderQuestionFailureHotspots(array $dashboard, array $course): string
```

Affichage :

```text
Questions à fort taux d’échec
```

Emplacements :

- Tableau de bord ;
- Analyse.

## 7. Analyse IA

Classe :

```text
classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php
```

Champ ajouté au payload IA :

```text
question_failure_analysis
```

La méthode dédiée construit cette section à partir des risques question :

```text
buildQuestionFailureAnalysis()
filterQuestionRisks()
```

L'IA reçoit uniquement les questions problématiques agrégées, pas les réponses individuelles.

## 8. Tables plugin

| Table | Rôle |
|---|---|
| `evnt_evhk_itxeb_log` | Journal des événements ILIAS captés. |
| `evnt_evhk_itxeb_out` | Outbox locale des statements xAPI générés. |
| `evnt_evhk_itxeb_ccfg` | Configuration de suivi par cours. |
| `evnt_evhk_itxeb_rcfg` | Configuration de suivi par ressource. |

## 9. Robustesse

Le plugin doit rester non bloquant :

- si une table ILIAS manque, l'extracteur retourne une liste vide ;
- si une question n'est pas lisible, elle est ignorée ;
- si le calcul des risques échoue, le bloc affiche une absence de données plutôt qu'une erreur fatale ;
- si l'IA est indisponible, l'analyse retourne un message d'échec sans bloquer le cours.

## 10. Contrôles techniques

```bash
php -l classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php
php -l classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php
php -l classes/class.ilIliasTraxEventBridgeStatementFactory.php
php -l classes/class.ilIliasTraxEventBridgeEventRouter.php
php -l classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php
php -l companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
```

Contrôle SQL :

```sql
SELECT id, event_type, verb_id, ref_id, obj_type, status, created_at
FROM evnt_evhk_itxeb_out
WHERE statement_json LIKE '%question_id%'
ORDER BY id DESC
LIMIT 20;
```
