# Objectif 03.1 — Diagnostic visible du bouton Tester connexion TRAX

Le résultat du test connexion TRAX est persisté dans les settings du plugin et affiché dans la configuration.

```sql
SELECT keyword, value
FROM settings
WHERE module = 'itxeb'
AND keyword LIKE 'last_trax_%'
ORDER BY keyword;
```
