# IliasTraxEventBridgeCourseUI — plugin compagnon

Plugin compagnon UIHook du plugin principal `IliasTraxEventBridge`.

## Version courante

| Élément | Valeur |
|---|---|
| Version compagnon | `0.8.47` |
| Version plugin principal associée | `0.27.1-dev` |
| Branche stable | `main` |
| Commit fonctionnel validé | `de90cb1` |

## Rôle

Le compagnon UIHook affiche l'entrée `Pilotage xAPI` dans le cours ILIAS et fournit les vues :

```text
Tableau de bord | Analyse | Analyse IA | Expert | Configuration | Retour contenu du cours
```

## V0.27.1

La version `0.8.47` ajoute le tableau de bord pédagogique amélioré :

- état global du cours ;
- jauge de réussite du cours avec icône diplôme 🎓 ;
- entonnoir pédagogique ;
- actions recommandées ;
- matrice ressources ;
- modes `Compact`, `Standard`, `Complet` ;
- configuration des blocs visibles depuis l'onglet `Configuration`.

## V0.26 intégrée

- affichage du taux de réussite du cours quand la progression ILIAS est paramétrée ;
- configuration des cartes de `Synthèse pédagogique` par cours ;
- icône diplôme 🎓 sur la réussite du cours.

## V0.25.6 intégrée

- affichage du login ILIAS dans `Analyse > Apprenants en difficulté` ;
- ajout de la colonne `Apprenant` dans `Expert` ;
- export CSV Expert avec `learner_identity`.

## Installation depuis le plugin principal

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

## Contrôle

```bash
php -l /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php
php -l /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php
```
