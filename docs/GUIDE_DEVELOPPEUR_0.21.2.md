# Guide développeur V0.21.2 — IliasTraxEventBridge

## 1. Objectif

Ce guide décrit les classes, tables et flux utilisés par le plugin `IliasTraxEventBridge` en V0.21.2.

Il est destiné aux développeurs qui doivent maintenir, corriger ou étendre le plugin ILIAS 10.

## 2. Structure du plugin

```text
IliasTraxEventBridge/
├── plugin.php
├── classes/
├── sql/dbupdate.php
├── scripts/
├── companion/IliasTraxEventBridgeCourseUI/
└── docs/
```

## 3. Plugins ILIAS

### Plugin principal

```text
Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

Type : `EventHook`.

Rôle : capter les événements ILIAS, générer les statements xAPI, alimenter l'outbox et gérer la configuration.

### Plugin compagnon

```text
Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

Type : `UIHook`.

Rôle : ajouter l'accès `Pilotage xAPI` dans l'objet cours et afficher les vues pédagogiques.

## 4. Classes principales

| Classe | Rôle |
|---|---|
| `ilIliasTraxEventBridgePlugin` | Point d'entrée EventHook, chargement du cron, gestion plugin. |
| `ilIliasTraxEventBridgeConfig` | Lecture/écriture des paramètres plugin. |
| `ilIliasTraxEventBridgeConfigGUI` | Écran d'administration du plugin. |
| `ilIliasTraxEventBridgeEventRouter` | Route les événements ILIAS et déclenche la génération xAPI. |
| `ilIliasTraxEventBridgeStatementFactory` | Construit les statements xAPI globaux et question. |
| `ilIliasTraxEventBridgeOutboxRepository` | Insère, lit et met à jour l'outbox locale. |
| `ilIliasTraxEventBridgeOutboxSender` | Envoie les statements vers TRAX/LRS. |
| `ilIliasTraxEventBridgeTraxClient` | Client HTTP d'écriture xAPI. |
| `ilIliasTraxEventBridgeLrsReadClient` | Client HTTP de lecture TRAX/LRS. |
| `ilIliasTraxEventBridgeLrsCourseSummary` | Agrégation de données xAPI pour les vues cours. |
| `ilIliasTraxEventBridgeCourseAiAnalyzer` | Préparation du payload IA et appel au fournisseur IA. |
| `ilIliasTraxEventBridgeAiClient` | Client HTTP pour le fournisseur IA. |
| `ilIliasTraxEventBridgeAiAnalysisHistory` | Historisation locale des analyses IA. |
| `ilIliasTraxEventBridgeTestQuestionResultExtractor` | Lit les résultats question par question dans les tables ILIAS test. |
| `ilIliasTraxEventBridgeQuestionRiskRepository` | Calcule les questions problématiques depuis l'outbox locale. |

## 5. Classes du compagnon UI

| Classe template | Rôle |
|---|---|
| `class.ilIliasTraxEventBridgeCourseUIPlugin.php.tpl` | Déclaration du plugin compagnon. |
| `class.ilIliasTraxEventBridgeCourseUIBridge.php.tpl` | Pont entre UIHook et plugin principal. |
| `class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl` | Injection du bouton `Pilotage xAPI`. |
| `class.ilIliasTraxEventBridgeCourseUIRouterGUI.php.tpl` | Router ILIAS des écrans de cours. |
| `class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl` | Rendu des vues Tableau de bord, Analyse, Analyse IA, Expert, Configuration. |

## 6. Tables plugin

### `evnt_evhk_itxeb_log`

Journal brut des événements ILIAS captés.

Colonnes importantes :

```text
id
created_at
created_ts
component
event_name
user_id
ref_id
obj_id
obj_type
payload_json
request_uri
```

### `evnt_evhk_itxeb_out`

Outbox locale des statements générés.

Colonnes importantes :

```text
id
event_log_id
statement_uuid
event_type
verb_id
user_id
ref_id
obj_id
obj_type
statement_json
status
created_at
created_ts
sent_at
last_error
retry_count
max_retry
last_attempt_at
```

Statuts :

```text
generated
sending
sent
failed
```

### `evnt_evhk_itxeb_ccfg`

Configuration par cours.

Rôle : activer ou désactiver le suivi xAPI au niveau du cours.

### `evnt_evhk_itxeb_rcfg`

Configuration par ressource.

Rôle : activer ou désactiver le suivi xAPI au niveau des ressources du cours.

## 7. Tables ILIAS utilisées pour les tests

| Table | Colonnes utilisées | Usage |
|---|---|---|
| `tst_tests` | `test_id`, `obj_fi` | Retrouver le test interne depuis l'objet ILIAS. |
| `tst_active` | `active_id`, `user_fi`, `test_fi`, `last_finished_pass` | Retrouver la tentative de l'utilisateur. |
| `tst_test_result` | `test_result_id`, `active_fi`, `question_fi`, `points`, `pass`, `answered` | Lire les résultats par question. |
| `qpl_questions` | `question_id`, `title`, `points`, `question_type_fi` | Obtenir le titre et les points maximum. |

## 8. Flux de génération des traces question

```text
1. ILIAS déclenche components/ILIAS/Tracking:updateStatus.
2. EventRouter reçoit l'événement.
3. StatementFactory génère le statement global du test.
4. TestQuestionResultExtractor lit les questions dans la base ILIAS.
5. StatementFactory génère un statement `answered` par question.
6. EventRouter enfile tous les statements dans evnt_evhk_itxeb_out.
7. Le cron envoie les statements vers TRAX/LRS.
```

## 9. Flux des questions à fort taux d'échec

```text
1. Les statements question sont présents dans evnt_evhk_itxeb_out.
2. QuestionRiskRepository lit les statements contenant question_id.
3. Il agrège par ref_id + question_id.
4. Il calcule attempts, failed, unanswered, failure_rate, avg_score.
5. Il garde uniquement les questions problématiques.
6. CourseUIScreen affiche le bloc pédagogique.
7. CourseAiAnalyzer ajoute les mêmes agrégats au payload IA.
```

## 10. Seuils métier

```text
Question problématique si :
- failure_rate >= 50 %
ou
- avg_score < 50 %

Critique si :
- failure_rate >= 70 %

Sinon :
- À surveiller
```

## 11. Ajouter un nouveau type d'événement

1. Activer le debug plugin.
2. Observer `evnt_evhk_itxeb_log`.
3. Identifier `component`, `event_name`, `payload_json`.
4. Modifier `EventRouter`.
5. Modifier `StatementFactory`.
6. Ajouter les extensions xAPI nécessaires.
7. Vérifier l'opt-in cours/ressource.
8. Tester l'outbox.
9. Tester l'envoi TRAX.
10. Mettre à jour la documentation.

## 12. Ajouter un indicateur pédagogique

1. Vérifier où l'information existe : TRAX/LRS, outbox, tables ILIAS.
2. Créer une classe dédiée si le calcul est spécifique.
3. Ne pas surcharger `CourseUIScreen` avec de la logique SQL lourde.
4. Garder les vues non Expert lisibles.
5. Préserver l'anonymisation.
6. Mettre à jour `FONCTIONNEL`, `TECHNIQUE`, `EXPLOITATION`.

## 13. Contrôles avant livraison

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

php -l plugin.php
find classes companion -name '*.php' -o -name '*.tpl' | xargs -r -n1 php -l
grep -n '\$version' plugin.php companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
git status -sb
git diff --check
```

Contrôles SQL :

```sql
SELECT COUNT(*) FROM evnt_evhk_itxeb_out WHERE statement_json LIKE '%question_id%';

SELECT id, event_type, verb_id, ref_id, obj_type, status, created_at
FROM evnt_evhk_itxeb_out
WHERE statement_json LIKE '%question_id%'
ORDER BY id DESC
LIMIT 20;
```

## 14. Règles de sécurité

- Ne jamais exposer la clé API IA.
- Ne pas envoyer de nom, courriel ou identité nominative apprenant à l'IA.
- Ne pas envoyer les statements bruts complets à l'IA.
- Garder les erreurs non bloquantes côté EventHook.
- Toujours tester `php -l` avant promotion.

## 15. Promotion vers main

La promotion se fait uniquement après :

- validation serveur ;
- documentation à jour ;
- `main` fast-forwardable ;
- absence de divergence entre `main` et la branche validée.

Commande type :

```bash
git checkout main
git merge --ff-only v0.13-ai-xapi-analysis
git push origin main
```
