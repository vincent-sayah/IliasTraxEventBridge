# Complément de stabilisation V0.10.1

La V0.10.1 est prête côté branche `v0.10-lrs-direct-read`.

Ce complément rappelle que le dépôt contient maintenant :

- une version plugin `0.10.1` ;
- la correction d'installation `sql/dbupdate.php` avec `<#1>` ;
- une documentation complète ;
- une note de release ;
- un changelog à jour.

À réaliser depuis un poste Git autorisé pour finaliser la release publique :

```bash
git fetch origin
git checkout v0.10-lrs-direct-read
git pull origin v0.10-lrs-direct-read
git tag -a v0.10.1 -m "Release stable v0.10.1"
git push origin v0.10.1
```
