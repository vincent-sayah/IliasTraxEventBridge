# IliasTraxEventBridgeCourseUI

Plugin compagnon UIHook pour `IliasTraxEventBridge`.

## Version documentée

| Élément | Valeur |
|---|---|
| Version stable projet | `0.10.1` |
| Branche stable | `main` |
| Tag stable | `v0.10.1` |
| Plugin principal | `IliasTraxEventBridge` |
| Plugin compagnon | `IliasTraxEventBridgeCourseUI` |
| Type | UIHook ILIAS |

## Objectif

Ce plugin compagnon ajoute l'accès au suivi xAPI directement dans l'objet cours ILIAS.

Accès attendu en V0.10.1 :

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
- l'outbox locale ;
- le cron ;
- l'envoi vers TRAX/LRS ;
- la lecture directe TRAX/LRS ;
- les tables `evnt_evhk_itxeb_*` ;
- le filtrage avant outbox ;
- la configuration globale TRAX/LRS.

Le plugin compagnon est responsable de l'intégration UI dans le cours et de l'affichage des vues de suivi.

## Rôle des vues

| Vue | Rôle |
|---|---|
| Tableau de bord | Synthèse pédagogique alimentée par TRAX/LRS. |
| Analyse | Analyse des ressources, verbes retournés par TRAX, ressources retournées par TRAX. |
| Expert | Statements TRAX détaillés et export CSV. |
| Configuration | Activation cours / ressources, préférences dashboard, diagnostic LRS, supervision outbox. |

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
sudo -i
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

bash scripts/install_course_ui_companion_with_standalone_fix.sh

cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
systemctl restart php-fpm
```

Variables optionnelles :

```bash
ILIAS_ROOT=/var/www/ilias
HTTPD_USER=apache
```

Exemple avec variables explicites :

```bash
ILIAS_ROOT=/var/www/ilias HTTPD_USER=apache bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

## Chemin d'installation cible

Le script installe le compagnon ici :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

Le plugin principal reste ici :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

## Validation Composer

Après installation par script, cette commande ne doit plus afficher de warning `Ambiguous class resolution` concernant `IliasTraxEventBridgeCourseUI` :

```bash
cd /var/www/ilias
sudo -u apache composer du
```

Les éventuels warnings ILIAS génériques sur `scripts/PHP-CS-Fixer/example` sont indépendants du plugin.

## Conditions d'affichage

Le lien ou l'onglet `Suivi xAPI` est affiché si les conditions suivantes sont réunies :

- un cours est détecté ;
- l'utilisateur a les droits nécessaires sur le cours ;
- le plugin principal est installé ;
- les classes de configuration sont disponibles ;
- la route de cours est exploitable ;
- le plugin compagnon est installé et actif.

## Navigation

En V0.10.1, l'objectif est de donner un accès direct :

```text
Cours > Suivi xAPI
```

Selon la version exacte d'ILIAS 10 et le thème actif, le compagnon peut s'appuyer techniquement sur une route existante du cours, puis remplacer le contenu central par l'écran xAPI.

## Validation visuelle

1. Ouvrir un cours avec un utilisateur qui peut gérer le cours.
2. Vérifier la présence de l'accès `Suivi xAPI`.
3. Cliquer sur `Suivi xAPI`.
4. Vérifier l'affichage des vues `Tableau de bord`, `Analyse`, `Expert`, `Configuration`.
5. Ouvrir `Configuration`.
6. Cocher `Activer les traces xAPI pour ce cours`.
7. Cocher une ou plusieurs ressources.
8. Cliquer sur `Enregistrer la configuration xAPI`.
9. Vérifier le message de succès.
10. Générer une activité sur une ressource.
11. Vérifier les vues alimentées par TRAX/LRS.

## SQL de contrôle

Configuration du cours :

```sql
SELECT *
FROM evnt_evhk_itxeb_ccfg
WHERE course_ref_id = 194;
```

Configuration des ressources :

```sql
SELECT course_ref_id, ref_id, obj_id, obj_type, enabled, updated_at, updated_by
FROM evnt_evhk_itxeb_rcfg
WHERE course_ref_id = 194
ORDER BY ref_id;
```

Outbox récente :

```sql
SELECT id, event_type, ref_id, obj_id, obj_type, status, created_at, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 20;
```

## Documentation associée

- [`../../README.md`](../../README.md) : README racine du projet.
- [`../../docs/README.md`](../../docs/README.md) : index documentaire.
- [`../../docs/FONCTIONNEL.md`](../../docs/FONCTIONNEL.md) : documentation fonctionnelle.
- [`../../docs/TECHNIQUE.md`](../../docs/TECHNIQUE.md) : documentation technique.
- [`../../docs/INSTALLATION.md`](../../docs/INSTALLATION.md) : installation complète.
- [`../../docs/ROADMAP.md`](../../docs/ROADMAP.md) : roadmap projet.
- [`../../docs/IA_ANALYSE_TRACES.md`](../../docs/IA_ANALYSE_TRACES.md) : cadrage futur IA.
