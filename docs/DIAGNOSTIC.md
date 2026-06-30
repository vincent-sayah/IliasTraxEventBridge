# Diagnostic exploitation — IliasTraxEventBridge

Ce document fournit une procédure de diagnostic pour le plugin `IliasTraxEventBridge`.

Il s'applique à partir de la V0.11 et reste utile pour contrôler une installation V0.10.1.

## 1. Objectif

Permettre à un administrateur technique de vérifier rapidement :

- l'installation du plugin principal ;
- l'installation du plugin compagnon ;
- la version installée ;
- les fichiers sensibles ;
- la syntaxe PHP ;
- la présence des tables SQL ;
- l'état de l'outbox ;
- la configuration TRAX/LRS ;
- le cron ILIAS ;
- les erreurs récentes.

## 2. Emplacements attendus

Plugin principal :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
```

Plugin compagnon :

```text
/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI
```

## 3. Diagnostic rapide côté serveur

```bash
sudo -i

export ILIAS_ROOT="/var/www/ilias"
export ITXEB_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge"
export ITXEB_UI_DIR="$ILIAS_ROOT/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI"

cd "$ITXEB_DIR"

pwd
grep -n '\$version' plugin.php
head -5 sql/dbupdate.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

Résultat attendu :

```text
$version = '0.10.1' ou version supérieure
<#1>
<?php
aucune erreur PHP
```

## 4. Vérifier la présence du plugin compagnon

```bash
ls -ld "$ITXEB_UI_DIR"
find "$ITXEB_UI_DIR" -maxdepth 3 -type f | sort | head -50
```

Contrôles attendus :

- dossier présent ;
- fichier `plugin.php` présent ;
- classes PHP matérialisées depuis les templates ;
- pas de doublon actif dans le dossier source `companion/`.

## 5. Rebuild ILIAS

Après installation ou modification :

```bash
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
systemctl restart php-fpm
```

Si `php-fpm` n'est pas utilisé sur l'environnement, ignorer le redémarrage `php-fpm`.

## 6. Contrôles SQL

Se connecter à la base ILIAS puis lancer :

```sql
SHOW TABLES LIKE 'evnt_evhk_itxeb%';
```

Tables attendues :

```text
evnt_evhk_itxeb_log
evnt_evhk_itxeb_out
evnt_evhk_itxeb_read
evnt_evhk_itxeb_ccfg
evnt_evhk_itxeb_rcfg
evnt_evhk_itxeb_dlog
```

Selon la version future, d'autres tables pourront être ajoutées.

## 7. Vérifier la configuration cours

```sql
SELECT *
FROM evnt_evhk_itxeb_ccfg
ORDER BY updated_at DESC
LIMIT 20;
```

Configuration des ressources :

```sql
SELECT course_ref_id, ref_id, obj_id, obj_type, enabled, updated_at, updated_by
FROM evnt_evhk_itxeb_rcfg
ORDER BY updated_at DESC
LIMIT 50;
```

## 8. Vérifier l'outbox

Outbox récente :

```sql
SELECT id, event_type, ref_id, obj_id, obj_type, user_id,
       status, retry_count, created_at, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 30;
```

Outbox en erreur :

```sql
SELECT id, event_type, ref_id, obj_id, obj_type, status,
       retry_count, last_attempt_at, last_error
FROM evnt_evhk_itxeb_out
WHERE status IN ('error', 'failed')
ORDER BY id DESC
LIMIT 30;
```

Outbox en attente :

```sql
SELECT COUNT(*) AS pending_count
FROM evnt_evhk_itxeb_out
WHERE status IN ('pending', 'retry');
```

## 9. Vérifier les refus de traces

Si le diagnostic des refus est activé :

```sql
SELECT id, created_at, reason, event_type, user_id, course_ref_id,
       ref_id, obj_id, obj_type, source_table, source_id
FROM evnt_evhk_itxeb_dlog
ORDER BY id DESC
LIMIT 50;
```

Exemples de causes possibles :

- cours non activé ;
- ressource non activée ;
- type d'objet non couvert ;
- utilisateur non exploitable ;
- événement hors périmètre.

## 10. Vérifier TRAX/LRS

Dans ILIAS :

```text
Administration > Plugins > IliasTraxEventBridge > Configurer
```

Contrôler :

- endpoint TRAX/LRS ;
- identifiant xAPI ;
- secret xAPI ;
- version xAPI ;
- timeout ;
- batch size ;
- max retry ;
- cron plugin activé.

Ne jamais afficher ou copier le secret TRAX dans un ticket ou une documentation publique.

## 11. Vérifier la lecture LRS

Depuis l'interface de cours :

```text
Cours > Suivi xAPI > Configuration > Diagnostic LRS
```

Contrôler :

- réponse HTTP ;
- message d'erreur ;
- nombre de statements retournés ;
- période analysée ;
- pagination éventuelle.

## 12. Vérifier le cron ILIAS

Dans ILIAS :

```text
Administration > Paramètres système et maintenance > Tâches cron
```

Job attendu :

```text
IliasTraxEventBridge — envoi outbox vers TRAX
```

Identifiant technique :

```text
itxeb_send_outbox_to_trax
```

Vérifier :

- job actif ;
- dernière exécution ;
- prochaine exécution ;
- erreurs éventuelles.

## 13. Logs utiles

Selon installation :

```bash
journalctl -u httpd -n 200 --no-pager
journalctl -u php-fpm -n 200 --no-pager
```

Logs ILIAS selon environnement :

```bash
find /var/www/ilias -type f -iname "*.log" | sort
```

Puis rechercher :

```bash
grep -RniE "IliasTraxEventBridge|itxeb|xapi|TRAX|LRS|exception|error" /var/www/ilias 2>/dev/null | tail -100
```

## 14. Symptômes fréquents

### Plugin non visible

Contrôler :

- chemin du plugin ;
- `plugin.php` ;
- droits fichiers ;
- rebuild ILIAS ;
- cache navigateur.

### Erreur pendant l'installation

Contrôler :

```bash
head -5 sql/dbupdate.php
```

Le fichier doit commencer par :

```text
<#1>
<?php
```

### Suivi xAPI absent dans le cours

Contrôler :

- plugin compagnon installé ;
- droits utilisateur ;
- rebuild ILIAS ;
- activation du plugin dans ILIAS ;
- compatibilité thème / version ILIAS.

### Tableau de bord vide

Contrôler :

- cours activé ;
- ressources activées ;
- activité réelle générée ;
- statements présents dans TRAX ;
- diagnostic LRS ;
- période de recherche.

### Outbox bloquée

Contrôler :

- cron actif ;
- endpoint TRAX ;
- identifiants xAPI ;
- connectivité réseau ;
- erreurs HTTP ;
- certificat TLS si HTTPS interne.

## 15. Script de diagnostic V0.11

La V0.11 prévoit un script :

```text
scripts/diagnostic_itxeb.sh
```

Usage prévu :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
bash scripts/diagnostic_itxeb.sh
```

Le script ne doit pas modifier la base de données.

## 16. Informations à fournir dans un ticket incident

Inclure :

- version plugin ;
- version ILIAS ;
- branche Git ;
- dernier commit ;
- sortie de `head -5 sql/dbupdate.php` ;
- sortie de `php -l` ;
- état du cron ;
- extrait d'erreur sans secret ;
- statut outbox agrégé ;
- symptômes observés.

Ne pas inclure :

- secret TRAX ;
- clé API ;
- mot de passe ;
- données personnelles non nécessaires.
