# Validation V0.22.4 — Checklist serveur

## 1. Préparation

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin
git checkout main
git pull --ff-only origin main
```

## 2. Contrôle versions

```bash
grep -n '\$version' plugin.php
grep -n '\$version' companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
```

Attendu :

```text
0.22.4-dev
0.8.10
```

## 3. Contrôle syntaxe

```bash
php -l plugin.php
php -l companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
php -l companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl
php -l classes/class.ilIliasTraxEventBridgeTestQuestionResultExtractor.php
php -l classes/class.ilIliasTraxEventBridgeQuestionRiskRepository.php
php -l classes/class.ilIliasTraxEventBridgeCourseAiAnalyzer.php
```

## 4. Réinstallation du companion

```bash
export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"

bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Adapter `ILIAS_ROOT` si ILIAS n'est pas installé dans `/var/www/ilias`.

## 5. Contrôle du fichier live companion

```bash
COMPANION_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI"

php -l "$COMPANION_DIR/plugin.php"
php -l "$COMPANION_DIR/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"

grep -n "Activité dans le temps\|V0.22.4 alignment\|showCourseAiAnalysis" \
"$COMPANION_DIR/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php"
```

## 6. Redémarrage

```bash
systemctl restart php-fpm
systemctl restart httpd
```

## 7. Validation Tableau de bord

Dans ILIAS :

```text
Cours > Pilotage xAPI > Tableau de bord
```

Vérifier :

- le bloc `Activité dans le temps` est visible ;
- les choix `7 jours`, `14 jours`, `30 jours`, `Par semaine`, `Détail complet` fonctionnent ;
- le détail complet est repliable ;
- les titres de blocs sont à gauche ;
- les données sont à droite ;
- la `Synthèse pédagogique` est alignée comme les autres blocs.

## 8. Validation Analyse

Dans :

```text
Cours > Pilotage xAPI > Analyse
```

Vérifier :

- les filtres sont lisibles ;
- le bloc `Priorité formateur` reste lisible ;
- le tableau ressources reste lisible ;
- le bloc `Apprenants en difficulté` reste lisible ;
- la `Synthèse pédagogique` est alignée.

## 9. Validation Analyse IA

Dans :

```text
Cours > Pilotage xAPI > Analyse IA
```

Vérifier :

- les actions IA sont lisibles ;
- l'historique des analyses IA est visible ;
- le bouton `Retirer` retire bien l'analyse après confirmation ;
- après validation du retrait, l'onglet `Analyse IA` reste sélectionné.

## 10. Validation Configuration

Dans :

```text
Cours > Pilotage xAPI > Configuration
```

Vérifier :

- les blocs sont présentés selon le modèle titre à gauche / données à droite ;
- le formulaire d'activation reste utilisable ;
- le tableau des ressources reste utilisable ;
- la supervision outbox et le diagnostic LRS restent lisibles.

## 11. Décision de validation

La V0.22.4 est validée si :

- le tableau de bord ne contient plus de longue liste quotidienne occupant toute la page ;
- les blocs sont alignés façon ILIAS ;
- la Synthèse pédagogique est alignée ;
- le retrait d'une analyse IA conserve l'onglet Analyse IA actif ;
- aucun écran ILIAS n'est bloqué ;
- aucun `php -l` n'est en erreur.
