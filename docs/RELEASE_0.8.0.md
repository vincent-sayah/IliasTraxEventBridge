# Release v0.8.0 — supervision outbox et diagnostic des refus

## Statut

Release publiée : **v0.8.0**.

Branche stable par défaut :

```text
main
```

Tag stable :

```text
v0.8.0
```

Version du plugin principal :

```text
IliasTraxEventBridge 0.8.0
```

Version du plugin compagnon UIHook :

```text
IliasTraxEventBridgeCourseUI 0.1.1
```

Base fonctionnelle :

```text
v0.7.1
```

La branche de développement `v0.8-outbox-supervision` est clôturée. Son contenu a été tagué en `v0.8.0` puis promu sur `main`.

## Objectif de la V0.8

La V0.8 ajoute une couche d'exploitation autour de l'outbox et du filtrage V0.7.1.

Le comportement métier reste :

```text
statement xAPI autorisé = cours activé ET ressource activée
```

La V0.8 permet de diagnostiquer pourquoi une trace n'a pas été générée, tout en évitant que ce diagnostic soit actif en permanence sur une plateforme volumineuse.

## Lots livrés

### Lot 1 — journal SQL des traces refusées

Ajout de la table :

```text
evnt_evhk_itxeb_dlog
```

Cette table journalise les refus de génération xAPI avec :

- motif métier du refus ;
- type d'événement ;
- utilisateur ;
- cours ;
- ressource ;
- source technique ;
- payload de diagnostic.

Motifs principaux :

```text
not_in_course
missing_course_context
missing_resource_context
course_not_configured
course_disabled
resource_not_configured
resource_disabled
unsupported_object_type
```

### Lot 2 — supervision admin des refus

Ajout dans l'administration du plugin :

```text
Administration > Plugins > IliasTraxEventBridge > Configurer
```

Nouvelle section :

```text
Diagnostic des traces refusées V0.8
```

Fonctions disponibles :

- compteur total des refus ;
- synthèse par motif ;
- synthèse par source technique ;
- synthèse par type d'événement ;
- tableau des 50 derniers refus ;
- payload JSON lisible ;
- case à cocher `Activer le diagnostic des traces refusées` ;
- bouton `Purger le diagnostic des traces refusées`.

Par défaut, le diagnostic des refus est désactivé.

### Lot 3 — packaging propre du plugin compagnon UIHook

Les fichiers PHP du plugin compagnon ne sont plus présents directement dans le dossier source `companion/`.

Ils sont fournis sous forme de templates :

```text
companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl
companion/IliasTraxEventBridgeCourseUI/classes/*.php.tpl
```

Un script installe le compagnon dans le slot actif ILIAS :

```text
scripts/install_course_ui_companion.sh
```

Objectif : supprimer les warnings Composer de type :

```text
Ambiguous class resolution
```

concernant les classes `IliasTraxEventBridgeCourseUI`.

## Installation stable depuis main

### 1. Installer le plugin principal

```bash
sudo -i

export ILIAS_ROOT="/var/www/ilias"
export EVENTHOOK_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook"
export PLUGIN_NAME="IliasTraxEventBridge"

mkdir -p "$EVENTHOOK_DIR"
cd "$EVENTHOOK_DIR"

git clone -b main --single-branch https://github.com/vincent-sayah/IliasTraxEventBridge.git "$PLUGIN_NAME"
cd "$PLUGIN_NAME"

grep -n '\$version' plugin.php
```

Résultat attendu :

```text
$version = "0.8.0";
```

### 2. Verrouiller exactement la release stable

Pour figer l'installation sur le tag publié :

```bash
git fetch origin --prune --tags
git checkout v0.8.0
```

### 3. Installer / régénérer le plugin compagnon UIHook

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

bash scripts/install_course_ui_companion.sh
```

Le script génère les fichiers PHP du compagnon dans :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

### 4. Contrôles syntaxe

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
find . -name "*.php" -print0 | xargs -0 -n1 php -l

find companion/IliasTraxEventBridgeCourseUI -name "*.php" -print
find /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI -name "*.php" -print
```

Résultat attendu pour le dossier source `companion/` : aucune ligne PHP.

### 5. Rebuild ILIAS

```bash
cd /var/www/ilias

sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes

systemctl restart httpd
```

Les warnings Composer suivants ne doivent plus apparaître :

```text
Ambiguous class resolution, "ilIliasTraxEventBridgeCourseUIPlugin" ...
Ambiguous class resolution, "ilIliasTraxEventBridgeCourseUIScreen" ...
Ambiguous class resolution, "ilIliasTraxEventBridgeCourseUIBridge" ...
Ambiguous class resolution, "ilIliasTraxEventBridgeCourseUIUIHookGUI" ...
```

Des warnings ILIAS indépendants peuvent rester, notamment ceux liés aux exemples `scripts/PHP-CS-Fixer`.

### 6. Mise à jour plugin ILIAS

Dans ILIAS :

```text
Administration > Plugins > IliasTraxEventBridge > Mettre à jour
```

L'étape SQL `<#6>` crée la table :

```text
evnt_evhk_itxeb_dlog
```

Contrôle SQL :

```sql
SHOW TABLES LIKE 'evnt_evhk_itxeb_dlog';
DESCRIBE evnt_evhk_itxeb_dlog;
```

## Mise à jour d'une installation existante

```bash
sudo -i

cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin --prune --tags
git checkout v0.8.0

grep -n '\$version' plugin.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
bash scripts/install_course_ui_companion.sh

cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
```

## Validation fonctionnelle

### Configuration cours

Dans un cours :

```text
Paramètres > Suivi xAPI
```

Contrôles :

- le sous-onglet `Suivi xAPI` est visible ;
- l'écran s'affiche dans le contenu central ILIAS ;
- les onglets du cours restent visibles ;
- l'activation du cours fonctionne ;
- l'activation des ressources fonctionne.

### Outbox

Pour une ressource activée :

```sql
SELECT id, event_type, obj_type, ref_id, obj_id, verb_id, status, retry_count, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 20;
```

Résultat attendu : lignes `generated`, puis `sent` après envoi/cron.

### Diagnostic des refus

Dans l'administration du plugin :

```text
Configuration TRAX / cron > Activer le diagnostic des traces refusées
```

Si la case est décochée : aucune nouvelle ligne n'est ajoutée dans `evnt_evhk_itxeb_dlog`.

Si la case est cochée : les refus sont journalisés.

Contrôle SQL :

```sql
SELECT id, created_at, reason, event_type, user_id, course_ref_id, ref_id, obj_id, obj_type, source_table, source_id
FROM evnt_evhk_itxeb_dlog
ORDER BY id DESC
LIMIT 30;

SELECT reason, COUNT(*) AS total
FROM evnt_evhk_itxeb_dlog
GROUP BY reason
ORDER BY total DESC, reason ASC;
```

### Purge

Bouton :

```text
Purger le diagnostic des traces refusées
```

Contrôle SQL :

```sql
SELECT COUNT(*) AS total
FROM evnt_evhk_itxeb_dlog;
```

Après purge :

```text
total = 0
```

## Validations réalisées

Validations réalisées pendant le développement V0.8 :

```text
Lot 1 — evnt_evhk_itxeb_dlog créée et alimentée : OK
Lot 1 — reason = resource_disabled sur ressources désactivées : OK
Lot 2 — section admin Diagnostic des traces refusées V0.8 : OK
Lot 2 — activation/désactivation du diagnostic : OK
Lot 2 — purge du diagnostic : OK
Lot 3 — installation du compagnon par templates .php.tpl : OK
Lot 3 — suppression des warnings Composer Ambiguous class resolution : OK
Lot 3 — Paramètres > Suivi xAPI toujours fonctionnel : OK
Tag v0.8.0 publié : OK
main promu sur v0.8.0 : OK
```

## Documentation liée

```text
README.md
README_TECHNIQUE.md
CHANGELOG.md
docs/VALIDATION.md
docs/OPERATIONS.md
docs/V0.8_LOT1_DENY_LOG.md
docs/V0.8_LOT2_DENY_SUPERVISION.md
docs/V0.8_LOT3_COMPANION_PACKAGING.md
```
