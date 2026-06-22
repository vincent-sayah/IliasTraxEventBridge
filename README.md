# IliasTraxEventBridge

Plugin ILIAS 10 EventHook pour transformer certains événements ILIAS en statements xAPI et les envoyer vers TRAX 3 LRS.

Version stable actuelle : **v0.4.3**. Branche de développement en cours : **v0.5.5**.

## Fonctionnalités v0.4.3

- Captation d'événements ILIAS via EventHook.
- Journal brut des événements reçus.
- Génération locale de statements xAPI.
- Outbox locale avec statuts `generated`, `sending`, `sent`, `failed`.
- Envoi manuel vers TRAX.
- Envoi automatique par job cron ILIAS `itxeb_send_outbox_to_trax`.
- Retry configurable avec `retry_count`, `max_retry` et `last_attempt_at`.
- Bouton de réinitialisation des statements en échec.
- Diagnostics du dernier test TRAX, du dernier envoi manuel et du dernier cron.
- Affichage amélioré des tableaux de configuration, notamment pour les colonnes Verb et URI.

## Chantier v0.5

La branche **v0.5** introduit le filtre métier suivant : un statement xAPI n'est généré que si l'objet ILIAS concerné est contenu dans un objet **cours**.

Comportement :

- l'événement brut reste journalisé dans `evnt_evhk_itxeb_log` ;
- si aucun cours parent n'est trouvé, aucun statement n'est ajouté dans `evnt_evhk_itxeb_out` ;
- les objets placés directement dans une catégorie, un dossier hors cours ou un autre contexte non cours sont exclus de l'outbox xAPI ;
- quand le cours parent est identifié, les extensions xAPI contiennent `course_ref_id` et `course_obj_id`.

Depuis **v0.5.4**, l'exploitation réelle des objets de dépôt est suivie via la table ILIAS `read_event`, avec une table anti-doublon locale `evnt_evhk_itxeb_read`.

Depuis **v0.5.5**, les événements génériques `Tracking:updateStatus` non-test, notamment sur `crs` ou `root`, ne génèrent plus de statements xAPI. Les consultations utiles passent par `repository_object_access`.

## Objets couverts en v0.5.5

| Action ILIAS | Source | Statement xAPI |
|---|---|---|
| Démarrage d'un test dans un cours | `Tracking:updateStatus` test | `attempted` |
| Test réussi dans un cours | `Tracking:updateStatus` test | `passed` |
| Test échoué dans un cours | `Tracking:updateStatus` test | `failed` |
| Téléchargement d'un fichier dans un cours | EventHook `sendfile` | `experienced` |
| Consultation blog dans un cours | `read_event` | `repository_object_access` / `experienced` |
| Consultation forum dans un cours | `read_event` | `repository_object_access` / `experienced` |
| Consultation lien web dans un cours | `read_event` | `repository_object_access` / `experienced` |
| Consultation mediacast dans un cours | `read_event` | `repository_object_access` / `experienced` |
| Consultation wiki dans un cours | `read_event` | `repository_object_access` / `experienced` |
| Consultation module HTML dans un cours | `read_event` | `repository_object_access` / `experienced` |
| Consultation module web dans un cours | `read_event` | `repository_object_access` / `experienced` |
| Consultation module SCORM dans un cours | `read_event` | `repository_object_access` / `experienced` |

Les actions d'administration comme la suppression des résultats de test sont journalisées mais ne sont pas envoyées dans l'outbox xAPI.

## Cron ILIAS

L'option **Activer le cron plugin** autorise le plugin à envoyer l'outbox, mais elle ne suffit pas à planifier l'exécution.

Il faut aussi activer le job dans ILIAS :

```text
Administration > Paramètres système et maintenance > Tâches cron
```

Job à activer :

```text
IliasTraxEventBridge — envoi outbox vers TRAX
```

Identifiant technique :

```text
itxeb_send_outbox_to_trax
```

## Roadmap

### Cible v0.5

La V0.5 doit limiter le périmètre métier aux objets contenus dans un objet cours et donner le contrôle à l'administrateur du cours.

Objectifs :

- [x] n'envoyer des traces xAPI que pour les objets contenus dans un objet **cours** ;
- [x] exclure les objets placés directement dans une catégorie, un dossier hors cours ou un autre contexte non cours ;
- [x] tracer l'exploitation réelle des objets de dépôt via `read_event` ;
- [x] étendre la couverture aux objets suivants : blog, forum, lien web, mediacast, wiki, module web et module SCORM ;
- [ ] permettre à l'administrateur du cours d'activer ou désactiver l'envoi xAPI vers TRAX dans les paramètres du cours ;
- [ ] permettre à l'administrateur du cours de choisir les types d'objets traçables.

### Cible v0.6

La V0.6 portera sur l'enrichissement xAPI et l'exploitation opérationnelle.

Objectifs :

- améliorer les verbes xAPI selon les types d'événements ILIAS ;
- générer des statements plus riches pour cours, tests, fichiers, modules CMI/xAPI et autres objets couverts ;
- ajouter des filtres dans la configuration globale du plugin ;
- ajouter une page de diagnostic TRAX ;
- ajouter une purge configurable des anciens événements et de l'outbox.

## Documentation complémentaire

- [README technique](README_TECHNIQUE.md)
- [Changelog](CHANGELOG.md)
- [Guide d'import GitHub](GITHUB_IMPORT.md)
- [Plan de validation](docs/VALIDATION.md)
