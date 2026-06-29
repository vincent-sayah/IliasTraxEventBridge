# Documentation fonctionnelle — IliasTraxEventBridge V0.10.1

## 1. Objectif général

`IliasTraxEventBridge` permet de mettre en place un suivi xAPI de cours dans ILIAS 10 avec TRAX/LRS comme référentiel de traces.

Le plugin couvre deux besoins :

1. produire des statements xAPI à partir de certains événements ILIAS ;
2. fournir au responsable de cours une interface de suivi exploitant les statements réellement présents dans TRAX/LRS.

La V0.10.1 stabilise le principe suivant :

```text
TRAX/LRS = source officielle du suivi xAPI pédagogique
Outbox locale = file technique d'envoi uniquement
```

## 2. Publics concernés

| Profil | Besoin couvert |
|---|---|
| Administrateur ILIAS | Installer, configurer et superviser le plugin. |
| Administrateur de cours | Activer le suivi xAPI sur son cours et ses ressources. |
| Formateur / pilote de cours | Consulter l'activité pédagogique dans `Suivi xAPI`. |
| Exploitant technique | Vérifier l'envoi vers TRAX/LRS et diagnostiquer les erreurs. |
| Développeur | Étendre les événements captés ou les vues affichées. |

## 3. Parcours fonctionnel principal

### 3.1 Activation globale

L'administrateur ILIAS installe le plugin principal et le plugin compagnon, puis configure l'accès TRAX/LRS.

L'accès TRAX/LRS contient :

- endpoint xAPI ;
- identifiant Basic HTTP ;
- secret Basic HTTP ;
- version xAPI ;
- timeout ;
- paramètres d'envoi outbox.

### 3.2 Activation d'un cours

Dans un cours, l'administrateur du cours ouvre :

```text
Cours > Suivi xAPI > Configuration
```

Il active ensuite :

1. le suivi xAPI du cours ;
2. les ressources du cours à tracer.

La génération xAPI est strictement opt-in :

```text
trace autorisée = cours activé ET ressource activée
```

### 3.3 Production de traces

Quand un utilisateur interagit avec une ressource activée, le plugin principal peut générer un statement xAPI et le placer dans l'outbox locale.

L'envoi vers TRAX/LRS peut être déclenché :

- manuellement depuis l'administration du plugin ;
- automatiquement par le cron ILIAS.

### 3.4 Consultation du suivi

Le formateur ou l'administrateur de cours consulte :

```text
Cours > Suivi xAPI
```

Les vues pédagogiques sont alimentées par TRAX/LRS via lecture directe `GET /statements`.

## 4. Écran Suivi xAPI

L'écran contient quatre vues :

```text
Tableau de bord | Analyse | Expert | Configuration
```

## 5. Vue Tableau de bord

### 5.1 Rôle

La vue `Tableau de bord` fournit une synthèse pédagogique du cours.

Elle n'affiche pas la supervision technique de l'outbox. Cette supervision est déplacée dans `Configuration`.

### 5.2 Données affichées

Le tableau de bord peut afficher :

- nombre de statements TRAX ;
- nombre d'apprenants actifs ;
- nombre de ressources utilisées ;
- score moyen ;
- nombre de tests tentés ;
- nombre de tests réussis ;
- nombre de tests échoués ;
- activité par jour ;
- répartition des actions xAPI ;
- top ressources ;
- ressources activées sans trace TRAX ;
- comparaison avec la période précédente ;
- export PDF.

### 5.3 Filtres

Les filtres disponibles sont :

| Filtre | Usage |
|---|---|
| Période | 7, 30, 90 ou 365 jours. |
| Ressource | Limiter l'analyse à une ressource précise. |
| Type d'objet | Limiter l'analyse à une famille de ressources. |

Si une ressource précise est sélectionnée, le filtre de type peut être ignoré pour éviter une incohérence de filtrage.

## 6. Vue Analyse

### 6.1 Rôle

La vue `Analyse` aide le responsable de cours à comprendre quelles ressources sont utilisées, sous-utilisées ou problématiques.

### 6.2 Informations disponibles

La vue peut afficher :

- analyse des ressources ;
- nombre de traces par ressource ;
- nombre d'apprenants par ressource ;
- dernière activité connue ;
- score moyen par ressource si disponible ;
- tentatives, réussites et échecs pour les tests ;
- signal pédagogique ;
- apprenants en difficulté sous forme anonymisée ;
- verbes retournés par TRAX ;
- ressources retournées par TRAX.

### 6.3 Signaux pédagogiques

Exemples de signaux :

| Signal | Sens |
|---|---|
| utilisée | La ressource a généré des statements TRAX. |
| activée sans trace | La ressource est activée mais aucune trace TRAX n'est trouvée sur la période. |
| à surveiller | L'activité ou les résultats méritent une attention. |
| échecs fréquents | Un test ou une ressource présente un taux d'échec notable. |

Ces signaux servent d'aide à l'analyse. Ils ne remplacent pas une interprétation pédagogique par le formateur.

## 7. Vue Expert

### 7.1 Rôle

La vue `Expert` affiche les statements retournés par TRAX/LRS.

Elle sert à :

- contrôler les données brutes ;
- vérifier les verbes xAPI ;
- vérifier les ressources concernées ;
- exporter les traces en CSV ;
- aider au diagnostic fonctionnel.

### 7.2 Colonnes principales

La vue peut afficher :

- date ;
- utilisateur anonymisé ;
- verbe ;
- identifiant du verbe ;
- titre de l'objet ;
- `ref_id` ;
- `obj_id` ;
- type objet ;
- score ;
- complétion ;
- succès ;
- source `TRAX` ;
- Statement ID.

### 7.3 Export CSV

L'export CSV est basé sur les statements TRAX/LRS et non sur l'outbox locale.

## 8. Vue Configuration

### 8.1 Rôle

La vue `Configuration` regroupe :

- l'activation xAPI du cours ;
- l'activation xAPI par ressource ;
- les préférences du tableau de bord ;
- le diagnostic TRAX/LRS ;
- la supervision technique de l'outbox.

### 8.2 Activation des ressources

L'administrateur de cours sélectionne les ressources à suivre.

Une ressource non activée ne doit pas générer de statement xAPI via ce plugin.

### 8.3 Préférences dashboard

Les préférences permettent d'adapter l'affichage à l'usage du responsable de cours.

Exemples :

- widgets affichés ;
- période par défaut ;
- type de synthèse souhaité.

## 9. Objets et actions couverts

| Action ILIAS | Source | `event_type` | Verbe xAPI / famille |
|---|---|---|---|
| Démarrage d'un test dans un cours | `Tracking:updateStatus` | `test_tracking_status` | `attempted` / `test_tracking` |
| Test réussi | `Tracking:updateStatus` | `test_tracking_status` | `passed` / `test_tracking` |
| Test échoué | `Tracking:updateStatus` | `test_tracking_status` | `failed` / `test_tracking` |
| Téléchargement d'un fichier | EventHook `sendfile` | `file_downloaded` | `downloaded` / `file_download` |
| Consultation blog | `read_event` | `repository_object_access` | `read` / `repository_blog_access` |
| Consultation forum | `read_event` | `repository_object_access` | `interacted` / `repository_forum_access` |
| Consultation lien web | `read_event` | `repository_object_access` | `visited` / `repository_web_link_access` |
| Consultation mediacast | `read_event` | `repository_object_access` | `viewed` / `repository_media_access` |
| Consultation wiki | `read_event` | `repository_object_access` | `read` / `repository_wiki_access` |
| Consultation module HTML | `read_event` | `repository_object_access` | `read` / `repository_html_module_access` |
| Consultation module web | `read_event` | `repository_object_access` | `read` / `repository_learning_module_access` |
| Consultation module SCORM | `read_event` | `repository_object_access` | `launched` / `repository_scorm_access` |

## 10. Données personnelles et anonymisation

La vue pédagogique évite d'exposer directement les identités dans les zones d'analyse de difficulté.

Dans la vue Expert, l'utilisateur est représenté de manière anonymisée lorsque l'information est issue des statements TRAX.

## 11. Limites fonctionnelles connues

- Les vues pédagogiques dépendent de la disponibilité de TRAX/LRS.
- Si TRAX ne contient pas les statements, le tableau de bord ne peut pas les inventer.
- L'outbox locale n'est pas une archive pédagogique durable.
- La comparaison locale / TRAX n'est pas retenue dans la V0.10.1.
- La qualité des indicateurs dépend de la qualité des statements générés et stockés dans TRAX/LRS.

## 12. Critères de validation fonctionnelle

Une installation V0.10.1 est fonctionnellement correcte si :

1. le plugin principal est installé ;
2. le plugin compagnon est installé ;
3. un cours affiche `Suivi xAPI` ;
4. le cours peut être activé ;
5. des ressources peuvent être activées ;
6. les événements génèrent des statements ;
7. l'outbox envoie vers TRAX/LRS ;
8. TRAX/LRS retourne les statements ;
9. le tableau de bord affiche des données TRAX ;
10. la vue Analyse affiche les ressources ;
11. la vue Expert affiche les statements TRAX ;
12. l'export CSV fonctionne ;
13. l'export PDF fonctionne ou fournit un rapport HTML imprimable selon le moteur disponible.
