# IliasTraxEventBridgeCourseUI

Plugin compagnon UIHook pour `IliasTraxEventBridge`.

## Version documentée

| Élément | Valeur |
|---|---|
| Version stable projet sur `main` | `0.12.0` |
| Branche stable | `main` |
| Dernier tag publié | `v0.11.0` |
| Tag V0.12 | `v0.12.0` à créer après validation finale de la promotion |
| Plugin principal | `IliasTraxEventBridge` |
| Plugin compagnon | `IliasTraxEventBridgeCourseUI` |
| Type | UIHook ILIAS |
| Source pédagogique | TRAX/LRS |
| Rôle de l'outbox locale | File technique d'envoi uniquement |

## Objectif

Ce plugin compagnon ajoute l'accès au suivi xAPI directement dans l'objet cours ILIAS.

Accès attendu en V0.12.0 :

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
- l'outbox locale technique ;
- le cron ;
- l'envoi vers TRAX/LRS ;
- la lecture directe TRAX/LRS ;
- les tables `evnt_evhk_itxeb_*` ;
- le filtrage avant outbox ;
- la configuration globale TRAX/LRS ;
- la section d'administration `Santé / Diagnostic V0.11` conservée ;
- le calcul des indicateurs pédagogiques V0.12 à partir de TRAX/LRS.

Le plugin compagnon est responsable de l'intégration UI dans le cours et de l'affichage des vues de suivi.

## Rôle des vues V0.12

| Vue | Rôle |
|---|---|
| Tableau de bord | Synthèse pédagogique alimentée par TRAX/LRS, compteurs OK / À surveiller / Critique / Sans trace, activité, top ressources et export PDF. |
| Analyse | Analyse des ressources avec statut pédagogique, raison, taux d'échec, score moyen, ressources sans trace et apprenants en difficulté anonymisés. |
| Expert | Statements TRAX détaillés et export CSV enrichi avec les colonnes pédagogiques V0.12. |
| Configuration | Activation cours / ressources, préférences dashboard, diagnostic LRS, supervision technique outbox. |

## Points ergonomiques V0.12

- Bouton `Export PDF` placé dans l'en-tête du tableau de bord.
- Blocs et tableaux mieux encadrés.
- Titres de blocs renforcés.
- `À surveiller` colorisé en orange.
- `Critique` colorisé en rouge.
- Colonne `Raison` de l'onglet Analyse rendue plus lisible.

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
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin
git checkout main
git pull origin main

bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Ensuite reconstruire ILIAS et redémarrer les services selon la procédure habituelle de l'environnement.

## Chemin d'installation cible

Le script installe le compagnon ici :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

Le plugin principal reste ici :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

## Validation V0.12

La validation détaillée est décrite dans :

```text
docs/VALIDATION_0.12.md
```
