# Documentation technique — IliasTraxEventBridge V0.10.1

## 1. Vue d'ensemble

`IliasTraxEventBridge` est composé de deux plugins ILIAS :

| Plugin | Type | Rôle |
|---|---|---|
| `IliasTraxEventBridge` | EventHook | Capte les événements ILIAS, génère les statements xAPI, gère l'outbox et l'envoi vers TRAX/LRS. |
| `IliasTraxEventBridgeCourseUI` | UIHook | Ajoute l'interface `Suivi xAPI` dans les cours ILIAS. |

La V0.10.1 sépare strictement :

```text
flux d'envoi technique  : ILIAS -> outbox locale -> TRAX/LRS
flux d'analyse cours    : ILIAS -> lecture directe TRAX/LRS -> vues Suivi xAPI
```

## 2. Architecture logique

```text
+-----------------------------+
| ILIAS 10                    |
|                             |
|  EventHook                  |
|  IliasTraxEventBridge       |
|        |                    |
|        | capte événements   |
|        v                    |
|  StatementFactory           |
|        |                    |
|        v                    |
|  evnt_evhk_itxeb_out        |
|        |                    |
|        | cron / manuel      |
|        v                    |
|  TraxClient POST statements |
|                             |
|  UIHook CourseUI            |
|        |                    |
|        | GET statements     |
|        v                    |
|  LrsReadClient              |
+-------------|---------------+
              |
              v
+-----------------------------+
| TRAX / LRS                  |
| - stockage xAPI             |
| - source officielle suivi   |
+-----------------------------+
```

## 3. Identité du plugin

Fichier :

```text
plugin.php
```

Valeurs attendues en V0.10.1 :

```php
$id = 'itxeb';
$version = '0.10.1';
$ilias_min_version = '10.0.0';
$ilias_max_version = '10.999.999';
```

L'identifiant technique `itxeb` est conservé pour assurer la continuité des versions précédentes.

## 4. Migration SQL

Fichier :

```text
sql/dbupdate.php
```

Point important V0.10.1 : le fichier doit commencer par le marqueur d'étape ILIAS :

```text
<#1>
<?php
```

Ce marqueur est indispensable pour que le moteur de migration ILIAS découpe correctement les étapes.

## 5. Tables SQL

### 5.1 `evnt_evhk_itxeb_log`

Journal brut des événements ILIAS reçus par le EventHook.

Usage :

- diagnostic ;
- observation des événements disponibles ;
- rattachement éventuel à une ligne outbox.

Colonnes importantes :

- `component` ;
- `event_name` ;
- `user_id` ;
- `ref_id` ;
- `obj_id` ;
- `obj_type` ;
- `payload_json` ;
- `request_uri` ;
- `created_at` ;
- `created_ts`.

### 5.2 `evnt_evhk_itxeb_out`

Outbox locale technique des statements xAPI.

Usage :

- stocker les statements générés ;
- piloter l'envoi vers TRAX/LRS ;
- rejouer les erreurs ;
- superviser l'état technique.

Statuts principaux :

```text
generated
sending
sent
failed
```

Colonnes importantes :

- `statement_uuid` ;
- `event_type` ;
- `verb_id` ;
- `statement_json` ;
- `status` ;
- `retry_count` ;
- `max_retry` ;
- `last_attempt_at` ;
- `sent_at` ;
- `last_error`.

### 5.3 `evnt_evhk_itxeb_read`

Table anti-doublon pour les consultations détectées par `read_event`.

Clé primaire :

```text
obj_id + usr_id
```

### 5.4 `evnt_evhk_itxeb_ccfg`

Configuration xAPI par cours.

Usage :

- activation du suivi xAPI du cours ;
- rattachement au `course_ref_id` et `course_obj_id` ;
- préférences dashboard.

### 5.5 `evnt_evhk_itxeb_rcfg`

Configuration xAPI par ressource dans un cours.

Usage :

- activation ou désactivation des ressources ;
- conservation de `ref_id`, `obj_id`, `obj_type` ;
- filtrage métier avant génération xAPI.

### 5.6 `evnt_evhk_itxeb_dlog`

Journal de diagnostic des traces refusées.

Usage :

- comprendre pourquoi une trace n'a pas été générée ;
- vérifier la règle `cours activé ET ressource activée` ;
- analyser les cas où le contexte cours n'est pas résolu.

Cette table peut grossir rapidement. Elle doit être activée uniquement pendant une analyse ciblée.

## 6. Classes principales

| Classe | Rôle |
|---|---|
| `ilIliasTraxEventBridgePlugin` | Classe principale EventHook et fournisseur de cron. |
| `ilIliasTraxEventBridgeConfig` | Lecture / écriture de la configuration plugin. |
| `ilIliasTraxEventBridgeConfigGUI` | Interface d'administration du plugin. |
| `ilIliasTraxEventBridgeEventRouter` | Routage des événements ILIAS vers les traitements xAPI. |
| `ilIliasTraxEventBridgeStatementFactory` | Construction des statements xAPI. |
| `ilIliasTraxEventBridgeOutboxRepository` | Accès SQL à l'outbox. |
| `ilIliasTraxEventBridgeOutboxSender` | Envoi des statements vers TRAX/LRS. |
| `ilIliasTraxEventBridgeTraxClient` | Client HTTP d'écriture xAPI. |
| `ilIliasTraxEventBridgeLrsReadClient` | Client HTTP de lecture `GET /statements`. |
| `ilIliasTraxEventBridgeLrsCourseSummary` | Agrégation des données TRAX/LRS pour les vues cours. |
| `ilIliasTraxEventBridgeCourseContextResolver` | Résolution du contexte cours / ressource. |
| `ilIliasTraxEventBridgeCourseTrackingRepository` | Configuration cours / ressources. |
| `ilIliasTraxEventBridgeDenyLogRepository` | Journalisation des refus. |
| `ilIliasTraxEventBridgeCron` | Job cron d'envoi outbox vers TRAX/LRS. |

## 7. Flux d'envoi xAPI

### 7.1 Déclenchement

ILIAS déclenche un événement capté par le plugin EventHook.

La méthode d'entrée est :

```php
handleEvent(string $a_component, string $a_event, array $a_parameter): void
```

### 7.2 Filtrage métier

Le plugin vérifie :

- plugin actif ;
- génération xAPI locale activée ;
- contexte cours résolu ;
- cours activé ;
- ressource activée.

### 7.3 Génération

`StatementFactory` construit le statement xAPI.

Le statement contient notamment :

- acteur ;
- verbe ;
- objet ;
- contexte cours ;
- extensions xAPI ;
- score / succès / complétion si disponibles.

### 7.4 Outbox

Le statement est inséré dans :

```text
evnt_evhk_itxeb_out
```

Statut initial :

```text
generated
```

### 7.5 Envoi

L'envoi peut être :

- manuel ;
- automatique via cron ILIAS.

Le client d'écriture appelle TRAX/LRS en HTTP Basic avec l'en-tête xAPI.

## 8. Flux de lecture TRAX/LRS

### 8.1 Client de lecture

Classe :

```text
classes/class.ilIliasTraxEventBridgeLrsReadClient.php
```

Ce client n'envoie jamais de statement.

Il exécute uniquement :

```text
GET /statements
```

En-têtes :

```text
Accept: application/json
X-Experience-API-Version: 1.0.3
```

Authentification :

```text
Basic HTTP
```

### 8.2 Paramètres de lecture

La lecture du suivi de cours utilise :

```text
activity=<activité xAPI du cours>
related_activities=true
since=<date début période>
limit=100
```

L'activité de cours suit la forme :

```text
<ILIAS_BASE_URL>/xapi/activity/course/ref/<course_ref_id>
```

### 8.3 Pagination

Le client suit le champ `more` retourné par TRAX/LRS.

Limites V0.10.1 :

```text
5 pages maximum
100 statements par page
```

Si la limite est atteinte, l'interface doit signaler que la pagination LRS n'est pas complète.

## 9. Agrégation pédagogique

Classe :

```text
classes/class.ilIliasTraxEventBridgeLrsCourseSummary.php
```

Cette classe construit :

- synthèse globale ;
- activité par jour ;
- répartition par verbe ;
- regroupement par ressource ;
- lignes Expert ;
- comparaison entre périodes ;
- indicateurs de tests ;
- pagination LRS.

## 10. Identification des ressources

La ressource est identifiée en priorité par l'extension xAPI :

```text
/xapi/extensions/ref_id
```

Si cette extension est absente, le regroupement peut utiliser :

```text
statement.object.id
```

## 11. Export PDF

Le bouton `Export PDF` du tableau de bord produit un rapport basé sur les données TRAX/LRS.

Ordre de sélection du moteur :

```text
1. Dompdf si disponible côté Composer
2. wkhtmltopdf si disponible côté serveur
3. rapport HTML imprimable si aucun moteur PDF n'est disponible
```

Chemin `wkhtmltopdf` supporté notamment :

```text
/opt/wkhtmltopdf/bin/wkhtmltopdf
```

## 12. Plugin compagnon UIHook

Le compagnon est généré depuis les templates du dossier :

```text
companion/
```

Script recommandé :

```bash
bash scripts/install_course_ui_companion_with_standalone_fix.sh
```

Ce script installe le companion et applique les correctifs de navigation validés pour l'affichage du lien `Suivi xAPI` dans les cours.

## 13. Scripts de patch V0.10

Scripts importants :

```text
scripts/patch_course_ui_lrs_direct_summary.php
scripts/patch_course_ui_lrs_primary_views.php
scripts/patch_course_ui_outbox_technical_config.php
scripts/patch_course_ui_lrs_diagnostics_config.php
scripts/patch_course_ui_lrs_analysis_details.php
scripts/patch_course_ui_pdf_export.php
scripts/patch_course_ui_pdf_route.php
scripts/patch_course_ui_pdf_wkhtmltopdf_paths.php
```

Ces scripts servent à générer ou corriger le plugin compagnon pour la V0.10.

## 14. Sécurité et robustesse

Principes retenus :

- les erreurs EventHook ne doivent pas bloquer la navigation ILIAS ;
- les appels TRAX/LRS doivent avoir un timeout ;
- l'indisponibilité TRAX/LRS ne doit pas provoquer d'erreur PHP fatale ;
- les secrets TRAX ne doivent pas être affichés en clair ;
- la vue pédagogique ne doit pas dépendre de l'outbox locale purgeable ;
- les apprenants en difficulté sont affichés sous forme anonymisée.

## 15. Contrôles techniques recommandés

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

grep -n '\$version' plugin.php
head -5 sql/dbupdate.php
find . -name "*.php" -print0 | xargs -0 -n1 php -l
git status
```

Résultats attendus :

```text
$version = '0.10.1';
sql/dbupdate.php commence par <#1>
aucune erreur PHP
working tree clean
```
