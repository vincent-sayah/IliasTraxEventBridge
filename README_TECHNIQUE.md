# README technique — IliasTraxEventBridge

## Version technique courante

| Élément | Valeur |
|---|---|
| Version plugin principal | `0.28.5-dev` |
| Version plugin compagnon UI | `0.8.50` |
| Branche stable | `main` |
| Branche de validation | `v0.28-dashboard-analysis-config-ai-prompt-validated` |
| Commit fonctionnel validé | `eabc786` |

## Architecture

```text
ILIAS 10
  ├─ EventHook IliasTraxEventBridge
  │    ├─ capte les événements ILIAS
  │    ├─ génère les statements xAPI
  │    ├─ génère les traces question par question
  │    ├─ génère les traces MediaCast client
  │    ├─ alimente l'outbox locale
  │    └─ lit la configuration globale du plugin
  │
  └─ UIHook IliasTraxEventBridgeCourseUI
       ├─ ajoute le bouton Pilotage xAPI dans les cours autorisés
       ├─ affiche Tableau de bord / Analyse / Analyse IA / Expert / Configuration
       └─ lit les données agrégées TRAX/LRS et la progression ILIAS
```

## Sources de données

| Donnée | Source |
|---|---|
| Statements xAPI | TRAX/LRS |
| Statut d'envoi | Outbox locale |
| Taux de réussite du cours | Progression ILIAS du cours |
| Configuration cours / ressources | Tables `evnt_evhk_itxeb_ccfg` et `evnt_evhk_itxeb_rcfg` |
| Préférences tableau de bord | `dashboard_widgets_json` |
| Cartes de synthèse pédagogique | `synthesis_cards_json` |
| Accès bouton Pilotage xAPI | `ilSetting` module `itxeb` |
| Prompt système IA | `ilSetting` module `itxeb`, clé `ai_system_prompt` |

## Évolutions techniques V0.28.5

### Séparation Tableau de bord / Analyse

La V0.28.5 évite de dupliquer les blocs entre les deux vues :

```text
Tableau de bord = lecture rapide, indicateurs principaux, décision immédiate.
Analyse = investigation détaillée, ressources, signaux et éléments à traiter.
```

Les blocs `Actions recommandées`, `Matrice ressources` et `Questions à fort taux d'échec` sont réservés à l'analyse des résultats. La `Synthèse pédagogique` reste côté tableau de bord.

### Modes du tableau de bord

Les modes ne sont plus seulement une sélection visuelle. Ils appliquent un vrai préréglage :

| Mode | Contenu |
|---|---|
| Compact | état global, réussite du cours, entonnoir pédagogique |
| Standard | compact + synthèse pédagogique, activité par jour, top ressources |
| Complet | standard + comparaison entre périodes, actions xAPI, ressources sans activité |

### Bouton Pilotage xAPI par cours

La configuration globale permet deux modes :

```text
all      = bouton visible dans tous les cours administrés par l'utilisateur.
selected = bouton visible uniquement dans les ref_id déclarés.
```

Le contrôle des droits ILIAS reste actif : un utilisateur qui n'administre pas le cours ne voit pas le bouton.

### Prompt IA configurable

Le prompt système n'est plus figé uniquement dans le code. La configuration du plugin expose :

```text
Prompt système IA
Remettre le prompt IA par défaut
```

Le prompt par défaut reste conservé dans `ilIliasTraxEventBridgeConfig::getDefaultAiSystemPrompt()`.

### Configuration plugin gauche/droite

`ilIliasTraxEventBridgeConfigGUI` affiche désormais les sections sous forme de grille : titre à gauche, contenu à droite.

Blocs conservés :

- Santé / Diagnostic
- État
- Diagnostics TRAX / cron
- Bouton Pilotage xAPI dans les cours
- Configuration TRAX / cron
- Configuration IA
- Envoi vers TRAX
- Supervision outbox
- Diagnostic des traces refusées
- Outbox xAPI locale
- Derniers événements ILIAS reçus

Le bloc `Ouvrir la configuration xAPI d’un cours` a été retiré de la configuration globale.

### Déplacement des actions de purge

Les purges sont replacées dans leur contexte fonctionnel :

```text
Outbox xAPI locale -> Vider l'outbox xAPI locale
Derniers événements ILIAS reçus -> Vider le journal debug
```

## Fichiers modifiés par V0.28.5

- `plugin.php`
- `classes/class.ilIliasTraxEventBridgeConfig.php`
- `classes/class.ilIliasTraxEventBridgeConfigGUI.php`
- `classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php`
- `companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl`
- `companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl`
- `companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIBridge.php.tpl`
- `companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl`

## Scripts V0.28

- `scripts/apply_v0281_dashboard_analysis_config_ai_prompt.py`
- `scripts/apply_v0283_pilotage_button_course_access_fix.py`
- `scripts/apply_v0284_config_plugin_layout.py`
- `scripts/apply_v0285_config_purge_buttons_layout.py`

## Installation compagnon

Après mise à jour de `main`, réinstaller le compagnon UIHook :

```bash
export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"

bash scripts/install_course_ui_companion_with_standalone_fix.sh

systemctl restart php-fpm
systemctl restart httpd
```
