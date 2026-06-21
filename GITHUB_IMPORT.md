# Guide d’import GitHub

Ce paquet contient un dépôt Git local complet avec :

- une branche `main` ;
- une branche par version ;
- des tags de version ;
- le code du plugin ;
- la documentation fonctionnelle et technique.

## Option A — pousser le dépôt local vers un nouveau repository GitHub

Créer d’abord un repository vide sur GitHub nommé :

```text
IliasTraxEventBridge
```

Puis, depuis le dossier extrait :

```bash
cd IliasTraxEventBridge

git remote add origin https://github.com/<organisation-ou-user>/IliasTraxEventBridge.git

git push -u origin main
git push origin v0.1.0 v0.1.1 v0.1.2 v0.1.3 v0.1.4 v0.1.5 v0.2.0 v0.2.1 v0.3.0 v0.3.1
git push origin --tags
```

## Option B — utiliser le bundle Git

Le paquet contient aussi :

```text
IliasTraxEventBridge_all_versions.bundle
```

Cloner depuis le bundle :

```bash
git clone IliasTraxEventBridge_all_versions.bundle IliasTraxEventBridge
cd IliasTraxEventBridge
```

Puis pousser vers GitHub :

```bash
git remote add origin https://github.com/<organisation-ou-user>/IliasTraxEventBridge.git
git push -u origin main
git push origin --all
git push origin --tags
```

## Vérifier les branches

```bash
git branch -a
```

Branches attendues :

```text
main
v0.1.0
v0.1.1
v0.1.2
v0.1.3
v0.1.4
v0.1.5
v0.2.0
v0.2.1
v0.3.0
v0.3.1
```

## Installation depuis GitHub après import

```bash
cd /var/www/ilias
mkdir -p public/Customizing/global/plugins/Services/EventHandling/EventHook

git clone https://github.com/<organisation-ou-user>/IliasTraxEventBridge.git \
public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

sudo -u apache composer du
sudo -u apache php cli/setup.php build --yes
```
