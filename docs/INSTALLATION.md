# Installation — IliasTraxEventBridge V0.25.6

Ce document décrit l'installation et la mise à jour de la version stable validée V0.25.6 du plugin `IliasTraxEventBridge` sur ILIAS 10.

## 1. Périmètre

La V0.25.6 contient :

- le plugin principal EventHook `IliasTraxEventBridge` ;
- le plugin compagnon UIHook `IliasTraxEventBridgeCourseUI` ;
- la génération et l'envoi des statements xAPI vers TRAX/LRS ;
- l'accès cours `Pilotage xAPI` ;
- les vues `Tableau de bord`, `Analyse`, `Analyse IA`, `Expert`, `Configuration` ;
- le suivi des tests ILIAS avec traces question par question ;
- le bloc `Questions à fort taux d’échec` dans Tableau de bord et Analyse lorsque le contexte est pertinent ;
- l'intégration des questions problématiques dans le payload Analyse IA ;
- le bloc `Activité dans le temps` avec graphique linéaire SVG ;
- l'alignement `Activité dans le temps` / `Top ressources` validé en V0.24.17 ;
- le suivi MediaCast validé en V0.23.8 ;
- le bloc `Apprenants en difficulté` avec affichage du login ILIAS ;
- la colonne `Apprenant` dans l'onglet Expert ;
- la colonne `learner_identity` dans l'export CSV Expert ;
- l'export PDF du tableau de bord ;
- la documentation V0.25.6.

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

Vérifier les versions :

```bash
grep -n '\$version' plugin.php
grep -n '\$version' companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
```

Résultat attendu après promotion V0.25.6 :

```text
$version = '0.25.6-dev';
$version = '0.8.44';
```

## 4. Installation du plugin compagnon UIHook

Le plugin compagnon ajoute l'accès `Pilotage xAPI` dans l'objet cours.

Depuis le dossier du plugin principal :

```bash
export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"

cd "$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge"
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Le script génère ou met à jour le plugin compagnon dans :

```text
$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

Si ILIAS n'est pas dans `/var/www/ilias`, définir `ILIAS_ROOT` avec le chemin réel avant de lancer le script.

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
export HTTPD_USER="apache"
cd "$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge"

git fetch origin
git checkout main
git pull --ff-only origin main

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

## 12. Contrôles post-installation V0.25.6

### 12.1 Contrôle versions et marqueurs

```bash
cd "$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge"

grep -n "0.25.6-dev\|0.8.44\|ITXEB V0.25.6 learner login resolution\|lookupIliasLogin\|usr_data\|learner_identity" \
plugin.php \
classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php \
companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
```

### 12.2 Contrôle PHP

```bash
php -l plugin.php
php -l classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php
php -l companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
php -l companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
php -l "$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php"
php -l "$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
```

### 12.3 Contrôle fonctionnel

Dans ILIAS :

```text
Pilotage xAPI > Analyse
```

Vérifier que le bloc `Apprenants en difficulté` affiche le login ILIAS.

Dans :

```text
Pilotage xAPI > Expert
```

Vérifier que le tableau affiche :

```text
Date | User ID | Apprenant | Verbe | Ressource | Type | Score | Completion | Success | Source | Statement ID
```

La colonne `Apprenant` doit afficher le login ILIAS.

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

### L'apprenant s'affiche encore sous la forme `ilias-user-ID`

Vérifier que le login existe dans ILIAS :

```sql
SELECT usr_id, login
FROM usr_data
WHERE usr_id IN (6, 401);
```

Vérifier ensuite que le marqueur V0.25.6 est présent :

```bash
grep -n "ITXEB V0.25.6 learner login resolution\|lookupIliasLogin" classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php
```

## 14. Tag stable recommandé

Après promotion de V0.25.6 dans `main`, un tag peut être créé :

```bash
git tag -a v0.25.6 -m "Release stable v0.25.6"
git push origin v0.25.6
```
