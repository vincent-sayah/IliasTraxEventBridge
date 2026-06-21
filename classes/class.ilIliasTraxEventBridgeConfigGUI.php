<?php

/**
 * Administration screen for the IliasTraxEventBridge debug/local xAPI version.
 *
 * @ilCtrl_IsCalledBy ilIliasTraxEventBridgeConfigGUI: ilObjComponentSettingsGUI
 */
require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeEventDebugRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeOutboxRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeStatementFactory.php';

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

    /** @var ilIliasTraxEventBridgeOutboxRepository|null */
    private $outbox = null;

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
            case 'clearOutbox':
                $this->clearOutbox();
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
            $this->plugin = ilPlugin::getPluginObject(
                IL_COMP_SERVICE,
                'EventHandling',
                'evhk',
                'IliasTraxEventBridge'
            );
        }

        $this->config = new ilIliasTraxEventBridgeConfig();
        $this->repo = new ilIliasTraxEventBridgeEventDebugRepository();
        $this->outbox = new ilIliasTraxEventBridgeOutboxRepository();
    }

    private function configure(): void
    {
        $html = '';
        $html .= $this->renderInlineStyles();
        $html .= '<div class="itxeb-page">';
        $html .= '<h1>IliasTraxEventBridge — Debug événements et xAPI locale</h1>';
        $html .= '<p><strong>Version 0.2.1 :</strong> nettoyage du mapping xAPI local et exclusion des actions d’administration de test.</p>';
        $html .= '<p>Cette version ne contacte pas encore TRAX. Elle stocke uniquement les statements xAPI métier dans une outbox locale pour validation.</p>';

        $html .= $this->renderState();
        $html .= $this->renderOutbox();
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
        $html .= '<tr><td>Génération xAPI locale</td><td><strong>' . ($this->config->isLocalXapiGenerationEnabled() ? 'oui' : 'non') . '</strong></td></tr>';
        $html .= '<tr><td>Base URL ILIAS utilisée dans les statements</td><td><code>' . $this->esc($this->config->getIliasBaseUrl()) . '</code></td></tr>';
        $html .= '<tr><td>Taille maximum payload debug</td><td>' . $this->esc((string) $this->config->getMaxPayloadChars()) . ' caractères</td></tr>';
        $html .= '<tr><td>Rétention debug</td><td>' . $this->esc((string) $this->config->getRetentionDays()) . ' jours</td></tr>';
        $html .= '</table>';

        $html .= '<p class="itxeb-actions">';
        $html .= '<a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearLog')) . '">Vider le journal debug</a> ';
        $html .= '<a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearOutbox')) . '">Vider l’outbox xAPI locale</a>';
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

    private function clearOutbox(): void
    {
        $this->outbox->clear();

        if (class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) {
            ilUtil::sendSuccess('Outbox xAPI locale vidée.', true);
        }

        $this->ctrl->redirect($this, 'configure');
    }

    private function renderOutbox(): string
    {
        $limit = 50;
        $rows = $this->outbox->findRecent($limit);
        $total = $this->outbox->countAll();

        $html = '<h2>Outbox xAPI locale</h2>';
        $html .= '<p>Cette table contient les statements xAPI générés localement. En V0.2, ils ne sont pas encore envoyés à TRAX.</p>';

        $html .= '<div class="itxeb-summary">';
        $html .= '<span><strong>Total statements :</strong> ' . $this->esc((string) $total) . '</span>';
        $html .= '<span><strong>Affichés :</strong> ' . $this->esc((string) count($rows)) . ' / ' . $this->esc((string) min($limit, max($total, 0))) . '</span>';
        $html .= '<span><strong>Statut V0.2 :</strong> generated</span>';
        $html .= '</div>';

        if (count($rows) === 0) {
            return $html . '<p><em>Aucun statement xAPI généré pour le moment.</em></p>';
        }

        $html .= '<div class="itxeb-table-wrapper">';
        $html .= '<table class="std itxeb-events">';
        $html .= '<thead><tr>'
            . '<th class="itxeb-col-id">ID<br>Date</th>'
            . '<th class="itxeb-col-analysis">Type</th>'
            . '<th class="itxeb-col-event">Verb</th>'
            . '<th class="itxeb-col-object">Utilisateur / objet</th>'
            . '<th class="itxeb-col-statement">Statement JSON</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $statement = $this->formatPayload((string) ($row['statement_json'] ?? ''));

            $html .= '<tr>'
                . '<td class="itxeb-id-cell">'
                    . '<div class="itxeb-id">#' . $this->esc((string) ($row['id'] ?? '')) . '</div>'
                    . '<div class="itxeb-date">' . $this->esc((string) ($row['created_at'] ?? '')) . '</div>'
                    . '<div class="itxeb-date">log #' . $this->esc((string) ($row['event_log_id'] ?? '')) . '</div>'
                . '</td>'
                . '<td>'
                    . '<span class="itxeb-badge itxeb-badge-xapi">' . $this->esc((string) ($row['event_type'] ?? '')) . '</span>'
                    . '<div class="itxeb-date">status: ' . $this->esc((string) ($row['status'] ?? '')) . '</div>'
                . '</td>'
                . '<td><div class="itxeb-uri">' . $this->esc((string) ($row['verb_id'] ?? '')) . '</div></td>'
                . '<td>'
                    . '<div><strong>User :</strong> ' . $this->esc((string) ($row['user_id'] ?? '')) . '</div>'
                    . '<div><strong>ref_id :</strong> ' . $this->esc((string) ($row['ref_id'] ?? '')) . '</div>'
                    . '<div><strong>obj_id :</strong> ' . $this->esc((string) ($row['obj_id'] ?? '')) . '</div>'
                    . '<div><strong>type :</strong> ' . $this->esc((string) ($row['obj_type'] ?? '')) . '</div>'
                . '</td>'
                . '<td><details class="itxeb-details"><summary>Afficher le statement xAPI</summary><pre>' . $this->esc($statement) . '</pre></details></td>'
                . '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    private function renderRecentEvents(): string
    {
        $limit = 100;
        $rows = $this->repo->findRecent($limit);
        $total = $this->repo->countAll();

        $html = '<h2>Derniers événements ILIAS reçus</h2>';
        $html .= '<p>Ces lignes sont le journal brut des événements EventHook reçus par le plugin.</p>';

        $html .= '<div class="itxeb-summary">';
        $html .= '<span><strong>Total journalisé :</strong> ' . $this->esc((string) $total) . '</span>';
        $html .= '<span><strong>Affichés :</strong> ' . $this->esc((string) count($rows)) . ' / ' . $this->esc((string) min($limit, max($total, 0))) . '</span>';
        $html .= '<span><strong>Limite d’affichage :</strong> ' . $this->esc((string) $limit) . '</span>';
        $html .= '</div>';

        if (count($rows) === 0) {
            return $html . '<p><em>Aucun événement journalisé pour le moment.</em></p>';
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

        $factory = new ilIliasTraxEventBridgeStatementFactory($this->config);
        $ignoreReason = $factory->getIgnoreReason($row);
        if ($ignoreReason !== '') {
            return $ignoreReason;
        }

        if ($component === 'components/ILIAS/Tracking' && $event === 'updateStatus') {
            if ($type === 'tst'
                || strpos($uri, 'cmdClass=ilTestPlayerFixedQuestionSetGUI') !== false
                || strpos($uri, 'cmdClass=ilTestPlayerDynamicQuestionSetGUI') !== false
                || strpos($uri, 'cmd=startTest') !== false
                || strpos($uri, 'cmd=finishTest') !== false
            ) {
                return 'candidat xAPI: test';
            }

            if ($type !== '') {
                return 'candidat xAPI: progression';
            }

            return 'observation: tracking ignoré';
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
        } elseif (strpos($analysis, 'ignored') === 0 || strpos($analysis, 'observation: tracking ignoré') === 0) {
            $class .= ' itxeb-badge-ignored';
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

        if (strlen($payload) > 8000) {
            $payload = substr($payload, 0, 8000) . "\n...<truncated for display>";
        }

        return $payload;
    }

    private function renderInlineStyles(): string
    {
        return '<style>
.itxeb-page { max-width: 100%; }
.itxeb-state { margin-bottom: 18px; }
.itxeb-state-table { width: auto; min-width: 520px; }
.itxeb-actions { margin: 8px 0 18px 0; }
.itxeb-summary { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 12px 0; }
.itxeb-summary span { display: inline-block; border: 1px solid #cfd7e2; background: #f7f9fb; padding: 6px 10px; border-radius: 4px; }
.itxeb-table-wrapper { width: 100%; max-width: 100%; overflow-x: auto; border: 1px solid #d5d5d5; margin-bottom: 26px; }
table.itxeb-events { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 12px; line-height: 1.35; }
table.itxeb-events th, table.itxeb-events td { vertical-align: top; padding: 8px 8px; border-bottom: 1px solid #e2e2e2; overflow-wrap: anywhere; word-break: break-word; }
table.itxeb-events th { position: sticky; top: 0; z-index: 1; background: #eef2f6; font-weight: 700; }
table.itxeb-events tr:nth-child(even) td { background: #fafafa; }
.itxeb-col-id { width: 105px; }
.itxeb-col-analysis { width: 170px; }
.itxeb-col-event { width: 220px; }
.itxeb-col-object { width: 125px; }
.itxeb-col-payload { width: 330px; }
.itxeb-col-statement { width: 600px; }
.itxeb-col-uri { width: 360px; }
.itxeb-id { font-weight: 700; font-size: 13px; }
.itxeb-date { white-space: normal; color: #555; margin-top: 4px; }
.itxeb-component { color: #555; margin-bottom: 4px; }
.itxeb-event-name { font-weight: 700; font-size: 13px; }
.itxeb-param-keys { font-family: monospace; font-size: 12px; margin-bottom: 5px; }
.itxeb-details summary { cursor: pointer; font-weight: 600; }
.itxeb-details pre { white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; max-height: 280px; overflow: auto; background: #f4f4f4; border: 1px solid #d6d6d6; padding: 8px; margin: 6px 0 0 0; font-size: 12px; }
.itxeb-uri { font-family: monospace; font-size: 12px; max-height: 120px; overflow: auto; background: #f8f8f8; border: 1px solid #e0e0e0; padding: 6px; }
.itxeb-badge { display: inline-block; padding: 4px 7px; border-radius: 4px; font-weight: 700; border: 1px solid #cfcfcf; background: #f5f5f5; }
.itxeb-badge-xapi { border-color: #7c9b4d; background: #f2f8e8; }
.itxeb-badge-admin { border-color: #c9a044; background: #fff7dc; }
.itxeb-badge-index { border-color: #7a9ac2; background: #eef5ff; }
.itxeb-badge-observation { border-color: #b8b8b8; background: #f6f6f6; }
.itxeb-badge-ignored { border-color: #b95d5d; background: #fff0f0; }
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
