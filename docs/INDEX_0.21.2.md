# Index documentaire V0.21.2 — IliasTraxEventBridge

Cette page est l'index de référence pour la version validée V0.21.2.

## Documents V0.21.2

| Document | Usage |
|---|---|
| [`RELEASE_0.21.2.md`](RELEASE_0.21.2.md) | Note de release et périmètre validé. |
| [`FONCTIONNEL_0.21.2.md`](FONCTIONNEL_0.21.2.md) | Documentation fonctionnelle pour formateur, pilote de cours et administrateur. |
| [`TECHNIQUE_0.21.2.md`](TECHNIQUE_0.21.2.md) | Architecture technique, flux xAPI, tables, extensions, Analyse IA. |
| [`GUIDE_DEVELOPPEUR_0.21.2.md`](GUIDE_DEVELOPPEUR_0.21.2.md) | Guide développeur : classes, tables, conventions et contrôles. |
| [`EXPLOITATION_0.21.2.md`](EXPLOITATION_0.21.2.md) | Exploitation, supervision, diagnostic et commandes SQL. |
| [`VALIDATION_0.21.2.md`](VALIDATION_0.21.2.md) | Checklist de validation serveur. |

## Résumé de la règle métier

```text
TRAX = toutes les questions de test.
Tableau de bord / Analyse = questions problématiques uniquement.
Analyse IA = questions problématiques uniquement.
Expert = vision technique complète.
```

## Classes V0.21.2 à connaître

```text
classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php
classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php
classes/class.ilIliasTraxEventBridgeStatementFactory.php
classes/class.ilIliasTraxEventBridgeEventRouter.php
classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
```

## Tables ILIAS test utilisées

```text
tst_tests
tst_active
tst_test_result
qpl_questions
```

## Tables plugin utilisées

```text
evnt_evhk_itxeb_log
evnt_evhk_itxeb_out
evnt_evhk_itxeb_ccfg
evnt_evhk_itxeb_rcfg
```

## Scripts V0.21 utiles

```text
scripts/apply_v0211_enable_question_tracing.php
scripts/apply_v0211_fix_question_source_event.php
scripts/apply_v0212b_outbox_question_hotspots_ui.php
scripts/apply_v0212c_ai_question_failures.php
```

À ne pas utiliser :

```text
scripts/apply_v0212_question_failure_dashboard.php
```

Ce script est conservé seulement pour historique technique.

## Contrôle rapide serveur

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

php -l classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php
php -l classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php
php -l classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php

grep -n "Questions à fort taux d’échec\|QuestionRiskRepository" \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php

grep -n "question_failure_analysis" classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php
```
