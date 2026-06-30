# Release V0.11.0 — Diagnostic exploitation

## Statut

| Élément | Valeur |
|---|---|
| Version | `0.11.0` |
| Branche stable | `main` |
| Tag stable | `v0.11.0` |
| Branche de développement | `v0.11-diagnostic-exploitation` |
| Type | Durcissement exploitation, diagnostic et rollback |
| Compatibilité ILIAS | `10.0.0` à `10.999.999` |

## Objectif

La V0.11.0 stabilise le plugin autour de l'exploitation et du diagnostic.

Elle conserve le fonctionnement pédagogique validé en V0.10.1 :

```text
Outbox locale = file technique d'envoi
TRAX/LRS      = source officielle du suivi xAPI pédagogique
```

## Nouveautés principales

### Section Santé / Diagnostic V0.11

Une nouvelle section est ajoutée dans l'administration du plugin :

```text
Administration > Plugins > IliasTraxEventBridge > Configurer
```

Elle vérifie notamment :

- version plugin ;
- marqueur `<#1>` dans `sql/dbupdate.php` ;
- plugin compagnon UIHook ;
- script serveur `scripts/diagnostic_itxeb.sh` ;
- endpoint TRAX/LRS ;
- cron plugin ;
- diagnostic des refus ;
- outbox failed ;
- retry épuisé ;
- tables SQL `evnt_evhk_itxeb_*`.

### Script diagnostic serveur

Ajout du script :

```text
scripts/diagnostic_itxeb.sh
```

Il est non destructif : il ne modifie ni les fichiers, ni la configuration, ni la base de données.

### Test lecture TRAX/LRS

Ajout du bouton :

```text
Tester lecture TRAX/LRS
```

Ce test exécute uniquement :

```text
GET /statements?limit=1
```

Il ne crée aucun statement xAPI.

### Test écriture TRAX/LRS

Ajout du bouton :

```text
Créer un statement test TRAX/LRS
```

Ce test crée volontairement un statement xAPI de diagnostic dans TRAX/LRS.

Le statement contient notamment :

```text
actor.account.name = itxeb-diagnostic
verb.id = http://adlnet.gov/expapi/verbs/experienced
extension itxeb_diagnostic = true
extension itxeb_version = 0.11.0
extension itxeb_test_type = admin_write_diagnostic
```

### Résultats persistants

Les résultats des tests lecture et écriture sont conservés dans les settings ILIAS du plugin et réaffichés dans :

```text
Diagnostics TRAX / cron
```

Avec :

- date ;
- succès ;
- code HTTP ;
- message.

## Documentation ajoutée ou mise à jour

- `docs/V0.11_DIAGNOSTIC_EXPLOITATION.md` ;
- `docs/DIAGNOSTIC.md` ;
- `docs/ROLLBACK.md` ;
- `docs/VALIDATION_0.11.md` ;
- `docs/README.md` ;
- `README.md` ;
- `companion/IliasTraxEventBridgeCourseUI/README.md` ;
- `CHANGELOG.md`.

## Contrôles techniques attendus

```bash
grep -n '\$version' plugin.php
head -5 sql/dbupdate.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
bash scripts/diagnostic_itxeb.sh
```

Résultat attendu :

```text
$version = '0.11.0';
<#1>
<?php
aucune erreur PHP
```

## Validation fonctionnelle attendue

Dans ILIAS :

```text
Administration > Plugins > IliasTraxEventBridge > Configurer
```

Contrôles attendus :

- section `Santé / Diagnostic V0.11` visible ;
- `Tester connexion TRAX` OK ;
- `Tester lecture TRAX/LRS` OK ;
- `Créer un statement test TRAX/LRS` OK ;
- résultats persistants dans `Diagnostics TRAX / cron` ;
- accès `Cours > Suivi xAPI` conservé ;
- vues pédagogiques toujours alimentées par TRAX/LRS.

## Points de vigilance

Le test d'écriture crée volontairement une trace de diagnostic dans TRAX/LRS.

Ne pas publier dans un ticket ou un dépôt Git :

- secret TRAX ;
- clé API ;
- mot de passe ;
- donnée personnelle non nécessaire.

## Rollback

La procédure de rollback est décrite dans :

```text
docs/ROLLBACK.md
```

## Validation détaillée

La procédure complète de validation est décrite dans :

```text
docs/VALIDATION_0.11.md
```
