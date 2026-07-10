# Documentation — IliasTraxEventBridge

Ce dossier regroupe la documentation du plugin `IliasTraxEventBridge`.

## Version stable actuelle

| Élément | Valeur |
|---|---|
| Branche stable | `main` |
| Version stable courante | `0.22.4-dev` validée et promue dans `main` |
| Commit de gel fonctionnel | `b4fdf9a` — `V0.22.4 validate dashboard layout and AI tab fixes` |
| Plugin principal | `IliasTraxEventBridge` |
| Version plugin principal | `0.22.4-dev` |
| Plugin compagnon | `IliasTraxEventBridgeCourseUI` |
| Version plugin compagnon | `0.8.10` |
| Source xAPI | TRAX/LRS |
| Rôle de l'outbox locale | File technique d'envoi et calcul robuste des questions problématiques |

## Lecture rapide — documents à utiliser maintenant

| Besoin | Document à lire |
|---|---|
| Index V0.22.4 | [`INDEX_0.22.4.md`](INDEX_0.22.4.md) |
| Installer ou mettre à jour | [`INSTALLATION.md`](INSTALLATION.md) |
| Lire la note de release courante | [`RELEASE_0.22.4.md`](RELEASE_0.22.4.md) |
| Activité dans le temps | [`V0.22_ACTIVITY_TIMELINE.md`](V0.22_ACTIVITY_TIMELINE.md) |
| Présentation type formulaire ILIAS | [`V0.22.1_ILIAS_LIKE_DASHBOARD_LAYOUT.md`](V0.22.1_ILIAS_LIKE_DASHBOARD_LAYOUT.md) |
| Validation V0.22.4 | [`VALIDATION_0.22.4.md`](VALIDATION_0.22.4.md) |
| Comprendre le fonctionnement métier | [`FONCTIONNEL_0.21.2.md`](FONCTIONNEL_0.21.2.md) + [`RELEASE_0.22.4.md`](RELEASE_0.22.4.md) |
| Comprendre l'architecture technique | [`TECHNIQUE_0.21.2.md`](TECHNIQUE_0.21.2.md) + [`RELEASE_0.22.4.md`](RELEASE_0.22.4.md) |
| Exploiter et dépanner | [`EXPLOITATION_0.21.2.md`](EXPLOITATION_0.21.2.md) |
| Développer ou modifier le plugin | [`GUIDE_DEVELOPPEUR_0.21.2.md`](GUIDE_DEVELOPPEUR_0.21.2.md) |
| Historique des versions | [`../CHANGELOG.md`](../CHANGELOG.md) |

## Règle métier courante

```text
TRAX = toutes les questions de test ILIAS sont tracées.
Tableau de bord / Analyse = questions problématiques uniquement.
Analyse IA = questions problématiques uniquement.
Expert = vision technique complète.
```

## Nouveautés ergonomiques V0.22.4

- Bloc `Activité dans le temps` compact avec choix de plage.
- Passage automatique ou manuel en vue hebdomadaire.
- Détail complet repliable pour éviter une liste trop longue.
- Présentation des blocs sur le modèle ILIAS : titre/fonctionnalité à gauche, données/formulaire/tableau à droite.
- Alignement corrigé de la `Synthèse pédagogique` dans Tableau de bord et Analyse.
- Correction du retour sur l'onglet `Analyse IA` après retrait d'une analyse historisée.

## Documents génériques maintenus

Ces documents sont conservés comme entrées génériques, mais la référence stable actuelle est la V0.22.4 :

| Document | Rôle |
|---|---|
| [`FONCTIONNEL.md`](FONCTIONNEL.md) | Entrée fonctionnelle générique. |
| [`TECHNIQUE.md`](TECHNIQUE.md) | Entrée technique générique. |
| [`EXPLOITATION.md`](EXPLOITATION.md) | Entrée exploitation générique. |
| [`DEVELOPPEUR.md`](DEVELOPPEUR.md) | Entrée développeur générique. |
| [`DIAGNOSTIC.md`](DIAGNOSTIC.md) | Diagnostic historique et compléments d'exploitation. |
| [`ROLLBACK.md`](ROLLBACK.md) | Procédures de retour arrière. |
| [`ROADMAP.md`](ROADMAP.md) | Roadmap recalée après V0.22.4. |
| [`IA_ANALYSE_TRACES.md`](IA_ANALYSE_TRACES.md) | Cadrage IA, toujours valable avec le complément V0.22.4. |

## Documents historiques

Les documents suivants sont conservés pour comprendre l'historique du projet. Ils ne doivent pas être utilisés comme référence d'installation courante si leur version est antérieure à V0.22.4 :

```text
FINAL_RELEASE_CHECKLIST_0.10.1.md
V0.10_LRS_DIRECT_READ.md
RELEASE_0.11.0.md
VALIDATION_0.11.md
V0.11_DIAGNOSTIC_EXPLOITATION.md
V0.12_DASHBOARD_PEDAGOGIQUE.md
V0.12_GUIDE_UTILISATION.md
VALIDATION_0.12.md
V0.12.1_CONSOLIDATION_UI_COMPANION.md
VALIDATION_0.12.1.md
V0.13_AI_ANALYSE_TRACES.md
VALIDATION_0.13.md
RELEASE_0.15.2.md
INDEX_0.21.2.md
RELEASE_0.21.2.md
VALIDATION_0.21.2.md
```

## Installation — rappel court

```bash
export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"
export EVENTHOOK_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook"
export PLUGIN_NAME="IliasTraxEventBridge"

mkdir -p "$EVENTHOOK_DIR"
cd "$EVENTHOOK_DIR"
git clone -b main --single-branch https://github.com/vincent-sayah/IliasTraxEventBridge.git "$PLUGIN_NAME"
cd "$PLUGIN_NAME"

bash scripts/install_course_ui_companion_with_standalone_fix.sh
cd "$ILIAS_ROOT"
sudo -u "$HTTPD_USER" composer du
sudo -u "$HTTPD_USER" php cli/setup.php build --yes
systemctl restart httpd
systemctl restart php-fpm
```

Si ILIAS n'est pas dans `/var/www/ilias`, modifier `ILIAS_ROOT` avant de lancer le script compagnon.
