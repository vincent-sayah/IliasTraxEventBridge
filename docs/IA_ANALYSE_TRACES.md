# Analyse IA des traces xAPI — cadrage futur

Ce document cadre une évolution possible du plugin `IliasTraxEventBridge` : ajouter une analyse optionnelle des traces xAPI par IA à partir des données TRAX/LRS.

Cette fonctionnalité n'est pas présente dans la V0.10.1. Elle est proposée pour une version future, probablement après une V0.11 de durcissement exploitation.

## 1. Objectif

L'objectif est d'aider un formateur, un pilote de cours ou un administrateur de cours à comprendre plus rapidement l'activité xAPI.

L'IA pourrait produire :

- une synthèse pédagogique de l'activité ;
- une explication simple du tableau de bord ;
- une détection des ressources peu utilisées ;
- une détection des ressources associées à des échecs ;
- une identification de tendances ;
- des recommandations d'amélioration du cours ;
- un rapport exportable.

L'IA doit rester une aide à l'interprétation. Elle ne doit pas décider à la place du formateur.

## 2. Principe général

```text
TRAX/LRS
  ↓ lecture xAPI
IliasTraxEventBridge
  ↓ agrégation locale
Anonymisation / pseudonymisation
  ↓ appel IA optionnel
Synthèse pédagogique affichée dans ILIAS
```

Le plugin ne doit pas envoyer directement les statements bruts complets si ce n'est pas nécessaire.

## 3. Activation optionnelle

L'analyse IA doit être désactivée par défaut.

Elle doit être activée explicitement par un administrateur technique :

```text
Administration > Plugins > IliasTraxEventBridge > Configuration IA
```

Paramètres envisagés :

| Paramètre | Description |
|---|---|
| Activer l'analyse IA | Active ou désactive toute la fonctionnalité IA. |
| Fournisseur IA | Fournisseur ou service IA interne. |
| URL API IA | Endpoint HTTP de l'API IA. |
| Clé API IA | Clé d'authentification, masquée en interface. |
| Modèle IA | Nom du modèle utilisé. |
| Timeout IA | Timeout des requêtes IA. |
| Mode anonymisation | Aucun, pseudonymisation, anonymisation stricte. |
| Limite de traces | Nombre maximum de traces utilisées pour une analyse. |
| Cache des réponses | Évite des appels répétés et coûteux. |
| Journalisation | Journal technique des appels sans contenu sensible. |

## 4. Clé API IA

### 4.1 Besoin

La clé API IA permet au plugin d'appeler un service IA externe ou interne.

### 4.2 Stockage

Options possibles :

1. stockage dans les settings ILIAS avec masquage en interface ;
2. stockage chiffré si une mécanique fiable est disponible ;
3. stockage hors base dans une variable d'environnement serveur ;
4. stockage dans un fichier de configuration serveur non versionné.

Option recommandée pour un environnement sensible :

```text
clé API stockée côté serveur hors dépôt Git, injectée par variable d'environnement ou fichier protégé
```

### 4.3 Affichage

La clé ne doit jamais être réaffichée en clair.

L'interface peut afficher :

```text
Clé configurée : oui
Dernière modification : date / administrateur
```

## 5. Données envoyées à l'IA

### 5.1 Données autorisées

Données agrégées recommandées :

- nombre total de statements ;
- activité par jour ;
- nombre d'apprenants actifs anonymisés ;
- ressources utilisées ;
- ressources activées sans trace ;
- taux de réussite / échec des tests ;
- score moyen ;
- verbes xAPI présents ;
- période analysée ;
- contexte pédagogique du cours si fourni par l'administrateur.

### 5.2 Données à éviter

Ne pas envoyer :

- mots de passe ;
- secrets TRAX ;
- clés API ;
- jetons techniques ;
- données personnelles directes si non nécessaires ;
- noms complets des apprenants si l'anonymisation est activée ;
- logs serveur bruts ;
- données hors périmètre du cours.

## 6. Anonymisation

Modes possibles :

| Mode | Description |
|---|---|
| Aucun | À éviter sauf environnement maîtrisé et autorisé. |
| Pseudonymisation | Remplace les utilisateurs par des identifiants stables non directement lisibles. |
| Anonymisation stricte | Supprime les identifiants utilisateur et ne conserve que les agrégats. |

Mode recommandé par défaut :

```text
Anonymisation stricte
```

## 7. Types d'analyse possibles

### 7.1 Synthèse courte

Une synthèse lisible par le formateur :

```text
Le cours présente une activité régulière sur les 30 derniers jours.
Les ressources les plus utilisées sont le module d'introduction et le test final.
Deux ressources activées ne génèrent aucune trace.
```

### 7.2 Analyse pédagogique

Analyse plus détaillée :

- ressources les plus consultées ;
- ressources sous-utilisées ;
- tests difficiles ;
- rythme d'activité ;
- anomalies d'engagement.

### 7.3 Recommandations

Exemples :

- revoir les consignes d'un test ;
- repositionner une ressource peu visible ;
- ajouter une activité intermédiaire ;
- vérifier qu'une ressource activée est bien accessible ;
- améliorer le guidage pédagogique.

### 7.4 Rapport exportable

Générer un rapport IA exportable au format :

- HTML imprimable ;
- PDF ;
- Markdown ;
- texte simple.

## 8. Prompt IA envisagé

Exemple de structure de prompt :

```text
Tu es un assistant pédagogique spécialisé en analyse de traces xAPI.
Analyse les indicateurs agrégés d'un cours ILIAS.
Ne déduis pas d'informations absentes des données.
Signale les limites de l'analyse.
Produit une synthèse courte, puis des recommandations actionnables.
```

Le prompt doit recevoir des données structurées, par exemple :

```json
{
  "course": {
    "title": "Cours exemple",
    "period_days": 30
  },
  "summary": {
    "statements": 128,
    "active_learners": 18,
    "resources_with_traces": 6,
    "resources_without_traces": 2,
    "tests_failed": 9,
    "tests_passed": 21
  },
  "resources": [
    {
      "title": "Test final",
      "type": "tst",
      "traces": 42,
      "passed": 21,
      "failed": 9
    }
  ]
}
```

## 9. Cache des analyses

Pour éviter des coûts et délais excessifs :

- ne pas appeler l'IA à chaque chargement de page ;
- prévoir un bouton `Générer l'analyse IA` ;
- stocker temporairement le résultat ;
- afficher la date de génération ;
- prévoir un bouton `Régénérer`.

Table possible :

```text
evnt_evhk_itxeb_ai_cache
```

Colonnes possibles :

- `course_ref_id` ;
- `period_days` ;
- `filters_hash` ;
- `created_at` ;
- `created_by` ;
- `provider` ;
- `model` ;
- `analysis_json` ;
- `analysis_text` ;
- `last_error`.

## 10. Journalisation

Table possible :

```text
evnt_evhk_itxeb_ai_log
```

Contenu recommandé :

- date ;
- cours ;
- utilisateur ayant lancé l'analyse ;
- fournisseur ;
- modèle ;
- statut ;
- durée ;
- nombre de tokens ou volume si disponible ;
- erreur éventuelle.

Ne pas stocker par défaut le prompt complet si celui-ci contient des informations sensibles.

## 11. Interface utilisateur envisagée

Dans `Cours > Suivi xAPI`, ajouter un onglet ou un bloc :

```text
Analyse IA
```

Ou dans le tableau de bord :

```text
Bouton : Générer une synthèse IA
```

Affichage possible :

- état de configuration IA ;
- dernière analyse générée ;
- synthèse ;
- points à surveiller ;
- recommandations ;
- limites de l'analyse ;
- bouton exporter.

## 12. Sécurité

Points non négociables :

- IA désactivée par défaut ;
- clé API jamais versionnée dans Git ;
- clé API jamais affichée en clair ;
- secrets TRAX jamais envoyés ;
- anonymisation disponible ;
- limitation du volume envoyé ;
- journal technique minimal ;
- timeout obligatoire ;
- message clair si le service IA est indisponible.

## 13. Environnement intranet / hors internet

Dans un environnement fermé, l'IA pourrait être :

- désactivée ;
- connectée à une API IA interne ;
- connectée à une passerelle interne autorisée ;
- utilisée uniquement sur données anonymisées exportées.

Le plugin doit donc permettre de configurer l'URL du fournisseur IA, et pas seulement un fournisseur public figé.

## 14. Risques

| Risque | Mesure proposée |
|---|---|
| Fuite de données personnelles | Anonymisation stricte par défaut. |
| Coût API élevé | Cache, limite de traces, action manuelle. |
| Réponse IA erronée | Afficher les limites et conserver les indicateurs sources. |
| Dépendance fournisseur | API configurable. |
| Temps de réponse long | Timeout, traitement asynchrone futur ou cache. |
| Non-conformité sécurité | Désactivation par défaut, documentation d'exploitation. |

## 15. Positionnement recommandé

L'analyse IA doit être présentée comme :

```text
une aide à la lecture des traces xAPI
```

Elle ne doit pas être présentée comme :

```text
un outil de décision automatique sur les apprenants
```

## 16. Étapes de mise en œuvre possibles

1. Ajouter la configuration IA sans appel effectif.
2. Ajouter un test de connexion IA.
3. Ajouter l'anonymisation / agrégation.
4. Ajouter un premier prompt de synthèse courte.
5. Ajouter le cache.
6. Ajouter l'affichage dans le cours.
7. Ajouter le rapport exportable.
8. Ajouter la documentation sécurité / exploitation.

## 17. Décision recommandée

Avant de développer cette fonctionnalité, valider :

- fournisseur IA autorisé ;
- mode de stockage de la clé API ;
- politique d'anonymisation ;
- périmètre des données envoyées ;
- coût acceptable ;
- droits utilisateurs ;
- contraintes RGPD ou sécurité internes.
