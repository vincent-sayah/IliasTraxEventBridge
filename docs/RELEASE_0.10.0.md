# Release v0.10.0 — Suivi xAPI TRAX/LRS direct

## Statut

```text
Branche source : v0.10-lrs-direct-read
Tag cible      : v0.10.0
Version plugin : 0.10.0
```

La V0.10.0 est la première version où les vues pédagogiques du cours sont alimentées directement par TRAX/LRS.

## Décision d'architecture

```text
Outbox locale = file technique d'envoi
TRAX/LRS      = source officielle du suivi xAPI pédagogique
```

L'outbox locale reste utilisée pour générer, envoyer et superviser les statements. Elle ne sert plus de source aux vues pédagogiques.

## Fonctionnalités validées

```text
Tableau de bord TRAX/LRS                 OK
Analyse TRAX/LRS                         OK
Expert TRAX/LRS                          OK
Export CSV Expert TRAX/LRS               OK
Comparaison entre périodes TRAX/LRS      OK
Purge outbox sans impact pédagogique      OK
Erreur LRS gérée sans erreur fatale       OK
Export PDF Tableau de bord                OK
wkhtmltopdf-opt supporté                  OK
```

## Contrôles avant tag

À exécuter sur la VM de validation :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin
git checkout v0.10-lrs-direct-read
git pull origin v0.10-lrs-direct-read

find . -name "*.php" -print0 | xargs -0 -n1 php -l

grep -n "\$version" plugin.php

git status
git log --oneline -10
```

Résultat attendu :

```text
$version = '0.10.0'
aucune erreur PHP
working tree clean
branche à jour avec origin/v0.10-lrs-direct-read
```

## Réinstallation du companion UIHook

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Puis rebuild ILIAS :

```bash
cd /var/www/ilias
sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
systemctl restart httpd
```

## Contrôles interface

Dans un cours ILIAS :

```text
Suivi xAPI > Tableau de bord
Suivi xAPI > Analyse
Suivi xAPI > Expert
Suivi xAPI > Configuration
```

À vérifier :

- le tableau de bord affiche les données TRAX/LRS ;
- l'analyse affiche les ressources, verbes et apprenants en difficulté ;
- l'expert affiche la source `TRAX` et le `Statement ID` ;
- l'export CSV fonctionne ;
- l'export PDF télécharge un fichier PDF ;
- la configuration contient la supervision technique de l'envoi ;
- le diagnostic `TRAX / LRS direct` est dans `Configuration` ;
- aucun bloc `État technique local` n'est affiché dans le tableau de bord.

## Moteur PDF

Le bouton `Export PDF` utilise l'ordre suivant :

```text
1. Dompdf si disponible
2. wkhtmltopdf si disponible
3. HTML imprimable si aucun moteur PDF n'est disponible
```

Sur AlmaLinux 8 avec les dépôts Remi, le paquet validé est :

```bash
dnf install -y wkhtmltopdf-opt
```

Chemin validé :

```text
/opt/wkhtmltopdf/bin/wkhtmltopdf
```

Le plugin sait détecter ce chemin.

## Création du tag Git

Une fois les contrôles validés :

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin
git checkout v0.10-lrs-direct-read
git pull origin v0.10-lrs-direct-read

git tag -a v0.10.0 -m "Release v0.10.0 - TRAX/LRS direct xAPI tracking"
git push origin v0.10.0
```

## Vérification du tag

```bash
git fetch --tags origin
git tag -l "v0.10.0"
git show --stat v0.10.0
```

## Notes de release GitHub

Titre :

```text
v0.10.0 — Suivi xAPI TRAX/LRS direct
```

Résumé :

```text
La V0.10.0 fait de TRAX/LRS la source officielle du suivi xAPI pédagogique du cours. L'outbox locale reste une file technique d'envoi et de supervision, mais n'alimente plus les vues Tableau de bord, Analyse, Expert, Export CSV, Export PDF et Comparaison entre périodes.
```

Points clés :

- lecture directe TRAX/LRS via `GET /statements` ;
- tableau de bord pédagogique TRAX/LRS ;
- analyse TRAX/LRS ;
- vue Expert TRAX/LRS avec `Statement ID` ;
- export CSV Expert TRAX/LRS ;
- export PDF du tableau de bord ;
- supervision outbox déplacée dans `Configuration` ;
- diagnostic LRS déplacé dans `Configuration` ;
- verbes et ressources TRAX déplacés dans `Analyse` ;
- support `wkhtmltopdf-opt`.
