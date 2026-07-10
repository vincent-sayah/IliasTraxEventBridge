# Documentation développeur — IliasTraxEventBridge

## Référence courante

La version stable courante est la V0.22.4 promue dans `main`.

Le guide développeur de base reste :

```text
docs/GUIDE_DEVELOPPEUR_0.21.2.md
```

Il est complété par :

```text
docs/RELEASE_0.22.4.md
docs/V0.22_ACTIVITY_TIMELINE.md
docs/V0.22.1_ILIAS_LIKE_DASHBOARD_LAYOUT.md
```

## Ce que contient le guide de base

- Structure du plugin principal EventHook.
- Structure du plugin compagnon UIHook.
- Classes principales et responsabilités.
- Tables plugin `evnt_evhk_itxeb_*`.
- Tables ILIAS utilisées pour les tests : `tst_tests`, `tst_active`, `tst_test_result`, `qpl_questions`.
- Flux de génération des statements xAPI.
- Flux des traces question par question.
- Calcul des questions à fort taux d'échec.
- Intégration des questions problématiques dans l'Analyse IA.
- Contrôles avant livraison.
- Règles de sécurité et d'anonymisation.

## Classes critiques V0.22.4

```text
classes/class.ilIliasTraxEventBridgeEventRouter.php
classes/class.ilIliasTraxEventBridgeStatementFactory.php
classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php
classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php
classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php
classes/class.ilIliasTraxEventBridgeAiClient.php
classes/class.ilIliasTraxEventBridgeAiAnalysisHistory.php
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
```

La V0.22.4 touche principalement le rendu dans `class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl`.

## Contrôles développeur rapides

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

php -l plugin.php
php -l classes/class.ilIliasTraxEventBridgeEventRouter.php
php -l classes/class.ilIliasTraxEventBridgeStatementFactory.php
php -l classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php
php -l classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php
php -l classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php
php -l companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl

grep -n '\$version' plugin.php companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
grep -n "Activité dans le temps\|V0.22.4 alignment\|showCourseAiAnalysis" companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
```

## Documents à lire

| Besoin | Document |
|---|---|
| Guide développeur complet de base | [`GUIDE_DEVELOPPEUR_0.21.2.md`](GUIDE_DEVELOPPEUR_0.21.2.md) |
| Release courante | [`RELEASE_0.22.4.md`](RELEASE_0.22.4.md) |
| Architecture technique | [`TECHNIQUE_0.21.2.md`](TECHNIQUE_0.21.2.md) |
| Installation | [`INSTALLATION.md`](INSTALLATION.md) |
| Validation | [`VALIDATION_0.22.4.md`](VALIDATION_0.22.4.md) |

Les anciennes consignes V0.10/V0.11/V0.12 sont historiques et ne doivent pas être utilisées comme référence de développement courante.
