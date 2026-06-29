# Roadmap — IliasTraxEventBridge

Cette roadmap décrit les évolutions possibles après la version stable **V0.10.1**.

Elle n'est pas un engagement de livraison. Elle sert à cadrer les priorités fonctionnelles, techniques et pédagogiques du projet.

## État actuel — V0.10.1 stable

La V0.10.1 est la version stable promue sur `main`.

Fonctions disponibles :

- captation d'événements ILIAS via EventHook ;
- génération de statements xAPI ;
- outbox locale technique ;
- envoi vers TRAX/LRS ;
- activation xAPI par cours ;
- activation xAPI par ressource ;
- écran `Suivi xAPI` dans le cours ;
- tableau de bord alimenté par TRAX/LRS ;
- analyse alimentée par TRAX/LRS ;
- vue Expert alimentée par TRAX/LRS ;
- export CSV ;
- export PDF ou rapport HTML imprimable ;
- diagnostic TRAX/LRS ;
- documentation complète.

Décision d'architecture maintenue :

```text
Outbox locale = file technique d'envoi
TRAX/LRS      = source officielle du suivi xAPI pédagogique
```

## V0.11 — Durcissement exploitation et packaging

### Objectif

Rendre le plugin plus robuste et plus simple à installer / maintenir sur différents environnements ILIAS 10.

### Pistes

- Ajouter une page `Santé du plugin` dans l'administration.
- Ajouter un diagnostic global : version plugin, tables SQL, endpoint TRAX, droits d'écriture et lecture LRS, cron actif, plugin compagnon installé.
- Ajouter un bouton `Tester la lecture TRAX/LRS` avec résultat détaillé.
- Ajouter un bouton `Tester l'envoi xAPI` avec statement de test contrôlé.
- Améliorer les messages d'erreur utilisateur.
- Ajouter une vérification de présence du marqueur `<#1>` dans `sql/dbupdate.php` dans la documentation de diagnostic.
- Ajouter une procédure de rollback documentée.
- Ajouter un mode maintenance empêchant temporairement la génération de nouvelles traces.

### Livrables possibles

- `docs/DIAGNOSTIC.md`.
- `docs/ROLLBACK.md`.
- Script de diagnostic shell.
- Écran admin de santé technique.

## V0.12 — Enrichissement pédagogique du tableau de bord

### Objectif

Rendre le suivi xAPI plus utile pour un formateur ou un pilote de cours.

### Pistes

- Ajouter des indicateurs de progression par ressource.
- Ajouter des tendances par semaine ou par session.
- Ajouter une détection des ressources peu consultées.
- Ajouter une détection des ressources très consultées mais associées à de mauvais résultats.
- Ajouter des indicateurs de décrochage.
- Ajouter une vue `Parcours apprenant` anonymisée ou pseudonymisée.
- Ajouter une vue comparative entre ressources d'un même cours.
- Ajouter une exportation JSON ou Excel pour analyse externe.

### Questions à trancher

- Jusqu'où afficher des informations individuelles dans ILIAS ?
- Faut-il anonymiser systématiquement les apprenants dans toutes les vues pédagogiques ?
- Faut-il prévoir des droits différents entre administrateur technique, administrateur de cours et formateur ?

## V0.13 — Analyse IA optionnelle des traces xAPI

### Objectif

Ajouter une fonctionnalité optionnelle d'analyse des traces xAPI par IA, configurable avec une clé API IA.

L'objectif n'est pas de remplacer le formateur, mais de fournir une aide à l'interprétation : synthèse, signaux faibles, ressources à surveiller, recommandations d'amélioration.

### Principe général

```text
ILIAS > lecture TRAX/LRS > agrégation locale > anonymisation > appel IA optionnel > synthèse pédagogique
```

L'appel IA ne doit jamais être obligatoire pour utiliser le plugin.

### Configuration envisagée

Dans l'administration du plugin :

| Paramètre | Description |
|---|---|
| Activer l'analyse IA | Active ou désactive toutes les fonctions IA. |
| Fournisseur IA | Fournisseur compatible API HTTP. |
| URL API IA | Endpoint du service IA. |
| Clé API IA | Secret chiffré ou au minimum masqué en interface. |
| Modèle IA | Nom du modèle utilisé. |
| Timeout IA | Timeout spécifique aux appels IA. |
| Mode anonymisation | Aucun, pseudonymisation, anonymisation stricte. |
| Nombre maximum de traces | Limite de statements envoyés à l'IA. |
| Journaliser les appels IA | Journal technique sans contenu sensible. |

### Fonctions IA possibles

- Résumé automatique de l'activité du cours sur une période.
- Identification des ressources à surveiller.
- Détection de ressources activées mais non utilisées.
- Analyse des tests avec échecs fréquents.
- Synthèse des tendances : hausse / baisse d'activité.
- Recommandations pédagogiques pour améliorer le cours.
- Explication en langage naturel du tableau de bord.
- Génération d'un rapport IA exportable.

### Exemple de sortie attendue

```text
Sur les 30 derniers jours, l'activité du cours est concentrée sur 3 ressources.
Le test final présente un taux d'échec élevé.
Deux ressources activées ne produisent aucune trace.
Il est recommandé de vérifier les consignes du test final et de repositionner les ressources peu consultées dans le parcours.
```

### Sécurité et données personnelles

Points obligatoires avant implémentation :

- ne jamais envoyer le secret TRAX à l'IA ;
- ne jamais envoyer les mots de passe ou jetons techniques ;
- limiter les données envoyées au strict nécessaire ;
- pseudonymiser les utilisateurs ;
- pouvoir désactiver totalement l'IA ;
- afficher clairement à l'administrateur qu'un service externe peut être appelé ;
- journaliser les appels sans stocker les prompts complets si ceux-ci contiennent des données sensibles ;
- prévoir une configuration compatible intranet / service IA interne.

### Architecture technique possible

Nouvelles classes possibles :

```text
classes/class.ilIliasTraxEventBridgeAiConfig.php
classes/class.ilIliasTraxEventBridgeAiClient.php
classes/class.ilIliasTraxEventBridgeAiPromptBuilder.php
classes/class.ilIliasTraxEventBridgeAiAnonymizer.php
classes/class.ilIliasTraxEventBridgeAiCourseAnalyzer.php
classes/class.ilIliasTraxEventBridgeAiAnalysisRepository.php
```

Nouvelles tables possibles :

```text
evnt_evhk_itxeb_ai_cfg
  Configuration IA, fournisseur, modèle, options, sans exposer la clé en clair.

evnt_evhk_itxeb_ai_log
  Journal technique des appels IA : date, cours, statut, durée, erreur éventuelle.

evnt_evhk_itxeb_ai_cache
  Cache optionnel des analyses IA pour éviter de rappeler l'API à chaque affichage.
```

### Points de vigilance

- Coût des appels API IA.
- Temps de réponse de l'interface ILIAS.
- Dépendance à un service externe.
- Confidentialité des traces xAPI.
- Qualité variable des recommandations IA.
- Risque d'interprétation erronée si peu de données.
- Nécessité d'afficher les analyses IA comme une aide, pas comme une vérité absolue.

## V0.14 — Historisation durable et gouvernance

### Objectif

Ajouter une couche durable indépendante de l'outbox pour conserver des indicateurs pédagogiques calculés.

### Pistes

- Ajouter une table d'agrégats locaux par cours / ressource / jour.
- Ajouter une table d'archives des indicateurs, pas des statements complets.
- Ajouter une politique de rétention configurable.
- Ajouter une purge planifiée.
- Ajouter des exports de conformité.
- Ajouter une séparation plus fine des droits.

### Pourquoi ne pas utiliser l'outbox ?

L'outbox locale est technique et purgeable. Elle ne doit pas devenir l'archive fonctionnelle du suivi pédagogique.

## V0.15 — Connecteurs et interopérabilité

### Objectif

Faciliter l'intégration avec d'autres outils d'analyse.

### Pistes

- Export JSON des indicateurs cours.
- Export CSV enrichi.
- Export Excel si une dépendance fiable est retenue.
- API interne de lecture des indicateurs.
- Webhook optionnel vers un portail d'analyse.
- Compatibilité avec un LRS autre que TRAX si les endpoints xAPI standards sont respectés.

## Backlog technique transverse

- Ajouter tests unitaires sur les classes de parsing LRS.
- Ajouter tests de non-régression sur `dbupdate.php`.
- Ajouter vérification automatique de syntaxe PHP.
- Ajouter script de contrôle de release.
- Nettoyer les scripts de patch devenus inutiles après stabilisation.
- Documenter précisément les différences ILIAS 10.5 / 10.8.
- Renforcer la compatibilité avec les thèmes autres que Delos.

## Backlog fonctionnel transverse

- Ajouter plus de verbes xAPI.
- Couvrir plus de types d'objets ILIAS.
- Ajouter suivi des dépôts de devoirs si pertinent.
- Ajouter suivi des forums plus fin.
- Ajouter suivi des parcours de modules.
- Ajouter vues par groupe si le cours utilise des groupes ILIAS.

## Priorisation proposée

| Priorité | Sujet | Pourquoi |
|---|---|---|
| Haute | Diagnostic santé plugin | Facilite l'exploitation et réduit les incidents. |
| Haute | Documentation rollback | Utile en production. |
| Haute | Stabilisation plugin compagnon | Point sensible de l'intégration ILIAS. |
| Moyenne | Indicateurs pédagogiques avancés | Améliore la valeur métier. |
| Moyenne | Analyse IA optionnelle | Forte valeur ajoutée mais nécessite cadrage sécurité. |
| Moyenne | Cache IA | Nécessaire pour maîtriser coût et performance. |
| Basse | Exports avancés | Utile mais non bloquant. |

## Décision recommandée pour la prochaine étape

Prochaine version conseillée : **V0.11 — Durcissement exploitation**.

L'analyse IA est très intéressante, mais elle doit arriver après :

1. un diagnostic technique fiable ;
2. une configuration TRAX/LRS stable ;
3. une stratégie d'anonymisation ;
4. une validation des contraintes de sécurité ;
5. un choix de fournisseur IA compatible avec l'environnement cible.
