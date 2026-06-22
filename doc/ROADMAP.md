# Roadmap — IliasTraxEventBridge

## Version stable actuelle

La version stable actuelle est **v0.5.5**.

## V0.5 — stabilisée

La V0.5.5 clôture la série V0.5.

Réalisé :

- envoi manuel vers TRAX ;
- envoi automatique par job cron ILIAS ;
- retry configurable ;
- filtre cours ;
- consultations suivies via `read_event` ;
- table anti-doublon `evnt_evhk_itxeb_read` ;
- couverture test, fichier, blog, forum, lien web, mediacast, wiki, module HTML, module web et SCORM.

## Cible v0.6

La V0.6 portera sur l'enrichissement xAPI.

Objectifs :

1. Améliorer les verbes xAPI.
2. Ajouter plus de contexte dans les statements.
3. Ajouter des filtres dans la configuration globale.
4. Ajouter une page de diagnostic TRAX.
