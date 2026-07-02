# Validation V0.12 — dashboard pédagogique

## Objectif

Cette procédure permet de valider la V0.12 avant promotion vers `main`.

La V0.12 ajoute une lecture pédagogique des traces xAPI depuis TRAX/LRS : dashboard pédagogique, synthèse pédagogique, analyse enrichie, apprenants en difficulté, export CSV Expert enrichi et ajustements ergonomiques.

## Rappel d’architecture

```text
Outbox locale = file technique d’envoi
TRAX/LRS      = source officielle du suivi xAPI pédagogique
```

Les données pédagogiques visibles dans le cours doivent provenir de TRAX/LRS. L’outbox locale reste un outil technique de diagnostic et d’envoi.

## Branche à valider

| Élément | Valeur |
|---|---|
| Branche | `v0.12-dashboard-pedagogique` |
| Base | `main` / `v0.11.0` |
| Version attendue | `0.12.0` |
| Plugin principal | `IliasTraxEventBridge` |
| Plugin compagnon | `IliasTraxEventBridgeCourseUI` |

## 1. Mise à jour du plugin sur la VM ILIAS

Se placer dans le plugin principal :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

Récupérer la branche V0.12 :

```bash
git fetch origin
git checkout v0.12-dashboard-pedagogique
git pull origin v0.12-dashboard-pedagogique
```

Si la branche locale n’existe pas :

```bash
git fetch origin v0.12-dashboard-pedagogique
git checkout -B v0.12-dashboard-pedagogique FETCH_HEAD
```

Contrôler la branche et la version :

```bash
git branch --show-current
grep -n '\$version' plugin.php
```

Résultat attendu :

```text
v0.12-dashboard-pedagogique
$version = '0.12.0';
```

## 2. Installation complète du compagnon UI

Relancer le script complet :

```bash
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Messages attendus :

```text
V0.12 pedagogical UI patch applied.
Struggling learners patch applied to Analysis only
V0.12 expert CSV pedagogy patch applied.
V0.12 layout patch applied.
```

Les messages peuvent indiquer `already applied` si le script a déjà été lancé. Ce n’est pas une erreur.

## 3. Contrôles fichiers installés

Fichier compagnon attendu :

```bash
TARGET=/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php
```

Contrôler les éléments V0.12 :

```bash
grep -n "renderPedagogicalSynthesis" "$TARGET"
grep -n "renderStrugglingLearners" "$TARGET"
grep -n "pedagogical_status" "$TARGET"
grep -n "resource_failure_rate" "$TARGET"
grep -n "itxeb-v012-header" "$TARGET"
grep -n "itxeb-cui-analysis-table td:nth-child(2)" "$TARGET"
```

Résultat attendu : chaque commande doit retourner au moins une ligne.

## 4. Rebuild ILIAS

Depuis la racine ILIAS :

```bash
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
systemctl restart php-fpm
```

Si le service `php-fpm` n’existe pas sur l’environnement, ignorer uniquement cette dernière ligne.

## 5. Validation Tableau de bord

Dans ILIAS :

```text
Cours > Suivi xAPI > Tableau de bord
```

Vérifier :

- le titre principal `Suivi xAPI — nom du cours` est visible ;
- le bouton `Export PDF` est placé à droite du titre principal ;
- le titre `Tableau de bord du cours` est plus visible ;
- les blocs sont mieux encadrés ;
- les titres de blocs sont en gras ;
- le bloc `Synthèse pédagogique` est visible ;
- les compteurs `OK`, `À surveiller`, `Critiques`, `Sans trace` sont visibles ;
- `À surveiller` est colorisé en orange ;
- `Critiques` est colorisé en rouge.

Critère d’acceptation :

```text
Le tableau de bord doit être lisible par un formateur sans interprétation technique de l’outbox.
```

## 6. Validation Analyse

Dans ILIAS :

```text
Cours > Suivi xAPI > Analyse
```

Vérifier :

- le bloc `Synthèse pédagogique` est visible ;
- `À surveiller` est colorisé en orange ;
- `Critiques` est colorisé en rouge ;
- les blocs sont mieux encadrés ;
- les titres de blocs sont en gras ;
- le tableau des ressources contient les colonnes `Statut`, `Raison`, `Ressource`, `Type`, `xAPI`, `Traces`, `Apprenants`, `Dernière trace`, `Score moyen`, `Tests`, `Taux échec` ;
- la police de la colonne `Raison` est lisible ;
- les statuts `Critique` et `À surveiller` ont un code couleur visible ;
- le bloc `Apprenants en difficulté` est visible si des signaux négatifs existent.

Critère d’acceptation :

```text
L’onglet Analyse doit permettre d’identifier rapidement les ressources critiques, les ressources à surveiller et les apprenants en difficulté.
```

## 7. Validation Export PDF

Depuis le tableau de bord, cliquer sur :

```text
Export PDF
```

Vérifier :

- le bouton déclenche bien un export ;
- le fichier PDF se télécharge ou un rapport HTML imprimable apparaît si aucun moteur PDF serveur n’est disponible ;
- le déplacement du bouton n’a pas cassé l’action `exportCourseDashboardPdf`.

Contrôle serveur possible :

```bash
grep -n "exportCourseDashboardPdf" "$TARGET"
grep -n "sendDashboardPdf" "$TARGET"
```

Critère d’acceptation :

```text
L’export PDF reste disponible depuis le tableau de bord après les modifications d’agencement.
```

## 8. Validation Export CSV Expert enrichi

Dans ILIAS :

```text
Cours > Suivi xAPI > Expert > Exporter CSV
```

Ouvrir le CSV et vérifier la présence des colonnes :

```text
pedagogical_status
pedagogical_label
pedagogical_reason
resource_failure_rate
resource_avg_score_raw
resource_traces
resource_learners_count
resource_is_critical
resource_is_watch
```

Critère d’acceptation :

```text
Le CSV Expert doit conserver les colonnes historiques et ajouter les colonnes pédagogiques V0.12.
```

## 9. Validation filtres

Tester au minimum :

- période par défaut ;
- autre période disponible ;
- filtre sur une ressource ;
- retour au filtre toutes ressources ;
- onglets `Tableau de bord`, `Analyse` et `Expert` après changement de filtre.

Critère d’acceptation :

```text
Les compteurs, tableaux et exports doivent rester cohérents avec la période et la ressource filtrées.
```

## 10. Validation diagnostic V0.11 conservé

Vérifier que les fonctions V0.11 sont toujours accessibles dans l’administration du plugin :

- santé / diagnostic ;
- test de lecture TRAX/LRS ;
- test d’écriture TRAX/LRS ;
- informations cron ;
- informations outbox technique.

Critère d’acceptation :

```text
La V0.12 ne doit pas supprimer ni dégrader les diagnostics V0.11.
```

## 11. Validation absence de régression serveur

Contrôler les erreurs PHP/Apache après navigation dans les onglets :

```bash
journalctl -u httpd -n 100 --no-pager
journalctl -u php-fpm -n 100 --no-pager
```

Si les logs ILIAS sont disponibles :

```bash
grep -iE "IliasTraxEventBridge|CourseUI|fatal|error|exception" /var/www/logs/ilias.log | tail -100
```

Critère d’acceptation :

```text
Aucune erreur fatale liée à IliasTraxEventBridge ou IliasTraxEventBridgeCourseUI.
```

## 12. Validation idempotence

Relancer une deuxième fois :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Résultat attendu :

- pas d’erreur ;
- les patchs déjà appliqués peuvent afficher `already applied` ;
- la vérification `php -l` finale doit rester OK.

Critère d’acceptation :

```text
Le script d’installation du compagnon peut être relancé sans casser le rendu V0.12.
```

## 13. Critères d’acceptation finale V0.12

La V0.12 est validée si :

- le plugin principal affiche la version `0.12.0` ;
- le dashboard pédagogique est visible ;
- la synthèse pédagogique est visible dans `Tableau de bord` et `Analyse` ;
- les statuts `OK`, `À surveiller`, `Critique`, `Désactivée` sont cohérents ;
- `À surveiller` est colorisé en orange ;
- `Critique` est colorisé en rouge ;
- le bloc `Apprenants en difficulté` est présent lorsque des signaux existent ;
- l’export PDF fonctionne depuis le titre du tableau de bord ;
- l’export CSV Expert contient les colonnes pédagogiques V0.12 ;
- les diagnostics V0.11 restent disponibles ;
- aucun diagnostic outbox n’est présenté comme source pédagogique ;
- aucun incident PHP bloquant n’est constaté.

## 14. Décision de promotion

Si tous les critères sont validés :

```text
V0.12 validée sur VM ILIAS
Promotion vers main autorisée
```

La prochaine étape est alors la création d’une Pull Request :

```text
v0.12-dashboard-pedagogique -> main
```
