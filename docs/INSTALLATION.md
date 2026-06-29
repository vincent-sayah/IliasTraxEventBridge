# Installation — IliasTraxEventBridge V0.10.1

Ce document décrit l'installation complète de la version stable V0.10.1 du plugin `IliasTraxEventBridge` sur ILIAS 10.

## 1. Périmètre

La V0.10.1 contient :

- le plugin principal EventHook `IliasTraxEventBridge` ;
- le plugin compagnon UIHook `IliasTraxEventBridgeCourseUI` ;
- la génération et l'envoi des statements xAPI vers TRAX/LRS ;
- l'écran `Suivi xAPI` dans les cours ;
- la lecture directe de TRAX/LRS pour les vues pédagogiques ;
- l'export CSV Expert ;
- l'export PDF du tableau de bord ;
- la correction stable du fichier `sql/dbupdate.php` avec le marqueur ILIAS `<#1>`.

## 2. Pré-requis

### 2.1 Serveur ILIAS

- ILIAS 10.x.
- Accès shell au serveur.
- Accès au compte système utilisé par le serveur web, par exemple `apache` sur AlmaLinux/RHEL.
- Accès Git vers le dépôt du plugin.
- PHP compatible avec ILIAS 10.
- Extension PHP cURL recommandée pour les appels HTTP vers TRAX/LRS.

### 2.2 TRAX / LRS

Le plugin a besoin d'un endpoint xAPI TRAX/LRS et d'un compte Basic HTTP autorisé à écrire et lire les statements.

Exemple de forme attendue :

```text
https://lrs.example.org/trax/api/<client>/xapi/<store>/statements
```

L'endpoint peut être saisi avec ou sans `/statements`. Le plugin ajoute automatiquement `/statements` si nécessaire.

### 2.3 Chemins ILIAS

Chemin racine utilisé dans les exemples :

```text
/var/www/ilias
```

Chemin cible du plugin EventHook :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

Chemin cible du plugin compagnon UIHook :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

Adapter les chemins si l'installation ILIAS utilise une autre arborescence.

## 3. Installation depuis zéro

Se connecter en root ou avec un compte ayant les droits nécessaires :

```bash
sudo -i
```

Définir les variables :

```bash
export ILIAS_ROOT="/var/www/ilias"
export EVENTHOOK_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook"
export PLUGIN_NAME="IliasTraxEventBridge"
```

Cloner la branche stable V0.10.1 :

```bash
mkdir -p "$EVENTHOOK_DIR"
cd "$EVENTHOOK_DIR"

git clone -b v0.10-lrs-direct-read --single-branch https://github.com/vincent-sayah/IliasTraxEventBridge.git "$PLUGIN_NAME"
cd "$PLUGIN_NAME"
```

Vérifier la version :

```bash
grep -n '\$version' plugin.php
```

Résultat attendu :

```text
$version = '0.10.1';
```

Vérifier la correction de migration ILIAS :

```bash
head -5 sql/dbupdate.php
```

Résultat attendu :

```text
<#1>
<?php
/** @var ilDBInterface $ilDB */
```

Vérifier la syntaxe PHP :

```bash
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

## 4. Installation du plugin compagnon UIHook

Le plugin compagnon ajoute l'accès `Suivi xAPI` dans l'objet cours.

Depuis le dossier du plugin principal :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Le script génère ou met à jour le plugin compagnon dans :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

## 5. Reconstruction ILIAS

Après installation ou mise à jour de fichiers plugin :

```bash
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
```

Si l'environnement utilise PHP-FPM :

```bash
systemctl restart php-fpm
```

Selon l'installation, le service PHP-FPM peut avoir un autre nom, par exemple `php83-php-fpm`.

## 6. Installation dans l'interface ILIAS

Dans ILIAS :

```text
Administration > Plugins
```

Installer ou mettre à jour :

```text
IliasTraxEventBridge
IliasTraxEventBridgeCourseUI
```

Le plugin principal doit être installé avant ou en même temps que le plugin compagnon.

## 7. Configuration TRAX / LRS

Aller dans :

```text
Administration > Plugins > IliasTraxEventBridge > Configurer
```

Renseigner :

| Champ | Valeur attendue |
|---|---|
| Endpoint xAPI TRAX | URL xAPI racine ou URL `/statements`. |
| Identifiant client TRAX | Compte Basic HTTP xAPI. |
| Secret client TRAX | Mot de passe ou secret du compte xAPI. |
| Version xAPI | `1.0.3`. |
| Timeout HTTP | Exemple : `10`. |
| Taille batch | Exemple : `20` ou `50`. |
| Max retry | Exemple : `5`. |
| Base URL ILIAS forcée | Optionnel, utile si ILIAS est derrière reverse proxy. |

Enregistrer, puis utiliser le test de connexion TRAX si disponible dans l'interface.

## 8. Configuration d'un cours

Dans le cours :

```text
Cours > Suivi xAPI > Configuration
```

Procédure :

1. activer le suivi xAPI du cours ;
2. sélectionner les ressources à tracer ;
3. enregistrer ;
4. générer des actions utilisateur sur les ressources ;
5. contrôler les onglets `Tableau de bord`, `Analyse` et `Expert`.

La règle métier est stricte :

```text
statement généré = cours activé ET ressource activée
```

## 9. Activation du cron

Dans la configuration du plugin, activer :

```text
Activer le cron plugin
```

Puis dans ILIAS :

```text
Administration > Paramètres système et maintenance > Tâches cron
```

Activer le job :

```text
IliasTraxEventBridge — envoi outbox vers TRAX
```

Identifiant technique :

```text
itxeb_send_outbox_to_trax
```

Le cron envoie les statements présents dans l'outbox locale vers TRAX/LRS.

## 10. Mise à jour depuis une ancienne version

Se placer dans le plugin existant :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

Sauvegarder l'état local :

```bash
git status
cp plugin.php plugin.php.bak.$(date +%Y%m%d_%H%M%S)
cp sql/dbupdate.php sql/dbupdate.php.bak.$(date +%Y%m%d_%H%M%S)
```

Mettre à jour :

```bash
git fetch origin
git checkout v0.10-lrs-direct-read
git pull origin v0.10-lrs-direct-read
```

Vérifier :

```bash
grep -n '\$version' plugin.php
head -5 sql/dbupdate.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

Réinstaller le plugin compagnon :

```bash
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Rebuild ILIAS :

```bash
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
systemctl restart php-fpm
```

Dans ILIAS :

```text
Administration > Plugins > IliasTraxEventBridge > Mettre à jour
Administration > Plugins > IliasTraxEventBridgeCourseUI > Mettre à jour
```

## 11. Cas particulier : ancienne V0.6 désinstallée

Si une V0.6 a été désinstallée avant installation de la V0.10.1, il peut rester des tables SQL `evnt_evhk_itxeb_*`.

La V0.10.1 est prévue pour gérer les tables existantes avec des tests `tableExists` et `tableColumnExists`.

Contrôler les tables :

```sql
SHOW TABLES LIKE 'evnt_evhk_itxeb%';
```

Ne supprimer les tables que si l'objectif est de repartir de zéro et après sauvegarde.

Suppression complète possible, uniquement si les traces et configurations peuvent être perdues :

```sql
DROP TABLE IF EXISTS evnt_evhk_itxeb_dlog;
DROP TABLE IF EXISTS evnt_evhk_itxeb_rcfg;
DROP TABLE IF EXISTS evnt_evhk_itxeb_ccfg;
DROP TABLE IF EXISTS evnt_evhk_itxeb_read;
DROP TABLE IF EXISTS evnt_evhk_itxeb_out;
DROP TABLE IF EXISTS evnt_evhk_itxeb_log;
```

## 12. Contrôles post-installation

### 12.1 Contrôle plugin

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

grep -n '\$version' plugin.php
head -5 sql/dbupdate.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

### 12.2 Contrôle SQL

```sql
SHOW TABLES LIKE 'evnt_evhk_itxeb%';

SELECT id, event_type, status, created_at, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 20;
```

### 12.3 Contrôle fonctionnel

1. ouvrir un cours ;
2. aller dans `Suivi xAPI > Configuration` ;
3. activer le cours ;
4. activer une ressource ;
5. ouvrir la ressource avec un utilisateur ;
6. lancer le cron ou l'envoi manuel ;
7. vérifier que TRAX reçoit les statements ;
8. vérifier les onglets `Tableau de bord`, `Analyse`, `Expert`.

## 13. Dépannage rapide

### 13.1 Erreur `Undefined array key ...` à l'installation

Contrôler le début de `sql/dbupdate.php` :

```bash
head -5 sql/dbupdate.php
```

Le fichier doit commencer par :

```text
<#1>
<?php
```

Si ce n'est pas le cas, la version installée n'est pas la V0.10.1 stable.

### 13.2 Le lien `Suivi xAPI` n'apparaît pas

Contrôler que le plugin compagnon est installé :

```text
Administration > Plugins > IliasTraxEventBridgeCourseUI
```

Relancer :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
bash scripts/install_course_ui_companion_with_standalone_fix.sh
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
```

### 13.3 Aucune donnée dans le tableau de bord

Vérifier :

- configuration TRAX complète ;
- cours activé ;
- ressources activées ;
- statements réellement reçus dans TRAX ;
- compte TRAX autorisé à lire les statements ;
- cohérence de la base URL ILIAS utilisée dans les activités xAPI.

### 13.4 Statements générés mais non envoyés

Vérifier l'outbox :

```sql
SELECT status, COUNT(*) AS total
FROM evnt_evhk_itxeb_out
GROUP BY status;
```

Vérifier le cron ILIAS et le diagnostic du plugin.

## 14. Tag stable recommandé

Après validation sur environnement cible :

```bash
git tag -a v0.10.1 -m "Release stable v0.10.1"
git push origin v0.10.1
```
