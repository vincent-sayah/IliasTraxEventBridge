# Installation et mise à jour — IliasTraxEventBridge

## Version stable courante

| Élément | Valeur |
|---|---|
| Branche stable | `main` |
| Version plugin principal | `0.28.5-dev` |
| Version plugin compagnon UI | `0.8.50` |
| Commit fonctionnel validé | `eabc786` |
| Branche de validation | `v0.28-dashboard-analysis-config-ai-prompt-validated` |

## Mise à jour depuis `main`

Depuis le serveur ILIAS :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin
git checkout -f main
git reset --hard origin/main

git status -sb
git log --oneline -8
```

Puis réinstaller le compagnon UIHook :

```bash
export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"

bash scripts/install_course_ui_companion_with_standalone_fix.sh

systemctl restart php-fpm
systemctl restart httpd
```

## Vérification rapide

```bash
grep -n "0.28.5-dev\|0.8.50\|isPilotageEnabledForCourse\|Prompt système IA\|itxeb-outbox\|itxeb-recent-events" \
plugin.php \
classes/class.ilIliasTraxEventBridgeConfig.php \
classes/class.ilIliasTraxEventBridgeConfigGUI.php \
classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php \
companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIBridge.php.tpl \
companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl
```

## Points de validation visuelle V0.28.5

### Configuration globale du plugin

La page doit afficher une présentation gauche/droite : titre de section à gauche, formulaire ou tableau à droite.

Blocs attendus :

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

Le bloc `Ouvrir la configuration xAPI d’un cours` ne doit plus être affiché.

### Boutons de purge

```text
Outbox xAPI locale -> Vider l'outbox xAPI locale
Derniers événements ILIAS reçus -> Vider le journal debug
```

### Bouton Pilotage xAPI

En configuration globale, choisir :

```text
Tous les cours où l'utilisateur est administrateur du cours
```

ou :

```text
Seulement les cours listés ci-dessous
```

En mode sélection, renseigner un ou plusieurs `ref_id` de cours. Pour retirer un cours, décocher son `ref_id` dans la liste des cours autorisés puis enregistrer.

### Prompt IA

Dans `Configuration IA`, le prompt système doit être visible, modifiable et restaurable via le bouton de retour au prompt par défaut.

## Scripts V0.28 à conserver

- `scripts/apply_v0281_dashboard_analysis_config_ai_prompt.py`
- `scripts/apply_v0283_pilotage_button_course_access_fix.py`
- `scripts/apply_v0284_config_plugin_layout.py`
- `scripts/apply_v0285_config_purge_buttons_layout.py`
