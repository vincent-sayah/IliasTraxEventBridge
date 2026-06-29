# Documentation développeur — IliasTraxEventBridge V0.10.1

## 1. Objectif

Ce document aide à maintenir et faire évoluer le plugin `IliasTraxEventBridge`.

Il complète :

- `docs/FONCTIONNEL.md` ;
- `docs/TECHNIQUE.md` ;
- `docs/INSTALLATION.md` ;
- `docs/EXPLOITATION.md`.

## 2. Structure générale

```text
IliasTraxEventBridge/
├── plugin.php
├── classes/
├── sql/
│   └── dbupdate.php
├── scripts/
├── companion/
├── docs/
└── README.md
```

## 3. Version plugin

La version du plugin principal est déclarée dans :

```text
plugin.php
```

V0.10.1 attendue :

```php
$id = 'itxeb';
$version = '0.10.1';
$ilias_min_version = '10.0.0';
$ilias_max_version = '10.999.999';
```

À chaque release stable, mettre à jour :

- `plugin.php` ;
- `README.md` ;
- `CHANGELOG.md` ;
- `docs/RELEASE_<version>.md` ;
- la documentation impactée.

## 4. Règle de migration SQL ILIAS

Le fichier :

```text
sql/dbupdate.php
```

Doit être découpé en étapes ILIAS :

```php
<#1>
<?php
// migration 1
?>
<#2>
<?php
// migration 2
?>
```

Point critique V0.10.1 : le fichier doit commencer par `<#1>`.

Contrôle :

```bash
head -5 sql/dbupdate.php
```

Résultat attendu :

```text
<#1>
<?php
/** @var ilDBInterface $ilDB */
```

## 5. Principes de développement

### 5.1 Ne pas bloquer ILIAS

Le plugin EventHook ne doit pas casser la navigation utilisateur.

Toute erreur non critique doit être interceptée et journalisée.

### 5.2 Garder TRAX/LRS comme source pédagogique

En V0.10.1, les vues pédagogiques ne doivent pas être réalimentées depuis l'outbox locale.

L'outbox est purgeable et technique.

### 5.3 Conserver l'opt-in cours / ressource

La règle suivante doit rester vraie :

```text
statement généré = cours activé ET ressource activée
```

### 5.4 Préserver la compatibilité ILIAS 10

Les signatures de méthodes héritées d'ILIAS doivent rester compatibles avec ILIAS 10.

Exemple :

```php
public function handleEvent(string $a_component, string $a_event, array $a_parameter): void
```

## 6. Classes principales

### 6.1 Plugin principal

```text
classes/class.ilIliasTraxEventBridgePlugin.php
```

Rôles :

- point d'entrée EventHook ;
- fournisseur du job cron ;
- gestion non bloquante des erreurs ;
- nettoyage des settings à la désinstallation.

### 6.2 Configuration

```text
classes/class.ilIliasTraxEventBridgeConfig.php
```

Rôles :

- lire les paramètres plugin ;
- écrire les paramètres plugin ;
- normaliser l'endpoint `/statements` ;
- fournir les valeurs nécessaires aux clients HTTP.

### 6.3 Interface admin

```text
classes/class.ilIliasTraxEventBridgeConfigGUI.php
```

Rôles :

- configuration TRAX/LRS ;
- actions manuelles outbox ;
- supervision ;
- accès à la configuration par cours ;
- diagnostic.

### 6.4 Routage événements

```text
classes/class.ilIliasTraxEventBridgeEventRouter.php
```

Rôles :

- analyser les événements ILIAS ;
- décider s'ils sont exploitables ;
- appeler la génération xAPI ;
- journaliser les refus si le diagnostic est activé.

### 6.5 Génération xAPI

```text
classes/class.ilIliasTraxEventBridgeStatementFactory.php
```

Rôles :

- construire l'acteur ;
- construire le verbe ;
- construire l'objet ;
- ajouter le contexte cours ;
- ajouter les extensions utiles ;
- intégrer score, succès, complétion si disponibles.

### 6.6 Outbox

```text
classes/class.ilIliasTraxEventBridgeOutboxRepository.php
classes/class.ilIliasTraxEventBridgeOutboxSender.php
```

Rôles :

- insérer les statements ;
- lire les statements à envoyer ;
- passer les statuts `generated`, `sending`, `sent`, `failed` ;
- gérer les retries ;
- stocker les erreurs d'envoi.

### 6.7 Clients HTTP

Écriture :

```text
classes/class.ilIliasTraxEventBridgeTraxClient.php
```

Lecture :

```text
classes/class.ilIliasTraxEventBridgeLrsReadClient.php
```

Le client de lecture ne doit jamais envoyer de statement.

### 6.8 Agrégation LRS

```text
classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php
```

Rôles :

- appeler le LRS ;
- suivre la pagination `more` ;
- construire les KPI ;
- construire les lignes Expert ;
- regrouper par ressource ;
- calculer activité par jour et verbes ;
- signaler les limites de pagination.

## 7. Plugin compagnon UIHook

Le compagnon est géré par templates et scripts.

Dossier source :

```text
companion/
```

Script recommandé :

```bash
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

À chaque évolution du compagnon :

1. modifier les templates ou scripts source ;
2. réinstaller le compagnon ;
3. reconstruire ILIAS ;
4. vérifier le lien `Suivi xAPI` dans un cours ;
5. vérifier les vues internes.

## 8. Ajout d'un nouveau type d'événement

Procédure recommandée :

1. identifier l'événement ILIAS dans `evnt_evhk_itxeb_log` ;
2. vérifier les paramètres disponibles dans `payload_json` ;
3. ajouter la reconnaissance dans le routeur ;
4. résoudre le contexte cours / ressource ;
5. ajouter le mapping dans `StatementFactory` ;
6. ajouter le type `event_type` ;
7. vérifier l'opt-in cours / ressource ;
8. tester la génération outbox ;
9. tester l'envoi TRAX ;
10. vérifier la lecture dans les vues LRS.

## 9. Ajout d'une donnée au tableau de bord

Procédure recommandée :

1. vérifier que la donnée existe dans les statements TRAX ;
2. ajouter l'extraction dans `LrsCourseSummary` ;
3. ajouter l'affichage dans le plugin compagnon ;
4. vérifier que l'affichage reste robuste si la donnée est absente ;
5. mettre à jour la documentation fonctionnelle et technique.

Ne pas alimenter une nouvelle donnée pédagogique depuis l'outbox locale.

## 10. Export PDF

L'export PDF doit utiliser le modèle TRAX/LRS.

Ordre de sélection :

```text
Dompdf -> wkhtmltopdf -> HTML imprimable
```

Les modifications d'export doivent être testées avec :

- moteur PDF disponible ;
- moteur PDF absent ;
- cours sans trace ;
- cours avec traces ;
- filtre période ;
- filtre ressource.

## 11. Contrôles avant commit

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

find . -name "*.php" -print0 | xargs -0 -n1 php -l
grep -n '\$version' plugin.php
head -5 sql/dbupdate.php
git status
git diff --check
```

Résultats attendus :

```text
aucune erreur PHP
$version cohérente
sql/dbupdate.php commence par <#1>
pas d'espaces invalides détectés par git diff --check
```

## 12. Contrôles avant release

1. installation depuis zéro ;
2. mise à jour depuis la version précédente ;
3. installation après désinstallation d'une ancienne version ;
4. installation du compagnon ;
5. rebuild ILIAS ;
6. installation depuis l'interface ILIAS ;
7. activation d'un cours ;
8. activation d'une ressource ;
9. génération d'une trace ;
10. envoi vers TRAX ;
11. lecture TRAX dans Tableau de bord ;
12. lecture TRAX dans Analyse ;
13. lecture TRAX dans Expert ;
14. export CSV ;
15. export PDF ;
16. purge outbox sans perte d'affichage pédagogique.

## 13. Préparation d'un tag stable

Mettre à jour la version :

```bash
grep -n '\$version' plugin.php
```

Créer le tag :

```bash
git tag -a v0.10.1 -m "Release stable v0.10.1"
git push origin v0.10.1
```

## 14. Bonnes pratiques de documentation

À chaque évolution fonctionnelle ou technique :

- mettre à jour `README.md` si l'usage change ;
- mettre à jour `CHANGELOG.md` ;
- mettre à jour `docs/FONCTIONNEL.md` si le comportement utilisateur change ;
- mettre à jour `docs/TECHNIQUE.md` si l'architecture change ;
- mettre à jour `docs/INSTALLATION.md` si la procédure change ;
- mettre à jour `docs/EXPLOITATION.md` si les contrôles changent ;
- ajouter une note de release si la version est livrée.
