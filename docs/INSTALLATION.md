# Installation — IliasTraxEventBridge V0.22.4

Ce document décrit l'installation complète de la version stable validée V0.22.4 du plugin `IliasTraxEventBridge` sur ILIAS 10.

## 1. Périmètre

La V0.22.4 contient :

- le plugin principal EventHook `IliasTraxEventBridge` ;
- le plugin compagnon UIHook `IliasTraxEventBridgeCourseUI` ;
- la génération et l'envoi des statements xAPI vers TRAX/LRS ;
- l'accès cours `Pilotage xAPI` ;
- les vues `Tableau de bord`, `Analyse`, `Analyse IA`, `Expert`, `Configuration` ;
- le suivi des tests ILIAS avec traces question par question ;
- le bloc `Questions à fort taux d’échec` dans Tableau de bord et Analyse ;
- l'intégration des questions problématiques dans le payload Analyse IA ;
- le bloc `Activité dans le temps` compact ;
- une présentation titre/données proche des formulaires ILIAS ;
- la correction du retour d'onglet après retrait d'une analyse IA ;
- l'export CSV Expert ;
- l'export PDF du tableau de bord ;
- la documentation V0.22.4.

## 2. Pré-requis

### 2.1 Serveur ILIAS

- ILIAS 10.x.
- Accès shell au serveur.
- Accès au compte système utilisé par le serveur web, par exemple `apache` sur AlmaLinux/RHEL.
- Accès Git vers le dépôt du plugin.
- PHP compatible avec ILIAS 10.
- Extension PHP cURL recommandée pour les appels HTTP vers TRAX/LRS et fournisseur IA.

### 2.2 TRAX / LRS

Le plugin a besoin d'un endpoint xAPI TRAX/LRS et d'un compte Basic HTTP autorisé à écrire et lire les statements.

Exemple de forme attendue :

```text
https://lrs.example.org/trax/api/<client>/xapi/<store>/statements
```

L'endpoint peut être saisi avec ou sans `/statements`. Le plugin ajoute automatiquement `/statements` si nécessaire.

### 2.3 Chemins ILIAS

Les exemples utilisent une variable unique :

```bash
export ILIAS_ROOT="/var/www/ilias"
```

Si ILIAS n'est pas installé dans `/var/www/ilias`, remplacer cette valeur par le chemin réel.

Exemples :

```bash
export ILIAS_ROOT="/var/www/html/ilias"
export ILIAS_ROOT="/data/www/ilias"
export ILIAS_ROOT="/srv/ilias"
```

Chemin cible du plugin EventHook :

```text
$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

Chemin cible du plugin compagnon UIHook :

```text
$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

## 3. Installation depuis zéro

Se connecter en root ou avec un compte ayant les droits nécessaires :

```bash
sudo -i
```

Définir les variables :

```bash
export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"
export EVENTHOOK_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook"
export PLUGIN_NAME="IliasTraxEventBridge"
```

Créer le slot EventHook et cloner la version stable promue dans `main` :

```bash
mkdir -p "$EVENTHOOK_DIR"
cd "$EVENTHOOK_DIR"

git clone -b main --single-branch https://github.com/vincent-sayah/IliasTraxEventBridge.git "$PLUGIN_NAME"
cd "$PLUGIN_NAME"
```

Ne plus utiliser l'ancienne branche :

```text
v0.10-lrs-direct-read
```

Cette branche correspond à une ancienne version historique. La référence stable actuelle est `main`.

Vérifier la version :

```bash
grep -n '\$version' plugin.php
grep -n '\$version' companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
```

Résultat attendu :

```text
$version = '0.22.4-dev';
$version = '0.8.10';
```

Vérifier la syntaxe PHP :

```bash
php -l plugin.php
php -l classes/class.ilIliasTraxEventBridgeEventRouter.php
php -l classes/class.ilIliasTraxEventBridgeStatementFactory.php
php -l classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php
php -l classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php
php -l classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php
```

## 4. Installation du plugin compagnon UIHook

Le plugin compagnon ajoute l'accès `Pilotage xAPI` dans l'objet cours.

### 4.1 Cas standard

Depuis le dossier du plugin principal :

```bash
cd "$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge"
export ILIAS_ROOT="$ILIAS_ROOT"
export HTTPD_USER="apache"

bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Le script génère ou met à jour le plugin compagnon dans :

```text
$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

### 4.2 Cas où ILIAS n'est pas dans `/var/www/ilias`

Le script accepte explicitement la variable `ILIAS_ROOT`.

Exemple avec ILIAS installé dans `/data/www/ilias` :

```bash
export ILIAS_ROOT="/data/www/ilias"
export HTTPD_USER="apache"

cd "$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge"
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Depuis V0.21.2, le script tente aussi de déduire automatiquement `ILIAS_ROOT` à partir du chemin réel du plugin principal. La variable explicite reste toutefois recommandée en exploitation.

### 4.3 Contrôle du compagnon

```bash
COMPANION_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI"

ls -la "$COMPANION_DIR"
php -l "$COMPANION_DIR/plugin.php"
php -l "$COMPANION_DIR/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
```

Contrôler les marqueurs V0.22.4 :

```bash
grep -n "Activité dans le temps\|V0.22.4 alignment\|showCourseAiAnalysis" \
"$COMPANION_DIR/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
```

## 5. Reconstruction ILIAS

Après installation ou mise à jour de fichiers plugin :

```bash
cd "$ILIAS_ROOT"
sudo -u "$HTTPD_USER" composer du
sudo -u "$HTTPD_USER" php cli/setup.php build --yes
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

Renseigner : endpoint xAPI TRAX, identifiant, secret, version xAPI `1.0.3`, timeout HTTP, taille batch, max retry et éventuellement une Base URL ILIAS forcée.

## 8. Configuration IA optionnelle

Dans la configuration du plugin, renseigner uniquement si l'analyse IA doit être utilisée : fournisseur IA, URL API, modèle, timeout, anonymisation et limite de traces.

La clé API ne doit jamais être affichée en clair.

## 9. Configuration d'un cours

Dans le cours :

```text
Cours > Pilotage xAPI > Configuration
```

Procédure :

1. activer le suivi d'apprentissage du cours ;
2. sélectionner les ressources à tracer ;
3. enregistrer ;
4. générer des actions utilisateur sur les ressources ;
5. contrôler les onglets `Tableau de bord`, `Analyse`, `Analyse IA`, `Expert`.

La règle métier est stricte :

```text
statement généré = cours activé ET ressource activée
```

## 10. Activation du cron

Dans la configuration du plugin, activer le cron plugin puis dans ILIAS activer le job :

```text
IliasTraxEventBridge — envoi outbox vers TRAX
```

Identifiant technique :

```text
itxeb_send_outbox_to_trax
```

## 11. Mise à jour depuis une ancienne version

```bash
export ILIAS_ROOT="/var/www/ilias"
cd "$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge"

git fetch origin
git checkout main
git pull --ff-only origin main

export HTTPD_USER="apache"
bash scripts/install_course_ui_companion_with_standalone_fix.sh

cd "$ILIAS_ROOT"
sudo -u "$HTTPD_USER" composer du
sudo -u "$HTTPD_USER" php cli/setup.php build --yes
systemctl restart httpd
systemctl restart php-fpm
```

Dans ILIAS :

```text
Administration > Plugins > IliasTraxEventBridge > Mettre à jour
Administration > Plugins > IliasTraxEventBridgeCourseUI > Mettre à jour
```

## 12. Contrôles post-installation

### 12.1 Contrôle plugin

```bash
cd "$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge"

grep -n '\$version' plugin.php
grep -n '\$version' companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

### 12.2 Contrôle fonctionnel V0.22.4

Dans ILIAS :

```text
Pilotage xAPI > Tableau de bord
```

Vérifier :

- bloc `Activité dans le temps` ;
- choix `7 jours`, `14 jours`, `30 jours`, `Par semaine`, `Détail complet` ;
- présentation titre/données alignée ;
- `Synthèse pédagogique` alignée comme les autres blocs.

Dans :

```text
Pilotage xAPI > Analyse IA
```

Vérifier :

- retrait d'une analyse IA historisée ;
- après validation, l'onglet `Analyse IA` reste sélectionné.

### 12.3 Contrôle SQL questions de test

Après une tentative de test ILIAS :

```sql
SELECT id, event_type, verb_id, ref_id, obj_type, status, created_at
FROM evnt_evhk_itxeb_out
WHERE statement_json LIKE '%question_id%'
ORDER BY id DESC
LIMIT 20;
```

## 13. Dépannage rapide

### Le script compagnon cherche `/var/www/ilias` alors que l'installation est ailleurs

Définir explicitement `ILIAS_ROOT` avant de lancer le script :

```bash
export ILIAS_ROOT="/chemin/reel/vers/ilias"
cd "$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge"
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

### Le lien `Pilotage xAPI` n'apparaît pas

Contrôler que le plugin compagnon est installé puis relancer le script compagnon avec le bon `ILIAS_ROOT`.

### Statements générés mais non envoyés

```sql
SELECT status, COUNT(*) AS total
FROM evnt_evhk_itxeb_out
GROUP BY status;
```

Vérifier ensuite le cron ILIAS et le diagnostic du plugin.

## 14. Tag stable recommandé

La V0.22.4 est promue dans `main`.

Si un tag de release doit être créé après validation finale :

```bash
git tag -a v0.22.4 -m "Release stable v0.22.4"
git push origin v0.22.4
```
