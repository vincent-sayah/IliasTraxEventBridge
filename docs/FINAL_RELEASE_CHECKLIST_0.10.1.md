# Checklist finale V0.10.1

## Contrôle code

```bash
grep -n '\$version' plugin.php
head -5 sql/dbupdate.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

Résultat attendu :

```text
$version = '0.10.1';
<#1>
<?php
aucune erreur PHP
```

## Contrôle ILIAS

- Plugin principal installé ou mis à jour.
- Plugin compagnon installé ou mis à jour.
- Rebuild ILIAS exécuté.
- Service web redémarré.
- Lien `Suivi xAPI` visible dans un cours.

## Contrôle fonctionnel

- Cours activé.
- Ressource activée.
- Trace générée.
- Statement envoyé dans TRAX/LRS.
- Tableau de bord alimenté par TRAX/LRS.
- Analyse alimentée par TRAX/LRS.
- Expert alimenté par TRAX/LRS.
- Export CSV testé.
- Export PDF ou HTML imprimable testé.
