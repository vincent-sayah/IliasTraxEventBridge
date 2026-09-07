# Release V0.25.6 — affichage du login apprenant

| Élément | Valeur |
|---|---|
| Version plugin principal | `0.25.6-dev` |
| Version plugin compagnon UI | `0.8.44` |
| Branche | `v0.25-learner-identity-display` |
| Commit fonctionnel validé | `8d97685` |
| Promotion cible | `main` |

## Résumé

La V0.25.6 remplace l'affichage technique de type `ilias-user-ID` par le login ILIAS dans les vues destinées au suivi formateur.

La version est centrée sur deux écrans :

- `Analyse`, pour le tableau `Apprenants en difficulté` ;
- `Expert`, pour l'ajout d'une colonne `Apprenant` entre `User ID` et `Verbe`.

## Changements fonctionnels

### Onglet Analyse

Le bloc `Apprenants en difficulté` n'utilise plus le pseudonyme `Apprenant xxxxxxxx`.

Il affiche désormais le login ILIAS résolu à partir de l'acteur xAPI retourné par TRAX/LRS.

### Onglet Expert

La vue Expert affiche désormais la structure suivante :

```text
Date | User ID | Apprenant | Verbe | Ressource | Type | Score | Completion | Success | Source | Statement ID
```

La colonne `User ID` reste conservée comme identifiant technique pseudonymisé.

La colonne `Apprenant` affiche le login ILIAS lisible par le formateur.

### Export CSV Expert

L'export CSV Expert contient maintenant la colonne :

```text
learner_identity
```

Cette colonne correspond à l'identité apprenant utilisée dans la vue Expert.

## Résolution technique du login

La résolution suit cet ordre :

1. lecture de `actor.account.name` dans le statement xAPI ;
2. détection du format `ilias-user-ID` ;
3. résolution via `ilObjUser::_lookupLogin()` lorsque la classe ILIAS est disponible ;
4. fallback via `$DIC->database()` ;
5. fallback via `$ilDB` ;
6. requête sur `usr_data.login` ;
7. fallback final sur l'identité transmise par TRAX/LRS si aucun login n'est trouvé.

Un cache local par requête limite les lectures répétées du même `usr_id`.

## Périmètre inchangé

- Génération des statements xAPI inchangée.
- Outbox locale inchangée.
- Envoi vers TRAX/LRS inchangé.
- Règle d'activation cours/ressource inchangée.
- Tableau de bord V0.24.17 conservé.
- Suivi MediaCast V0.23.8 conservé.
- Analyse IA inchangée.
- Filtrage des questions problématiques inchangé.

## Règle métier conservée

```text
TRAX = toutes les questions de test ILIAS sont tracées.
Tableau de bord / Analyse = seules les questions problématiques sont remontées.
Analyse IA = seules les questions problématiques sont intégrées au payload IA.
Expert = vision technique complète.
Analyse = détail MediaCast des vidéos internes lues et médias externes ouverts.
```

## Validation

Validation effectuée sur serveur ILIAS 10 :

- script V0.25.6 appliqué ;
- `php -l` OK sur le plugin principal ;
- `php -l` OK sur `class.ilIliasTraxEventBridgeLrsCourseSummary.php` ;
- `php -l` OK sur le template écran compagnon ;
- `php -l` OK sur le plugin compagnon live ;
- affichage fonctionnel dans `Analyse` ;
- affichage fonctionnel dans `Expert` ;
- remplacement de `ilias-user-6` / `ilias-user-401` par le login ILIAS validé.

## Script de réapplication

Script conservé dans le dépôt :

```bash
python3 scripts/apply_v0256_learner_login_resolution.py
```

Les scripts V0.25.0 à V0.25.4 sont conservés comme historique de développement, mais la référence validée est V0.25.6.
