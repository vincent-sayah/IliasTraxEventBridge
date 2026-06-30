# IliasTraxEventBridgeCourseUI

Plugin compagnon UIHook pour `IliasTraxEventBridge`.

## Version documentée

| Élément | Valeur |
|---|---|
| Version stable projet | `0.11.0` |
| Branche stable | `main` |
| Tag stable | `v0.11.0` |
| Plugin principal | `IliasTraxEventBridge` |
| Plugin compagnon | `IliasTraxEventBridgeCourseUI` |
| Type | UIHook ILIAS |

## Objectif

Ce plugin compagnon ajoute l'accès au suivi xAPI directement dans l'objet cours ILIAS.

Accès attendu en V0.11.0 :

```text
Cours > Suivi xAPI
```

L'écran `Suivi xAPI` expose quatre vues :

```text
Tableau de bord | Analyse | Expert | Configuration
```

Le plugin principal `IliasTraxEventBridge` reste responsable de :

- la captation EventHook ;
- la génération des statements xAPI ;
- l'outbox locale ;
- le cron ;
- l'envoi vers TRAX/LRS ;
- la lecture directe TRAX/LRS ;
- les tables `evnt_evhk_itxeb_*` ;
- le filtrage avant outbox ;
- la configuration globale TRAX/LRS ;
- la section d'administration `Santé / Diagnostic V0.11`.

Le plugin compagnon est responsable de l'intégration UI dans le cours et de l'affichage des vues de suivi.

## Rôle des vues

| Vue | Rôle |
|---|---|
| Tableau de bord | Synthèse pédagogique alimentée par TRAX/LRS. |
| Analyse | Analyse des ressources, verbes retournés par TRAX, ressources retournées par TRAX. |
| Expert | Statements TRAX détaillés et export CSV. |
| Configuration | Activation cours / ressources, préférences dashboard, diagnostic LRS, supervision outbox. |

## Packaging

Les fichiers PHP du compagnon ne sont pas stockés directement comme fichiers actifs dans le dossier source `companion/`.

Ils sont stockés sous forme de templates :

```text
plugin.php.tpl
classes/*.php.tpl
```

Objectif : éviter que Composer voie deux copies des mêmes classes lorsque :

```text
1. le dépôt principal est cloné dans EventHandling/EventHook/IliasTraxEventBridge ;
2. le compagnon est installé dans UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI.
```

Sans ce packaging, Composer peut produire des warnings du type :

```text
Ambiguous class resolution, "ilIliasTraxEventBridgeCourseUIPlugin" was found in both ...
```

Le script d'installation matérialise les templates `.php.tpl` en vrais fichiers `.php` uniquement dans le slot actif `UserInterfaceHook`.

## Installation serveur

Depuis le serveur ILIAS, avec le dépôt principal déjà présent :

```bash
sudo -i
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

bash scripts/install_course_ui_companion_with_standalone_fix.sh

cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
systemctl restart php-fpm
```

Variables optionnelles :

```bash
ILIAS_ROOT=/var/www/ilias
HTTPD_USER=apache
```

Exemple avec variables explicites :

```bash
ILIAS_ROOT=/var/www/ilias HTTPD_USER=apache bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

## Chemin d'installation cible

Le script installe le compagnon ici :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

Le plugin principal reste ici :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```
