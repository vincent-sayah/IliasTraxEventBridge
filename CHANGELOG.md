# Changelog — IliasTraxEventBridge

Toutes les évolutions notables du plugin sont listées ici.

## v0.12.1 — consolidation technique du compagnon UI

### Statut

- Branche concernée : `v0.12.1-consolidation-ui-companion`.
- Base : `main` / `v0.12.0`.
- Version plugin : `0.12.1`.
- Type : maintenance technique, consolidation et non-régression.
- Source fonctionnelle du suivi xAPI : TRAX/LRS.
- Rôle de l'outbox locale : file technique d'envoi uniquement.

### Objectif

La V0.12.1 consolide les apports stabilisés de la V0.12.0 directement dans les templates du compagnon `IliasTraxEventBridgeCourseUI`.

Cette version ne modifie pas le comportement fonctionnel. Elle simplifie l'installation et la maintenance en supprimant la dépendance aux patchers successifs de l'écran cours.

La V0.13 reste réservée à l'analyse IA optionnelle des traces xAPI.

### Ajouts réalisés

- Création de la branche `v0.12.1-consolidation-ui-companion` depuis l'état de consolidation déjà validé.
- Passage du plugin principal en version `0.12.1`.
- Consolidation du template `class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl`.
- Consolidation de la route PDF `exportCourseDashboardPdf` dans le template UIHookGUI.
- Simplification de `scripts/install_course_ui_companion.sh`.
- Simplification du wrapper `scripts/install_course_ui_companion_with_standalone_fix.sh`.
- Ajout de `docs/V0.12.1_CONSOLIDATION_UI_COMPANION.md`.
- Ajout de `docs/VALIDATION_0.12.1.md`.
- Ajout du script `scripts/audit_course_ui_companion_v0121.sh`.

### Validation

- Installation du compagnon : OK.
- Audit technique : OK.
- Tableau de bord : OK.
- Analyse : OK.
- Expert / CSV : OK.
- Configuration : OK.
- Export PDF : OK.
- Logs : pas de nouvelle erreur bloquante observée.

## v0.12.0 — enrichissement pédagogique du tableau de bord

### Statut

- Branche concernée : `v0.12-dashboard-pedagogique`.
- Base : `main` / `v0.11.0`.
- Version plugin : `0.12.0`.
- Type : évolution fonctionnelle pédagogique.
- Source fonctionnelle du suivi xAPI : TRAX/LRS.
- Rôle de l'outbox locale : file technique d'envoi uniquement.

### Objectif

La V0.12 vise à transformer le suivi xAPI en outil d'aide au pilotage pédagogique.

Elle doit aider un formateur, un concepteur ou un pilote de cours à comprendre rapidement :

- quelles ressources sont réellement utilisées ;
- quelles ressources sont ignorées ;
- où les apprenants échouent ;
- quelles activités progressent ou régressent ;
- quels signaux faibles doivent attirer l'attention.

### Ajouts réalisés

- Création de la branche `v0.12-dashboard-pedagogique` depuis `main`.
- Passage du plugin principal en version `0.12.0`.
- Ajout du cadrage `docs/V0.12_DASHBOARD_PEDAGOGIQUE.md`.
- Ajout de statuts pédagogiques calculés dans la synthèse LRS : `ok`, `watch`, `critical`, `disabled`.
- Ajout d'une synthèse pédagogique déterministe côté LRS : ressources critiques, ressources à surveiller, ressources activées sans trace.
- Ajout du patcher `scripts/patch_course_ui_pedagogical_dashboard.php`.
- Intégration automatique du patcher V0.12 dans `scripts/install_course_ui_companion.sh`.
- Affichage des statuts pédagogiques dans `Tableau de bord` et `Analyse`.
- Restauration du bloc anonymisé `Apprenants en difficulté` après application du rendu V0.12.
- Ajout du patcher `scripts/patch_course_ui_expert_csv_pedagogy.php`.
- Enrichissement de l'export CSV Expert avec les colonnes pédagogiques : statut, libellé, raison, taux d'échec, score moyen ressource, nombre de traces, apprenants distincts, indicateurs critique / à surveiller.

### Pistes techniques V0.12

- Tester l'export CSV Expert enrichi sur VM ILIAS.
- Vérifier l'ouverture du CSV dans LibreOffice / Excel.
- Ajuster les seuils pédagogiques si nécessaire.
- Mettre à jour la documentation utilisateur V0.12.
- Conserver les diagnostics V0.11.

## v0.11.0 — diagnostic et durcissement exploitation

### Statut

- Branche concernée : `v0.11-diagnostic-exploitation`.
- Base : `main` / `v0.10.1`.
- Version plugin : `0.11.0`.
- Type : durcissement exploitation, diagnostic et rollback.
- Source fonctionnelle du suivi xAPI : TRAX/LRS.
- Rôle de l'outbox locale : file technique d'envoi uniquement.

### Objectif

La V0.11 prépare une version plus robuste pour l'exploitation : installation plus contrôlable, diagnostic plus simple, procédures de retour arrière documentées, et préparation d'une page de santé du plugin.

### Ajouts documentaires

- `docs/V0.11_DIAGNOSTIC_EXPLOITATION.md` : cadrage V0.11.
- `docs/DIAGNOSTIC.md` : procédure de diagnostic exploitation.
- `docs/ROLLBACK.md` : procédure de retour arrière.
- `docs/VALIDATION_0.11.md` : procédure complète de validation sur VM ILIAS.
- `docs/README.md` mis à jour pour rendre les nouveaux documents visibles.

### Ajouts techniques réalisés

- Ajout du script non destructif `scripts/diagnostic_itxeb.sh`.
- Ajout d'une section `Santé / Diagnostic V0.11` dans l'administration du plugin.
- Contrôle applicatif de la version plugin, du marqueur `<#1>`, du plugin compagnon UIHook, de l'endpoint TRAX/LRS, du cron, de l'outbox et des tables SQL.
- Ajout d'un bouton `Tester lecture TRAX/LRS` qui exécute uniquement un `GET /statements?limit=1` sans créer de statement.
- Ajout d'un bouton `Créer un statement test TRAX/LRS` qui envoie un statement xAPI de diagnostic clairement identifiable.
- Le statement de test contient les extensions `itxeb_diagnostic`, `itxeb_version` et `itxeb_test_type`.
- Les résultats des tests lecture et écriture TRAX/LRS sont maintenant persistés dans les settings du plugin et réaffichés dans `Diagnostics TRAX / cron`.
- Passage du plugin principal en version `0.11.0` sur la branche V0.11.

### Pistes techniques restantes V0.11

- Ajouter un diagnostic plus détaillé du plugin compagnon UIHook.
- Nettoyer les libellés d'administration restants hérités des anciennes versions.
- Tester sur VM ILIAS 10.5 puis stabiliser la release V0.11.0.

## Documentation main — README, docs et roadmap IA

### Statut

- Branche concernée : `main`.
- Version plugin inchangée : `0.10.1`.
- Type : mise à jour documentaire post-promotion.

### Corrections documentaires

- Mise à jour du `README.md` racine pour indiquer que la branche stable officielle est maintenant `main`.
- Ajout d'un index documentaire dans `docs/README.md`.
- Mise à jour de `docs/ROADMAP.md`, qui contenait encore une roadmap obsolète V0.5.5.
- Ajout de `docs/IA_ANALYSE_TRACES.md` pour cadrer l'analyse future des traces xAPI par IA avec clé API IA configurable.
- Mise à jour de `companion/IliasTraxEventBridgeCourseUI/README.md` pour refléter la V0.10.1 et l'accès `Cours > Suivi xAPI`.

### Roadmap ajoutée

La roadmap couvre désormais :

- V0.11 : durcissement exploitation et packaging ;
- V0.12 : enrichissement pédagogique du tableau de bord ;
- V0.13 : analyse IA optionnelle des traces xAPI ;
- V0.14 : historisation durable et gouvernance ;
- V0.15 : connecteurs et interopérabilité.
