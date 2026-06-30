<?php
/**
 * @ilCtrl_IsCalledBy ilIliasTraxEventBridgeConfigGUI: ilObjComponentSettingsGUI
 */
require_once __DIR__ . '/class.ilIliasTraxEventBridgeConfig.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeEventDebugRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeOutboxRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeDenyLogRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeTraxClient.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeLrsReadClient.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeOutboxSender.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseTrackingGUI.php';

class ilIliasTraxEventBridgeConfigGUI extends ilPluginConfigGUI
{
    private $ctrl;
    private $tpl;
    private $config;
    private $repo;
    private $outbox;
    private $denyLog;

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
            case 'testLrsRead': $this->testLrsRead(); break;
            case 'testLrsWrite': $this->testLrsWrite(); break;
            case 'sendGenerated': $this->sendGenerated(); break;
            case 'resetFailed': $this->resetFailed(); break;
            case 'configureCourseTracking': $this->handleCourseTracking('show'); break;
            case 'saveCourseTracking': $this->handleCourseTracking('save'); break;
            case 'enableAllCourseTracking': $this->handleCourseTracking('enableAll'); break;
            case 'disableAllCourseTracking': $this->handleCourseTracking('disableAll'); break;
            case 'resetCourseTracking': $this->handleCourseTracking('resetCourse'); break;
            case 'clearLog': $this->repo->clear(); $this->success('Journal vidé.'); $this->ctrl->redirect($this, 'configure'); break;
            case 'clearOutbox': $this->outbox->clear(); $this->success('Outbox vidée.'); $this->ctrl->redirect($this, 'configure'); break;
            case 'clearDenyLog': $this->success($this->denyLog->clear() . ' refus supprimé(s) du journal de diagnostic.'); $this->ctrl->redirect($this, 'configure'); break;
            case 'configure': default: $this->configure(); break;
        }
    }

    private function init(): void
    {
        $this->config = new ilIliasTraxEventBridgeConfig();
        $this->repo = new ilIliasTraxEventBridgeEventDebugRepository();
        $this->outbox = new ilIliasTraxEventBridgeOutboxRepository();
        $this->denyLog = new ilIliasTraxEventBridgeDenyLogRepository();
        $this->outbox->resetStuckSending();
    }

    private function configure(): void
    {
        $html = $this->styles() . '<div id="itxeb-config-page"><h1>IliasTraxEventBridge — V0.11 diagnostic exploitation</h1>'
            . '<p><strong>V0.11 :</strong> durcissement exploitation, diagnostic santé, rollback et contrôles d’installation. La source pédagogique du suivi xAPI reste TRAX/LRS ; l’outbox locale reste une file technique.</p>'
            . $this->renderHealthCheck()
            . $this->renderState()
            . $this->renderCourseTrackingAccess()
            . $this->renderConfigForm()
            . $this->renderSendActions()
            . $this->renderAdminDashboard()
            . $this->renderDenyLogSupervision()
            . $this->renderOutbox()
            . $this->renderRecentEvents()
            . '</div>';
        $this->setContent($html);
    }

    private function renderHealthCheck(): string
    {
        $root = dirname(__DIR__);
        $companion = $this->companionPath($root);
        $dbupdate = $root . '/sql/dbupdate.php';
        $script = $root . '/scripts/diagnostic_itxeb.sh';
        $version = $this->pluginVersion($root . '/plugin.php');
        $dbupdateFirstLine = $this->firstLine($dbupdate);
        $endpoint = trim($this->config->getStatementsEndpoint());
        $outboxFailed = $this->outbox->countByStatus('failed');
        $retryExhausted = $this->outbox->countRetryExhausted($this->config->getMaxRetry());

        $rows = '';
        $rows .= $this->healthRow('Version plugin', $version !== '', $version !== '' ? $version : 'version non détectée', $version !== '' ? 'ok' : 'warn');
        $rows .= $this->healthRow('dbupdate.php', $dbupdateFirstLine === '<#1>', $dbupdateFirstLine === '<#1>' ? 'marqueur <#1> présent' : 'marqueur <#1> absent ou fichier illisible', $dbupdateFirstLine === '<#1>' ? 'ok' : 'error');
        $rows .= $this->healthRow('Plugin compagnon UIHook', is_dir($companion), is_dir($companion) ? $companion : 'dossier compagnon non trouvé', is_dir($companion) ? 'ok' : 'warn');
        $rows .= $this->healthRow('Script diagnostic serveur', is_file($script), is_file($script) ? 'scripts/diagnostic_itxeb.sh présent' : 'script absent', is_file($script) ? 'ok' : 'warn');
        $rows .= $this->healthRow('Endpoint TRAX/LRS', $endpoint !== '', $endpoint !== '' ? $endpoint : 'endpoint non configuré', $endpoint !== '' ? 'ok' : 'warn');
        $rows .= $this->healthRow('Cron plugin', $this->config->isCronEnabled(), $this->config->isCronEnabled() ? 'activé côté plugin' : 'désactivé côté plugin', $this->config->isCronEnabled() ? 'ok' : 'warn');
        $rows .= $this->healthRow('Diagnostic refus', !$this->config->isDenyLogEnabled(), $this->config->isDenyLogEnabled() ? 'activé : à réserver à une analyse ciblée' : 'désactivé : état recommandé en exploitation courante', $this->config->isDenyLogEnabled() ? 'warn' : 'ok');
        $rows .= $this->healthRow('Outbox failed', $outboxFailed === 0, (string)$outboxFailed . ' ligne(s) failed', $outboxFailed === 0 ? 'ok' : 'warn');
        $rows .= $this->healthRow('Retry épuisé', $retryExhausted === 0, (string)$retryExhausted . ' ligne(s) avec retry épuisé', $retryExhausted === 0 ? 'ok' : 'warn');

        foreach (['evnt_evhk_itxeb_log', 'evnt_evhk_itxeb_out', 'evnt_evhk_itxeb_read', 'evnt_evhk_itxeb_ccfg', 'evnt_evhk_itxeb_rcfg', 'evnt_evhk_itxeb_dlog'] as $table) {
            $exists = $this->tableExists($table);
            $rows .= $this->healthRow('Table ' . $table, $exists, $exists ? 'présente' : 'absente ou non vérifiable', $exists ? 'ok' : 'warn');
        }

        return '<section class="itxeb-section itxeb-health"><h2>Santé / Diagnostic V0.11</h2>'
            . '<p>Cette section réalise des contrôles non destructifs : elle ne modifie ni la configuration, ni l’outbox, ni les tables SQL.</p>'
            . '<table class="std itxeb-state-table"><thead><tr><th>Contrôle</th><th>État</th><th>Détail</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>'
            . '<p><strong>Diagnostic serveur :</strong> pour un contrôle plus complet côté AlmaLinux, lancer <code>bash scripts/diagnostic_itxeb.sh</code> depuis le dossier du plugin.</p>'
            . '<p><strong>Tests applicatifs :</strong> utiliser les boutons <code>Tester connexion TRAX</code>, <code>Tester lecture TRAX/LRS</code> et <code>Créer un statement test TRAX/LRS</code> dans la section configuration.</p>'
            . '<p><strong>Documentation :</strong> consulter <code>docs/DIAGNOSTIC.md</code> et <code>docs/ROLLBACK.md</code>.</p>'
            . '</section>';
    }

    private function renderState(): string
    {
        $html = '<section class="itxeb-section"><h2>État</h2><table class="std itxeb-state-table">';
        foreach (['Plugin actif'=>$this->config->isEnabled()?'oui':'non','Mode debug'=>$this->config->isDebugEnabled()?'oui':'non','Génération xAPI locale'=>$this->config->isLocalXapiGenerationEnabled()?'oui':'non','Cron plugin'=>$this->config->isCronEnabled()?'activé':'désactivé','Diagnostic traces refusées'=>$this->config->isDenyLogEnabled()?'activé':'désactivé'] as $k=>$v) {
            $html .= '<tr><td>'.$this->esc($k).'</td><td><strong>'.$this->esc($v).'</strong></td></tr>';
        }
        $html .= '<tr><td>Endpoint statements</td><td><code>'.$this->esc($this->config->getStatementsEndpoint()).'</code></td></tr></table>';
        $html .= '<h3>Diagnostics TRAX / cron</h3><table class="std itxeb-state-table">'
            . $this->diagRow('Dernier test connexion', $this->config->getLastTraxTestAt(), $this->config->getLastTraxTestSuccess(), $this->config->getLastTraxTestHttpStatus(), $this->config->getLastTraxTestMessage())
            . $this->diagRow('Dernier envoi manuel', $this->config->getLastTraxSendAt(), $this->config->getLastTraxSendSuccess(), $this->config->getLastTraxSendHttpStatus(), $this->config->getLastTraxSendMessage())
            . $this->diagRow('Dernier cron', $this->config->getLastCronAt(), $this->config->getLastCronSuccess(), $this->config->getLastCronHttpStatus(), $this->config->getLastCronMessage())
            . '</table><p><a class="btn btn-default" href="'.$this->esc($this->ctrl->getLinkTarget($this, 'clearLog')).'">Vider le journal debug</a> '
            . '<a class="btn btn-default" href="'.$this->esc($this->ctrl->getLinkTarget($this, 'clearOutbox')).'">Vider l’outbox xAPI locale</a></p></section>';
        return $html;
    }

    private function diagRow(string $label, string $at, string $success, string $http, string $message): string
    {
        if ($at === '') { return '<tr><td>'.$this->esc($label).'</td><td><em>Aucun diagnostic disponible.</em></td></tr>'; }
        return '<tr><td>'.$this->esc($label).'</td><td><strong>date :</strong> '.$this->esc($at).'<br><strong>succès :</strong> '.$this->esc($success).'<br><strong>HTTP :</strong> '.$this->esc($http).'<br><strong>message :</strong> '.$this->esc($message).'</td></tr>';
    }

    private function renderCourseTrackingAccess(): string
    {
        $action = $this->ctrl->getLinkTarget($this, 'configureCourseTracking');
        return '<section class="itxeb-section"><h2>Configuration xAPI par cours</h2>'
            . '<p>En V0.10.1 et V0.11, l’accès fonctionnel attendu est <code>Cours &gt; Suivi xAPI</code>. Cette section admin reste disponible pour ouvrir directement un cours par <code>ref_id</code>.</p>'
            . '<form method="post" action="'.$this->esc($action).'"><table class="std itxeb-form-table"><tbody>'
            . '<tr><td><label for="itxeb_course_ref_id">course_ref_id</label></td><td><input id="itxeb_course_ref_id" name="course_ref_id" type="number" min="1" value="" class="form-control"><div class="small">Exemple : le <code>ref_id</code> visible dans l’URL du cours ILIAS.</div></td></tr>'
            . '</tbody></table><p><button class="btn btn-primary" type="submit">Ouvrir la configuration xAPI du cours</button></p></form></section>';
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
        $html = '<section class="itxeb-section"><h2>Configuration TRAX / cron</h2><p><strong>Important :</strong> cette section pilote les paramètres techniques. Le diagnostic des traces refusées doit rester désactivé en exploitation courante et être activé uniquement pendant une analyse ciblée.</p>';
        $html .= '<form method="post" action="'.$this->esc($this->ctrl->getLinkTarget($this, 'saveConfig')).'"><table class="std itxeb-form-table">';
        $html .= $this->checkboxRow('Activer le cron plugin', 'cron_enabled', $this->config->isCronEnabled(), 'Autorise le job cron du plugin. Le job cron ILIAS global doit aussi être activé dans les tâches cron.');
        $html .= $this->checkboxRow('Activer le diagnostic des traces refusées', 'deny_log_enabled', $this->config->isDenyLogEnabled(), 'À activer uniquement à la demande. Si activé sur une plateforme volumineuse, la table evnt_evhk_itxeb_dlog peut grossir rapidement.');
        foreach ([['Endpoint xAPI TRAX','trax_endpoint',$this->config->getTraxEndpoint(),'Endpoint xAPI racine ou URL complète /statements.'],['Identifiant client TRAX','trax_username',$this->config->getTraxUsername(),'Client xAPI autorisé à écrire.'],['Version xAPI','xapi_version',$this->config->getXapiVersion(),'Recommandé : 1.0.3.'],['Timeout HTTP','http_timeout',(string)$this->config->getHttpTimeout(),'Entre 2 et 120 secondes.'],['Taille batch','batch_size',(string)$this->config->getBatchSize(),'Entre 1 et 100 statements.'],['Max retry','max_retry',(string)$this->config->getMaxRetry(),'Nombre maximum de tentatives par statement.'],['Base URL ILIAS forcée','ilias_base_url',$this->config->getIliasBaseUrl(),'Optionnel. Utilisé pour les IRIs xAPI.']] as $r) { $html .= $this->inputRow($r[0], $r[1], $r[2], $r[3]); }
        $html .= $this->passwordRow('Secret client TRAX', 'trax_password', 'Laisser vide pour conserver le secret.');
        return $html . '</table><p><button class="btn btn-primary" type="submit">Enregistrer</button></p></form>'
            . '<form method="post" action="'.$this->esc($this->ctrl->getLinkTarget($this, 'testTraxConnection')).'"><p><button class="btn btn-default" type="submit">Tester connexion TRAX</button></p></form>'
            . '<form method="post" action="'.$this->esc($this->ctrl->getLinkTarget($this, 'testLrsRead')).'"><p><button class="btn btn-default" type="submit">Tester lecture TRAX/LRS</button> <span class="small">Effectue uniquement un <code>GET /statements?limit=1</code>, sans créer de trace.</span></p></form>'
            . '<form method="post" action="'.$this->esc($this->ctrl->getLinkTarget($this, 'testLrsWrite')).'"><p><button class="btn btn-warning" type="submit">Créer un statement test TRAX/LRS</button> <span class="small"><strong>Attention :</strong> crée volontairement un statement xAPI de diagnostic dans TRAX/LRS.</span></p></form></section>';
    }

    private function renderSendActions(): string
    {
        $html = '<section class="itxeb-section"><h2>Envoi vers TRAX</h2><div class="itxeb-summary">';
        foreach (['generated'=>$this->outbox->countByStatus('generated'),'failed'=>$this->outbox->countByStatus('failed'),'retry épuisé'=>$this->outbox->countRetryExhausted($this->config->getMaxRetry()),'taille batch'=>$this->config->getBatchSize(),'max_retry'=>$this->config->getMaxRetry()] as $k=>$v) { $html .= '<span><strong>'.$this->esc((string)$k).' :</strong> '.$this->esc((string)$v).'</span>'; }
        return $html . '</div><p>L’envoi manuel et le cron traitent les statements <code>generated</code> ou <code>failed</code> lorsque <code>retry_count &lt; max_retry</code>.</p><p><a class="btn btn-primary" href="'.$this->esc($this->ctrl->getLinkTarget($this, 'sendGenerated')).'">Envoyer maintenant</a> <a class="btn btn-default" href="'.$this->esc($this->ctrl->getLinkTarget($this, 'resetFailed')).'">Réinitialiser les failed</a></p></section>';
    }

    private function renderAdminDashboard(): string
    {
        $rows = $this->outbox->findRecent(200);
        $html = '<section class="itxeb-section"><h2>Supervision outbox</h2>'.$this->renderOperationsMetrics();
        if (count($rows) === 0) { return $html . '<p><em>Aucune donnée outbox disponible pour la supervision détaillée.</em></p></section>'; }
        $status = []; $eventTypes = []; $objTypes = []; $families = []; $interactions = []; $sources = []; $recentFailures = []; $recentDiagnostics = [];
        foreach ($rows as $r) {
            $this->countValue($status, (string)($r['status'] ?? ''));
            $this->countValue($eventTypes, (string)($r['event_type'] ?? ''));
            $this->countValue($objTypes, (string)($r['obj_type'] ?? ''));
            $extensions = $this->statementExtensions((string)($r['statement_json'] ?? ''));
            $family = $this->extensionValue($extensions, 'statement_family');
            $interaction = $this->extensionValue($extensions, 'interaction_type');
            $source = $this->extensionValue($extensions, 'source_table');
            $recordSource = $this->extensionValue($extensions, 'event_record_source');
            $deduplicationKey = $this->extensionValue($extensions, 'deduplication_key');
            $this->countValue($families, $family); $this->countValue($interactions, $interaction); $this->countValue($sources, $source !== '' ? $source : $recordSource);
            if ((string)($r['status'] ?? '') === 'failed' || trim((string)($r['last_error'] ?? '')) !== '') { $recentFailures[] = $r; }
            if (count($recentDiagnostics) < 12) { $recentDiagnostics[] = ['id'=>(string)($r['id']??''),'status'=>(string)($r['status']??''),'event_type'=>(string)($r['event_type']??''),'obj_type'=>(string)($r['obj_type']??''),'family'=>$family,'source'=>$source!==''?$source:$recordSource,'deduplication_key'=>$deduplicationKey]; }
        }
        $html .= '<p>Vue synthétique calculée sur les 200 dernières lignes outbox.</p>'
            . $this->renderCounterBlock('Statuts', $status)
            . $this->renderCounterBlock('Types d’événements SQL', $eventTypes)
            . $this->renderCounterBlock('Types objets ILIAS', $objTypes)
            . $this->renderCounterBlock('Familles xAPI', $families)
            . $this->renderCounterBlock('Types d’interaction', $interactions)
            . $this->renderCounterBlock('Sources techniques', $sources)
            . $this->renderDiagnosticRows($recentDiagnostics)
            . $this->renderFailureRows(array_slice($recentFailures, 0, 8));
        return $html . '</section>';
    }

    private function renderOperationsMetrics(): string
    {
        $now = time(); $since24h = $now - 86400; $since7d = $now - 604800;
        $metrics = ['Total outbox'=>$this->outbox->countAll(),'Créés 24h'=>$this->outbox->countCreatedSince($since24h),'Créés 7j'=>$this->outbox->countCreatedSince($since7d),'Sent total'=>$this->outbox->countByStatus('sent'),'Sent 24h'=>$this->outbox->countByStatusSince('sent', $since24h),'Generated total'=>$this->outbox->countByStatus('generated'),'Failed total'=>$this->outbox->countByStatus('failed'),'Failed 24h'=>$this->outbox->countByStatusSince('failed', $since24h),'Failed/erreurs à inspecter'=>$this->outbox->countFailedWithError(),'Retry épuisé'=>$this->outbox->countRetryExhausted($this->config->getMaxRetry())];
        $html = '<div class="itxeb-dashboard-block"><h3>Exploitation / maintenance</h3><div class="itxeb-summary">';
        foreach ($metrics as $label => $value) { $html .= '<span><strong>'.$this->esc($label).' :</strong> '.$this->esc((string)$value).'</span>'; }
        return $html . '</div></div>';
    }

    private function renderDenyLogSupervision(): string
    {
        $rows = $this->denyLog->findRecent(50); $reasonCounts = []; $sourceCounts = []; $eventTypeCounts = [];
        foreach ($this->denyLog->findRecent(200) as $row) { $this->countValue($reasonCounts, (string)($row['reason'] ?? '')); $this->countValue($sourceCounts, (string)($row['source_table'] ?? '')); $this->countValue($eventTypeCounts, (string)($row['event_type'] ?? '')); }
        $statusText = $this->config->isDenyLogEnabled() ? 'activé' : 'désactivé'; $statusClass = $this->config->isDenyLogEnabled() ? 'itxeb-badge-warn' : 'itxeb-badge-muted';
        $html = '<section class="itxeb-section"><h2>Diagnostic des traces refusées</h2><p>État actuel : <span class="itxeb-badge '.$statusClass.'">'.$this->esc($statusText).'</span>. À laisser désactivé en exploitation courante.</p>'
            . '<p><a class="btn btn-default" href="'.$this->esc($this->ctrl->getLinkTarget($this, 'clearDenyLog')).'">Purger le diagnostic des traces refusées</a></p>'
            . '<div class="itxeb-summary"><span><strong>Total refus :</strong> '.$this->esc((string)$this->denyLog->countAll()).'</span></div>'
            . $this->renderCounterBlock('Motifs de refus', $reasonCounts)
            . $this->renderCounterBlock('Sources des refus', $sourceCounts)
            . $this->renderCounterBlock('Types d’événements refusés', $eventTypeCounts);
        if (count($rows) === 0) { return $html . '<p><em>Aucun refus journalisé pour le moment.</em></p></section>'; }
        $html .= '<div class="table-responsive"><table class="std itxeb-events"><thead><tr><th>ID / date</th><th>Motif</th><th>Événement</th><th>Contexte</th><th>Source</th><th>Payload</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $reason = (string)($r['reason'] ?? '');
            $html .= '<tr><td>#'.$this->esc((string)($r['id'] ?? '')).'<br><small>'.$this->esc((string)($r['created_at'] ?? '')).'</small></td><td><span class="itxeb-badge '.$this->reasonBadgeClass($reason).'">'.$this->esc($reason).'</span></td><td>'.$this->esc((string)($r['event_type'] ?? '')).'<br><small>'.$this->esc((string)($r['component'] ?? '')).'<br>'.$this->esc((string)($r['event_name'] ?? '')).'</small></td><td>user '.$this->esc((string)($r['user_id'] ?? '')).'<br>course '.$this->esc((string)($r['course_ref_id'] ?? '')).'<br>ref '.$this->esc((string)($r['ref_id'] ?? '')).'<br>obj '.$this->esc((string)($r['obj_id'] ?? '')).'<br>'.$this->esc((string)($r['obj_type'] ?? '')).'</td><td>'.$this->esc((string)($r['source_table'] ?? '')).'<br>#'.$this->esc((string)($r['source_id'] ?? '')).'</td><td><details><summary>Payload</summary><pre>'.$this->esc($this->formatPayload((string)($r['payload_json'] ?? ''))).'</pre></details></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    private function renderCounterBlock(string $title, array $counts): string
    {
        arsort($counts); $html = '<div class="itxeb-dashboard-block"><h3>'.$this->esc($title).'</h3><div class="itxeb-summary">';
        if (count($counts) === 0) { return $html . '<span><em>Aucune donnée.</em></span></div></div>'; }
        foreach ($counts as $label => $count) { $html .= '<span><strong>'.$this->esc((string)$label).' :</strong> '.$this->esc((string)$count).'</span>'; }
        return $html . '</div></div>';
    }

    private function renderDiagnosticRows(array $rows): string
    {
        $html = '<div class="itxeb-dashboard-block"><h3>Dernières clés de diagnostic</h3>';
        if (count($rows) === 0) { return $html . '<p><em>Aucune clé de diagnostic disponible.</em></p></div>'; }
        $html .= '<div class="table-responsive"><table class="std itxeb-events"><thead><tr><th>ID</th><th>Statut</th><th>Événement</th><th>Famille</th><th>Source</th><th>Déduplication</th></tr></thead><tbody>';
        foreach ($rows as $r) { $html .= '<tr><td>#'.$this->esc($r['id']).'</td><td><span class="itxeb-badge '.$this->statusBadgeClass($r['status']).'">'.$this->esc($r['status']).'</span></td><td>'.$this->esc($r['event_type']).'<br><small>'.$this->esc($r['obj_type']).'</small></td><td>'.$this->esc($r['family']).'</td><td>'.$this->esc($r['source']).'</td><td><code>'.$this->esc($r['deduplication_key']).'</code></td></tr>'; }
        return $html . '</tbody></table></div></div>';
    }

    private function renderFailureRows(array $rows): string
    {
        $html = '<div class="itxeb-dashboard-block"><h3>Dernières erreurs</h3>';
        if (count($rows) === 0) { return $html . '<p><em>Aucune erreur récente dans l’outbox.</em></p></div>'; }
        $html .= '<div class="table-responsive"><table class="std itxeb-events"><thead><tr><th>ID</th><th>Statut</th><th>Événement</th><th>Retry</th><th>Erreur</th></tr></thead><tbody>';
        foreach ($rows as $r) { $html .= '<tr><td>#'.$this->esc((string)($r['id'] ?? '')).'</td><td><span class="itxeb-badge '.$this->statusBadgeClass((string)($r['status'] ?? '')).'">'.$this->esc((string)($r['status'] ?? '')).'</span></td><td>'.$this->esc((string)($r['event_type'] ?? '')).'<br><small>'.$this->esc((string)($r['obj_type'] ?? '')).'</small></td><td>'.$this->esc((string)($r['retry_count'] ?? '0')).' / '.$this->esc((string)($r['max_retry'] ?? $this->config->getMaxRetry())).'</td><td>'.$this->esc((string)($r['last_error'] ?? '')).'</td></tr>'; }
        return $html . '</tbody></table></div></div>';
    }

    private function saveConfig(): void
    {
        $this->config->setCronEnabled($this->postString('cron_enabled') === '1');
        $this->config->setDenyLogEnabled($this->postString('deny_log_enabled') === '1');
        foreach (['TraxEndpoint'=>'trax_endpoint','TraxUsername'=>'trax_username','XapiVersion'=>'xapi_version','IliasBaseUrl'=>'ilias_base_url'] as $m=>$k) { $this->config->{'set'.$m}($this->postString($k)); }
        $this->config->setHttpTimeout((int)$this->postString('http_timeout')); $this->config->setBatchSize((int)$this->postString('batch_size')); $this->config->setMaxRetry((int)$this->postString('max_retry'));
        if ($this->postString('trax_password') !== '') { $this->config->setTraxPassword($this->postString('trax_password')); }
        $this->success('Configuration enregistrée.'); $this->ctrl->redirect($this, 'configure');
    }

    private function testTraxConnection(): void
    {
        $r = (new ilIliasTraxEventBridgeTraxClient($this->config))->testConnection();
        $this->config->setLastTraxTestResult($r->isSuccess(), $r->getHttpStatus(), $r->getShortMessage());
        if ($r->isSuccess()) { $this->success('Connexion TRAX réussie : '.$r->getShortMessage()); }
        else { $this->failure('Connexion TRAX échouée : '.$r->getShortMessage()); }
        $this->ctrl->redirect($this, 'configure');
    }

    private function testLrsRead(): void
    {
        $r = (new ilIliasTraxEventBridgeLrsReadClient($this->config))->queryStatements(['limit' => 1]);
        if (!$r->isSuccess()) {
            $this->failure('Lecture TRAX/LRS échouée : '.$r->getShortMessage());
            $this->ctrl->redirect($this, 'configure');
            return;
        }

        $count = 0;
        $decoded = json_decode($r->getBody(), true);
        if (is_array($decoded) && isset($decoded['statements']) && is_array($decoded['statements'])) {
            $count = count($decoded['statements']);
        }

        $this->success('Lecture TRAX/LRS réussie : HTTP '.$r->getHttpStatus().' ; '.$count.' statement(s) retourné(s) avec limit=1.');
        $this->ctrl->redirect($this, 'configure');
    }

    private function testLrsWrite(): void
    {
        $statement = $this->buildDiagnosticStatement();
        $statementId = is_string($statement['id'] ?? null) ? (string)$statement['id'] : '';
        $r = (new ilIliasTraxEventBridgeTraxClient($this->config))->sendStatements([$statement]);

        if ($r->isSuccess()) {
            $this->success('Statement test TRAX/LRS créé : HTTP '.$r->getHttpStatus().' ; id '.$statementId.'.');
        } else {
            $this->failure('Création du statement test TRAX/LRS échouée : '.$r->getShortMessage());
        }

        $this->ctrl->redirect($this, 'configure');
    }

    /** @return array<string,mixed> */
    private function buildDiagnosticStatement(): array
    {
        $id = $this->uuidV4();
        $baseUrl = trim($this->config->getIliasBaseUrl());
        $homePage = preg_match('~^https?://~i', $baseUrl) ? rtrim($baseUrl, '/') : 'https://example.invalid/itxeb';
        $objectId = $homePage . '/xapi/diagnostic/write-test/' . $id;

        return [
            'id' => $id,
            'actor' => [
                'account' => [
                    'homePage' => $homePage,
                    'name' => 'itxeb-diagnostic'
                ],
                'name' => 'IliasTraxEventBridge diagnostic'
            ],
            'verb' => [
                'id' => 'http://adlnet.gov/expapi/verbs/experienced',
                'display' => [
                    'en-US' => 'experienced',
                    'fr-FR' => 'a testé'
                ]
            ],
            'object' => [
                'id' => $objectId,
                'definition' => [
                    'name' => [
                        'fr-FR' => 'Statement de diagnostic IliasTraxEventBridge',
                        'en-US' => 'IliasTraxEventBridge diagnostic statement'
                    ],
                    'description' => [
                        'fr-FR' => 'Statement créé volontairement par le test d’écriture V0.11 du plugin IliasTraxEventBridge.',
                        'en-US' => 'Statement intentionally created by the V0.11 write diagnostic test of IliasTraxEventBridge.'
                    ],
                    'type' => 'https://w3id.org/xapi/acrossx/activities/diagnostic'
                ],
                'objectType' => 'Activity'
            ],
            'context' => [
                'extensions' => [
                    $homePage . '/xapi/extensions/itxeb_diagnostic' => true,
                    $homePage . '/xapi/extensions/itxeb_version' => '0.11.0',
                    $homePage . '/xapi/extensions/itxeb_test_type' => 'admin_write_diagnostic'
                ]
            ],
            'timestamp' => gmdate('c')
        ];
    }

    private function uuidV4(): string
    {
        try {
            $data = random_bytes(16);
            $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
            $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        } catch (Throwable $e) {
            return str_replace('.', '-', uniqid('itxeb-', true));
        }
    }

    private function sendGenerated(): void
    {
        $r = (new ilIliasTraxEventBridgeOutboxSender($this->config, $this->outbox))->sendBatch();
        $this->config->setLastTraxSendResult((bool)$r['success'], (int)$r['http_status'], (string)$r['message']);
        if ($r['success']) { $this->success((string)$r['message']); }
        else { $this->failure((string)$r['message']); }
        $this->ctrl->redirect($this, 'configure');
    }

    private function resetFailed(): void { $this->success($this->outbox->resetFailedToGenerated() . ' statement(s) failed réinitialisé(s).'); $this->ctrl->redirect($this, 'configure'); }

    private function renderOutbox(): string
    {
        $rows = $this->outbox->findRecent(50); $html = '<section class="itxeb-section"><h2>Outbox xAPI locale</h2>';
        if (count($rows) === 0) { return $html . '<p><em>Aucun statement xAPI généré pour le moment.</em></p></section>'; }
        $html .= '<div class="table-responsive"><table class="std itxeb-events"><thead><tr><th>ID / date</th><th>Statut</th><th>Retry</th><th>Verb</th><th>Objet</th><th>Erreur / statement</th></tr></thead><tbody>';
        foreach ($rows as $r) { $err = trim((string)($r['last_error'] ?? '')); $html .= '<tr><td>#'.$this->esc((string)($r['id'] ?? '')).'<br><small>'.$this->esc((string)($r['created_at'] ?? '')).'</small></td><td><span class="itxeb-badge '.$this->statusBadgeClass((string)($r['status'] ?? '')).'">'.$this->esc((string)($r['status'] ?? '')).'</span></td><td>'.$this->esc((string)($r['retry_count'] ?? '0')).' / '.$this->esc((string)($r['max_retry'] ?? $this->config->getMaxRetry())).'</td><td><code>'.$this->esc((string)($r['verb_id'] ?? '')).'</code></td><td>user '.$this->esc((string)($r['user_id'] ?? '')).'<br>ref '.$this->esc((string)($r['ref_id'] ?? '')).'<br>obj '.$this->esc((string)($r['obj_id'] ?? '')).'<br>'.$this->esc((string)($r['obj_type'] ?? '')).'</td><td>'.($err !== '' ? '<div>'.$this->esc($err).'</div>' : '').'<details><summary>Statement</summary><pre>'.$this->esc($this->formatPayload((string)($r['statement_json'] ?? ''))).'</pre></details></td></tr>'; }
        return $html . '</tbody></table></div></section>';
    }

    private function renderRecentEvents(): string
    {
        $rows = $this->repo->findRecent(100); $html = '<section class="itxeb-section"><h2>Derniers événements ILIAS reçus</h2>';
        if (count($rows) === 0) { return $html . '<p><em>Aucun événement journalisé pour le moment.</em></p></section>'; }
        $html .= '<div class="table-responsive"><table class="std itxeb-events"><thead><tr><th>ID / date</th><th>Événement</th><th>Objet</th><th>URI</th><th>Payload</th></tr></thead><tbody>';
        foreach ($rows as $r) { $html .= '<tr><td>#'.$this->esc((string)($r['id'] ?? '')).'<br><small>'.$this->esc((string)($r['created_at'] ?? '')).'</small></td><td>'.$this->esc((string)($r['component'] ?? '')).'<br><strong>'.$this->esc((string)($r['event_name'] ?? '')).'</strong></td><td>user '.$this->esc((string)($r['user_id'] ?? '')).'<br>ref '.$this->esc((string)($r['ref_id'] ?? '')).'<br>obj '.$this->esc((string)($r['obj_id'] ?? '')).'<br>'.$this->esc((string)($r['obj_type'] ?? '')).'</td><td><code>'.$this->esc((string)($r['request_uri'] ?? '')).'</code></td><td><details><summary>Payload</summary><pre>'.$this->esc($this->formatPayload((string)($r['payload_json'] ?? ''))).'</pre></details></td></tr>'; }
        return $html . '</tbody></table></div></section>';
    }

    private function healthRow(string $label, bool $ok, string $detail, string $level): string
    {
        $class = $level === 'ok' ? 'itxeb-badge-ok' : ($level === 'error' ? 'itxeb-badge-error' : 'itxeb-badge-warn');
        $text = $level === 'ok' ? 'OK' : ($level === 'error' ? 'ERREUR' : 'ATTENTION');
        return '<tr><td>'.$this->esc($label).'</td><td><span class="itxeb-badge '.$class.'">'.$text.'</span></td><td>'.$this->esc($detail).'</td></tr>';
    }

    private function tableExists(string $table): bool
    {
        global $DIC, $ilDB;
        try {
            $db = null;
            if (isset($DIC) && method_exists($DIC, 'database')) { $db = $DIC->database(); }
            elseif (isset($ilDB)) { $db = $ilDB; }
            if (is_object($db) && method_exists($db, 'tableExists')) { return (bool)$db->tableExists($table); }
        } catch (Throwable $e) {
            return false;
        }
        return false;
    }

    private function pluginVersion(string $pluginPhp): string
    {
        if (!is_file($pluginPhp)) { return ''; }
        $content = (string)file_get_contents($pluginPhp);
        if (preg_match('/\$version\s*=\s*[\'\"]([^\'\"]+)[\'\"]\s*;/', $content, $m)) { return $m[1]; }
        return '';
    }

    private function firstLine(string $file): string
    {
        if (!is_file($file)) { return ''; }
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        return is_array($lines) && isset($lines[0]) ? trim((string)$lines[0]) : '';
    }

    private function companionPath(string $pluginRoot): string
    {
        $search = '/Services/EventHandling/EventHook/IliasTraxEventBridge';
        $replace = '/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
        if (substr($pluginRoot, -strlen($search)) === $search) {
            return substr($pluginRoot, 0, -strlen($search)) . $replace;
        }
        return dirname(dirname(dirname($pluginRoot))) . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
    }

    private function inputRow(string $l, string $n, string $v, string $h): string { return '<tr><td><label for="'.$this->esc($n).'">'.$this->esc($l).'</label></td><td><input id="'.$this->esc($n).'" name="'.$this->esc($n).'" type="text" value="'.$this->esc($v).'" class="form-control"><div class="small">'.$this->esc($h).'</div></td></tr>'; }
    private function passwordRow(string $l, string $n, string $h): string { return '<tr><td><label for="'.$this->esc($n).'">'.$this->esc($l).'</label></td><td><input id="'.$this->esc($n).'" name="'.$this->esc($n).'" type="password" value="" class="form-control"><div class="small">'.$this->esc($h).'</div></td></tr>'; }
    private function checkboxRow(string $l, string $n, bool $c, string $h): string { return '<tr><td><label for="'.$this->esc($n).'">'.$this->esc($l).'</label></td><td><label><input id="'.$this->esc($n).'" name="'.$this->esc($n).'" type="checkbox" value="1"'.($c ? ' checked="checked"' : '').'> activé</label><div class="small">'.$this->esc($h).'</div></td></tr>'; }
    private function statusBadgeClass(string $s): string { return $s === 'sent' ? 'itxeb-badge-ok' : ($s === 'failed' ? 'itxeb-badge-error' : ($s === 'sending' ? 'itxeb-badge-warn' : 'itxeb-badge-muted')); }
    private function reasonBadgeClass(string $s): string { return $s === 'resource_disabled' || $s === 'course_disabled' ? 'itxeb-badge-warn' : ($s === 'unsupported_object_type' ? 'itxeb-badge-error' : 'itxeb-badge-muted'); }
    private function formatPayload(string $j): string { $d = json_decode($j, true); if (is_array($d)) { $p = json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); if (is_string($p)) { return $p; } } return $j; }
    private function postString(string $k): string { return isset($_POST[$k]) && is_scalar($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
    private function esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    private function success(string $m): void { if (class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) { ilUtil::sendSuccess($m, true); } }
    private function failure(string $m): void { if (class_exists('ilUtil') && method_exists('ilUtil', 'sendFailure')) { ilUtil::sendFailure($m, true); } }
    private function setContent(string $html): void { if (is_object($this->tpl) && method_exists($this->tpl, 'setContent')) { $this->tpl->setContent($html); } }
    private function countValue(array &$counts, string $value): void { $value = trim($value); if ($value === '') { return; } if (!isset($counts[$value])) { $counts[$value] = 0; } $counts[$value]++; }
    private function statementExtensions(string $statementJson): array { $decoded = json_decode($statementJson, true); if (!is_array($decoded)) { return []; } $context = $decoded['context'] ?? []; if (!is_array($context)) { return []; } $extensions = $context['extensions'] ?? []; return is_array($extensions) ? $extensions : []; }
    private function extensionValue(array $extensions, string $name): string { $suffix = '/xapi/extensions/' . $name; foreach ($extensions as $key => $value) { if (!is_string($key)) { continue; } if (substr($key, -strlen($suffix)) !== $suffix) { continue; } if (is_scalar($value)) { return (string) $value; } $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); return is_string($encoded) ? $encoded : ''; } return ''; }

    private function styles(): string
    {
        return '<style>'
            . '#itxeb-config-page{max-width:none;width:100%;margin:0 0 4rem 0}'
            . '#itxeb-config-page .itxeb-section{margin-bottom:1.5rem}'
            . '#itxeb-config-page table.std{width:100%;border-collapse:collapse;background:#fff}'
            . '#itxeb-config-page table.std th,#itxeb-config-page table.std td{padding:.55rem .7rem;vertical-align:top;line-height:1.35}'
            . '#itxeb-config-page .itxeb-state-table,#itxeb-config-page .itxeb-form-table{max-width:1080px}'
            . '#itxeb-config-page .itxeb-summary{display:flex;flex-wrap:wrap;gap:.5rem;margin:.5rem 0 1rem}'
            . '#itxeb-config-page .itxeb-summary span{background:#f5f5f5;border:1px solid #ddd;padding:.35rem .55rem;border-radius:4px}'
            . '#itxeb-config-page .itxeb-dashboard-block{margin:.8rem 0 1.1rem}'
            . '#itxeb-config-page .itxeb-badge{display:inline-block;padding:.2rem .45rem;border-radius:3px;background:#eee;font-weight:600}'
            . '#itxeb-config-page .itxeb-badge-ok{background:#dff0d8}'
            . '#itxeb-config-page .itxeb-badge-warn{background:#fcf8e3}'
            . '#itxeb-config-page .itxeb-badge-error{background:#f2dede}'
            . '#itxeb-config-page .itxeb-badge-muted{background:#eee}'
            . '#itxeb-config-page .itxeb-health table td:nth-child(2){width:8rem;text-align:center}'
            . '#itxeb-config-page pre{max-height:320px;overflow:auto;white-space:pre-wrap;word-break:break-word;overflow-wrap:anywhere}'
            . '#itxeb-config-page code{white-space:normal;word-break:break-word;overflow-wrap:anywhere}'
            . '</style>';
    }
}
