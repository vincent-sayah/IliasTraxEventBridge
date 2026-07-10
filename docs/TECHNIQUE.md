# Documentation technique — IliasTraxEventBridge

## Référence courante

La version stable courante est la V0.22.4 promue dans `main`.

La documentation technique de base reste :

```text
docs/TECHNIQUE_0.21.2.md
```

Elle est complétée pour la version courante par :

```text
docs/RELEASE_0.22.4.md
docs/V0.22_ACTIVITY_TIMELINE.md
docs/V0.22.1_ILIAS_LIKE_DASHBOARD_LAYOUT.md
```

## Architecture actuelle

```text
ILIAS 10
  ├─ EventHook IliasTraxEventBridge
  │  ├─ capte les événements ILIAS
  │  ├─ génère les statements xAPI
  │  ├─ lit les résultats détaillés des tests ILIAS
  │  ├─ génère les statements question par question
  │  └─ alimente l'outbox locale
  │
  ├─ Cron ILIAS
  │  └─ envoie l'outbox vers TRAX/LRS
  │
  └─ UIHook IliasTraxEventBridgeCourseUI
     └─ affiche Pilotage xAPI dans le cours
```

## Classes clés V0.22.4

```text
classes/class.ilIliasTraxEventBridgeEventRouter.php
classes/class.ilIliasTraxEventBridgeStatementFactory.php
classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php
classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php
classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
```

La V0.22.4 modifie principalement le rendu de `class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl`.

## Tables principales

Tables plugin :

```text
evnt_evhk_itxeb_log
evnt_evhk_itxeb_out
evnt_evhk_itxeb_ccfg
evnt_evhk_itxeb_rcfg
```

Tables ILIAS test utilisées :

```text
tst_tests
tst_active
tst_test_result
qpl_questions
```

## Documents à lire

| Besoin | Document |
|---|---|
| Architecture complète de base | [`TECHNIQUE_0.21.2.md`](TECHNIQUE_0.21.2.md) |
| Release courante | [`RELEASE_0.22.4.md`](RELEASE_0.22.4.md) |
| Guide développeur | [`GUIDE_DEVELOPPEUR_0.21.2.md`](GUIDE_DEVELOPPEUR_0.21.2.md) |
| Installation | [`INSTALLATION.md`](INSTALLATION.md) |
| Exploitation | [`EXPLOITATION_0.21.2.md`](EXPLOITATION_0.21.2.md) |

Les anciens documents V0.10, V0.11 et V0.12 sont conservés comme historique.
