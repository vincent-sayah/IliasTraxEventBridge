# Validation V0.11 — IliasTraxEventBridge

Ce document décrit la procédure de validation de la branche **V0.11 diagnostic exploitation**.

Branche concernée :

```text
v0.11-diagnostic-exploitation
```

Version attendue :

```text
0.11.0
```

## 1. Objectif de validation

Valider que la V0.11 :

- reste compatible avec le comportement stable V0.10.1 ;
- affiche la section `Santé / Diagnostic V0.11` ;
- conserve la source pédagogique TRAX/LRS ;
- conserve l'outbox locale comme file technique uniquement ;
- vérifie l'installation du plugin principal ;
- vérifie l'installation du plugin compagnon UIHook ;
- vérifie la présence des tables SQL ;
- vérifie l'état de l'outbox ;
- teste la connexion TRAX ;
- teste la lecture TRAX/LRS ;
- teste l'écriture TRAX/LRS avec un statement de diagnostic contrôlé ;
- conserve durablement les résultats des tests dans l'administration du plugin.

## 2. Préparation côté poste Windows / Git Bash

Sur le poste Windows :

```bash
cd ~/Downloads/IliasTraxEventBridge_github_ready_package/package/IliasTraxEventBridge

git fetch origin
git checkout v0.11-diagnostic-exploitation
git pull origin v0.11-diagnostic-exploitation

git status
git log --oneline -5
grep -n '\$version' plugin.php
grep -n "Santé / Diagnostic V0.11" classes/class.ilIliasTraxEventBridgeConfigGUI.php
grep -n "testLrsRead" classes/class.ilIliasTraxEventBridgeConfigGUI.php
grep -n "testLrsWrite" classes/class.ilIliasTraxEventBridgeConfigGUI.php
```

Résultat attendu :

```text
working tree clean
$version = '0.11.0';
Santé / Diagnostic V0.11 présent
testLrsRead présent
testLrsWrite présent
```

## 3. Préparation côté VM ILIAS

Sur la VM AlmaLinux / ILIAS :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin
git checkout v0.11-diagnostic-exploitation
git pull origin v0.11-diagnostic-exploitation

grep -n '\$version' plugin.php
head -5 sql/dbupdate.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

Résultat attendu :

```text
$version = '0.11.0';
<#1>
<?php
No syntax errors detected
```

## 4. Lancer le script diagnostic serveur

Depuis le dossier du plugin :

```bash
bash scripts/diagnostic_itxeb.sh
```

Contrôles attendus :

```text
[OK] Plugin principal
[OK] dbupdate.php commence par <#1>
[OK] Aucune erreur de syntaxe PHP détectée
[OK] Script présent : scripts/diagnostic_itxeb.sh
```

Le script ne doit pas modifier :

- la base de données ;
- la configuration plugin ;
- l'outbox ;
- les fichiers.

## 5. Rebuild ILIAS

```bash
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
systemctl restart php-fpm
```

Si `php-fpm` n'est pas utilisé, ignorer la dernière commande.

## 6. Mise à jour du plugin dans ILIAS

Dans ILIAS :

```text
Administration > Plugins > IliasTraxEventBridge > Mettre à jour
```

Puis :

```text
Administration > Plugins > IliasTraxEventBridge > Configurer
```

Résultat attendu :

```text
IliasTraxEventBridge — V0.11 diagnostic exploitation
```

La section suivante doit être visible en haut de page :

```text
Santé / Diagnostic V0.11
```

## 7. Vérifier la section Santé / Diagnostic V0.11

La section doit afficher les contrôles suivants :

- Version plugin ;
- dbupdate.php ;
- Plugin compagnon UIHook ;
- Script diagnostic serveur ;
- Endpoint TRAX/LRS ;
- Cron plugin ;
- Diagnostic refus ;
- Outbox failed ;
- Retry épuisé ;
- tables `evnt_evhk_itxeb_*`.

Résultat attendu :

```text
Version plugin : 0.11.0
Marqueur <#1> présent
Tables SQL présentes
```

Les avertissements éventuels sur l'outbox ou le cron doivent être analysés selon l'état réel de la plateforme.

## 8. Vérifier le bloc Diagnostics TRAX / cron

Dans la page de configuration, le bloc doit contenir :

```text
Dernier test connexion
Dernier test lecture TRAX/LRS
Dernier test écriture TRAX/LRS
Dernier envoi manuel
Dernier cron
```

Avant exécution des tests, les lignes peuvent indiquer :

```text
Aucun diagnostic disponible.
```

Après exécution des tests, les lignes doivent conserver :

- date ;
- succès ;
- HTTP ;
- message.

## 9. Test 1 — Tester connexion TRAX

Cliquer sur :

```text
Tester connexion TRAX
```

Résultat attendu :

```text
Connexion TRAX réussie
```

Puis vérifier dans `Diagnostics TRAX / cron` :

```text
Dernier test connexion
  date : renseignée
  succès : oui
  HTTP : 200 ou autre 2xx selon LRS
  message : renseigné
```

## 10. Test 2 — Tester lecture TRAX/LRS

Cliquer sur :

```text
Tester lecture TRAX/LRS
```

Ce test est non destructif.

Il exécute :

```text
GET /statements?limit=1
```

Résultat attendu :

```text
Lecture TRAX/LRS réussie : HTTP 200 ; 0 ou 1 statement(s) retourné(s) avec limit=1.
```

Puis vérifier dans `Diagnostics TRAX / cron` :

```text
Dernier test lecture TRAX/LRS
  date : renseignée
  succès : oui
  HTTP : 200
  message : Lecture TRAX/LRS réussie...
```

## 11. Test 3 — Créer un statement test TRAX/LRS

Cliquer sur :

```text
Créer un statement test TRAX/LRS
```

Attention : ce test crée volontairement un statement xAPI de diagnostic dans TRAX/LRS.

Résultat attendu :

```text
Statement test TRAX/LRS créé : HTTP 200 ; id <uuid>.
```

ou :

```text
Statement test TRAX/LRS créé : HTTP 204 ; id <uuid>.
```

Puis vérifier dans `Diagnostics TRAX / cron` :

```text
Dernier test écriture TRAX/LRS
  date : renseignée
  succès : oui
  HTTP : 200, 204 ou autre 2xx
  message : Statement test TRAX/LRS créé...
```

Le statement de diagnostic contient :

```text
actor.account.name = itxeb-diagnostic
verb.id = http://adlnet.gov/expapi/verbs/experienced
extension itxeb_diagnostic = true
extension itxeb_version = 0.11.0
extension itxeb_test_type = admin_write_diagnostic
```

## 12. Vérifier dans TRAX/LRS

Dans TRAX/LRS, rechercher le statement avec :

```text
itxeb_test_type = admin_write_diagnostic
```

ou avec l'acteur :

```text
itxeb-diagnostic
```

Objectif : confirmer que le test d'écriture a réellement créé une trace identifiable.

## 13. Vérifier l'accès cours

Dans un cours ILIAS :

```text
Cours > Suivi xAPI
```

Résultat attendu :

- accès `Suivi xAPI` visible ;
- onglets `Tableau de bord`, `Analyse`, `Expert`, `Configuration` visibles ;
- aucune régression par rapport à la V0.10.1.

## 14. Vérifier la configuration cours / ressources

Dans :

```text
Cours > Suivi xAPI > Configuration
```

Vérifier :

- activation du cours ;
- activation des ressources ;
- sauvegarde de la configuration ;
- absence d'erreur PHP ou ILIAS.

## 15. Vérifier les vues pédagogiques

Dans le cours :

```text
Tableau de bord
Analyse
Expert
```

Résultat attendu :

- les vues restent alimentées par TRAX/LRS ;
- l'outbox locale n'est pas utilisée comme source pédagogique ;
- l'export CSV Expert fonctionne ;
- l'export PDF ou rapport imprimable fonctionne si déjà opérationnel en V0.10.1.

## 16. Contrôles SQL après tests

Dans la base ILIAS :

```sql
SHOW TABLES LIKE 'evnt_evhk_itxeb%';
```

Outbox récente :

```sql
SELECT id, event_type, ref_id, obj_id, obj_type, user_id,
       status, retry_count, created_at, sent_at, last_error
FROM evnt_evhk_itxeb_out
ORDER BY id DESC
LIMIT 30;
```

Les tests applicatifs de lecture et d'écriture TRAX/LRS ne doivent pas casser l'outbox.

## 17. Logs à contrôler

```bash
journalctl -u httpd -n 200 --no-pager
journalctl -u php-fpm -n 200 --no-pager
```

Recherche dans ILIAS :

```bash
grep -RniE "IliasTraxEventBridge|itxeb|xapi|TRAX|LRS|exception|error" /var/www/ilias 2>/dev/null | tail -100
```

Résultat attendu :

- pas de fatal error ;
- pas d'exception bloquante ;
- pas d'erreur de classe manquante ;
- pas de secret affiché dans les logs.

## 18. Critères d'acceptation V0.11

La V0.11 peut être considérée comme validée si :

- la version plugin affiche `0.11.0` ;
- la section `Santé / Diagnostic V0.11` est visible ;
- `dbupdate.php` commence par `<#1>` ;
- aucune erreur de syntaxe PHP n'est détectée ;
- le plugin compagnon UIHook est détecté ;
- les tables SQL sont détectées ;
- le test connexion TRAX fonctionne ;
- le test lecture TRAX/LRS fonctionne ;
- le test écriture TRAX/LRS fonctionne ;
- les résultats des tests restent affichés après retour sur la page ;
- l'accès `Cours > Suivi xAPI` fonctionne ;
- les vues pédagogiques restent alimentées par TRAX/LRS ;
- aucune régression visible par rapport à la V0.10.1.

## 19. En cas d'échec

Consulter :

- [`DIAGNOSTIC.md`](DIAGNOSTIC.md) ;
- [`ROLLBACK.md`](ROLLBACK.md) ;
- [`EXPLOITATION.md`](EXPLOITATION.md).

Ne pas supprimer les tables plugin sans sauvegarde.

Ne pas publier de secret TRAX dans un ticket, une capture d'écran ou un dépôt Git.
