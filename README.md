# IliasTraxEventBridge

Plugin ILIAS 10 EventHook permettant de transformer des événements ILIAS en statements xAPI, de les envoyer vers TRAX/LRS, puis d'afficher un pilotage pédagogique directement dans le cours ILIAS via un plugin compagnon UIHook.

## Version courante validée

| Élément | Valeur |
|---|---|
| Branche stable officielle | `main` après promotion V0.28.5 |
| Version plugin principal | `0.28.5-dev` |
| Version plugin compagnon UI | `0.8.50` |
| Branche de validation | `v0.28-dashboard-analysis-config-ai-prompt-validated` |
| Commit fonctionnel validé | `eabc786` — `V0.28.5 validate plugin configuration layout` |
| Compatibilité ILIAS | `10.0.0` à `10.999.999` |

Installation stable courante :

```bash
git clone -b main --single-branch https://github.com/vincent-sayah/IliasTraxEventBridge.git IliasTraxEventBridge
```

## Règle métier

```text
TRAX/LRS = destination xAPI et source principale de suivi pédagogique xAPI.
Outbox locale = file technique d'envoi.
Progression ILIAS = source du taux de réussite du cours quand la progression du cours est paramétrée.
MediaCast = suivi des ouvertures, vidéos internes lues et médias externes sélectionnés.
Pilotage xAPI = bouton réservé aux administrateurs de cours, avec affichage global ou limité à une liste de ref_id.
Analyse IA = analyse optionnelle fondée sur les indicateurs agrégés/anonymisés ; prompt système configurable.
```

## Fonctionnalités principales

- Captation d'événements ILIAS via EventHook.
- Génération locale de statements xAPI.
- Envoi vers TRAX/LRS via outbox locale.
- Activation stricte par cours et par ressource.
- Accès `Pilotage xAPI` depuis l'objet cours via le plugin compagnon UIHook.
- Possibilité de limiter le bouton `Pilotage xAPI` à une liste de cours `ref_id` depuis la configuration du plugin.
- Tableau de bord pédagogique enrichi avec modes `Compact`, `Standard` et `Complet` réellement différenciés.
- Tableau de bord recentré sur la décision rapide : état global, réussite, entonnoir, synthèse et indicateurs essentiels.
- Onglet `Analyse` recentré sur l'investigation des résultats : actions recommandées, matrice ressources, questions à fort taux d'échec, MediaCast, apprenants en difficulté.
- Synthèse pédagogique configurable par cours.
- Jauge `Réussite du cours` avec icône diplôme 🎓, calculée depuis la progression ILIAS.
- Entonnoir pédagogique : inscrits, actifs, tentatives, réussites.
- Bloc `Actions recommandées` pour guider le formateur.
- Matrice ressources : activité, réussite, signal pédagogique.
- Activité dans le temps avec graphique linéaire SVG.
- Top ressources aligné avec le graphique en vue large.
- Questions à fort taux d'échec filtrées selon le contexte.
- Bloc `Apprenants en difficulté` avec affichage du login ILIAS.
- Suivi MediaCast : vidéos internes lues et médias externes ouverts.
- Onglet `Analyse IA` avec génération, historique, comparaison et retrait d'analyses.
- Prompt système IA affiché dans la configuration du plugin, modifiable et restaurable par défaut.
- Vue Expert technique avec colonne `Apprenant` entre `User ID` et `Verbe`.
- Export CSV Expert avec colonne `learner_identity`.
- Export PDF du tableau de bord.
- Diagnostic TRAX/LRS dans l'onglet Configuration.
- Supervision technique de l'outbox.
- Page de configuration plugin réorganisée en blocs gauche/droite.

## Vues du pilotage xAPI

```text
Tableau de bord | Analyse | Analyse IA | Expert | Configuration | Retour contenu du cours
```

### Tableau de bord

Vue de décision rapide pour le formateur. Depuis V0.28.5, les blocs d'analyse détaillée n'y sont plus dupliqués.

### Analyse

Vue d'investigation des résultats et des signaux pédagogiques : ressources à traiter, questions difficiles, MediaCast et apprenants en difficulté.

### Configuration du plugin

La configuration globale conserve les blocs suivants : santé/diagnostic, état, diagnostics TRAX/cron, bouton Pilotage xAPI, configuration TRAX/cron, configuration IA, envoi vers TRAX, supervision outbox, diagnostic des traces refusées, outbox xAPI locale et derniers événements ILIAS reçus.

## Mise à jour serveur depuis `main`

```bash
cd /var/www/ilias/public/Customizing/global/plugins/Services/EventHandling/EventHook/IliasTraxEventBridge

git fetch origin
git checkout -f main
git reset --hard origin/main

export ILIAS_ROOT="/var/www/ilias"
export HTTPD_USER="apache"
bash scripts/install_course_ui_companion_with_standalone_fix.sh

systemctl restart php-fpm
systemctl restart httpd
```

## Documentation V0.28.5

- `docs/INDEX_0.28.5.md`
- `docs/RELEASE_0.28.5.md`
- `docs/VALIDATION_0.28.5.md`
