<?php
/**
 * @ilCtrl_IsCalledBy ilIliasTraxEventBridgeConfigGUI: ilObjComponentSettingsGUI
 */
require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeEventDebugRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeOutboxRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeTraxClient.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeOutboxSender.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseTrackingGUI.php';

class ilIliasTraxEventBridgeConfigGUI extends ilPluginConfigGUI
{
    private $ctrl; private $tpl; private $config; private $repo; private $outbox;

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
            case 'configureCourseTracking': $this->handleCourseTracking('show'); break;
            case 'saveCourseTracking': $this->handleCourseTracking('save'); break;
            case 'enableAllCourseTracking': $this->handleCourseTracking('enableAll'); break;
            case 'disableAllCourseTracking': $this->handleCourseTracking('disableAll'); break;
            case 'resetCourseTracking': $this->handleCourseTracking('resetCourse'); break;
            case 'clearLog': $this->repo->clear(); $this->success('Journal vidé.'); $this->ctrl->redirect($this, 'configure'); break;
            case 'clearOutbox': $this->outbox->clear(); $this->success('Outbox vidée.'); $this->ctrl->redirect($this, 'configure'); break;
            case 'configure': default: $this->configure(); break;
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
        $html = $this->styles() . '<div class="itxeb-page"><h1>IliasTraxEventBridge — V0.7</h1>'
            . '<p><strong>V0.7 :</strong> pilotage des traces xAPI par cours et par ressource. Le filtrage effectif avant outbox sera ajouté dans le prochain lot.</p>'
            . $this->renderState()
            . $this->renderCourseTrackingAccess()
            . $this->renderConfigForm()
            . $this->renderSendActions()
            . $this->renderAdminDashboard()
            . $this->renderOutbox()
            . $this->renderRecentEvents()
            . '</div>';
        $this->setContent($html);
    }

    private function renderState(): string
    {
        $html = '<section class="itxeb-section"><h2>État</h2><table class="std itxeb-state-table">';
        foreach (['Plugin actif'=>$this->config->isEnabled()?'oui':'non','Mode debug'=>$this->config->isDebugEnabled()?'oui':'non','Génération xAPI locale'=>$this->config->isLocalXapiGenerationEnabled()?'oui':'non','Cron plugin'=>$this->config->isCronEnabled()?'activé':'désactivé'] as $k=>$v) { $html .= '<tr><td>'.$this->esc($k).'</td><td><strong>'.$this->esc($v).'</strong></td></tr>'; }
        $html .= '<tr><td>Endpoint statements</td><td><code class="itxeb-code-inline">' . $this->esc($this->config->getStatementsEndpoint()) . '</code></td></tr></table>';
        $html .= '<h3>Diagnostics</h3><table class="std itxeb-state-table">'
            . $this->diagRow('Dernier test connexion', $this->config->getLastTraxTestAt(), $this->config->getLastTraxTestSuccess(), $this->config->getLastTraxTestHttpStatus(), $this->config->getLastTraxTestMessage())
            . $this->diagRow('Dernier envoi manuel', $this->config->getLastTraxSendAt(), $this->config->getLastTraxSendSuccess(), $this->config->getLastTraxSendHttpStatus(), $this->config->getLastTraxSendMessage())
            . $this->diagRow('Dernier cron', $this->config->getLastCronAt(), $this->config->getLastCronSuccess(), $this->config->getLastCronHttpStatus(), $this->config->getLastCronMessage())
            . '</table><p class="itxeb-actions"><a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearLog')) . '">Vider le journal debug</a> '
            . '<a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearOutbox')) . '">Vider l’outbox xAPI locale</a></p></section>';
        return $html;
    }

    private function diagRow(string $label, string $at, string $success, string $http, string $message): string
    {
        if ($at === '') { return '<tr><td>' . $this->esc($label) . '</td><td><em>Aucun diagnostic disponible.</em></td></tr>'; }
        return '<tr><td>' . $this->esc($label) . '</td><td><strong>date :</strong> ' . $this->esc($at) . '<br><strong>succès :</strong> ' . $this->esc($success) . '<br><strong>HTTP :</strong> ' . $this->esc($http) . '<br><strong>message :</strong> ' . $this->esc($message) . '</td></tr>';
    }

    private function renderCourseTrackingAccess(): string
    {
        $action = $this->ctrl->getLinkTarget($this, 'configureCourseTracking');
        return '<section class="itxeb-section itxeb-course-admin-access"><h2>Configuration xAPI par cours</h2>'
            . '<div class="itxeb-alert"><strong>V0.7 :</strong> cette section donne accès à l’écran de configuration xAPI d’un cours. Saisir le <code>ref_id</code> du cours, puis activer/désactiver le cours et ses ressources traçables. L’intégration directe dans les paramètres natifs du cours reste à valider selon les points d’extension ILIAS 10 disponibles.</div>'
            . '<form method="post" action="' . $this->esc($action) . '"><table class="std itxeb-form-table"><tbody>'
            . '<tr><td><label for="itxeb_course_ref_id">course_ref_id</label></td><td><input id="itxeb_course_ref_id" name="course_ref_id" type="number" min="1" value="" class="form-control itxeb-input"><div class="itxeb-help">Exemple : le <code>ref_id</code> visible dans l’URL du cours ILIAS.</div></td></tr>'
            . '</tbody></table><p class="itxeb-actions"><button class="btn btn-primary" type="submit">Ouvrir la configuration xAPI du cours</button></p></form></section>';
    }

    private function handleCourseTracking(string $courseCommand): void
    {
        $gui = new ilIliasTraxEventBridgeCourseTrackingGUI($this, [
            'show' => 'configureCourseTracking',
            'save' => 'saveCourseTracking',
            'enableAll' => 'enableAllCourseTracking',
            'disableAll' => 'disableAllCourseTracking',
            'resetCourse' => 'resetCourseTracking',
        ]);
        $gui->performCommand($courseCommand);
    }

    private function renderConfigForm(): string
    {
        $html = '<section class="itxeb-section"><h2>Configuration TRAX / cron</h2><div class="itxeb-alert"><strong>Important :</strong> cette case autorise seulement le plugin à envoyer via cron. Il faut aussi activer le job cron ILIAS <code>itxeb_send_outbox_to_trax</code> dans <em>Administration &gt; Paramètres système et maintenance &gt; Tâches cron</em>.</div>';
        $html .= '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'saveConfig')) . '"><table class="std itxeb-form-table">';
        $html .= $this->checkboxRow('Activer le cron plugin', 'cron_enabled', $this->config->isCronEnabled(), 'Autorise le job cron du plugin. Le job cron ILIAS global doit aussi être activé dans les tâches cron.');
        foreach ([['Endpoint xAPI TRAX','trax_endpoint',$this->config->getTraxEndpoint(),'Endpoint xAPI racine ou URL complète /statements.'],['Identifiant client TRAX','trax_username',$this->config->getTraxUsername(),'Client xAPI autorisé à écrire.'],['Version xAPI','xapi_version',$this->config->getXapiVersion(),'Recommandé : 1.0.3.'],['Timeout HTTP','http_timeout',(string)$this->config->getHttpTimeout(),'Entre 2 et 120 secondes.'],['Taille batch','batch_size',(string)$this->config->getBatchSize(),'Entre 1 et 100 statements.'],['Max retry','max_retry',(string)$this->config->getMaxRetry(),'Nombre maximum de tentatives par statement.'],['Base URL ILIAS forcée','ilias_base_url',$this->config->getIliasBaseUrl(),'Optionnel. Utilisé pour les IRIs xAPI.']] as $r) { $html .= $this->inputRow($r[0], $r[1], $r[2], $r[3]); }
        $html .= $this->passwordRow('Secret client TRAX', 'trax_password', 'Laisser vide pour conserver le secret.');
        return $html . '</table><p class="itxeb-actions"><button class="btn btn-primary" type="submit">Enregistrer</button></p></form><form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testTraxConnection')) . '"><p><button class="btn btn-default" type="submit">Tester connexion TRAX</button></p></form></section>';
    }

    private function renderSendActions(): string
    {
        $html = '<section class="itxeb-section"><h2>Envoi vers TRAX</h2><div class="itxeb-summary">';
        foreach (['generated'=>$this->outbox->countByStatus('generated'),'failed'=>$this->outbox->countByStatus('failed'),'retry épuisé'=>$this->outbox->countRetryExhausted($this->config->getMaxRetry()),'sent'=>$this->config->getBatchSize(),'max_retry'=>$this->config->getMaxRetry()] as $k=>$v) { $html .= '<span><strong>'.$this->esc((string)$k).' :</strong> '.$this->esc((string)$v).'</span>'; }
        return $html . '</div><p>L’envoi manuel et le cron traitent les statements <code>generated</code> ou <code>failed</code> lorsque <code>retry_count &lt; max_retry</code>. Pour l’envoi automatique, le job cron ILIAS doit être activé et exécuté.</p><p class="itxeb-actions"><a class="btn btn-primary" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'sendGenerated')) . '">Envoyer maintenant</a> <a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'resetFailed')) . '">Réinitialiser les failed</a></p></section>';
    }

    private function renderAdminDashboard(): string
    {
        $rows = $this->outbox->findRecent(200);
        $html = '<section class="itxeb-section"><h2>Supervision V0.7</h2>';
        $html .= $this->renderOperationsMetrics();
        if (count($rows) === 0) {
            return $html . '<p><em>Aucune donnée outbox disponible pour la supervision détaillée.</em></p></section>';
        }

        $status = [];
        $eventTypes = [];
        $objTypes = [];
        $families = [];
        $interactions = [];
        $sources = [];
        $recentFailures = [];
        $recentDiagnostics = [];

        foreach ($rows as $r) {
            $this->countValue($status, (string) ($r['status'] ?? ''));
            $this->countValue($eventTypes, (string) ($r['event_type'] ?? ''));
            $this->countValue($objTypes, (string) ($r['obj_type'] ?? ''));

            $extensions = $this->statementExtensions((string) ($r['statement_json'] ?? ''));
            $family = $this->extensionValue($extensions, 'statement_family');
            $interaction = $this->extensionValue($extensions, 'interaction_type');
            $source = $this->extensionValue($extensions, 'source_table');
            $recordSource = $this->extensionValue($extensions, 'event_record_source');
            $deduplicationKey = $this->extensionValue($extensions, 'deduplication_key');

            $this->countValue($families, $family);
            $this->countValue($interactions, $interaction);
            $this->countValue($sources, $source !== '' ? $source : $recordSource);

            if ((string) ($r['status'] ?? '') === 'failed' || trim((string) ($r['last_error'] ?? '')) !== '') {
                $recentFailures[] = $r;
            }

            if (count($recentDiagnostics) < 12) {
                $recentDiagnostics[] = [
                    'id' => (string) ($r['id'] ?? ''),
                    'status' => (string) ($r['status'] ?? ''),
                    'event_type' => (string) ($r['event_type'] ?? ''),
                    'obj_type' => (string) ($r['obj_type'] ?? ''),
                    'family' => $family,
                    'source' => $source !== '' ? $source : $recordSource,
                    'deduplication_key' => $deduplicationKey,
                ];
            }
        }

        $html .= '<p>Vue synthétique calculée sur les 200 dernières lignes outbox. Elle sert à contrôler rapidement les volumes, familles xAPI, sources et clés de déduplication sans lancer de requêtes SQL.</p>';
        $html .= $this->renderCounterBlock('Statuts', $status)
            . $this->renderCounterBlock('Types d’événements SQL', $eventTypes)
            . $this->renderCounterBlock('Types objets ILIAS', $objTypes)
            . $this->renderCounterBlock('Familles xAPI', $families)
            . $this->renderCounterBlock('Types d’interaction', $interactions)
            . $this->renderCounterBlock('Sources techniques', $sources);
        $html .= $this->renderDiagnosticRows($recentDiagnostics);
        $html .= $this->renderFailureRows(array_slice($recentFailures, 0, 8));

        return $html . '</section>';
    }

    private function renderOperationsMetrics(): string
    {
        $now = time();
        $since24h = $now - 86400;
        $since7d = $now - (7 * 86400);
        $metrics = [
            'Total outbox' => $this->outbox->countAll(),
            'Créés 24h' => $this->outbox->countCreatedSince($since24h),
            'Créés 7j' => $this->outbox->countCreatedSince($since7d),
            'Sent total' => $this->outbox->countByStatus('sent'),
            'Sent 24h' => $this->outbox->countByStatusSince('sent', $since24h),
            'Generated total' => $this->outbox->countByStatus('generated'),
            'Failed total' => $this->outbox->countByStatus('failed'),
            'Failed 24h' => $this->outbox->countByStatusSince('failed', $since24h),
            'Failed/erreurs à inspecter' => $this->outbox->countFailedWithError(),
            'Retry épuisé' => $this->outbox->countRetryExhausted($this->config->getMaxRetry()),
        ];

        $html = '<div class="itxeb-dashboard-block itxeb-ops-block"><h3>Exploitation / maintenance</h3>';
        $html .= '<p>Compteurs calculés directement sur l’outbox locale. Les périodes 24h et 7j s’appuient sur <code>created_ts</code>.</p><div class="itxeb-summary itxeb-ops-summary">';
        foreach ($metrics as $label => $value) {
            $html .= '<span><strong>' . $this->esc($label) . ' :</strong> ' . $this->esc((string) $value) . '</span>';
        }
        $html .= '</div><p class="itxeb-help">À surveiller : <code>generated</code> qui augmente sans envoi, <code>failed</code>, erreurs non vides et retry épuisé.</p></div>';
        return $html;
    }

    private function renderCounterBlock(string $title, array $counts): string
    {
        arsort($counts);
        $html = '<div class="itxeb-dashboard-block"><h3>' . $this->esc($title) . '</h3><div class="itxeb-summary">';
        if (count($counts) === 0) {
            return $html . '<span><em>Aucune donnée.</em></span></div></div>';
        }
        foreach ($counts as $label => $count) {
            $html .= '<span><strong>' . $this->esc((string) $label) . ' :</strong> ' . $this->esc((string) $count) . '</span>';
        }
        return $html . '</div></div>';
    }

    private function renderDiagnosticRows(array $rows): string
    {
        $html = '<div class="itxeb-dashboard-block"><h3>Dernières clés de diagnostic</h3>';
        if (count($rows) === 0) {
            return $html . '<p><em>Aucune clé de diagnostic disponible.</em></p></div>';
        }
        $html .= '<div class="itxeb-table-wrapper"><table class="std itxeb-events itxeb-diagnostic-table"><thead><tr><th>ID</th><th>Statut</th><th>Événement</th><th>Famille</th><th>Source</th><th>Déduplication</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr><td class="itxeb-nowrap">#' . $this->esc($r['id']) . '</td>'
                . '<td><span class="itxeb-badge ' . $this->statusBadgeClass($r['status']) . '">' . $this->esc($r['status']) . '</span></td>'
                . '<td>' . $this->esc($r['event_type']) . '<br><span class="itxeb-date">' . $this->esc($r['obj_type']) . '</span></td>'
                . '<td>' . $this->esc($r['family']) . '</td>'
                . '<td>' . $this->esc($r['source']) . '</td>'
                . '<td><code class="itxeb-code-inline">' . $this->esc($r['deduplication_key']) . '</code></td></tr>';
        }
        return $html . '</tbody></table></div></div>';
    }

    private function renderFailureRows(array $rows): string
    {
        $html = '<div class="itxeb-dashboard-block"><h3>Dernières erreurs</h3>';
        if (count($rows) === 0) {
            return $html . '<p><em>Aucune erreur récente dans l’outbox.</em></p></div>';
        }
        $html .= '<div class="itxeb-table-wrapper"><table class="std itxeb-events itxeb-failure-table"><thead><tr><th>ID</th><th>Statut</th><th>Événement</th><th>Retry</th><th>Erreur</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr><td class="itxeb-nowrap">#' . $this->esc((string) ($r['id'] ?? '')) . '</td>'
                . '<td><span class="itxeb-badge ' . $this->statusBadgeClass((string) ($r['status'] ?? '')) . '">' . $this->esc((string) ($r['status'] ?? '')) . '</span></td>'
                . '<td>' . $this->esc((string) ($r['event_type'] ?? '')) . '<br><span class="itxeb-date">' . $this->esc((string) ($r['obj_type'] ?? '')) . '</span></td>'
                . '<td>' . $this->esc((string) ($r['retry_count'] ?? '0')) . ' / ' . $this->esc((string) ($r['max_retry'] ?? $this->config->getMaxRetry())) . '</td>'
                . '<td class="itxeb-error">' . $this->esc((string) ($r['last_error'] ?? '')) . '</td></tr>';
        }
        return $html . '</tbody></table></div></div>';
    }

    private function saveConfig(): void
    {
        $this->config->setCronEnabled($this->postString('cron_enabled') === '1');
        foreach (['TraxEndpoint'=>'trax_endpoint','TraxUsername'=>'trax_username','XapiVersion'=>'xapi_version','IliasBaseUrl'=>'ilias_base_url'] as $m=>$k) { $this->config->{'set'.$m}($this->postString($k)); }
        $this->config->setHttpTimeout((int)$this->postString('http_timeout'));
        $this->config->setBatchSize((int)$this->postString('batch_size'));
        $this->config->setMaxRetry((int)$this->postString('max_retry'));
        if ($this->postString('trax_password') !== '') { $this->config->setTraxPassword($this->postString('trax_password')); }
        $this->success('Configuration enregistrée.'); $this->ctrl->redirect($this, 'configure');
    }

    private function testTraxConnection(): void
    {
        $r = (new ilIliasTraxEventBridgeTraxClient($this->config))->testConnection();
        $this->config->setLastTraxTestResult($r->isSuccess(), $r->getHttpStatus(), $r->getShortMessage());
        if ($r->isSuccess()) { $this->success('Connexion TRAX réussie : ' . $r->getShortMessage()); }
        elseif (class_exists('ilUtil') && method_exists('ilUtil', 'sendFailure')) { ilUtil::sendFailure('Connexion TRAX échouée : ' . $r->getShortMessage(), true); }
        $this->ctrl->redirect($this, 'configure');
    }

    private function sendGenerated(): void
    {
        $r = (new ilIliasTraxEventBridgeOutboxSender($this->config, $this->outbox))->sendBatch();
        $this->config->setLastTraxSendResult((bool)$r['success'], (int)$r['http_status'], (string)$r['message']);
        if ($r['success']) { $this->success((string)$r['message']); }
        elseif (class_exists('ilUtil') && method_exists('ilUtil', 'sendFailure')) { ilUtil::sendFailure((string)$r['message'], true); }
        $this->ctrl->redirect($this, 'configure');
    }

    private function resetFailed(): void { $this->success($this->outbox->resetFailedToGenerated() . ' statement(s) failed réinitialisé(s).'); $this->ctrl->redirect($this, 'configure'); }

    private function renderOutbox(): string
    {
        $rows = $this->outbox->findRecent(50);
        $html = '<section class="itxeb-section"><h2>Outbox xAPI locale</h2>';
        if (count($rows) === 0) { return $html . '<p><em>Aucun statement xAPI généré pour le moment.</em></p></section>'; }
        $html .= '<div class="itxeb-table-wrapper"><table class="std itxeb-events itxeb-outbox-table"><thead><tr><th>ID / date</th><th>Statut</th><th>Retry</th><th>Verb</th><th>Objet</th><th>Erreur / statement</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $err = trim((string)($r['last_error'] ?? ''));
            $html .= '<tr><td class="itxeb-nowrap">#'.$this->esc((string)($r['id'] ?? '')).'<br><span class="itxeb-date">'.$this->esc((string)($r['created_at'] ?? '')).'</span><br><span class="itxeb-date">last try: '.$this->esc((string)($r['last_attempt_at'] ?? '')).'</span></td>'
                . '<td><span class="itxeb-badge '.$this->statusBadgeClass((string)($r['status'] ?? '')).'">'.$this->esc((string)($r['status'] ?? '')).'</span></td>'
                . '<td class="itxeb-nowrap">'.$this->esc((string)($r['retry_count'] ?? '0')).' / '.$this->esc((string)($r['max_retry'] ?? $this->config->getMaxRetry())).'</td>'
                . '<td class="itxeb-verb-cell"><div class="itxeb-verb">'.$this->esc((string)($r['verb_id'] ?? '')).'</div></td>'
                . '<td class="itxeb-object">user '.$this->esc((string)($r['user_id'] ?? '')).'<br>ref '.$this->esc((string)($r['ref_id'] ?? '')).'<br>obj '.$this->esc((string)($r['obj_id'] ?? '')).'<br>'.$this->esc((string)($r['obj_type'] ?? '')).'</td>'
                . '<td class="itxeb-wide">'.($err !== '' ? '<div class="itxeb-error">'.$this->esc($err).'</div>' : '').'<details><summary>Statement</summary><pre>'.$this->esc($this->formatPayload((string)($r['statement_json'] ?? ''))).'</pre></details></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    private function renderRecentEvents(): string
    {
        $rows = $this->repo->findRecent(100);
        $html = '<section class="itxeb-section"><h2>Derniers événements ILIAS reçus</h2>';
        if (count($rows) === 0) { return $html . '<p><em>Aucun événement journalisé pour le moment.</em></p></section>'; }
        $html .= '<div class="itxeb-table-wrapper"><table class="std itxeb-events itxeb-log-table"><thead><tr><th>ID / date</th><th>Événement</th><th>Objet</th><th>URI</th><th>Payload</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr><td class="itxeb-nowrap">#'.$this->esc((string)($r['id'] ?? '')).'<br><span class="itxeb-date">'.$this->esc((string)($r['created_at'] ?? '')).'</span></td>'
                . '<td><div class="itxeb-text-block">'.$this->esc((string)($r['component'] ?? '')).'<br><strong>'.$this->esc((string)($r['event_name'] ?? '')).'</strong></div></td>'
                . '<td class="itxeb-object">user '.$this->esc((string)($r['user_id'] ?? '')).'<br>ref '.$this->esc((string)($r['ref_id'] ?? '')).'<br>obj '.$this->esc((string)($r['obj_id'] ?? '')).'<br>'.$this->esc((string)($r['obj_type'] ?? '')).'</td>'
                . '<td class="itxeb-uri-cell"><div class="itxeb-uri itxeb-request-uri">'.$this->esc((string)($r['request_uri'] ?? '')).'</div></td>'
                . '<td class="itxeb-wide"><details><summary>Payload</summary><pre>'.$this->esc($this->formatPayload((string)($r['payload_json'] ?? ''))).'</pre></details></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    private function inputRow(string $l, string $n, string $v, string $h): string { return '<tr><td><label for="'.$this->esc($n).'">'.$this->esc($l).'</label></td><td><input id="'.$this->esc($n).'" name="'.$this->esc($n).'" type="text" value="'.$this->esc($v).'" class="form-control itxeb-input"><div class="itxeb-help">'.$this->esc($h).'</div></td></tr>'; }
    private function passwordRow(string $l, string $n, string $h): string { return '<tr><td><label for="'.$this->esc($n).'">'.$this->esc($l).'</label></td><td><input id="'.$this->esc($n).'" name="'.$this->esc($n).'" type="password" value="" class="form-control itxeb-input"><div class="itxeb-help">'.$this->esc($h).'</div></td></tr>'; }
    private function checkboxRow(string $l, string $n, bool $c, string $h): string { return '<tr><td><label for="'.$this->esc($n).'">'.$this->esc($l).'</label></td><td><label class="itxeb-check"><input id="'.$this->esc($n).'" name="'.$this->esc($n).'" type="checkbox" value="1"'.($c ? ' checked="checked"' : '').'> activé</label><div class="itxeb-help">'.$this->esc($h).'</div></td></tr>'; }
    private function statusBadgeClass(string $s): string { return $s === 'sent' ? 'itxeb-badge-ok' : ($s === 'failed' ? 'itxeb-badge-error' : ($s === 'sending' ? 'itxeb-badge-warn' : 'itxeb-badge-muted')); }
    private function formatPayload(string $j): string { $d = json_decode($j, true); if (is_array($d)) { $p = json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); if (is_string($p)) { return $p; } } return $j; }
    private function postString(string $k): string { return isset($_POST[$k]) && is_scalar($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
    private function esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    private function success(string $m): void { if (class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) { ilUtil::sendSuccess($m, true); } }
    private function setContent(string $html): void { if (is_object($this->tpl) && method_exists($this->tpl, 'setContent')) { $this->tpl->setContent($html); } }

    private function countValue(array &$counts, string $value): void
    {
        $value = trim($value);
        if ($value === '') { return; }
        if (!isset($counts[$value])) { $counts[$value] = 0; }
        $counts[$value]++;
    }

    private function statementExtensions(string $statementJson): array
    {
        $decoded = json_decode($statementJson, true);
        if (!is_array($decoded)) { return []; }
        $context = $decoded['context'] ?? [];
        if (!is_array($context)) { return []; }
        $extensions = $context['extensions'] ?? [];
        return is_array($extensions) ? $extensions : [];
    }

    private function extensionValue(array $extensions, string $name): string
    {
        $suffix = '/xapi/extensions/' . $name;
        foreach ($extensions as $key => $value) {
            if (!is_string($key)) { continue; }
            if (substr($key, -strlen($suffix)) !== $suffix) { continue; }
            if (is_scalar($value)) { return (string) $value; }
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return is_string($encoded) ? $encoded : '';
        }
        return '';
    }

    private function styles(): string
    {
        return '<style>'
            . '.itxeb-page{max-width:none;width:100%;margin:0 0 4rem 0}.itxeb-section{margin-bottom:1.5rem}.itxeb-alert{max-width:980px;padding:.65rem .8rem;margin:.4rem 0 .9rem;border:1px solid #bce8f1;background:#eef8fc;border-radius:4px;line-height:1.4}'
            . '.itxeb-page table.std{width:100%;border-collapse:collapse;background:#fff}.itxeb-page table.std th,.itxeb-page table.std td{padding:.6rem .75rem;vertical-align:top;line-height:1.35}.itxeb-page table.std th{white-space:nowrap;background:#f7f7f7}'
            . '.itxeb-state-table,.itxeb-form-table{max-width:980px}.itxeb-state-table td:first-child,.itxeb-form-table td:first-child{width:230px;min-width:230px;font-weight:600;white-space:nowrap}.itxeb-input{width:100%;max-width:780px;box-sizing:border-box}.itxeb-help,.itxeb-date{font-size:.9em;color:#666}.itxeb-code-inline{display:inline-block;max-width:100%;overflow:auto;white-space:nowrap}.itxeb-actions{margin:.8rem 0 1.2rem}'
            . '.itxeb-summary{display:flex;flex-wrap:wrap;gap:.5rem;margin:.5rem 0 1rem}.itxeb-summary span{background:#f5f5f5;border:1px solid #ddd;padding:.35rem .55rem;border-radius:4px}.itxeb-ops-summary span{background:#eef8fc;border-color:#bce8f1}.itxeb-dashboard-block{margin:.8rem 0 1.1rem}.itxeb-dashboard-block h3{margin:.4rem 0}.itxeb-ops-block{max-width:1200px;padding:.65rem .8rem;border:1px solid #bce8f1;background:#fbfeff;border-radius:4px}.itxeb-table-wrapper{width:100%;max-width:100%;overflow-x:auto;border:1px solid #ddd;border-radius:4px;background:#fff;margin:.4rem 0 1rem}.itxeb-events{width:100%;table-layout:fixed}.itxeb-events th,.itxeb-events td{border-bottom:1px solid #eee}'
            . '.itxeb-outbox-table{min-width:1500px}.itxeb-outbox-table th:nth-child(1),.itxeb-outbox-table td:nth-child(1){width:170px}.itxeb-outbox-table th:nth-child(2),.itxeb-outbox-table td:nth-child(2){width:110px}.itxeb-outbox-table th:nth-child(3),.itxeb-outbox-table td:nth-child(3){width:90px}.itxeb-outbox-table th:nth-child(4),.itxeb-outbox-table td:nth-child(4){width:430px}.itxeb-outbox-table th:nth-child(5),.itxeb-outbox-table td:nth-child(5){width:145px}'
            . '.itxeb-log-table{min-width:1550px}.itxeb-log-table th:nth-child(1),.itxeb-log-table td:nth-child(1){width:165px}.itxeb-log-table th:nth-child(2),.itxeb-log-table td:nth-child(2){width:290px}.itxeb-log-table th:nth-child(3),.itxeb-log-table td:nth-child(3){width:145px}.itxeb-log-table th:nth-child(4),.itxeb-log-table td:nth-child(4){width:560px}.itxeb-log-table th:nth-child(5),.itxeb-log-table td:nth-child(5){width:390px}'
            . '.itxeb-diagnostic-table{min-width:1300px}.itxeb-diagnostic-table th:nth-child(1),.itxeb-diagnostic-table td:nth-child(1){width:90px}.itxeb-diagnostic-table th:nth-child(2),.itxeb-diagnostic-table td:nth-child(2){width:110px}.itxeb-diagnostic-table th:nth-child(3),.itxeb-diagnostic-table td:nth-child(3){width:230px}.itxeb-diagnostic-table th:nth-child(4),.itxeb-diagnostic-table td:nth-child(4){width:260px}.itxeb-diagnostic-table th:nth-child(5),.itxeb-diagnostic-table td:nth-child(5){width:170px}.itxeb-failure-table{min-width:1050px}'
            . '.itxeb-nowrap,.itxeb-object{white-space:nowrap}.itxeb-wide{min-width:360px}.itxeb-verb,.itxeb-uri{max-width:100%;white-space:normal;word-break:break-word;overflow-wrap:anywhere;font-family:monospace;font-size:.92em;line-height:1.35;background:#f8f8f8;border:1px solid #eee;border-radius:3px;padding:.25rem .35rem}.itxeb-request-uri{max-height:9em;overflow:auto}.itxeb-text-block{max-width:100%;white-space:normal;word-break:break-word;overflow-wrap:anywhere}.itxeb-badge{display:inline-block;padding:.2rem .45rem;border-radius:3px;background:#eee;font-weight:600}.itxeb-badge-ok{background:#dff0d8}.itxeb-badge-warn{background:#fcf8e3}.itxeb-badge-error{background:#f2dede}.itxeb-badge-muted{background:#eee}.itxeb-error{color:#a94442;margin-bottom:.35rem;word-break:break-word;overflow-wrap:anywhere}.itxeb-events pre{max-height:320px;overflow:auto;white-space:pre-wrap;word-break:break-word;overflow-wrap:anywhere;margin:.4rem 0 0}.itxeb-events details summary{cursor:pointer;font-weight:600}'
            . '@media (max-width:900px){.itxeb-state-table td:first-child,.itxeb-form-table td:first-child{width:auto;min-width:0;white-space:normal}.itxeb-outbox-table{min-width:1250px}.itxeb-log-table{min-width:1250px}.itxeb-diagnostic-table{min-width:1100px}}</style>';
    }
}
