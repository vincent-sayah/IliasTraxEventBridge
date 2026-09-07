# Documentation V0.25.6 — affichage du login apprenant

| Élément | Valeur |
|---|---|
| Version plugin principal | `0.25.6-dev` |
| Version plugin compagnon UI | `0.8.44` |
| Branche de développement | `v0.25-learner-identity-display` |
| Commit de validation fonctionnelle | `8d97685` — `V0.25.6 validate learner login display` |
| Base stable précédente | `0.24.17-dev` |
| Compatibilité | ILIAS 10.x |

## Objectif

La V0.25.6 finalise l'affichage nominatif des apprenants dans les vues de pilotage du cours.

Elle répond au besoin suivant :

```text
Analyse : ne plus anonymiser les apprenants en difficulté.
Expert : ajouter une colonne Apprenant entre User ID et Verbe.
Affichage attendu : login ILIAS, pas ilias-user-ID.
```

## Changements validés

- Onglet `Analyse` : le bloc `Apprenants en difficulté` affiche le login ILIAS.
- Onglet `Expert` : ajout de la colonne `Apprenant` entre `User ID` et `Verbe`.
- Export CSV Expert : ajout de la colonne `learner_identity`.
- Résolution de l'acteur xAPI `ilias-user-ID` vers `usr_data.login`.
- Conservation de `User ID` comme identifiant technique pseudonymisé.
- Suppression de l'ancien pseudonyme `Apprenant xxxxxxxx`.
- Fallback conservé sur l'identité xAPI si le login ILIAS n'est pas résolu.

## Fichiers fonctionnels concernés

| Fichier | Rôle |
|---|---|
| `classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php` | Ajoute `learner_identity` dans les lignes Expert et résout `ilias-user-ID` vers le login ILIAS. |
| `companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl` | Affiche le login dans Analyse et Expert, ajoute la colonne `Apprenant`. |
| `plugin.php` | Version principale `0.25.6-dev`. |
| `companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl` | Version compagnon `0.8.44`. |
| `scripts/apply_v0256_learner_login_resolution.py` | Script de réapplication du correctif login apprenant. |

## Documentation liée

| Document | Rôle |
|---|---|
| `docs/RELEASE_0.25.6.md` | Note de release fonctionnelle et technique. |
| `docs/VALIDATION_0.25.6.md` | Checklist de validation serveur et navigateur. |
| `docs/INSTALLATION.md` | Installation et mise à jour depuis `main`. |
| `README.md` | Synthèse projet et version courante. |
| `README_TECHNIQUE.md` | Architecture technique courante. |
| `companion/IliasTraxEventBridgeCourseUI/README.md` | Documentation du plugin compagnon UIHook. |
| `CHANGELOG.md` | Historique des versions. |

## Résultat attendu côté formateur

### Analyse

Le tableau `Apprenants en difficulté` affiche un login lisible, par exemple :

```text
jdupont
msayah
formation01
```

Il ne doit plus afficher :

```text
Apprenant xxxxxxxx
ilias-user-6
ilias-user-401
```

### Expert

Le tableau Expert affiche :

```text
Date | User ID | Apprenant | Verbe | Ressource | Type | Score | Completion | Success | Source | Statement ID
```

`User ID` reste technique et pseudonymisé. `Apprenant` contient le login ILIAS.

## Scripts V0.25

Les scripts V0.25.0 à V0.25.4 sont conservés pour historique mais ne doivent plus être utilisés en exploitation.

Script validé :

```bash
python3 scripts/apply_v0256_learner_login_resolution.py
```

## Statut

V0.25.6 validée fonctionnellement sur serveur ILIAS 10 puis poussée sur GitHub via bundle Windows.
