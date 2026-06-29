# Commandes de tag V0.10.1

À exécuter depuis un poste Git autorisé :

```bash
git fetch origin
git checkout v0.10-lrs-direct-read
git pull origin v0.10-lrs-direct-read
git tag -a v0.10.1 -m "Release stable v0.10.1"
git push origin v0.10.1
```

Contrôle :

```bash
git tag --list "v0.10.1"
git show v0.10.1 --stat
```
