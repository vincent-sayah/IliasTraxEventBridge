# IliasTraxEventBridgeCourseUI — plugin compagnon

Plugin compagnon UIHook du plugin principal `IliasTraxEventBridge`.

## Version courante

| Élément | Valeur |
|---|---|
| Version compagnon | `0.8.50` |
| Version plugin principal associée | `0.28.5-dev` |
| Branche stable | `main` |
| Branche de validation | `v0.28-dashboard-analysis-config-ai-prompt-validated` |
| Commit fonctionnel validé | `eabc786` |

## Rôle

Le compagnon UIHook affiche l'entrée `Pilotage xAPI` dans le cours ILIAS et fournit les vues :

```text
Tableau de bord | Analyse | Analyse IA | Expert | Configuration | Retour contenu du cours
```

## Contrôle d'accès

Depuis V0.28.5, l'affichage du bouton `Pilotage xAPI` suit deux contrôles :

```text
1. l'utilisateur doit administrer le cours ;
2. le cours doit être autorisé par la configuration globale du plugin.
```

La configuration globale permet :

```text
- tous les cours administrés ;
- seulement les cours listés par ref_id.
```

En mode sélection, plusieurs `ref_id` peuvent être ajoutés. Un cours peut être retiré en décochant sa ligne puis en enregistrant.

## Répartition fonctionnelle des vues

### Tableau de bord

Vue de décision rapide. Les blocs d'analyse détaillée ne sont pas dupliqués.

### Analyse

Vue d'investigation des résultats : actions recommandées, matrice ressources, questions à fort taux d'échec, MediaCast et apprenants en difficulté.

### Configuration cours

Configuration locale du cours : activation xAPI du cours, ressources traçables, mode d'affichage du tableau de bord et cartes de synthèse pédagogique.

## Installation / mise à jour du compagnon

Depuis le dossier du plugin principal :

```bash
export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"

bash scripts/install_course_ui_companion_with_standalone_fix.sh

systemctl restart php-fpm
systemctl restart httpd
```

## Fichiers compagnon impactés par V0.28.5

- `plugin.php.tpl`
- `classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl`
- `classes/class.ilIliasTraxEventBridgeCourseUIBridge.php.tpl`
- `classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl`
