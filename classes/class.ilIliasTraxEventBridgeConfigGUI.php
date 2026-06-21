<?php

/**
 * Administration screen for the IliasTraxEventBridge debug/local xAPI/TRAX version.
 *
 * @ilCtrl_IsCalledBy ilIliasTraxEventBridgeConfigGUI: ilObjComponentSettingsGUI
 */
require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeEventDebugRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeOutboxRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeStatementFactory.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeTraxClient.php';

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
            case 'saveConfig':
                $this->saveConfig();
                break;
            case 'testTraxConnection':
                $this->testTraxConnection();
                break;
            case 'sendGenerated':
                $this->sendGenerated();
                break;
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
        $this->outbox->resetStuckSending();
    }

    private function configure(): void
    {
        $html = '';
        $html .= $this->renderInlineStyles();
        $html .= '<div class="itxeb-page">';
        $html .= '<h1>IliasTraxEventBridge — Debug événements, xAPI locale et envoi TRAX</h1>';
        $html .= '<p><strong>Version 0.3.1 :</strong> test de connexion TRAX visible et diagnostic persistant dans l’écran de configuration.</p>';
        $html .= '<p>Cette version n’a pas encore de cron automatique. L’envoi vers TRAX est déclenché manuellement depuis cet écran.</p>';

        $html .= $this->renderState();
        $html .= $this->renderTraxConfigForm();
        $html .= $this->renderSendActions();
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
        $html .= '<tr><td>Endpoint xAPI statements</td><td><code>' . $this->esc($this->config->getStatementsEndpoint()) . '</code></td></tr>';
        $html .= '</table>';

        $html .= $this->renderTraxDiagnostics();

        $html .= '<p class="itxeb-actions">';
        $html .= '<a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearLog')) . '">Vider le journal debug</a> ';
        $html .= '<a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearOutbox')) . '">Vider l’outbox xAPI locale</a>';
        $html .= '</p>';
        $html .= '</div>';

        return $html;
    }

    private function renderTraxDiagnostics(): string
    {
        $html = '<h3>Derniers diagnostics TRAX</h3>';
        $html .= '<table class="std itxeb-state-table">';

        $lastTestAt = $this->config->getLastTraxTestAt();
        if ($lastTestAt === '') {
            $html .= '<tr><td>Dernier test connexion</td><td><em>Aucun test lancé depuis le plugin.</em></td></tr>';
        } else {
            $html .= '<tr><td>Dernier test connexion</td><td>'
                . '<strong>date :</strong> ' . $this->esc($lastTestAt)
                . '<br><strong>succès :</strong> ' . $this->esc($this->config->getLastTraxTestSuccess())
                . '<br><strong>HTTP :</strong> ' . $this->esc($this->config->getLastTraxTestHttpStatus())
                . '<br><strong>message :</strong> ' . $this->esc($this->config->getLastTraxTestMessage())
                . '</td></tr>';
        }

        $lastSendAt = $this->config->getLastTraxSendAt();
        if ($lastSendAt === '') {
            $html .= '<tr><td>Dernier envoi manuel</td><td><em>Aucun envoi manuel lancé depuis le plugin.</em></td></tr>';
        } else {
            $html .= '<tr><td>Dernier envoi manuel</td><td>'
                . '<strong>date :</strong> ' . $this->esc($lastSendAt)
                . '<br><strong>succès :</strong> ' . $this->esc($this->config->getLastTraxSendSuccess())
                . '<br><strong>HTTP :</strong> ' . $this->esc($this->config->getLastTraxSendHttpStatus())
                . '<br><strong>message :</strong> ' . $this->esc($this->config->getLastTraxSendMessage())
                . '</td></tr>';
        }

        $html .= '</table>';
        return $html;
    }

    private function renderTraxConfigForm(): string
    {
        $html = '<h2>Configuration TRAX 3 / xAPI</h2>';
        $html .= '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'saveConfig')) . '" class="itxeb-form">';
        $html .= '<table class="std itxeb-form-table">';

        $html .= $this->inputRow('Endpoint xAPI TRAX', 'trax_endpoint', $this->config->getTraxEndpoint(), 'Exemple : https://trax.example.com/trax/ws/xapi ou URL complète finissant par /statements');
        $html .= $this->inputRow('Identifiant client TRAX', 'trax_username', $this->config->getTraxUsername(), 'Client xAPI TRAX autorisé à écrire les statements.');
        $html .= $this->passwordRow('Secret client TRAX', 'trax_password', 'Laisser vide pour conserver le secret déjà enregistré.');
        $html .= $this->inputRow('Version xAPI', 'xapi_version', $this->config->getXapiVersion(), 'Valeur recommandée : 1.0.3');
        $html .= $this->inputRow('Timeout HTTP', 'http_timeout', (string) $this->config->getHttpTimeout(), 'Entre 2 et 120 secondes.');
        $html .= $this->inputRow('Taille batch manuel', 'batch_size', (string) $this->config->getBatchSize(), 'Entre 1 et 100 statements.');
        $html .= $this->inputRow('Base URL ILIAS forcée', 'ilias_base_url', $this->config->getIliasBaseUrl(), 'Optionnel. Sert à construire actor.account.homePage et les IRIs des activités.');

        $html .= '</table>';
        $html .= '<p class="itxeb-actions"><button class="btn btn-primary" type="submit">Enregistrer la configuration</button></p>';
        $html .= '</form>';

        $html .= '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testTraxConnection')) . '" class="itxeb-inline-form">';
        $html .= '<p class="itxeb-actions"><button class="btn btn-default" type="submit">Tester connexion TRAX</button></p>';
        $html .= '</form>';

        return $html;
    }

    private function renderSendActions(): string
    {
        $generated = $this->outbox->countByStatus('generated');
        $failed = $this->outbox->countByStatus('failed');
        $sent = $this->outbox->countByStatus('sent');

        $html = '<h2>Envoi manuel vers TRAX</h2>';
        $html .= '<div class="itxeb-summary">';
        $html .= '<span><strong>generated :</strong> ' . $this->esc((string) $generated) . '</span>';
        $html .= '<span><strong>failed :</strong> ' . $this->esc((string) $failed) . '</span>';
        $html .= '<span><strong>sent :</strong> ' . $this->esc((string) $sent) . '</span>';
        $html .= '<span><strong>batch :</strong> ' . $this->esc((string) $this->config->getBatchSize()) . '</span>';
        $html .= '</div>';
        $html .= '<p>Le bouton ci-dessous envoie uniquement les statements au statut <code>generated</code> ou <code>failed</code>. Les statements déjà <code>sent</code> ne sont pas renvoyés.</p>';
        $html .= '<p class="itxeb-actions"><a class="btn btn-primary" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'sendGenerated')) . '">Envoyer les statements générés vers TRAX</a></p>';

        return $html;
    }

    private function inputRow(string $label, string $name, string $value, string $help): string
    {
        return '<tr><td><label for="' . $this->esc($name) . '">' . $this->esc($label) . '</label></td><td>'
            . '<input id="' . $this->esc($name) . '" name="' . $this->esc($name) . '" type="text" value="' . $this->esc($value) . '" class="form-control itxeb-input">'
            . '<div class="itxeb-help">' . $this->esc($help) . '</div></td></tr>';
    }

    private function passwordRow(string $label, string $name, string $help): string
    {
        return '<tr><td><label for="' . $this->esc($name) . '">' . $this->esc($label) . '</label></td><td>'
            . '<input id="' . $this->esc($name) . '" name="' . $this->esc($name) . '" type="password" value="" class="form-control itxeb-input">'
            . '<div class="itxeb-help">' . $this->esc($help) . '</div></td></tr>';
    }

    private function saveConfig(): void
    {
        $this->config->setTraxEndpoint($this->postString('trax_endpoint'));
        $this->config->setTraxUsername($this->postString('trax_username'));

        $password = $this->postString('trax_password');
        if ($password !== '') {
            $this->config->setTraxPassword($password);
        }

        $this->config->setXapiVersion($this->postString('xapi_version'));
        $this->config->setHttpTimeout((int) $this->postString('http_timeout'));
        $this->config->setBatchSize((int) $this->postString('batch_size'));
        $this->config->setIliasBaseUrl($this->postString('ilias_base_url'));

        if (class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) {
            ilUtil::sendSuccess('Configuration TRAX enregistrée.', true);
        }

        $this->ctrl->redirect($this, 'configure');
    }

    private function testTraxConnection(): void
    {
        $client = new ilIliasTraxEventBridgeTraxClient($this->config);
        $result = $client->testConnection();

        $message = $result->getShortMessage();
        $this->config->setLastTraxTestResult($result->isSuccess(), $result->getHttpStatus(), $message);

        if ($result->isSuccess()) {
            if (class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) {
                ilUtil::sendSuccess('Connexion TRAX réussie : ' . $message, true);
            }
        } else {
            if (class_exists('ilUtil') && method_exists('ilUtil', 'sendFailure')) {
                ilUtil::sendFailure('Connexion TRAX échouée : ' . $message, true);
            }
        }

        $this->ctrl->redirect($this, 'configure');
    }

    private function sendGenerated(): void
    {
        $rows = $this->outbox->findSendable($this->config->getBatchSize());

        if (count($rows) === 0) {
            $this->config->setLastTraxSendResult(true, 0, 'Aucun statement generated/failed à envoyer.');
            if (class_exists('ilUtil') && method_exists('ilUtil', 'sendInfo')) {
                ilUtil::sendInfo('Aucun statement generated/failed à envoyer.', true);
            }
            $this->ctrl->redirect($this, 'configure');
            return;
        }

        $ids = [];
        $statements = [];

        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
            $decoded = json_decode((string) $row['statement_json'], true);
            if (is_array($decoded)) {
                $statements[] = $decoded;
            }
        }

        if (count($statements) === 0) {
            $this->outbox->markFailed($ids, 'Aucun statement JSON valide dans le batch.');
            $this->config->setLastTraxSendResult(false, 0, 'Aucun statement JSON valide dans le batch.');
            if (class_exists('ilUtil') && method_exists('ilUtil', 'sendFailure')) {
                ilUtil::sendFailure('Aucun statement JSON valide dans le batch.', true);
            }
            $this->ctrl->redirect($this, 'configure');
            return;
        }

        $this->outbox->markSending($ids);

        $client = new ilIliasTraxEventBridgeTraxClient($this->config);
        $result = $client->sendStatements($statements);

        if ($result->isSuccess()) {
            $this->outbox->markSent($ids);
            $message = count($ids) . ' statement(s) envoyé(s) vers TRAX. ' . $result->getShortMessage();
            $this->config->setLastTraxSendResult(true, $result->getHttpStatus(), $message);
            if (class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) {
                ilUtil::sendSuccess($message, true);
            }
        } else {
            $message = 'Envoi TRAX échoué : ' . $result->getShortMessage();
            $this->outbox->markFailed($ids, $result->getShortMessage());
            $this->config->setLastTraxSendResult(false, $result->getHttpStatus(), $message);
            if (class_exists('ilUtil') && method_exists('ilUtil', 'sendFailure')) {
                ilUtil::sendFailure($message, true);
            }
        }

        $this->ctrl->redirect($this, 'configure');
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
        $html .= '<p>Cette table contient les statements xAPI générés localement et leur statut d’envoi vers TRAX.</p>';

        $html .= '<div class="itxeb-summary">';
        $html .= '<span><strong>Total statements :</strong> ' . $this->esc((string) $total) . '</span>';
        $html .= '<span><strong>Affichés :</strong> ' . $this->esc((string) count($rows)) . ' / ' . $this->esc((string) min($limit, max($total, 0))) . '</span>';
        $html .= '<span><strong>generated :</strong> ' . $this->esc((string) $this->outbox->countByStatus('generated')) . '</span>';
        $html .= '<span><strong>sent :</strong> ' . $this->esc((string) $this->outbox->countByStatus('sent')) . '</span>';
        $html .= '<span><strong>failed :</strong> ' . $this->esc((string) $this->outbox->countByStatus('failed')) . '</span>';
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
            . '<th class="itxeb-col-statement">Statement / erreur</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $statement = $this->formatPayload((string) ($row['statement_json'] ?? ''));
            $lastError = trim((string) ($row['last_error'] ?? ''));

            $html .= '<tr>'
                . '<td class="itxeb-id-cell">'
                    . '<div class="itxeb-id">#' . $this->esc((string) ($row['id'] ?? '')) . '</div>'
                    . '<div class="itxeb-date">' . $this->esc((string) ($row['created_at'] ?? '')) . '</div>'
                    . '<div class="itxeb-date">log #' . $this->esc((string) ($row['event_log_id'] ?? '')) . '</div>'
                    . '<div class="itxeb-date">sent: ' . $this->esc((string) ($row['sent_at'] ?? '')) . '</div>'
                . '</td>'
                . '<td>'
                    . '<span class="itxeb-badge ' . $this->statusBadgeClass((string) ($row['status'] ?? '')) . '">' . $this->esc((string) ($row['status'] ?? '')) . '</span>'
                    . '<div class="itxeb-date">' . $this->esc((string) ($row['event_type'] ?? '')) . '</div>'
                . '</td>'
                . '<td><div class="itxeb-uri">' . $this->esc((string) ($row['verb_id'] ?? '')) . '</div></td>'
                . '<td>'
                    . '<div><strong>User :</strong> ' . $this->esc((string) ($row['user_id'] ?? '')) . '</div>'
                    . '<div><strong>ref_id :</strong> ' . $this->esc((string) ($row['ref_id'] ?? '')) . '</div>'
                    . '<div><strong>obj_id :</strong> ' . $this->esc((string) ($row['obj_id'] ?? '')) . '</div>'
                    . '<div><strong>type :</strong> ' . $this->esc((string) ($row['obj_type'] ?? '')) . '</div>'
                . '</td>'
                . '<td>'
                    . ($lastError !== '' ? '<div class="itxeb-error"><strong>Erreur :</strong> ' . $this->esc($lastError) . '</div>' : '')
                    . '<details class="itxeb-details"><summary>Afficher le statement xAPI</summary><pre>' . $this->esc($statement) . '</pre></details>'
                . '</td>'
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

    private function statusBadgeClass(string $status): string
    {
        if ($status === 'sent') {
            return 'itxeb-badge-sent';
        }
        if ($status === 'failed') {
            return 'itxeb-badge-failed';
        }
        if ($status === 'sending') {
            return 'itxeb-badge-admin';
        }

        return 'itxeb-badge-xapi';
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

    private function postString(string $key): string
    {
        if (isset($_POST[$key]) && is_scalar($_POST[$key])) {
            return trim((string) $_POST[$key]);
        }

        return '';
    }

    private function renderInlineStyles(): string
    {
        return '<style>
.itxeb-page { max-width: 100%; }
.itxeb-state { margin-bottom: 18px; }
.itxeb-state-table { width: auto; min-width: 520px; }
.itxeb-form-table { width: 100%; max-width: 1120px; }
.itxeb-form-table td { vertical-align: top; padding: 8px; }
.itxeb-form-table td:first-child { width: 260px; font-weight: 700; }
.itxeb-input { width: 100%; max-width: 760px; }
.itxeb-help { color: #666; font-size: 12px; margin-top: 4px; }
.itxeb-actions { margin: 8px 0 18px 0; }
.itxeb-inline-form { display: inline; }
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
.itxeb-error { border-left: 4px solid #b95d5d; background: #fff0f0; padding: 6px 8px; margin-bottom: 6px; }
.itxeb-badge { display: inline-block; padding: 4px 7px; border-radius: 4px; font-weight: 700; border: 1px solid #cfcfcf; background: #f5f5f5; }
.itxeb-badge-xapi { border-color: #7c9b4d; background: #f2f8e8; }
.itxeb-badge-sent { border-color: #3f7f3f; background: #eaf8ea; }
.itxeb-badge-failed { border-color: #b95d5d; background: #fff0f0; }
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
