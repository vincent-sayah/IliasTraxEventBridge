# Index V0.28.5 — IliasTraxEventBridge

## Version

| Élément | Valeur |
|---|---|
| Version plugin principal | `0.28.5-dev` |
| Version compagnon UI | `0.8.50` |
| Branche de validation | `v0.28-dashboard-analysis-config-ai-prompt-validated` |
| Commit fonctionnel validé | `eabc786` |
| Branche stable | `main` |

## Objectif de la V0.28.5

La V0.28.5 stabilise la série V0.28 autour de quatre axes :

```text
1. séparation claire Tableau de bord / Analyse ;
2. modes Compact / Standard / Complet réellement différenciés ;
3. configuration globale du bouton Pilotage xAPI par cours ;
4. configuration plugin plus lisible, avec prompt IA modifiable.
```

## Synthèse fonctionnelle

### Tableau de bord

Vue de décision rapide pour le formateur. Les blocs lourds d'analyse n'y sont plus dupliqués.

### Analyse

Vue d'investigation des résultats : actions recommandées, matrice ressources, questions à fort taux d'échec, MediaCast et apprenants en difficulté.

### Configuration globale du plugin

Présentation gauche/droite, avec les blocs suivants :

- Santé / Diagnostic
- État
- Diagnostics TRAX / cron
- Bouton Pilotage xAPI dans les cours
- Configuration TRAX / cron
- Configuration IA
- Envoi vers TRAX
- Supervision outbox
- Diagnostic des traces refusées
- Outbox xAPI locale
- Derniers événements ILIAS reçus

Le bloc `Ouvrir la configuration xAPI d’un cours` est supprimé.

## Fichiers de référence

- `README.md`
- `CHANGELOG.md`
- `README_TECHNIQUE.md`
- `GITHUB_IMPORT.md`
- `docs/INSTALLATION.md`
- `docs/RELEASE_0.28.5.md`
- `docs/VALIDATION_0.28.5.md`

## Scripts de patch V0.28

- `scripts/apply_v0281_dashboard_analysis_config_ai_prompt.py`
- `scripts/apply_v0283_pilotage_button_course_access_fix.py`
- `scripts/apply_v0284_config_plugin_layout.py`
- `scripts/apply_v0285_config_purge_buttons_layout.py`
