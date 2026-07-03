# Validation V0.13 — consolidation du compagnon UI

## Objectif de la validation

Valider que la V0.13 conserve le comportement fonctionnel de la V0.12 tout en installant le compagnon `IliasTraxEventBridgeCourseUI` depuis des templates consolidés, sans rejouer les patchers successifs historiques.

## Référence de version

```text
Branche : v0.13-consolidation-ui-companion
Version : 0.13.0-dev
```

La version finale sera passée à `0.13.0` après validation complète.

## Préparation VM

Depuis la VM ILIAS :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin v0.13-consolidation-ui-companion
git reset --hard FETCH_HEAD

rm -f FETCH_HEAD

git branch --show-current
grep -n '\$version' plugin.php
git log --oneline -5
```

Résultat attendu :

```text
v0.13-consolidation-ui-companion
4:$version = '0.13.0-dev';
```

Le dernier historique doit contenir le commit de consolidation de la route PDF dans le template UIHook.

## Installation du compagnon UI

Exécuter :

```bash
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Résultat attendu :

```text
Installing IliasTraxEventBridgeCourseUI companion
Mode  : V0.13 consolidated templates
PHP syntax check
No syntax errors detected ...
Companion installation completed.
```

Points attendus :

- le wrapper historique reste exécutable ;
- il appelle simplement le script principal ;
- le script principal copie les templates consolidés ;
- aucun patcher écran V0.12 n'est rejoué ;
- aucun patcher PDF n'est rejoué ;
- les contrôles `php -l` sont OK.

## Audit technique V0.13

Exécuter :

```bash
bash scripts/audit_course_ui_companion_v013.sh
```

Résultat attendu :

```text
Active V0.12 markers   : OK partout
Template V0.12 markers : OK partout
Patchers referenced by wrapper : bash "$SCRIPT_DIR/install_course_ui_companion.sh"
PHP syntax check : OK
Diff summary template vs active screen : vide
```

Critères :

- le template source existe ;
- le fichier actif installé existe ;
- le wrapper existe ;
- la branche affichée est `v0.13-consolidation-ui-companion` ;
- la version est `0.13.0-dev` ;
- les marqueurs V0.12 sont présents côté template et côté actif ;
- le diff entre template écran et fichier actif est vide.

## Validation fonctionnelle ILIAS

Dans un cours ILIAS de test, ouvrir l'onglet `Suivi xAPI`.

### Tableau de bord

À vérifier :

- la page s'ouvre sans erreur ;
- le titre du cours est affiché ;
- le bandeau V0.12 est présent ;
- les KPI principaux sont visibles ;
- les compteurs TRAX/LRS sont visibles ;
- la synthèse pédagogique est visible ;
- les statuts `OK`, `À surveiller`, `Critique` sont lisibles ;
- les couleurs orange et rouge restent visibles ;
- le bouton ou lien d'export PDF est présent.

Résultat V0.13 : OK.

### Analyse

À vérifier :

- la vue `Analyse` s'ouvre sans erreur ;
- la synthèse pédagogique est visible ;
- le tableau des ressources est affiché ;
- les colonnes de statut pédagogique sont présentes ;
- la colonne `Raison` est lisible ;
- les compteurs traces, apprenants, score moyen, tests et taux d'échec sont présents ;
- le bloc `Apprenants en difficulté` est présent lorsque des données de test le justifient.

Résultat V0.13 : OK.

### Expert

À vérifier :

- la vue `Expert` s'ouvre sans erreur ;
- l'export CSV fonctionne ;
- le fichier CSV contient les colonnes pédagogiques V0.12, notamment :
  - `pedagogical_status` ;
  - `pedagogical_label` ;
  - `pedagogical_reason` ;
  - `resource_failure_rate` ;
  - `resource_avg_score_raw` ;
  - `resource_traces` ;
  - `resource_learners_count` ;
  - `resource_is_critical` ;
  - `resource_is_watch`.

Résultat V0.13 : OK.

### Configuration

À vérifier :

- la vue `Configuration` s'ouvre sans erreur ;
- la configuration de suivi par ressource reste accessible ;
- les actions d'activation/désactivation fonctionnent ;
- la supervision technique outbox reste visible ;
- la lecture directe TRAX/LRS reste visible ;
- les diagnostics V0.11 restent accessibles.

Résultat V0.13 : OK.

### Export PDF

À vérifier :

- l'export PDF du tableau de bord fonctionne ;
- la route `exportCourseDashboardPdf` est reconnue sans patcher post-installation ;
- aucune erreur PHP n'apparaît dans les logs ILIAS ;
- le fichier PDF généré contient le tableau de bord attendu.

Résultat V0.13 : OK.

## Validation logs

Après navigation dans les vues, contrôler les logs ILIAS :

```bash
grep -iE "itxeb|trax|xapi|fatal|error|exception" /var/www/logs/ilias.log | tail -100
```

Résultat attendu :

- pas de fatal error ;
- pas d'exception liée au compagnon UI ;
- pas d'erreur liée à `exportCourseDashboardPdf` ;
- pas d'erreur liée aux classes du compagnon.

Résultat V0.13 : OK. Les erreurs visibles dans les extraits de validation étaient antérieures aux tests V0.13 et ne correspondent pas à une régression de cette version.

## Nettoyage local

Sur la VM :

```bash
rm -f FETCH_HEAD
```

Sur le poste Windows / Git Bash :

```bash
rm -f v013_pdf_route_consolidation.patch
git status
```

Résultat attendu côté Git Bash :

```text
nothing to commit, working tree clean
```

## Critères de validation finale

La V0.13 peut être finalisée si :

- l'installation du compagnon est OK ;
- l'audit V0.13 est OK ;
- le diff template/actif est vide ;
- les vues ILIAS fonctionnent ;
- les exports CSV et PDF fonctionnent ;
- les logs ne montrent pas d'erreur bloquante ;
- le comportement fonctionnel est équivalent à la V0.12 ;
- le flux d'installation ne dépend plus des patchers historiques.

Résultat : tous les critères sont validés.

## Décision

```text
Statut : validé
Date   : 2026-07-03
Validé par : Vincent Sayah
Remarques : validation fonctionnelle V0.13 OK. Installation depuis templates consolidés, audit OK, vues Tableau de bord / Analyse / Expert / Configuration OK, exports CSV et PDF OK, pas de nouvelle erreur bloquante observée dans les logs.
```
