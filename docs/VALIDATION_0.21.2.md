# Validation V0.21.2 — Checklist serveur

## 1. Préparation

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin
git pull --ff-only origin v0.13-ai-xapi-analysis
```

## 2. Contrôle syntaxe

```bash
php -l plugin.php
php -l classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php
php -l classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php
php -l classes/class.ilIliasTraxEventBridgeStatementFactory.php
php -l classes/class.ilIliasTraxEventBridgeEventRouter.php
php -l classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php
php -l companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
```

Résultat attendu :

```text
No syntax errors detected
```

## 3. Contrôle versions

```bash
grep -n '\$version' plugin.php
grep -n '\$version' companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
```

Attendu :

```text
0.21.2-dev
0.8.5
```

## 4. Test fonctionnel test ILIAS

1. Ouvrir un test ILIAS contenant plusieurs questions.
2. Réaliser une tentative complète.
3. Terminer le test.
4. Attendre la génération des traces.

## 5. Validation outbox question

```bash
mysql -u ilias -p -e "
USE ilias;

SELECT id, event_type, verb_id, ref_id, obj_type, status, created_at
FROM evnt_evhk_itxeb_out
WHERE statement_json LIKE '%question_id%'
ORDER BY id DESC
LIMIT 20;
"
```

Attendu :

```text
- plusieurs lignes pour le même test ;
- verb_id = http://adlnet.gov/expapi/verbs/answered ;
- obj_type = tst ;
- statement_json contient question_id.
```

## 6. Validation contenu statement

```bash
mysql -u ilias -p -e "
USE ilias;

SELECT id, LEFT(statement_json, 1500) AS statement_preview
FROM evnt_evhk_itxeb_out
WHERE statement_json LIKE '%question_id%'
ORDER BY id DESC
LIMIT 3;
"
```

Attendu dans le JSON :

```text
question_id
question_title
question_points
question_max_points
question_score_percent
question_answered
test_pass
```

## 7. Validation interface

Dans ILIAS :

```text
Cours > Pilotage xAPI > Tableau de bord
Cours > Pilotage xAPI > Analyse
```

Attendu :

```text
Questions à fort taux d’échec
```

Si aucune question ne dépasse les seuils, le bloc affiche :

```text
Aucune question à fort taux d’échec détectée sur la période sélectionnée.
```

## 8. Validation Analyse IA

Contrôle code :

```bash
grep -n "question_failure_analysis\|buildQuestionFailureAnalysis\|filterQuestionRisks" \
classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php
```

Attendu :

```text
question_failure_analysis
buildQuestionFailureAnalysis
filterQuestionRisks
```

Test fonctionnel :

1. Aller dans `Pilotage xAPI > Analyse IA`.
2. Générer une nouvelle analyse IA.
3. Vérifier que l'analyse ne se limite pas aux ressources, mais peut exploiter les questions problématiques.

## 9. Validation Expert

Dans :

```text
Pilotage xAPI > Expert
```

Vérifier que la vision technique conserve les traces détaillées, y compris les verbes et identifiants techniques.

## 10. Validation envoi TRAX

```bash
mysql -u ilias -p -e "
USE ilias;
SELECT status, COUNT(*) AS total
FROM evnt_evhk_itxeb_out
GROUP BY status;
"
```

Attendu : les lignes passent progressivement de `generated` à `sent` selon le cron d'envoi.

## 11. Redémarrage final

```bash
systemctl restart php-fpm
systemctl restart httpd
```

## 12. Décision de validation

La V0.21.2 est validée si :

- les traces globales de test sont conservées ;
- les traces par question sont créées ;
- les questions à fort taux d'échec sont visibles dans Tableau de bord / Analyse ;
- l'Analyse IA reçoit les questions problématiques ;
- aucun écran ILIAS n'est bloqué ;
- aucun `php -l` n'est en erreur.
