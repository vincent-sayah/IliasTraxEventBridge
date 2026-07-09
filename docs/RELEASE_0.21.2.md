# Release V0.21.2 — Pilotage pédagogique xAPI, questions de test et Analyse IA

## Statut

| Élément | Valeur |
|---|---|
| Version plugin principal | `0.21.2-dev` |
| Version plugin compagnon UI | `0.8.5` |
| Branche de validation | `v0.13-ai-xapi-analysis` |
| Branche promue | `main` |
| Compatibilité ILIAS | ILIAS 10.x |
| Source fonctionnelle | TRAX/LRS + outbox locale pour le calcul des questions problématiques |
| Validation | validée sur serveur `ilias10` |

## Objectif de la version

La V0.21.2 finalise la chaîne pédagogique suivante :

```text
ILIAS Test -> xAPI question par question -> TRAX -> Pilotage pédagogique -> Analyse IA
```

Règle fonctionnelle validée :

```text
TRAX : toutes les questions d'un test ILIAS sont tracées.
Tableau de bord / Analyse : seules les questions problématiques sont remontées.
Analyse IA : seules les questions problématiques sont intégrées au payload IA.
Expert : la vision technique complète reste disponible.
```

## Nouveautés majeures

### 1. Traces question par question

Avant V0.21.1, le plugin traçait essentiellement le résultat global du test : réussite, échec, score global.

Depuis V0.21.1, lorsqu'un test ILIAS est terminé, le plugin génère également un statement xAPI par question.

Chaque statement question contient notamment :

- `question_id` ;
- `question_title` ;
- `question_type` ;
- `question_points` ;
- `question_max_points` ;
- `question_score_percent` ;
- `question_answered` ;
- `test_id` ;
- `test_active_id` ;
- `test_pass` ;
- `test_result_id`.

Le verbe xAPI utilisé est :

```text
http://adlnet.gov/expapi/verbs/answered
```

L'activité xAPI produite suit le modèle :

```text
/xapi/activity/tst/ref/<ref_id>/question/<question_id>
```

### 2. Extraction des résultats ILIAS

L'extraction s'appuie sur le schéma ILIAS 10 validé :

```text
tst_tests(test_id, obj_fi)
tst_active(active_id, user_fi, test_fi, last_finished_pass)
tst_test_result(active_fi, question_fi, points, pass, answered, test_result_id)
qpl_questions(question_id, title, points, question_type_fi)
```

Classe dédiée :

```text
classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php
```

### 3. Questions à fort taux d'échec

Un bloc pédagogique est ajouté dans :

```text
Tableau de bord
Analyse
```

Titre du bloc :

```text
Questions à fort taux d’échec
```

Il ne liste pas toutes les questions. Il remonte uniquement les questions dont :

```text
failure_rate >= 50 %
ou
score moyen < 50 %
```

Classe utilisée pour l'agrégation robuste depuis l'outbox locale :

```text
classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php
```

### 4. Analyse IA enrichie

Le payload envoyé à l'IA contient désormais :

```text
question_failure_analysis
```

Cette section contient uniquement les questions problématiques, jamais toutes les questions.

Champs envoyés :

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

## Validation réalisée

- `php -l` OK sur les scripts et classes ajoutés.
- Génération de traces question dans `evnt_evhk_itxeb_out` validée.
- Statements de question contenant `question_id` validés.
- Correction du `source_event` interne en `test_question_result` validée.
- Bloc `Questions à fort taux d’échec` visible dans l'interface validé.
- Intégration du payload IA validée.

## Points d'attention

La V0.21.2b utilise l'outbox locale pour calculer les questions problématiques, car les statements question sont disponibles localement dès leur génération.

Cela constitue une exception volontaire à la règle générale `TRAX/LRS = source pédagogique`, limitée au calcul de risque question dans le contexte ILIAS. Les traces continuent bien à être envoyées vers TRAX.

## Scripts d'application historiques

Les scripts suivants ont servi à appliquer progressivement la V0.21 :

```text
scripts/apply_v0211_enable_question_tracing.php
scripts/apply_v0211_fix_question_source_event.php
scripts/apply_v0212b_outbox_question_hotspots_ui.php
scripts/apply_v0212c_ai_question_failures.php
```

Le script `scripts/apply_v0212_question_failure_dashboard.php` est conservé uniquement comme historique technique et ne doit pas être utilisé en exploitation.
