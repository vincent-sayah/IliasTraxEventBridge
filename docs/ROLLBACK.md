# Rollback — IliasTraxEventBridge

Ce document décrit les procédures de retour arrière du plugin `IliasTraxEventBridge`.

Le rollback doit toujours être préparé avant une mise à jour en environnement sensible.

## 1. Principes

Un rollback peut concerner :

- le code Git du plugin principal ;
- le plugin compagnon UIHook ;
- la configuration ILIAS ;
- les tables SQL ;
- les données de l'outbox ;
- la configuration TRAX/LRS.

Le rollback ne doit jamais supprimer des données sans sauvegarde.

## 2. Avant toute mise à jour

### 2.1 Identifier la version actuelle

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git branch --show-current
git log --oneline -5
grep -n '\$version' plugin.php
```

### 2.2 Sauvegarder le dossier plugin

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook

tar czf /root/IliasTraxEventBridge_backup_$(date +%Y%m%d_%H%M%S).tar.gz IliasTraxEventBridge
```

### 2.3 Sauvegarder le plugin compagnon

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook

tar czf /root/IliasTraxEventBridgeCourseUI_backup_$(date +%Y%m%d_%H%M%S).tar.gz IliasTraxEventBridgeCourseUI
```

Si le dossier compagnon n'existe pas, ignorer cette étape.

### 2.4 Sauvegarder les tables plugin

Exemple MariaDB/MySQL :

```bash
mysqldump -u root -p NOM_BASE_ILIAS \
  evnt_evhk_itxeb_log \
  evnt_evhk_itxeb_out \
  evnt_evhk_itxeb_read \
  evnt_evhk_itxeb_ccfg \
  evnt_evhk_itxeb_rcfg \
  evnt_evhk_itxeb_dlog \
  > /root/itxeb_tables_backup_$(date +%Y%m%d_%H%M%S).sql
```

Adapter `NOM_BASE_ILIAS` au nom réel de la base.

## 3. Rollback simple par Git

Si le dépôt Git est propre et qu'un tag stable est disponible :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin --tags
git checkout v0.10.1
```

Puis reconstruire ILIAS :

```bash
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
systemctl restart php-fpm
```

## 4. Rollback depuis une sauvegarde tar.gz

### 4.1 Restaurer le plugin principal

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook

mv IliasTraxEventBridge IliasTraxEventBridge_failed_$(date +%Y%m%d_%H%M%S)
tar xzf /root/IliasTraxEventBridge_backup_YYYYMMDD_HHMMSS.tar.gz
```

### 4.2 Restaurer le plugin compagnon

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook

mv IliasTraxEventBridgeCourseUI IliasTraxEventBridgeCourseUI_failed_$(date +%Y%m%d_%H%M%S)
tar xzf /root/IliasTraxEventBridgeCourseUI_backup_YYYYMMDD_HHMMSS.tar.gz
```

### 4.3 Rebuild

```bash
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
systemctl restart php-fpm
```

## 5. Rollback base de données

Le rollback base doit être fait avec prudence.

### 5.1 Cas recommandé

Dans la plupart des cas, éviter de restaurer immédiatement les tables SQL.

Commencer par restaurer le code et vérifier :

- installation plugin ;
- accès administration ;
- accès cours ;
- outbox ;
- logs.

### 5.2 Cas nécessitant restauration SQL

Restaurer les tables seulement si :

- une migration a modifié le schéma ;
- des données critiques ont été altérées ;
- l'installation ne peut plus démarrer ;
- le rollback code seul ne suffit pas.

### 5.3 Restauration SQL

Exemple :

```bash
mysql -u root -p NOM_BASE_ILIAS < /root/itxeb_tables_backup_YYYYMMDD_HHMMSS.sql
```

Attention : cette commande remplace l'état des tables sauvegardées selon le contenu du dump.

## 6. Rollback plugin compagnon uniquement

Si le problème concerne seulement l'onglet ou l'interface de cours :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook
rm -rf IliasTraxEventBridgeCourseUI

cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
bash scripts/install_course_ui_companion_with_standalone_fix.sh

cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
systemctl restart php-fpm
```

## 7. Contrôles après rollback

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

grep -n '\$version' plugin.php
head -5 sql/dbupdate.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
git status
git log --oneline -5
```

Dans ILIAS :

```text
Administration > Plugins
```

Vérifier :

- plugin principal actif ;
- plugin compagnon actif ;
- écran de configuration accessible ;
- accès `Cours > Suivi xAPI` fonctionnel.

## 8. Contrôles SQL après rollback

```sql
SHOW TABLES LIKE 'evnt_evhk_itxeb%';

SELECT id, event_type, status, retry_count, created_at, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 20;
```

## 9. Revenir à la stable officielle

Pour revenir à la stable officielle actuelle :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin --tags
git checkout main
git pull origin main
```

Ou directement sur le tag stable :

```bash
git checkout v0.10.1
```

Différence :

| Option | Usage |
|---|---|
| `main` | stable officielle évolutive. |
| `v0.10.1` | version figée, reproductible. |

## 10. Ce qu'il ne faut pas faire

Ne pas faire sans sauvegarde :

```sql
DROP TABLE evnt_evhk_itxeb_log;
DROP TABLE evnt_evhk_itxeb_out;
DROP TABLE evnt_evhk_itxeb_read;
DROP TABLE evnt_evhk_itxeb_ccfg;
DROP TABLE evnt_evhk_itxeb_rcfg;
DROP TABLE evnt_evhk_itxeb_dlog;
```

Ne pas supprimer :

- la configuration TRAX ;
- les secrets ;
- les tables ;
- les logs ;
- les sauvegardes ;

sans avoir identifié précisément la cause de l'incident.

## 11. Informations à conserver

Après rollback, conserver :

- date de l'incident ;
- version avant rollback ;
- version après rollback ;
- sauvegardes utilisées ;
- erreur initiale ;
- commandes exécutées ;
- résultat final.
