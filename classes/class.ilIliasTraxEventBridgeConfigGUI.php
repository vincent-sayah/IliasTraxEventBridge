<?php

/**
 * Administration screen for IliasTraxEventBridge.
 *
 * @ilCtrl_IsCalledBy ilIliasTraxEventBridgeConfigGUI: ilObjComponentSettingsGUI
 */
require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeEventDebugRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeOutboxRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeTraxClient.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeOutboxSender.php';

class ilIliasTraxEventBridgeConfigGUI extends ilPluginConfigGUI
{
    private $ctrl;
    private $tpl;
    /** @var ilIliasTraxEventBridgeConfig */
    private $config;
    /** @var ilIliasTraxEventBridgeEventDebugRepository */
    private $repo;
    /** @var ilIliasTraxEventBridgeOutboxRepository */
    private $outbox;

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
            case 'saveConfig': $this->saveConfig(); break;
            case 'testTraxConnection': $this->testTraxConnection(); break;
            case 'sendGenerated': $this->sendGenerated(); break;
            case 'resetFailed': $this->resetFailed(); break;
            case 'clearLog': $this->clearLog(); break;
            case 'clearOutbox': $this->clearOutbox(); break;
            case 'configure':
            default: $this->configure(); break;
        }
    }

    private function init(): void
    {
        $this->config = new ilIliasTraxEventBridgeConfig();
        $this->repo = new ilIliasTraxEventBridgeEventDebugRepository();
        $this->outbox = new ilIliasTraxEventBridgeOutboxRepository();
        $this->outbox->resetStuckSending();
    }

    private function configure(): void
    {
        $html = $this->styles();
        $html .= '<div class="itxeb-page"><h1>IliasTraxEventBridge — V0.4.0</h1>';
        $html .= '<p><strong>V0.4 :</strong> cron ILIAS, retry_count/max_retry, reset des failed et diagnostics cron.</p>';
        $html .= $this->renderState();
        $html .= $this->renderConfigForm();
        $html .= $this->renderSendActions();
        $html .= $this->renderOutbox();
        $html .= $this->renderRecentEvents();
        $html .= '</div>';
        $this->setContent($html);
    }

    private function renderState(): string
    {
        $html = '<section class="itxeb-section"><h2>État</h2><table class="std itxeb-state-table">';
        $html .= '<tr><td>Plugin actif</td><td><strong>' . ($this->config->isEnabled() ? 'oui' : 'non') . '</strong></td></tr>';
        $html .= '<tr><td>Mode debug</td><td><strong>' . ($this->config->isDebugEnabled() ? 'oui' : 'non') . '</strong></td></tr>';
        $html .= '<tr><td>Génération xAPI locale</td><td><strong>' . ($this->config->isLocalXapiGenerationEnabled() ? 'oui' : 'non') . '</strong></td></tr>';
        $html .= '<tr><td>Cron plugin</td><td><strong>' . ($this->config->isCronEnabled() ? 'activé' : 'désactivé') . '</strong></td></tr>';
        $html .= '<tr><td>Endpoint statements</td><td><code class="itxeb-code-inline">' . $this->esc($this->config->getStatementsEndpoint()) . '</code></td></tr>';
        $html .= '</table>';
        $html .= '<h3>Diagnostics</h3><table class="std itxeb-state-table">';
        $html .= $this->diagRow('Dernier test connexion', $this->config->getLastTraxTestAt(), $this->config->getLastTraxTestSuccess(), $this->config->getLastTraxTestHttpStatus(), $this->config->getLastTraxTestMessage());
        $html .= $this->diagRow('Dernier envoi manuel', $this->config->getLastTraxSendAt(), $this->config->getLastTraxSendSuccess(), $this->config->getLastTraxSendHttpStatus(), $this->config->getLastTraxSendMessage());
        $html .= $this->diagRow('Dernier cron', $this->config->getLastCronAt(), $this->config->getLastCronSuccess(), $this->config->getLastCronHttpStatus(), $this->config->getLastCronMessage());
        $html .= '</table>';
        $html .= '<p class="itxeb-actions"><a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearLog')) . '">Vider le journal debug</a> ';
        $html .= '<a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearOutbox')) . '">Vider l’outbox xAPI locale</a></p></section>';
        return $html;
    }

    private function diagRow(string $label, string $at, string $success, string $http, string $message): string
    {
        if ($at === '') {
            return '<tr><td>' . $this->esc($label) . '</td><td><em>Aucun diagnostic disponible.</em></td></tr>';
        }
        return '<tr><td>' . $this->esc($label) . '</td><td><strong>date :</strong> ' . $this->esc($at)
            . '<br><strong>succès :</strong> ' . $this->esc($success)
            . '<br><strong>HTTP :</strong> ' . $this->esc($http)
            . '<br><strong>message :</strong> ' . $this->esc($message) . '</td></tr>';
    }

    private function renderConfigForm(): string
    {
        $html = '<section class="itxeb-section"><h2>Configuration TRAX / cron</h2>';
        $html .= '<div class="itxeb-alert"><strong>Important :</strong> cette case autorise seulement le plugin à envoyer via cron. Il faut aussi activer le job cron ILIAS <code>itxeb_send_outbox_to_trax</code> dans <em>Administration &gt; Paramètres système et maintenance &gt; Tâches cron</em>.</div>';
        $html .= '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'saveConfig')) . '"><table class="std itxeb-form-table">';
        $html .= $this->checkboxRow('Activer le cron plugin', 'cron_enabled', $this->config->isCronEnabled(), 'Autorise le job cron du plugin. Le job cron ILIAS global doit aussi être activé dans les tâches cron.');
        $html .= $this->inputRow('Endpoint xAPI TRAX', 'trax_endpoint', $this->config->getTraxEndpoint(), 'Endpoint xAPI racine ou URL complète /statements.');
        $html .= $this->inputRow('Identifiant client TRAX', 'trax_username', $this->config->getTraxUsername(), 'Client xAPI autorisé à écrire.');
        $html .= $this->passwordRow('Secret client TRAX', 'trax_password', 'Laisser vide pour conserver le secret.');
        $html .= $this->inputRow('Version xAPI', 'xapi_version', $this->config->getXapiVersion(), 'Recommandé : 1.0.3.');
        $html .= $this->inputRow('Timeout HTTP', 'http_timeout', (string) $this->config->getHttpTimeout(), 'Entre 2 et 120 secondes.');
        $html .= $this->inputRow('Taille batch', 'batch_size', (string) $this->config->getBatchSize(), 'Entre 1 et 100 statements.');
        $html .= $this->inputRow('Max retry', 'max_retry', (string) $this->config->getMaxRetry(), 'Nombre maximum de tentatives par statement.');
        $html .= $this->inputRow('Base URL ILIAS forcée', 'ilias_base_url', $this->config->getIliasBaseUrl(), 'Optionnel. Utilisé pour les IRIs xAPI.');
        $html .= '</table><p class="itxeb-actions"><button class="btn btn-primary" type="submit">Enregistrer</button></p></form>';
        $html .= '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testTraxConnection')) . '"><p><button class="btn btn-default" type="submit">Tester connexion TRAX</button></p></form></section>';
        return $html;
    }

    private function renderSendActions(): string
    {
        $html = '<section class="itxeb-section"><h2>Envoi vers TRAX</h2><div class="itxeb-summary">';
        $html .= '<span><strong>generated :</strong> ' . $this->esc((string) $this->outbox->countByStatus('generated')) . '</span>';
        $html .= '<span><strong>failed :</strong> ' . $this->esc((string) $this->outbox->countByStatus('failed')) . '</span>';
        $html .= '<span><strong>retry épuisé :</strong> ' . $this->esc((string) $this->outbox->countRetryExhausted($this->config->getMaxRetry())) . '</span>';
        $html .= '<span><strong>sent :</strong> ' . $this->esc((string) $this->outbox->countByStatus('sent')) . '</span>';
        $html .= '<span><strong>batch :</strong> ' . $this->esc((string) $this->config->getBatchSize()) . '</span>';
        $html .= '<span><strong>max_retry :</strong> ' . $this->esc((string) $this->config->getMaxRetry()) . '</span></div>';
        $html .= '<p>L’envoi manuel et le cron traitent les statements <code>generated</code> ou <code>failed</code> lorsque <code>retry_count &lt; max_retry</code>. Pour l’envoi automatique, le job cron ILIAS doit être activé et exécuté.</p>';
        $html .= '<p class="itxeb-actions"><a class="btn btn-primary" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'sendGenerated')) . '">Envoyer maintenant</a> ';
        $html .= '<a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'resetFailed')) . '">Réinitialiser les failed</a></p></section>';
        return $html;
    }

    private function saveConfig(): void
    {
        $this->config->setCronEnabled($this->postString('cron_enabled') === '1');
        $this->config->setTraxEndpoint($this->postString('trax_endpoint'));
        $this->config->setTraxUsername($this->postString('trax_username'));
        $password = $this->postString('trax_password');
        if ($password !== '') { $this->config->setTraxPassword($password); }
        $this->config->setXapiVersion($this->postString('xapi_version'));
        $this->config->setHttpTimeout((int) $this->postString('http_timeout'));
        $this->config->setBatchSize((int) $this->postString('batch_size'));
        $this->config->setMaxRetry((int) $this->postString('max_retry'));
        $this->config->setIliasBaseUrl($this->postString('ilias_base_url'));
        if (class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) { ilUtil::sendSuccess('Configuration enregistrée.', true); }
        $this->ctrl->redirect($this, 'configure');
    }

    private function testTraxConnection(): void
    {
        $result = (new ilIliasTraxEventBridgeTraxClient($this->config))->testConnection();
        $message = $result->getShortMessage();
        $this->config->setLastTraxTestResult($result->isSuccess(), $result->getHttpStatus(), $message);
        if ($result->isSuccess() && class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) {
            ilUtil::sendSuccess('Connexion TRAX réussie : ' . $message, true);
        } elseif (class_exists('ilUtil') && method_exists('ilUtil', 'sendFailure')) {
            ilUtil::sendFailure('Connexion TRAX échouée : ' . $message, true);
        }
        $this->ctrl->redirect($this, 'configure');
    }

    private function sendGenerated(): void
    {
        $result = (new ilIliasTraxEventBridgeOutboxSender($this->config, $this->outbox))->sendBatch();
        $this->config->setLastTraxSendResult((bool) $result['success'], (int) $result['http_status'], (string) $result['message']);
        if ($result['success'] && class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) {
            ilUtil::sendSuccess((string) $result['message'], true);
        } elseif (class_exists('ilUtil') && method_exists('ilUtil', 'sendFailure')) {
            ilUtil::sendFailure((string) $result['message'], true);
        }
        $this->ctrl->redirect($this, 'configure');
    }

    private function resetFailed(): void
    {
        $count = $this->outbox->resetFailedToGenerated();
        if (class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) { ilUtil::sendSuccess($count . ' statement(s) failed réinitialisé(s).', true); }
        $this->ctrl->redirect($this, 'configure');
    }

    private function clearLog(): void
    {
        $this->repo->clear();
        if (class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) { ilUtil::sendSuccess('Journal vidé.', true); }
        $this->ctrl->redirect($this, 'configure');
    }

    private function clearOutbox(): void
    {
        $this->outbox->clear();
        if (class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) { ilUtil::sendSuccess('Outbox vidée.', true); }
        $this->ctrl->redirect($this, 'configure');
    }

    private function renderOutbox(): string
    {
        $rows = $this->outbox->findRecent(50);
        $html = '<section class="itxeb-section"><h2>Outbox xAPI locale</h2>';
        if (count($rows) === 0) { return $html . '<p><em>Aucun statement xAPI généré pour le moment.</em></p></section>'; }
        $html .= '<div class="itxeb-table-wrapper"><table class="std itxeb-events itxeb-outbox-table"><thead><tr><th>ID / date</th><th>Statut</th><th>Retry</th><th>Verb</th><th>Objet</th><th>Erreur / statement</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $lastError = trim((string) ($row['last_error'] ?? ''));
            $html .= '<tr><td class="itxeb-nowrap">#' . $this->esc((string) ($row['id'] ?? '')) . '<br><span class="itxeb-date">' . $this->esc((string) ($row['created_at'] ?? '')) . '</span><br><span class="itxeb-date">last try: ' . $this->esc((string) ($row['last_attempt_at'] ?? '')) . '</span></td>';
            $html .= '<td><span class="itxeb-badge ' . $this->statusBadgeClass((string) ($row['status'] ?? '')) . '">' . $this->esc((string) ($row['status'] ?? '')) . '</span></td>';
            $html .= '<td class="itxeb-nowrap">' . $this->esc((string) ($row['retry_count'] ?? '0')) . ' / ' . $this->esc((string) ($row['max_retry'] ?? $this->config->getMaxRetry())) . '</td>';
            $html .= '<td><div class="itxeb-uri">' . $this->esc((string) ($row['verb_id'] ?? '')) . '</div></td>';
            $html .= '<td class="itxeb-object">user ' . $this->esc((string) ($row['user_id'] ?? '')) . '<br>ref ' . $this->esc((string) ($row['ref_id'] ?? '')) . '<br>obj ' . $this->esc((string) ($row['obj_id'] ?? '')) . '<br>' . $this->esc((string) ($row['obj_type'] ?? '')) . '</td>';
            $html .= '<td class="itxeb-wide">' . ($lastError !== '' ? '<div class="itxeb-error">' . $this->esc($lastError) . '</div>' : '') . '<details><summary>Statement</summary><pre>' . $this->esc($this->formatPayload((string) ($row['statement_json'] ?? ''))) . '</pre></details></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    private function renderRecentEvents(): string
    {
        $rows = $this->repo->findRecent(100);
        $html = '<section class="itxeb-section"><h2>Derniers événements ILIAS reçus</h2>';
        if (count($rows) === 0) { return $html . '<p><em>Aucun événement journalisé pour le moment.</em></p></section>'; }
        $html .= '<div class="itxeb-table-wrapper"><table class="std itxeb-events itxeb-log-table"><thead><tr><th>ID / date</th><th>Événement</th><th>Objet</th><th>Payload</th><th>URI</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr><td class="itxeb-nowrap">#' . $this->esc((string) ($row['id'] ?? '')) . '<br><span class="itxeb-date">' . $this->esc((string) ($row['created_at'] ?? '')) . '</span></td>';
            $html .= '<td><div class="itxeb-text-block">' . $this->esc((string) ($row['component'] ?? '')) . '<br><strong>' . $this->esc((string) ($row['event_name'] ?? '')) . '</strong></div></td>';
            $html .= '<td class="itxeb-object">user ' . $this->esc((string) ($row['user_id'] ?? '')) . '<br>ref ' . $this->esc((string) ($row['ref_id'] ?? '')) . '<br>obj ' . $this->esc((string) ($row['obj_id'] ?? '')) . '<br>' . $this->esc((string) ($row['obj_type'] ?? '')) . '</td>';
            $html .= '<td class="itxeb-wide"><details><summary>Payload</summary><pre>' . $this->esc($this->formatPayload((string) ($row['payload_json'] ?? ''))) . '</pre></details></td>';
            $html .= '<td><div class="itxeb-uri">' . $this->esc((string) ($row['request_uri'] ?? '')) . '</div></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    private function inputRow(string $label, string $name, string $value, string $help): string
    {
        return '<tr><td><label for="' . $this->esc($name) . '">' . $this->esc($label) . '</label></td><td><input id="' . $this->esc($name) . '" name="' . $this->esc($name) . '" type="text" value="' . $this->esc($value) . '" class="form-control itxeb-input"><div class="itxeb-help">' . $this->esc($help) . '</div></td></tr>';
    }

    private function passwordRow(string $label, string $name, string $help): string
    {
        return '<tr><td><label for="' . $this->esc($name) . '">' . $this->esc($label) . '</label></td><td><input id="' . $this->esc($name) . '" name="' . $this->esc($name) . '" type="password" value="" class="form-control itxeb-input"><div class="itxeb-help">' . $this->esc($help) . '</div></td></tr>';
    }

    private function checkboxRow(string $label, string $name, bool $checked, string $help): string
    {
        return '<tr><td><label for="' . $this->esc($name) . '">' . $this->esc($label) . '</label></td><td><label class="itxeb-check"><input id="' . $this->esc($name) . '" name="' . $this->esc($name) . '" type="checkbox" value="1"' . ($checked ? ' checked="checked"' : '') . '> activé</label><div class="itxeb-help">' . $this->esc($help) . '</div></td></tr>';
    }

    private function statusBadgeClass(string $status): string
    {
        if ($status === 'sent') { return 'itxeb-badge-ok'; }
        if ($status === 'failed') { return 'itxeb-badge-error'; }
        if ($status === 'sending') { return 'itxeb-badge-warn'; }
        return 'itxeb-badge-muted';
    }

    private function formatPayload(string $json): string
    {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $pretty = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if (is_string($pretty)) { return $pretty; }
        }
        return $json;
    }

    private function postString(string $key): string
    {
        if (isset($_POST[$key]) && is_scalar($_POST[$key])) { return trim((string) $_POST[$key]); }
        return '';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function setContent(string $html): void
    {
        if (is_object($this->tpl) && method_exists($this->tpl, 'setContent')) { $this->tpl->setContent($html); }
    }

    private function styles(): string
    {
        return '<style>'
            . '.itxeb-page{max-width:1600px;margin:0 0 4rem 0}'
            . '.itxeb-page h2{margin-top:2rem;margin-bottom:.6rem}'
            . '.itxeb-page h3{margin-top:1.2rem;margin-bottom:.5rem}'
            . '.itxeb-section{margin-bottom:1.5rem}'
            . '.itxeb-alert{max-width:980px;padding:.65rem .8rem;margin:.4rem 0 .9rem;border:1px solid #bce8f1;background:#eef8fc;border-radius:4px;line-height:1.4}'
            . '.itxeb-page table.std{width:100%;border-collapse:collapse;background:#fff}'
            . '.itxeb-page table.std th,.itxeb-page table.std td{padding:.55rem .7rem;vertical-align:top;line-height:1.35}'
            . '.itxeb-page table.std th{white-space:nowrap}'
            . '.itxeb-state-table,.itxeb-form-table{max-width:980px}'
            . '.itxeb-state-table td:first-child,.itxeb-form-table td:first-child{width:230px;min-width:230px;font-weight:600;white-space:nowrap}'
            . '.itxeb-form-table td:last-child{width:100%}'
            . '.itxeb-input{width:100%;max-width:780px;box-sizing:border-box}'
            . '.itxeb-check{display:inline-flex;gap:.35rem;align-items:center;margin:0;font-weight:normal}'
            . '.itxeb-help,.itxeb-date{font-size:.9em;color:#666}'
            . '.itxeb-code-inline{display:inline-block;max-width:100%;overflow:auto;white-space:nowrap}'
            . '.itxeb-actions{margin:.8rem 0 1.2rem}'
            . '.itxeb-summary{display:flex;flex-wrap:wrap;gap:.5rem;margin:.5rem 0 1rem}'
            . '.itxeb-summary span{background:#f5f5f5;border:1px solid #ddd;padding:.35rem .55rem;border-radius:4px}'
            . '.itxeb-table-wrapper{max-width:100%;overflow-x:auto;border:1px solid #ddd;border-radius:4px;background:#fff;margin:.4rem 0 1rem}'
            . '.itxeb-events{min-width:1180px;table-layout:fixed}'
            . '.itxeb-events th,.itxeb-events td{border-bottom:1px solid #eee}'
            . '.itxeb-outbox-table th:nth-child(1){width:160px}.itxeb-outbox-table th:nth-child(2){width:110px}.itxeb-outbox-table th:nth-child(3){width:85px}.itxeb-outbox-table th:nth-child(4){width:260px}.itxeb-outbox-table th:nth-child(5){width:135px}'
            . '.itxeb-log-table{min-width:1250px}.itxeb-log-table th:nth-child(1){width:155px}.itxeb-log-table th:nth-child(2){width:300px}.itxeb-log-table th:nth-child(3){width:135px}.itxeb-log-table th:nth-child(5){width:330px}'
            . '.itxeb-nowrap{white-space:nowrap}.itxeb-object{white-space:nowrap}.itxeb-wide{min-width:360px}'
            . '.itxeb-uri{max-width:100%;overflow:auto;white-space:nowrap;font-family:monospace;font-size:.92em}'
            . '.itxeb-text-block{max-width:100%;white-space:normal;word-break:break-word;overflow-wrap:anywhere}'
            . '.itxeb-badge{display:inline-block;padding:.2rem .45rem;border-radius:3px;background:#eee;font-weight:600}'
            . '.itxeb-badge-ok{background:#dff0d8}.itxeb-badge-warn{background:#fcf8e3}.itxeb-badge-error{background:#f2dede}.itxeb-badge-muted{background:#eee}'
            . '.itxeb-error{color:#a94442;margin-bottom:.35rem;word-break:break-word;overflow-wrap:anywhere}'
            . '.itxeb-events pre{max-height:320px;overflow:auto;white-space:pre-wrap;word-break:break-word;overflow-wrap:anywhere;margin:.4rem 0 0}'
            . '.itxeb-events details summary{cursor:pointer;font-weight:600}'
            . '@media (max-width:900px){.itxeb-state-table td:first-child,.itxeb-form-table td:first-child{width:auto;min-width:0;white-space:normal}.itxeb-events{min-width:980px}}'
            . '</style>';
    }
}
