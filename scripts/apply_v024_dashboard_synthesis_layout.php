<?php
/**
 * V0.24.0-dev
 * Affichage tableau de bord : regroupe les tuiles de synthèse dans le bloc Synthèse pédagogique.
 *
 * Objectifs :
 * - retirer le bloc KPI inférieur sans titre ;
 * - intégrer les tuiles utiles dans Synthèse pédagogique ;
 * - supprimer les doublons Ressources sans activité / Critiques / À surveiller ;
 * - ajouter des icônes aux tuiles sans dépendance graphique externe.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$screenTemplate = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php.tpl';
$mainPlugin = $root . '/plugin.php';
$companionPlugin = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';
$liveScreen = '/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIScreen.php';
$livePlugin = '/var/www/ilias/public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI/plugin.php';

function itxeb_read(string $path): string
{
    if (!is_file($path)) {
        fwrite(STDERR, "ERREUR: fichier introuvable: $path\n");
        exit(1);
    }
    $content = file_get_contents($path);
    if (!is_string($content)) {
        fwrite(STDERR, "ERREUR: lecture impossible: $path\n");
        exit(1);
    }
    return $content;
}

function itxeb_write(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: écriture impossible: $path\n");
        exit(1);
    }
    echo "WRITE: $path\n";
}

function itxeb_replace(string $content, string $search, string $replace, string $label): string
{
    if (strpos($content, $search) === false) {
        fwrite(STDERR, "ERREUR: point de remplacement introuvable: $label\n");
        exit(1);
    }
    return str_replace($search, $replace, $content);
}

$screen = itxeb_read($screenTemplate);

if (strpos($screen, 'ITXEB V0.24 dashboard synthesis layout') !== false) {
    echo "SKIP: écran déjà patché V0.24\n";
} else {
    $oldDashboardKpis = <<<'PHP'
            . '<div class="itxeb-kpi-grid">'
            . $this->metricCard('Statements TRAX', (string) ($summary['total'] ?? 0), 'Lecture LRS')
            . $this->metricCard('Apprenants actifs', (string) ($summary['active_learners'] ?? 0), 'Comptage anonyme')
            . $this->metricCard('Ressources utilisées', (string) ($summary['resources_with_traces'] ?? 0) . ' / ' . (string) ($summary['resources_total'] ?? 0), 'Au moins une trace')
            . $this->metricCard('Sans statement TRAX', (string) $this->countEnabledWithoutTraceResources($dashboard), 'À surveiller')
            . $this->metricCard('Pages LRS', (string) ($dashboard['pages'] ?? 0), 'pagination')
            . $this->metricCard('Critiques', (string) ($dashboard['pedagogy']['critical_count'] ?? 0), 'Priorité')
            . $this->metricCard('À surveiller', (string) ($dashboard['pedagogy']['watch_count'] ?? 0), 'Signal pédagogique')
            . $this->metricCard('Score moyen', $summary['avg_score_raw'] === null ? '-' : (string) $summary['avg_score_raw'] . ' %', 'Tests')
            . '</div>';
PHP;

    // Toutes les tuiles utiles sont maintenant dans Synthèse pédagogique.
    // On retire donc ce second bloc sans titre du tableau de bord.
    $screen = itxeb_replace($screen, $oldDashboardKpis, "            . '';", 'suppression bloc KPI inférieur du tableau de bord');

    $oldSynthesis = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderPedagogicalSynthesis(array $dashboard): string
    {
        $pedagogy = is_array($dashboard['pedagogy'] ?? null) ? $dashboard['pedagogy'] : [];
        $lines = is_array($pedagogy['synthesis_lines'] ?? null) ? $pedagogy['synthesis_lines'] : [];
        $html = '<div class="itxeb-pedagogy-summary"><h3>Synthèse pédagogique</h3><div class="itxeb-pedagogy-kpis">'
            . $this->metricCard('OK', (string) ($pedagogy['ok_count'] ?? 0), 'Ressources sans signal')
            . $this->metricCard('À surveiller', (string) ($pedagogy['watch_count'] ?? 0), 'Signal faible')
            . $this->metricCard('Critiques', (string) ($pedagogy['critical_count'] ?? 0), 'Priorité')
            . $this->metricCard('Sans trace', (string) ($pedagogy['resources_without_trace'] ?? 0), 'Sans statement TRAX')
            . '</div>';
        if (count($lines) > 0) {
            $html .= '<ul class="itxeb-pedagogy-lines">';
            foreach ($lines as $line) {
                if (is_scalar($line) && trim((string) $line) !== '') {
                    $html .= '<li>' . $this->esc((string) $line) . '</li>';
                }
            }
            $html .= '</ul>';
        }
        return $html . '</div>';
    }
PHP;

    $newSynthesis = <<<'PHP'
    /** @param array<string,mixed> $dashboard */
    private function renderPedagogicalSynthesis(array $dashboard): string
    {
        // ITXEB V0.24 dashboard synthesis layout
        $pedagogy = is_array($dashboard['pedagogy'] ?? null) ? $dashboard['pedagogy'] : [];
        $summary = is_array($dashboard['summary'] ?? null) ? $dashboard['summary'] : [];
        $lines = is_array($pedagogy['synthesis_lines'] ?? null) ? $pedagogy['synthesis_lines'] : [];
        $html = '<div class="itxeb-pedagogy-summary itxeb-v024-synthesis"><h3>Synthèse pédagogique</h3><div class="itxeb-pedagogy-kpis itxeb-v024-synthesis-kpis">'
            . $this->metricCardWithIcon('OK', (string) ($pedagogy['ok_count'] ?? 0), 'Ressources sans signal', '✅')
            . $this->metricCardWithIcon('À surveiller', (string) ($pedagogy['watch_count'] ?? 0), 'Signal faible', '⚠️')
            . $this->metricCardWithIcon('Critiques', (string) ($pedagogy['critical_count'] ?? 0), 'Priorité', '🚨')
            . $this->metricCardWithIcon('Sans activité enregistrée', (string) ($pedagogy['resources_without_trace'] ?? 0), 'Ressources sans activité', '🔇')
            . $this->metricCardWithIcon('Données d’apprentissage', (string) ($summary['total'] ?? 0), 'Lecture des données', '📊')
            . $this->metricCardWithIcon('Apprenants actifs', (string) ($summary['active_learners'] ?? 0), 'Comptage anonyme', '👥')
            . $this->metricCardWithIcon('Ressources utilisées', (string) ($summary['resources_with_traces'] ?? 0) . ' / ' . (string) ($summary['resources_total'] ?? 0), 'Au moins une activité enregistrée', '📚')
            . $this->metricCardWithIcon('Lots de données lus', (string) ($dashboard['pages'] ?? 0), 'lecture par lots', '📦')
            . $this->metricCardWithIcon('Score moyen', $summary['avg_score_raw'] === null ? '-' : (string) $summary['avg_score_raw'] . ' %', 'Tests', '🎯')
            . '</div>';
        if (count($lines) > 0) {
            $html .= '<ul class="itxeb-pedagogy-lines">';
            foreach ($lines as $line) {
                if (is_scalar($line) && trim((string) $line) !== '') {
                    $html .= '<li>' . $this->esc((string) $line) . '</li>';
                }
            }
            $html .= '</ul>';
        }
        return $html . '</div>';
    }

    private function metricCardWithIcon(string $label, string $value, string $hint, string $icon): string
    {
        return '<div class="itxeb-kpi-card itxeb-kpi-card-icon" style="display:flex;align-items:center;gap:10px;min-height:86px">'
            . '<div class="itxeb-kpi-icon" aria-hidden="true" style="font-size:24px;line-height:1;width:34px;text-align:center;flex:0 0 34px">' . $this->esc($icon) . '</div>'
            . '<div class="itxeb-kpi-body" style="min-width:0">'
            . '<div class="itxeb-kpi-label">' . $this->esc($label) . '</div>'
            . '<div class="itxeb-kpi-value">' . $this->esc($value) . '</div>'
            . '<div class="itxeb-kpi-hint">' . $this->esc($hint) . '</div>'
            . '</div></div>';
    }
PHP;
    $screen = itxeb_replace($screen, $oldSynthesis, $newSynthesis, 'Synthèse pédagogique enrichie');

    itxeb_write($screenTemplate, $screen);
    if (is_file($liveScreen)) {
        itxeb_write($liveScreen, $screen);
    }
}

$plugin = itxeb_read($mainPlugin);
$plugin = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.24.0-dev';", $plugin) ?? $plugin;
itxeb_write($mainPlugin, $plugin);

$companion = itxeb_read($companionPlugin);
$companion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.20';", $companion) ?? $companion;
itxeb_write($companionPlugin, $companion);
if (is_file($livePlugin)) {
    $liveCompanion = itxeb_read($livePlugin);
    $liveCompanion = preg_replace("/\$version\s*=\s*'[^']+';/", "\$version = '0.8.20';", $liveCompanion) ?? $liveCompanion;
    itxeb_write($livePlugin, $liveCompanion);
}

$filesToLint = [$mainPlugin, $companionPlugin, $screenTemplate];
if (is_file($livePlugin)) { $filesToLint[] = $livePlugin; }
if (is_file($liveScreen)) { $filesToLint[] = $liveScreen; }
foreach ($filesToLint as $file) {
    passthru('php -l ' . escapeshellarg($file), $code);
    if ($code !== 0) {
        fwrite(STDERR, "ERREUR: syntaxe PHP invalide: $file\n");
        exit(1);
    }
}

echo "V0.24 dashboard synthesis layout applied.\n";
