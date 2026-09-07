# Index de référence — V0.24.17

Version : `0.24.17-dev`  
Plugin compagnon UI : `0.8.37`  
Branche de développement : `v0.24-dashboard-synthesis-layout`  
Commit de validation fonctionnelle : `3a05d77` — `V0.24.17 validate dashboard synthesis and activity layout`

## Statut

La V0.24.17 est la version validée du chantier d'amélioration du tableau de bord pédagogique.

Elle conserve les règles métier xAPI précédentes et ne modifie pas le suivi MediaCast validé en V0.23.8.

## Documents V0.24.17

| Document | Rôle |
|---|---|
| [`RELEASE_0.24.17.md`](RELEASE_0.24.17.md) | Note de release fonctionnelle et technique. |
| [`VALIDATION_0.24.17.md`](VALIDATION_0.24.17.md) | Checklist de validation avant promotion. |
| [`INDEX_0.23.8.md`](INDEX_0.23.8.md) | Référence de la version stable précédente MediaCast. |
| [`V0.23_MEDIACAST.md`](V0.23_MEDIACAST.md) | Cadrage MediaCast conservé. |

## Périmètre validé

- Réorganisation du tableau de bord autour d'une `Synthèse pédagogique` plus lisible.
- Intégration des indicateurs pédagogiques dans la synthèse.
- Suppression des doublons visuels des tuiles KPI.
- Ajout d'icônes dans les cartes KPI.
- Filtrage du bloc `Questions à fort taux d’échec` pour éviter son affichage sur des ressources non-test.
- Remplacement de l'ancien graphique horizontal d'activité par un graphique linéaire SVG.
- Alignement du bloc `Activité dans le temps` selon le modèle titre ILIAS à gauche / contenu à droite.
- Alignement final de `Top ressources` sur la même ligne que la carte `Progression de l’activité`.

## Règle métier conservée

```text
TRAX = toutes les questions de test ILIAS sont tracées.
Tableau de bord / Analyse = seules les questions problématiques sont remontées.
Analyse IA = seules les questions problématiques sont intégrées au payload IA.
Expert = vision technique complète.
Analyse = détail MediaCast des vidéos internes lues et médias externes ouverts.
```

## Ordre des scripts de migration V0.24 retenus

Les scripts historiques V0.24.3 à V0.24.16 ont servi aux essais d'alignement et ne doivent pas être relancés pour une installation propre.

Pour reconstruire l'état V0.24.17 depuis `main`, l'ordre retenu est :

```bash
php scripts/apply_v024_dashboard_synthesis_layout.php
php scripts/apply_v0241_dashboard_question_hotspots_filter.php
php scripts/apply_v0242_dashboard_activity_line_chart.php
php scripts/apply_v02410_dashboard_activity_alignment_final.php
php scripts/apply_v02411_dashboard_activity_section_alignment.php
php scripts/apply_v02417_dashboard_top_resources_chart_row_safe.php
```

## À ne pas relancer

```text
V0.24.3
V0.24.4
V0.24.5
V0.24.6
V0.24.7
V0.24.8
V0.24.9
V0.24.12
V0.24.13
V0.24.14
V0.24.15
V0.24.16
```

Ces scripts sont conservés pour historique mais ne constituent pas la chaîne de migration finale validée.
