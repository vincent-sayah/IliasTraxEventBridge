# Documentation fonctionnelle — IliasTraxEventBridge

## Référence courante

La version stable courante est la V0.22.4 promue dans `main`.

La documentation fonctionnelle de base reste :

```text
docs/FONCTIONNEL_0.21.2.md
```

Elle est complétée pour la version courante par :

```text
docs/RELEASE_0.22.4.md
docs/V0.22_ACTIVITY_TIMELINE.md
docs/V0.22.1_ILIAS_LIKE_DASHBOARD_LAYOUT.md
```

## Résumé fonctionnel V0.22.4

`IliasTraxEventBridge` permet de piloter un cours ILIAS 10 à partir de traces xAPI envoyées vers TRAX/LRS.

Accès dans un cours :

```text
Cours > Pilotage xAPI
```

Vues disponibles :

```text
Tableau de bord | Analyse | Analyse IA | Expert | Configuration | Retour contenu du cours
```

## Règle métier actuelle

```text
TRAX = toutes les questions de test ILIAS sont tracées.
Tableau de bord / Analyse = questions problématiques uniquement.
Analyse IA = questions problématiques uniquement.
Expert = vision technique complète.
```

## Fonctions principales

- Activation du suivi par cours et par ressource.
- Génération de statements xAPI depuis les événements ILIAS.
- Envoi vers TRAX/LRS via outbox locale.
- Tableau de bord pédagogique.
- Bloc `Activité dans le temps` compact.
- Présentation type ILIAS avec titre à gauche et données à droite.
- Analyse formateur.
- Analyse IA optionnelle avec historique.
- Retrait d'une analyse IA avec maintien de l'onglet Analyse IA actif.
- Vue Expert technique.
- Export CSV et PDF.
- Suivi question par question des tests ILIAS.
- Détection des questions à fort taux d'échec.

## Documents à lire

| Besoin | Document |
|---|---|
| Note de release courante | [`RELEASE_0.22.4.md`](RELEASE_0.22.4.md) |
| Détail fonctionnel de base | [`FONCTIONNEL_0.21.2.md`](FONCTIONNEL_0.21.2.md) |
| Installation | [`INSTALLATION.md`](INSTALLATION.md) |
| Exploitation | [`EXPLOITATION_0.21.2.md`](EXPLOITATION_0.21.2.md) |
| Validation | [`VALIDATION_0.22.4.md`](VALIDATION_0.22.4.md) |

Les anciens documents V0.10, V0.11 et V0.12 sont conservés comme historique et ne doivent pas être utilisés comme référence fonctionnelle courante.
