# CHANGELOG

## v0.28.5 — configuration plugin réorganisée et stabilisation V0.28

### Statut

Version validée fonctionnellement sur serveur ILIAS 10 puis intégrée dans GitHub.

- Plugin principal : `0.28.5-dev`
- Plugin compagnon UI : `0.8.50`
- Branche de validation : `v0.28-dashboard-analysis-config-ai-prompt-validated`
- Commit fonctionnel validé : `eabc786`
- Promotion cible : `main`

### Changements fonctionnels

- Réaménagement des onglets `Tableau de bord` et `Analyse` pour supprimer les redondances.
- `Tableau de bord` recentré sur la décision rapide : état global, réussite du cours, entonnoir pédagogique, synthèse et indicateurs essentiels.
- `Analyse` recentré sur l'investigation : actions recommandées, matrice ressources, questions à fort taux d'échec, MediaCast et apprenants en difficulté.
- Modes `Compact`, `Standard` et `Complet` réellement différenciés dans la personnalisation du tableau de bord.
- Retour sur la même zone de page après les actions d'enregistrement grâce aux ancres.
- Bouton `Pilotage xAPI` toujours réservé aux administrateurs du cours, avec choix global : tous les cours administrés ou seulement les cours listés.
- Gestion de plusieurs `ref_id` de cours autorisés pour le bouton `Pilotage xAPI`.
- Possibilité de retirer un cours de la liste en décochant son `ref_id`.
- Prompt système IA rendu configurable dans la configuration du plugin.
- Possibilité de restaurer le prompt IA par défaut.
- Page de configuration plugin réorganisée avec une présentation gauche/droite.
- Suppression du bloc global `Ouvrir la configuration xAPI d’un cours`.
- Bouton `Vider l’outbox xAPI locale` déplacé dans le bloc `Outbox xAPI locale`.
- Bouton `Vider le journal debug` déplacé dans le bloc `Derniers événements ILIAS reçus`.

### Blocs conservés dans la configuration plugin

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

### Scripts de migration / patch

- `scripts/apply_v0281_dashboard_analysis_config_ai_prompt.py`
- `scripts/apply_v0283_pilotage_button_course_access_fix.py`
- `scripts/apply_v0284_config_plugin_layout.py`
- `scripts/apply_v0285_config_purge_buttons_layout.py`

### Validation serveur

Validation réalisée côté serveur avec :

```bash
python3 -m py_compile scripts/apply_v0284_config_plugin_layout.py
python3 -m py_compile scripts/apply_v0285_config_purge_buttons_layout.py
python3 scripts/apply_v0284_config_plugin_layout.py
python3 scripts/apply_v0285_config_purge_buttons_layout.py
systemctl restart php-fpm
systemctl restart httpd
```

Les contrôles `php -l` ont été exécutés par les scripts avant et après écriture.

---

## v0.27.1 — tableau de bord pédagogique amélioré

### Statut

Version validée fonctionnellement sur serveur ILIAS 10 puis intégrée dans GitHub.

- Plugin principal : `0.27.1-dev`
- Plugin compagnon UI : `0.8.47`
- Branche de validation : `v0.27-dashboard-command-center-validated`
- Commit fonctionnel validé : `de90cb1`
- Promotion cible : `main`

### Changements fonctionnels

- Ajout d'un centre de décision pédagogique dans l'onglet `Tableau de bord`.
- Ajout d'une carte `État global du cours` avec statut stable, à surveiller ou critique.
- Ajout d'une jauge `Réussite du cours` avec icône diplôme 🎓.
- Ajout d'un entonnoir pédagogique : inscrits, actifs, tentatives, réussites.
- Ajout d'un bloc `Actions recommandées`.
- Ajout d'une matrice ressources.
- Ajout des modes `Compact`, `Standard`, `Complet` dans la configuration du tableau de bord.

---

## v0.25.6 — affichage identité apprenant

- Affichage du login ILIAS dans `Apprenants en difficulté`.
- Ajout de la colonne `Apprenant` dans la vue Expert.
- Export CSV Expert avec colonne `learner_identity`.
