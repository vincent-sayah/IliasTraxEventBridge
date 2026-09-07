# Validation V0.25.6 — login apprenant

## Versions attendues

| Élément | Valeur |
|---|---|
| Plugin principal | `0.25.6-dev` |
| Plugin compagnon UI | `0.8.44` |
| Branche de validation | `v0.25-learner-identity-display` |
| Commit fonctionnel | `8d97685` |

## Contrôle Git

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git status -sb
git log --oneline -5
```

Attendu après promotion :

```text
## main...origin/main
```

Le haut du log doit contenir la documentation V0.25.6 puis le commit fonctionnel :

```text
8d97685 V0.25.6 validate learner login display
```

## Contrôle versions et marqueurs

```bash
grep -n "0.25.6-dev\|0.8.44\|ITXEB V0.25.6 learner login resolution\|lookupIliasLogin\|usr_data\|learner_identity" \
plugin.php \
classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php \
companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
```

Points attendus :

- `plugin.php` contient `0.25.6-dev` ;
- `companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl` contient `0.8.44` ;
- `class.ilIliasTraxEventBridgeLrsCourseSummary.php` contient `learner_identity` ;
- `class.ilIliasTraxEventBridgeLrsCourseSummary.php` contient `lookupIliasLogin` ;
- la requête `usr_data.login` est présente ;
- le template écran contient la colonne `Apprenant`.

## Contrôle absence d'ancien anonymat

```bash
grep -n "anonymous_id\|Vue anonymisée\|Apprenant [a-f0-9]\{8\}" \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl \
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php || true
```

Résultat attendu : aucune ligne.

## Contrôle PHP

```bash
php -l plugin.php
php -l classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php
php -l companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
php -l companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
php -l /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php
php -l /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php
```

Tous les contrôles doivent retourner :

```text
No syntax errors detected
```

## Réinstallation du compagnon après pull main

```bash
export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"

bash scripts/install_course_ui_companion_with_standalone_fix.sh

systemctl restart php-fpm
systemctl restart httpd
```

## Validation navigateur

Après redémarrage :

```text
Ctrl + F5
```

### Onglet Analyse

Dans `Analyse > Apprenants en difficulté`, vérifier que :

- le login ILIAS est affiché ;
- `Apprenant xxxxxxxx` n'est plus affiché ;
- `ilias-user-ID` n'est plus affiché lorsque le login existe dans `usr_data.login`.

### Onglet Expert

Le tableau doit afficher :

```text
Date | User ID | Apprenant | Verbe | Ressource | Type | Score | Completion | Success | Source | Statement ID
```

La colonne `Apprenant` doit contenir le login ILIAS.

### Export CSV Expert

L'export CSV doit contenir la colonne :

```text
learner_identity
```

## Critères d'acceptation

- Analyse nominative validée.
- Expert avec colonne `Apprenant` validé.
- Export CSV enrichi validé.
- Aucune régression visible sur le tableau de bord V0.24.17.
- Aucune régression visible sur le suivi MediaCast V0.23.8.
