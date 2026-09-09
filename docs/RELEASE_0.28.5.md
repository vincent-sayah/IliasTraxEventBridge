# Release V0.28.5 — configuration plugin et pilotage pédagogique

## Statut

```text
V0.28.5 stable après validation serveur.
```

| Élément | Valeur |
|---|---|
| Plugin principal | `0.28.5-dev` |
| Plugin compagnon UI | `0.8.50` |
| Commit fonctionnel validé | `eabc786` |
| Branche de validation | `v0.28-dashboard-analysis-config-ai-prompt-validated` |
| Base précédente | V0.27.1 `f7f8611` |

## Contenu de la release

### 1. Onglets Tableau de bord / Analyse

Les deux vues sont séparées pour éviter la redondance :

```text
Tableau de bord = lecture rapide et décision.
Analyse = investigation détaillée des résultats.
```

Blocs déplacés vers `Analyse` :

- Actions recommandées
- Matrice ressources
- Questions à fort taux d'échec
- Analyse détaillée des ressources

La `Synthèse pédagogique` reste dans le `Tableau de bord`.

### 2. Modes du tableau de bord

Les modes appliquent désormais des préréglages réels :

| Mode | Objectif | Blocs principaux |
|---|---|---|
| Compact | décision immédiate | état global, réussite, entonnoir |
| Standard | suivi formateur | compact + synthèse, activité, top ressources |
| Complet | vue étendue | standard + comparaison, actions xAPI, ressources sans activité |

### 3. Bouton Pilotage xAPI par cours

La configuration globale permet de choisir :

```text
- tous les cours administrés ;
- seulement les ref_id listés.
```

L'utilisateur doit toujours avoir les droits d'administration du cours. La liste des `ref_id` peut contenir plusieurs cours. Un cours peut être retiré en décochant son identifiant.

### 4. Prompt IA configurable

La configuration IA expose le prompt système :

```text
Prompt système IA
Remettre le prompt IA par défaut
```

Le prompt par défaut reste disponible côté code.

### 5. Présentation de la configuration plugin

La page globale de configuration est réorganisée en lecture gauche/droite, cohérente avec le style du tableau de bord :

```text
Titre à gauche / contenu à droite
```

Blocs conservés :

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

Le bloc `Ouvrir la configuration xAPI d’un cours` est retiré.

### 6. Déplacement des boutons de purge

Les boutons de purge sont replacés dans leurs blocs fonctionnels :

```text
Outbox xAPI locale -> Vider l'outbox xAPI locale
Derniers événements ILIAS reçus -> Vider le journal debug
```

## Validation

Les scripts V0.28.4 et V0.28.5 ont été compilés avec Python 3.6 et les fichiers PHP générés ont été vérifiés avec `php -l`.

Le serveur a ensuite redémarré :

```bash
systemctl restart php-fpm
systemctl restart httpd
```

## Mise à jour depuis main

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
