# IliasTraxEventBridgeCourseUI

Plugin compagnon UIHook pour `IliasTraxEventBridge`.

## Version documentée

| Élément | Valeur |
|---|---|
| Branche stable projet | `main` |
| Version stable courante | `0.21.2-dev` validée et promue dans `main` |
| Version companion UI | `0.8.5` |
| Plugin principal | `IliasTraxEventBridge` |
| Plugin compagnon | `IliasTraxEventBridgeCourseUI` |
| Type | UIHook ILIAS |
| Compatibilité | ILIAS 10.x |

## Objectif

Ce plugin compagnon ajoute l'accès au pilotage xAPI directement dans l'objet cours ILIAS.

Accès attendu en V0.21.2 :

```text
Cours > Pilotage xAPI
```

L'écran expose les vues :

```text
Tableau de bord | Analyse | Analyse IA | Expert | Configuration | Retour contenu du cours
```

## Rôle des vues V0.21.2

| Vue | Rôle |
|---|---|
| Tableau de bord | Synthèse pédagogique, activité, ressources, tests, questions à fort taux d'échec, export PDF. |
| Analyse | Analyse formateur des ressources et questions problématiques. |
| Analyse IA | Génération, historique, comparaison et retrait d'analyses IA. |
| Expert | Vision technique détaillée et export CSV. |
| Configuration | Activation cours / ressources, préférences, diagnostic LRS, supervision outbox. |

## Règle métier V0.21.2

```text
TRAX = toutes les questions de test ILIAS sont tracées.
Tableau de bord / Analyse = questions problématiques uniquement.
Analyse IA = questions problématiques uniquement.
Expert = vision technique complète.
```

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

Le script d'installation matérialise les templates `.php.tpl` en vrais fichiers `.php` uniquement dans le slot actif `UserInterfaceHook`.

## Installation serveur

Depuis le serveur ILIAS, avec le dépôt principal déjà présent :

```bash
export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"

cd "$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge"

git fetch origin
git checkout main
git pull --ff-only origin main

bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Si ILIAS n'est pas dans `/var/www/ilias`, remplacer `ILIAS_ROOT` par le chemin réel :

```bash
export ILIAS_ROOT="/data/www/ilias"
export HTTPD_USER="apache"

cd "$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge"
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Depuis V0.21.2, le script tente aussi de déduire `ILIAS_ROOT` depuis le chemin réel du plugin principal. La variable explicite reste recommandée en exploitation.

## Chemin d'installation cible

Le script installe le compagnon ici :

```text
$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

Le plugin principal reste ici :

```text
$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

## Validation

```bash
COMPANION_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI"

php -l "$COMPANION_DIR/plugin.php"
php -l "$COMPANION_DIR/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"

grep -n "Questions à fort taux d’échec\|QuestionRiskRepository" \
"$COMPANION_DIR/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
```

La validation détaillée est décrite dans :

```text
docs/VALIDATION_0.21.2.md
```
