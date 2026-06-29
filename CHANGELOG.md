# Changelog — IliasTraxEventBridge

Toutes les évolutions notables du plugin sont listées ici.

## v0.10.1 — release stable corrective et documentation complète

### Statut

- Branche concernée : `v0.10-lrs-direct-read`.
- Version plugin principal : `0.10.1`.
- Compatibilité : ILIAS 10.x.
- Type : version stable corrective après V0.10.0.
- Source fonctionnelle du suivi xAPI : TRAX/LRS.
- Rôle de l'outbox locale : file technique d'envoi uniquement.

### Correction principale

- Correction du fichier `sql/dbupdate.php` pour ajouter le marqueur d'étape ILIAS `<#1>` au début du fichier.
- Correction destinée à éviter l'erreur d'installation de type `Undefined array key ...` observée lors de l'installation du plugin après désinstallation d'une ancienne version.
- Stabilisation du scénario d'installation depuis zéro et du scénario de mise à jour depuis une ancienne V0.6/V0.9.

### Documentation ajoutée / mise à jour

- `README.md` transformé en page d'accueil stable V0.10.1.
- `docs/INSTALLATION.md` ajouté.
- `docs/FONCTIONNEL.md` ajouté.
- `docs/TECHNIQUE.md` ajouté.
- `docs/EXPLOITATION.md` ajouté.
- `docs/DEVELOPPEUR.md` ajouté.
- `docs/RELEASE_0.10.1.md` ajouté.
- `docs/V0.10_LRS_DIRECT_READ.md` mis en cohérence avec la V0.10.1.

### Contrôles attendus

```bash
grep -n '\$version' plugin.php
head -5 sql/dbupdate.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

Résultats attendus :

```text
$version = '0.10.1';
<#1>
<?php
aucune erreur PHP
```

## v0.10.0 — suivi xAPI alimenté par TRAX/LRS

### Statut

- Branche concernée : `v0.10-lrs-direct-read`.
- Base : V0.9.1 feedback dashboard.
- Version plugin principal : `0.10.0`.
- Source fonctionnelle du suivi xAPI : TRAX/LRS.
- Rôle de l'outbox locale : file technique d'envoi uniquement.
- Statut : validée fonctionnellement avant tag `v0.10.0`.

### Décision d'architecture

La V0.10.0 stabilise la séparation suivante :

```text
Outbox locale = file technique d'envoi
TRAX/LRS      = source officielle du suivi xAPI pédagogique
```

L'outbox locale `evnt_evhk_itxeb_out` reste utilisée pour générer, envoyer, rejouer et superviser les statements xAPI. Elle n'est plus utilisée comme source fonctionnelle des vues pédagogiques, car elle peut être purgée en exploitation.

### Nouveautés principales

- Lecture directe des statements depuis TRAX/LRS via `GET /statements`.
- Tableau de bord alimenté par TRAX/LRS.
- Analyse des ressources alimentée par TRAX/LRS.
- Vue Expert alimentée par TRAX/LRS.
- Export CSV Expert alimenté par TRAX/LRS.
- Comparaison entre périodes calculée depuis TRAX/LRS.
- Export PDF du tableau de bord basé sur TRAX/LRS.
- Supervision technique de l'outbox déplacée dans l'onglet `Configuration`.
- Diagnostic `TRAX / LRS direct` déplacé dans l'onglet `Configuration`.
- Détails `Verbes retournés par TRAX` et `Ressources retournées par TRAX` déplacés dans l'onglet `Analyse`.

### Tableau de bord

Le tableau de bord devient une vue pédagogique.

Il affiche notamment :

- statements TRAX ;
- apprenants actifs ;
- ressources utilisées ;
- score moyen ;
- ressources sans statement TRAX ;
- pages LRS lues ;
- activité par jour ;
- répartition des actions xAPI ;
- top ressources ;
- comparaison entre périodes ;
- bouton `Export PDF`.

Les blocs techniques suivants ne sont plus affichés dans le tableau de bord :

- `État technique local` ;
- diagnostic complet `TRAX / LRS direct` ;
- statuts outbox `generated`, `sending`, `sent`, `failed`.

### Analyse

L'onglet `Analyse` contient les données fonctionnelles issues de TRAX/LRS :

- analyse des ressources ;
- apprenants en difficulté, affichés sous forme anonymisée ;
- verbes retournés par TRAX ;
- ressources retournées par TRAX.

### Expert

La vue `Expert` affiche les statements retournés par TRAX/LRS.

Changements principaux :

- la source affichée est `TRAX` ;
- le `Statement ID` est affiché ;
- les anciennes colonnes locales `Outbox` et `Erreur` ont été retirées ;
- l'export CSV ne dépend plus de l'outbox locale.

### Configuration

L'onglet `Configuration` regroupe désormais les fonctions techniques :

- activation xAPI du cours ;
- activation xAPI des ressources ;
- préférences du tableau de bord ;
- supervision technique de l'envoi xAPI ;
- diagnostic de lecture directe TRAX/LRS.

La section `Supervision technique de l'envoi xAPI` affiche uniquement l'état de la file locale :

```text
generated
sending
sent
failed
other
```

Cette section ne sert pas au suivi pédagogique.

### Export PDF

Un bouton `Export PDF` est ajouté dans le tableau de bord.

Le rapport contient :

- cours ;
- `course_ref_id` ;
- période ;
- filtre ressource ;
- filtre type ;
- synthèse KPI ;
- activité par jour ;
- actions xAPI ;
- ressources.

Le moteur PDF est sélectionné dans cet ordre :

```text
1. Dompdf si disponible côté Composer
2. wkhtmltopdf si disponible côté serveur
3. rapport HTML imprimable si aucun moteur PDF n'est disponible
```

Le paquet `wkhtmltopdf-opt` est supporté, y compris lorsque le binaire est installé dans :

```text
/opt/wkhtmltopdf/bin/wkhtmltopdf
```

### Robustesse validée

Les validations suivantes ont été réalisées :

- purge de `evnt_evhk_itxeb_out` sans perte d'affichage pédagogique ;
- filtres période / ressource / type ;
- export CSV Expert ;
- indisponibilité ou mauvaise configuration LRS sans erreur PHP fatale ;
- export PDF avec moteur `wkhtmltopdf` ;
- séparation stricte entre suivi pédagogique TRAX/LRS et supervision technique locale.

### Fichiers et scripts importants

- `classes/class.ilIliasTraxEventBridgeLrsReadClient.php`
- `classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php`
- `docs/V0.10_LRS_DIRECT_READ.md`
- `scripts/patch_course_ui_lrs_direct_summary.php`
- `scripts/patch_course_ui_lrs_primary_views.php`
- `scripts/patch_course_ui_outbox_technical_config.php`
- `scripts/patch_course_ui_lrs_diagnostics_config.php`
- `scripts/patch_course_ui_lrs_analysis_details.php`
- `scripts/patch_course_ui_pdf_export.php`
- `scripts/patch_course_ui_pdf_route.php`
- `scripts/patch_course_ui_pdf_wkhtmltopdf_paths.php`

## v0.9.1 — feedback cours, dashboard pédagogique et navigation Delos

### Statut

- Branche concernée : `v0.9-feedback-dashboard`.
- Version plugin principal : `0.9.1`.
- Version plugin compagnon UIHook : `0.2.1`.
- Statut : validée fonctionnellement avant merge `main` et tag `v0.9.1`.

### Objectif

La V0.9.1 ajoute un feedback pédagogique et technique directement dans l'objet cours ILIAS.

Elle complète la configuration xAPI par cours avec des vues d'analyse exploitables par un formateur, un pilote de cours ou un administrateur.

### Navigation ILIAS 10 / Delos

- Déplacement de l'accès `Suivi xAPI` dans la barre principale du cours.
- Compatibilité avec le thème par défaut Delos.
- Utilisation de la route support `Info / showSummary`, puis remplacement du contenu central par l'écran xAPI.
- Correction des liens internes `Tableau de bord`, `Analyse`, `Expert`, `Configuration` afin de rester dans l'écran xAPI.

### Vues ajoutées

```text
Tableau de bord | Analyse | Expert | Configuration
```

- `Tableau de bord` : synthèse de l'activité xAPI du cours.
- `Analyse` : vue pédagogique par ressource.
- `Expert` : traces locales détaillées et export CSV.
- `Configuration` : activation du cours, activation des ressources et personnalisation du dashboard.

## v0.8.0 — supervision outbox et diagnostic des refus

### Objectif

La V0.8.0 ajoute une supervision technique plus complète de l'envoi xAPI.

### Apports

- Supervision outbox dans l'administration du plugin.
- Diagnostic des traces refusées avec table `evnt_evhk_itxeb_dlog`.
- Purge possible du diagnostic.
- Amélioration des informations d'exploitation.
- Maintien du filtrage métier introduit en V0.7.

## v0.7.1 — configuration xAPI depuis le cours

### Objectif

La V0.7.1 ajoute l'accès à la configuration xAPI directement depuis un objet cours.

### Apports

- Configuration du suivi xAPI par cours.
- Activation / désactivation des ressources dans le cours.
- Amélioration de l'ergonomie pour l'administrateur de cours.
- Introduction du plugin compagnon UIHook.

## v0.7.0 — filtrage métier cours / ressource

### Objectif

La V0.7.0 introduit la règle métier d'activation stricte.

```text
statement xAPI autorisé = cours activé ET ressource activée
```

### Apports

- Tables de configuration cours et ressources.
- Filtrage avant génération de statements.
- Réduction du bruit xAPI.
- Meilleur pilotage des traces par les responsables de cours.

## v0.6.0 — base fonctionnelle EventHook / outbox

### Objectif

La V0.6.0 constitue une base stable de génération et d'envoi xAPI.

### Apports

- Captation d'événements ILIAS via EventHook.
- Journal brut `evnt_evhk_itxeb_log`.
- Outbox `evnt_evhk_itxeb_out`.
- Génération de statements xAPI.
- Envoi manuel ou automatique vers TRAX/LRS.
- Retry technique.
