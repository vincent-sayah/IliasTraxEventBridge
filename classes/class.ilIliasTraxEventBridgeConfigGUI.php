<?php

/**
 * Administration screen for the IliasTraxEventBridge debug version.
 *
 * ILIAS 8+ requires an explicit ilCtrl directive for plugin configuration
 * screens. Without this directive, the plugin administration may display
 * "The requested page could not be found" after activation.
 *
 * @ilCtrl_IsCalledBy ilIliasTraxEventBridgeConfigGUI: ilObjComponentSettingsGUI
 */
require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeEventDebugRepository.php';

class ilIliasTraxEventBridgeConfigGUI extends ilPluginConfigGUI
{
    /** @var ilIliasTraxEventBridgePlugin|null */
    private $plugin = null;

    /** @var ilCtrl|mixed */
    private $ctrl;

    /** @var ilGlobalTemplateInterface|mixed */
    private $tpl;

    /** @var ilIliasTraxEventBridgeConfig|null */
    private $config = null;

    /** @var ilIliasTraxEventBridgeEventDebugRepository|null */
    private $repo = null;

    public function __construct()
    {
        global $DIC, $ilCtrl, $tpl;

        $this->ctrl = isset($DIC) && method_exists($DIC, 'ctrl') ? $DIC->ctrl() : $ilCtrl;
        $this->tpl = isset($DIC) && isset($DIC['tpl']) ? $DIC['tpl'] : $tpl;
    }

    public function performCommand(string $cmd): void
    {
        $this->init();

        switch ($cmd) {
            case 'clearLog':
                $this->clearLog();
                break;
            case 'configure':
            default:
                $this->configure();
                break;
        }
    }

    private function init(): void
    {
        $plugin = $this->getPluginObject();

        if ($plugin instanceof ilIliasTraxEventBridgePlugin) {
            $this->plugin = $plugin;
        } else {
            // Defensive fallback for unusual plugin-admin execution paths.
            $this->plugin = ilPlugin::getPluginObject(
                IL_COMP_SERVICE,
                'EventHandling',
                'evhk',
                'IliasTraxEventBridge'
            );
        }

        $this->config = new ilIliasTraxEventBridgeConfig();
        $this->repo = new ilIliasTraxEventBridgeEventDebugRepository();
    }

    private function configure(): void
    {
        $html = '';
        $html .= $this->renderInlineStyles();
        $html .= '<div class="itxeb-page">';
        $html .= '<h1>IliasTraxEventBridge — Debug événements ILIAS</h1>';
        $html .= '<p><strong>Version 0.1.5 :</strong> écran de configuration amélioré pour rendre les événements lisibles dans ILIAS 10.</p>';
        $html .= '<p>Cette version sert uniquement à observer les événements réellement émis par ILIAS 10 avant le mapping xAPI vers TRAX.</p>';

        $html .= $this->renderState();
        $html .= $this->renderRecentEvents();
        $html .= '</div>';

        $this->setContent($html);
    }

    private function renderState(): string
    {
        $html = '<h2>État</h2>';
        $html .= '<div class="itxeb-state">';
        $html .= '<table class="std itxeb-state-table">';
        $html .= '<tr><td>Plugin actif</td><td><strong>' . ($this->config->isEnabled() ? 'oui' : 'non') . '</strong></td></tr>';
        $html .= '<tr><td>Mode debug</td><td><strong>' . ($this->config->isDebugEnabled() ? 'oui' : 'non') . '</strong></td></tr>';
        $html .= '<tr><td>Taille maximum payload</td><td>' . $this->esc((string) $this->config->getMaxPayloadChars()) . ' caractères</td></tr>';
        $html .= '<tr><td>Rétention</td><td>' . $this->esc((string) $this->config->getRetentionDays()) . ' jours</td></tr>';
        $html .= '</table>';

        $html .= '<p class="itxeb-actions">';
        $html .= '<a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearLog')) . '">Vider le journal</a>';
        $html .= '</p>';
        $html .= '</div>';

        return $html;
    }

    private function clearLog(): void
    {
        $this->repo->clear();

        if (class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) {
            ilUtil::sendSuccess('Journal des événements vidé.', true);
        }

        $this->ctrl->redirect($this, 'configure');
    }

    private function renderRecentEvents(): string
    {
        $limit = 100;
        $rows = $this->repo->findRecent($limit);
        $total = $this->repo->countAll();

        $html = '<h2>Derniers événements reçus</h2>';
        $html .= '<p>Effectue les actions cibles dans ILIAS : entrer dans un cours, ouvrir un objet, lancer puis terminer un test. Les événements apparaîtront ici.</p>';

        $html .= '<div class="itxeb-summary">';
        $html .= '<span><strong>Total journalisé :</strong> ' . $this->esc((string) $total) . '</span>';
        $html .= '<span><strong>Affichés :</strong> ' . $this->esc((string) count($rows)) . ' / ' . $this->esc((string) min($limit, max($total, 0))) . '</span>';
        $html .= '<span><strong>Limite d’affichage :</strong> ' . $this->esc((string) $limit) . '</span>';
        $html .= '</div>';

        if (count($rows) === 0) {
            return $html . '<p><em>Aucun événement journalisé pour le moment.</em></p>';
        }

        if ($total > count($rows)) {
            $html .= '<p class="itxeb-note">Le tableau affiche les ' . $this->esc((string) count($rows)) . ' événements les plus récents sur ' . $this->esc((string) $total) . ' événement(s) journalisé(s).</p>';
        }

        $html .= '<div class="itxeb-table-wrapper">';
        $html .= '<table class="std itxeb-events">';
        $html .= '<thead><tr>'
            . '<th class="itxeb-col-id">ID<br>Date</th>'
            . '<th class="itxeb-col-analysis">Analyse</th>'
            . '<th class="itxeb-col-event">Événement ILIAS</th>'
            . '<th class="itxeb-col-object">Utilisateur / objet</th>'
            . '<th class="itxeb-col-payload">Paramètres / payload</th>'
            . '<th class="itxeb-col-uri">URI</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $analysis = $this->classifyEvent($row);
            $payload = $this->formatPayload((string) ($row['payload_json'] ?? ''));
            $eventName = (string) ($row['event_name'] ?? '');
            $component = (string) ($row['component'] ?? '');

            $html .= '<tr>'
                . '<td class="itxeb-id-cell">'
                    . '<div class="itxeb-id">#' . $this->esc((string) ($row['id'] ?? '')) . '</div>'
                    . '<div class="itxeb-date">' . $this->esc((string) ($row['created_at'] ?? '')) . '</div>'
                . '</td>'
                . '<td>' . $this->renderAnalysisBadge($analysis) . '</td>'
                . '<td>'
                    . '<div class="itxeb-component">' . $this->esc($component) . '</div>'
                    . '<div class="itxeb-event-name">' . $this->esc($eventName) . '</div>'
                . '</td>'
                . '<td>'
                    . '<div><strong>User :</strong> ' . $this->esc((string) ($row['user_id'] ?? '')) . '</div>'
                    . '<div><strong>ref_id :</strong> ' . $this->esc((string) ($row['ref_id'] ?? '')) . '</div>'
                    . '<div><strong>obj_id :</strong> ' . $this->esc((string) ($row['obj_id'] ?? '')) . '</div>'
                    . '<div><strong>type :</strong> ' . $this->esc((string) ($row['obj_type'] ?? '')) . '</div>'
                . '</td>'
                . '<td>'
                    . '<div class="itxeb-param-keys">' . $this->esc((string) ($row['param_keys'] ?? '')) . '</div>'
                    . '<details class="itxeb-details"><summary>Afficher le payload</summary><pre>' . $this->esc($payload) . '</pre></details>'
                . '</td>'
                . '<td><div class="itxeb-uri">' . $this->esc((string) ($row['request_uri'] ?? '')) . '</div></td>'
                . '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    /**
     * Classification is only a discovery aid. It does not send anything to TRAX.
     */
    private function classifyEvent(array $row): string
    {
        $component = (string) ($row['component'] ?? '');
        $event = (string) ($row['event_name'] ?? '');
        $uri = (string) ($row['request_uri'] ?? '');
        $type = (string) ($row['obj_type'] ?? '');

        if ($component === 'components/ILIAS/Tracking' && $event === 'updateStatus') {
            return 'candidat xAPI: progression/test';
        }

        if ($type === 'file' && strpos($uri, 'cmd=sendfile') !== false) {
            return 'candidat xAPI: fichier téléchargé';
        }

        if ($component === 'components/ILIAS/ILIASObject' && $event === 'create') {
            return 'administration: création objet';
        }

        if ($component === 'components/ILIAS/ILIASObject' && $event === 'update') {
            return 'administration: mise à jour objet';
        }

        if ($component === 'components/ILIAS/Search' && $event === 'contentChanged') {
            return 'indexation: contenu modifié';
        }

        return 'observation';
    }

    private function renderAnalysisBadge(string $analysis): string
    {
        $class = 'itxeb-badge';
        if (strpos($analysis, 'candidat xAPI') === 0) {
            $class .= ' itxeb-badge-xapi';
        } elseif (strpos($analysis, 'administration') === 0) {
            $class .= ' itxeb-badge-admin';
        } elseif (strpos($analysis, 'indexation') === 0) {
            $class .= ' itxeb-badge-index';
        } else {
            $class .= ' itxeb-badge-observation';
        }

        return '<span class="' . $class . '">' . $this->esc($analysis) . '</span>';
    }

    private function formatPayload(string $payload): string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return '';
        }

        $decoded = json_decode($payload, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($pretty)) {
                $payload = $pretty;
            }
        }

        if (strlen($payload) > 5000) {
            $payload = substr($payload, 0, 5000) . "\n...<truncated for display>";
        }

        return $payload;
    }

    private function renderInlineStyles(): string
    {
        return '<style>
.itxeb-page { max-width: 100%; }
.itxeb-state { margin-bottom: 18px; }
.itxeb-state-table { width: auto; min-width: 360px; }
.itxeb-actions { margin: 8px 0 18px 0; }
.itxeb-summary { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 12px 0; }
.itxeb-summary span { display: inline-block; border: 1px solid #cfd7e2; background: #f7f9fb; padding: 6px 10px; border-radius: 4px; }
.itxeb-note { border-left: 4px solid #55708d; background: #f7f9fb; padding: 8px 10px; }
.itxeb-table-wrapper { width: 100%; max-width: 100%; overflow-x: auto; border: 1px solid #d5d5d5; }
table.itxeb-events { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 12px; line-height: 1.35; }
table.itxeb-events th, table.itxeb-events td { vertical-align: top; padding: 8px 8px; border-bottom: 1px solid #e2e2e2; overflow-wrap: anywhere; word-break: break-word; }
table.itxeb-events th { position: sticky; top: 0; z-index: 1; background: #eef2f6; font-weight: 700; }
table.itxeb-events tr:nth-child(even) td { background: #fafafa; }
.itxeb-col-id { width: 105px; }
.itxeb-col-analysis { width: 160px; }
.itxeb-col-event { width: 210px; }
.itxeb-col-object { width: 120px; }
.itxeb-col-payload { width: 330px; }
.itxeb-col-uri { width: 360px; }
.itxeb-id { font-weight: 700; font-size: 13px; }
.itxeb-date { white-space: normal; color: #555; margin-top: 4px; }
.itxeb-component { color: #555; margin-bottom: 4px; }
.itxeb-event-name { font-weight: 700; font-size: 13px; }
.itxeb-param-keys { font-family: monospace; font-size: 12px; margin-bottom: 5px; }
.itxeb-details summary { cursor: pointer; font-weight: 600; }
.itxeb-details pre { white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; max-height: 220px; overflow: auto; background: #f4f4f4; border: 1px solid #d6d6d6; padding: 8px; margin: 6px 0 0 0; font-size: 12px; }
.itxeb-uri { font-family: monospace; font-size: 12px; max-height: 120px; overflow: auto; background: #f8f8f8; border: 1px solid #e0e0e0; padding: 6px; }
.itxeb-badge { display: inline-block; padding: 4px 7px; border-radius: 4px; font-weight: 700; border: 1px solid #cfcfcf; background: #f5f5f5; }
.itxeb-badge-xapi { border-color: #7c9b4d; background: #f2f8e8; }
.itxeb-badge-admin { border-color: #c9a044; background: #fff7dc; }
.itxeb-badge-index { border-color: #7a9ac2; background: #eef5ff; }
.itxeb-badge-observation { border-color: #b8b8b8; background: #f6f6f6; }
@media (max-width: 1200px) {
    table.itxeb-events { min-width: 1280px; }
}
</style>';
    }

    private function setContent(string $html): void
    {
        if (is_object($this->tpl) && method_exists($this->tpl, 'setContent')) {
            $this->tpl->setContent($html);
            return;
        }

        echo $html;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
