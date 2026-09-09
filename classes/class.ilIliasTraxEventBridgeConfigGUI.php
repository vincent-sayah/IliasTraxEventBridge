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
require_once __DIR__ . '/class.ilIliasTraxEventBridgeAiClient.php';
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
            case 'savePilotageAccess': $this->savePilotageAccess(); break;
            case 'testTraxConnection': $this->testTraxConnection(); break;
            case 'testLrsRead': $this->testLrsRead(); break;
            case 'testLrsWrite': $this->testLrsWrite(); break;
            case 'testAiConfiguration': $this->testAiConfiguration(); break;
            case 'sendGenerated': $this->sendGenerated(); break;
            case 'resetFailed': $this->resetFailed(); break;
            case 'configureCourseTracking': $this->handleCourseTracking('show'); break;
            case 'saveCourseTracking': $this->handleCourseTracking('save'); break;
            case 'enableAllCourseTracking': $this->handleCourseTracking('enableAll'); break;
            case 'disableAllCourseTracking': $this->handleCourseTracking('disableAll'); break;
            case 'resetCourseTracking': $this->handleCourseTracking('resetCourse'); break;
            case 'clearLog': $this->repo->clear(); $this->success('Journal debug vidé.'); $this->redirectConfigureAnchor('itxeb-recent-events'); break;
            case 'clearOutbox': $this->outbox->clear(); $this->success('Outbox xAPI locale vidée.'); $this->redirectConfigureAnchor('itxeb-outbox'); break;
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
        $html = $this->styles() . '<div id="itxeb-config-page"><h1>IliasTraxEventBridge — configuration</h1>'
            . '<p class="itxeb-config-intro"><strong>V0.28.4 :</strong> page de configuration réorganisée avec une lecture gauche/droite, identique à l’esprit des vues Tableau de bord et Analyse.</p>'
            . $this->renderHealthCheck()
            . $this->renderState()
            . $this->renderDiagnosticsTraxCron()
            . $this->renderPilotageAccessForm()
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
        $rows .= $this->healthRow('Outbox failed', $outboxFailed === 0, (string) $outboxFailed . ' ligne(s) failed', $outboxFailed === 0 ? 'ok' : 'warn');
        $rows .= $this->healthRow('Retry épuisé', $retryExhausted === 0, (string) $retryExhausted . ' ligne(s) avec retry épuisé', $retryExhausted === 0 ? 'ok' : 'warn');
        foreach (['evnt_evhk_itxeb_log', 'evnt_evhk_itxeb_out', 'evnt_evhk_itxeb_read', 'evnt_evhk_itxeb_ccfg', 'evnt_evhk_itxeb_rcfg', 'evnt_evhk_itxeb_dlog'] as $table) {
            $exists = $this->tableExists($table);
            $rows .= $this->healthRow('Table ' . $table, $exists, $exists ? 'présente' : 'absente ou non vérifiable', $exists ? 'ok' : 'warn');
        }
        return '<section class="itxeb-section itxeb-health"><h2>Santé / Diagnostic</h2>'
            . '<p>Cette section réalise des contrôles non destructifs : elle ne modifie ni la configuration, ni l’outbox, ni les tables SQL.</p>'
            . '<table class="std itxeb-state-table"><thead><tr><th>Contrôle</th><th>État</th><th>Détail</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<p><strong>Diagnostic serveur :</strong> pour un contrôle plus complet côté AlmaLinux, lancer <code>bash scripts/diagnostic_itxeb.sh</code> depuis le dossier du plugin.</p>'
            . '<p><strong>Tests applicatifs :</strong> utiliser les boutons de test dans la section configuration. Les résultats restent affichés dans <code>Diagnostics TRAX / cron</code>.</p>'
            . '</section>';
    }

            private function renderState(): string
    {
        $html = '<section id="itxeb-state" class="itxeb-section"><h2>État</h2><table class="std itxeb-state-table"><tbody>';
        foreach ([
            'Plugin actif' => $this->config->isEnabled() ? 'oui' : 'non',
            'Mode debug' => $this->config->isDebugEnabled() ? 'oui' : 'non',
            'Génération xAPI locale' => $this->config->isLocalXapiGenerationEnabled() ? 'oui' : 'non',
            'Cron plugin' => $this->config->isCronEnabled() ? 'activé' : 'désactivé',
            'Diagnostic traces refusées' => $this->config->isDenyLogEnabled() ? 'activé' : 'désactivé',
            'Analyse IA' => $this->config->isAiEnabled() ? 'activée' : 'désactivée',
            'Clé API IA' => $this->config->getAiApiKeyStatus(),
            'Bouton Pilotage xAPI' => method_exists($this->config, 'getPilotageAccessMode') && $this->config->getPilotageAccessMode() === 'selected' ? 'limité aux cours sélectionnés' : 'tous les cours administrés',
        ] as $k => $v) {
            $html .= '<tr><td>' . $this->esc($k) . '</td><td><strong>' . $this->esc($v) . '</strong></td></tr>';
        }
        $html .= '<tr><td>Endpoint statements</td><td><code>' . $this->esc($this->config->getStatementsEndpoint()) . '</code></td></tr>';
        return $html . '</tbody></table></section>';
    }



                private function renderDiagnosticsTraxCron(): string
    {
        $html = '<section id="itxeb-diagnostics-trax-cron" class="itxeb-section"><h2>Diagnostics TRAX / cron</h2><div class="itxeb-section-body">'
            . '<p>Derniers résultats des tests de connexion, lecture, écriture, IA, envoi manuel et cron. Les actions de purge sont placées dans leurs blocs fonctionnels : Outbox xAPI locale et Derniers événements ILIAS reçus.</p>'
            . '<table class="std itxeb-state-table"><tbody>'
            . $this->diagRow('Dernier test connexion', $this->config->getLastTraxTestAt(), $this->config->getLastTraxTestSuccess(), $this->config->getLastTraxTestHttpStatus(), $this->config->getLastTraxTestMessage())
            . $this->diagRow('Dernier test lecture TRAX/LRS', $this->config->getLastLrsReadAt(), $this->config->getLastLrsReadSuccess(), $this->config->getLastLrsReadHttpStatus(), $this->config->getLastLrsReadMessage())
            . $this->diagRow('Dernier test écriture TRAX/LRS', $this->config->getLastLrsWriteAt(), $this->config->getLastLrsWriteSuccess(), $this->config->getLastLrsWriteHttpStatus(), $this->config->getLastLrsWriteMessage())
            . $this->diagRow('Dernier test IA', $this->config->getLastAiTestAt(), $this->config->getLastAiTestSuccess(), $this->config->getLastAiTestHttpStatus(), $this->config->getLastAiTestMessage())
            . $this->diagRow('Dernier envoi manuel', $this->config->getLastTraxSendAt(), $this->config->getLastTraxSendSuccess(), $this->config->getLastTraxSendHttpStatus(), $this->config->getLastTraxSendMessage())
            . $this->diagRow('Dernier cron', $this->config->getLastCronAt(), $this->config->getLastCronSuccess(), $this->config->getLastCronHttpStatus(), $this->config->getLastCronMessage())
            . '</tbody></table>';
        $html .= '<div class="itxeb-action-row">'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testTraxConnection') . '#itxeb-diagnostics-trax-cron') . '"><button class="btn btn-default" type="submit">Tester connexion TRAX</button></form>'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testLrsRead') . '#itxeb-diagnostics-trax-cron') . '"><button class="btn btn-default" type="submit">Tester lecture TRAX/LRS</button></form>'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testLrsWrite') . '#itxeb-diagnostics-trax-cron') . '"><button class="btn btn-warning" type="submit">Créer un statement test TRAX/LRS</button></form>'
            . '<form method="post" action="' . $this->esc($this->ctrl->getLinkTarget($this, 'testAiConfiguration') . '#itxeb-diagnostics-trax-cron') . '"><button class="btn btn-default" type="submit">Tester configuration IA</button></form>'
            . '</div>';
        return $html . '</div></section>';
    }



private function diagRow(string $label, string $at, string $success, string $http, string $message): string
    {
        if ($at === '') { return '<tr><td>' . $this->esc($label) . '</td><td><em>Aucun diagnostic disponible.</em></td></tr>'; }
        return '<tr><td>' . $this->esc($label) . '</td><td><strong>date :</strong> ' . $this->esc($at) . '<br><strong>succès :</strong> ' . $this->esc($success) . '<br><strong>HTTP :</strong> ' . $this->esc($http) . '<br><strong>message :</strong> ' . $this->esc($message) . '</td></tr>';
    }

        private function renderPilotageAccessForm(): string
    {
        $mode = $this->config->getPilotageAccessMode();
        $ids = $this->config->getPilotageCourseRefIds();
        $action = $this->ctrl->getLinkTarget($this, 'savePilotageAccess') . '#itxeb-pilotage-access';

        $html = '<section id="itxeb-pilotage-access" class="itxeb-section"><h2>Bouton Pilotage xAPI dans les cours</h2>'
            . '<p>Cette section décide dans quels cours le bouton <strong>Pilotage xAPI</strong> est affiché aux administrateurs du cours. Elle ne remplace pas la configuration xAPI du cours ni l’activation des ressources.</p>'
            . '<form method="post" action="' . $this->esc($action) . '"><table class="std itxeb-form-table"><tbody>'
            . '<tr><td>Mode d’affichage</td><td>'
            . '<label><input type="radio" name="pilotage_access_mode" value="all"' . ($mode === 'all' ? ' checked="checked"' : '') . '> Tous les cours où l’utilisateur est administrateur du cours</label><br>'
            . '<label><input type="radio" name="pilotage_access_mode" value="selected"' . ($mode === 'selected' ? ' checked="checked"' : '') . '> Seulement les cours listés ci-dessous</label>'
            . '<div class="small">Le contrôle des droits reste conservé : le bouton n’est jamais affiché à un utilisateur qui n’administre pas le cours.</div>'
            . '</td></tr>';

        $html .= '<tr><td>Cours actuellement autorisés</td><td>';
        if (count($ids) === 0) {
            $html .= '<em>Aucun cours listé.</em>';
        } else {
            foreach ($ids as $id) {
                $html .= '<label class="itxeb-course-ref-choice"><input type="checkbox" name="pilotage_keep_ids[]" value="' . $this->esc((string) $id) . '" checked="checked"> ref_id ' . $this->esc((string) $id) . '</label><br>';
            }
            $html .= '<div class="small">Pour retirer la fonctionnalité d’un cours, décocher son ref_id puis enregistrer.</div>';
        }
        $html .= '</td></tr>';

        $html .= '<tr><td><label for="pilotage_course_ref_ids_add">Ajouter des cours</label></td><td>'
            . '<textarea id="pilotage_course_ref_ids_add" name="pilotage_course_ref_ids_add" rows="4" class="form-control" placeholder="210&#10;211&#10;245"></textarea>'
            . '<div class="small">Saisir un ou plusieurs <code>ref_id</code> de cours, séparés par retour ligne, virgule, espace ou point-virgule.</div>'
            . '</td></tr>';

        if ($mode === 'selected' && count($ids) === 0) {
            $html .= '<tr><td>Attention</td><td><span class="itxeb-badge itxeb-badge-warn">Aucun cours autorisé</span> En mode sélection, le bouton Pilotage xAPI ne s’affiche dans aucun cours tant qu’aucun ref_id n’est enregistré.</td></tr>';
        }

        return $html . '</tbody></table><p><button class="btn btn-primary" type="submit">Enregistrer les cours autorisés</button></p></form></section>';
    }

    private function savePilotageAccess(): void
    {
        $mode = $this->postString('pilotage_access_mode');
        if (!in_array($mode, ['all', 'selected'], true)) {
            $mode = 'all';
        }

        $ids = $this->postIntArray('pilotage_keep_ids');
        $addText = $this->postString('pilotage_course_ref_ids_add');
        if (preg_match_all('/\d+/', $addText, $matches)) {
            foreach ($matches[0] as $raw) {
                $value = (int) $raw;
                if ($value > 0) {
                    $ids[] = $value;
                }
            }
        }

        $this->config->setPilotageAccessMode($mode);
        $this->config->setPilotageCourseRefIds($ids);

        $saved = $this->config->getPilotageCourseRefIds();
        if ($mode === 'selected' && count($saved) === 0) {
            $this->failure('Aucun cours autorisé : le bouton Pilotage xAPI sera masqué dans tous les cours.');
        } else {
            $this->success('Configuration du bouton Pilotage xAPI enregistrée.');
        }
        $this->redirectConfigureAnchor('itxeb-pilotage-access');
    }

    private function redirectConfigureAnchor(string $anchor): void
    {
        $anchor = preg_replace('/[^a-zA-Z0-9_-]/', '', $anchor);
        if ($anchor === '') {
            $this->ctrl->redirect($this, 'configure');
            return;
        }
        $url = $this->ctrl->getLinkTarget($this, 'configure') . '#' . $anchor;
        if (class_exists('ilUtil') && method_exists('ilUtil', 'redirect')) {
            ilUtil::redirect($url);
            return;
        }
        $this->ctrl->redirect($this, 'configure');
    }

    /** @return array<int,int> */
    private function postIntArray(string $key): array
    {
        $values = isset($_POST[$key]) && is_array($_POST[$key]) ? $_POST[$key] : [];
        $out = [];
        foreach ($values as $value) {
            if (is_scalar($value) && (int) $value > 0) {
                $out[(int) $value] = (int) $value;
            }
        }
        $out = array_values($out);
        sort($out);
        return $out;
    }

        private function renderCourseTrackingAccess(): string
    {
        // ITXEB V0.28.4 : le bloc "Ouvrir la configuration xAPI d’un cours" est retiré de la page de configuration plugin.
        return '';
    }



    private function handleCourseTracking(string $courseCommand): void
    {
        $gui = new ilIliasTraxEventBridgeCourseTrackingGUI($this, ['show' => 'configureCourseTracking', 'save' => 'saveCourseTracking', 'enableAll' => 'enableAllCourseTracking', 'disableAll' => 'disableAllCourseTracking', 'resetCourse' => 'resetCourseTracking']);
        $gui->performCommand($courseCommand);
    }

    
            private function renderConfigForm(): string
    {
        $action = $this->ctrl->getLinkTarget($this, 'saveConfig');
        $html = '<form method="post" action="' . $this->esc($action . '#itxeb-config-trax') . '">';
        $html .= '<section id="itxeb-config-trax" class="itxeb-section"><h2>Configuration TRAX / cron</h2><div class="itxeb-section-body">'
            . '<p><strong>Important :</strong> cette section pilote les paramètres techniques TRAX et cron. Le diagnostic des traces refusées doit rester désactivé en exploitation courante.</p>'
            . '<table class="std itxeb-form-table"><tbody>';
        $html .= $this->checkboxRow('Activer le cron plugin', 'cron_enabled', $this->config->isCronEnabled(), 'Autorise le job cron du plugin. Le job cron ILIAS global doit aussi être activé dans les tâches cron.');
        $html .= $this->checkboxRow('Activer le diagnostic des traces refusées', 'deny_log_enabled', $this->config->isDenyLogEnabled(), 'À activer uniquement à la demande. Si activé sur une plateforme volumineuse, la table evnt_evhk_itxeb_dlog peut grossir rapidement.');
        foreach ([['Endpoint xAPI TRAX', 'trax_endpoint', $this->config->getTraxEndpoint(), 'Endpoint xAPI racine ou URL complète /statements.'], ['Identifiant client TRAX', 'trax_username', $this->config->getTraxUsername(), 'Client xAPI autorisé à écrire.'], ['Version xAPI', 'xapi_version', $this->config->getXapiVersion(), 'Recommandé : 1.0.3.'], ['Timeout HTTP', 'http_timeout', (string) $this->config->getHttpTimeout(), 'Entre 2 et 120 secondes.'], ['Taille batch', 'batch_size', (string) $this->config->getBatchSize(), 'Entre 1 et 100 statements.'], ['Max retry', 'max_retry', (string) $this->config->getMaxRetry(), 'Nombre maximum de tentatives par statement.'], ['Base URL ILIAS forcée', 'ilias_base_url', $this->config->getIliasBaseUrl(), 'Optionnel. Utilisé pour les IRIs xAPI.']] as $r) { $html .= $this->inputRow($r[0], $r[1], $r[2], $r[3]); }
        $html .= $this->passwordRow('Secret client TRAX', 'trax_password', 'Laisser vide pour conserver le secret.');
        $html .= '</tbody></table><p><button class="btn btn-primary" type="submit" name="itxeb_return_anchor" value="itxeb-config-trax" formaction="' . $this->esc($action . '#itxeb-config-trax') . '">Enregistrer TRAX / cron</button></p></div></section>';
        $html .= '<section id="itxeb-config-ia" class="itxeb-section"><h2>Configuration IA</h2><div class="itxeb-section-body">'
            . '<p>Analyse optionnelle. Le prompt système est modifiable depuis cette page ; le prompt par défaut peut être restauré à tout moment.</p>'
            . '<table class="std itxeb-form-table"><tbody>';
        $html .= $this->checkboxRow('Activer l’analyse IA', 'ai_enabled', $this->config->isAiEnabled(), 'Active les fonctions IA. Désactivé par défaut.');
        foreach ([['Fournisseur IA', 'ai_provider', $this->config->getAiProvider(), 'Exemple : vibe, mistral, passerelle interne.'], ['URL API IA', 'ai_api_url', $this->config->getAiApiUrl(), 'Exemple Mistral : https://api.mistral.ai/v1/chat/completions'], ['Modèle IA', 'ai_model', $this->config->getAiModel(), 'Exemple Mistral : mistral-small-latest'], ['Timeout IA', 'ai_timeout', (string) $this->config->getAiTimeout(), 'Entre 2 et 120 secondes.'], ['Mode anonymisation', 'ai_anonymization_mode', $this->config->getAiAnonymizationMode(), 'Valeurs autorisées : strict, pseudonymized, none. Recommandé : strict.'], ['Limite de traces IA', 'ai_trace_limit', (string) $this->config->getAiTraceLimit(), 'Nombre maximum d’éléments agrégés envoyés à l’IA, entre 1 et 1000.']] as $r) { $html .= $this->inputRow($r[0], $r[1], $r[2], $r[3]); }
        $html .= $this->checkboxRow('Journaliser les appels IA', 'ai_log_enabled', $this->config->isAiLogEnabled(), 'Journal technique uniquement, sans prompt sensible ni clé API.');
        if (method_exists($this->config, 'getAiSystemPrompt')) {
            $html .= $this->textareaRow('Prompt système IA', 'ai_system_prompt', $this->config->getAiSystemPrompt(), 'Prompt envoyé dans le message system. Modifier uniquement si la consigne pédagogique ou le format attendu doit changer.', 14);
            $html .= '<tr><td>Prompt IA par défaut</td><td><button class="btn btn-default" type="submit" name="ai_prompt_reset_default" value="1" formaction="' . $this->esc($action . '#itxeb-config-ia') . '">Remettre le prompt IA par défaut</button><div class="small">Le prompt par défaut sera restauré lors de l’enregistrement.</div></td></tr>';
        }
        $html .= '<tr><td>État clé API IA</td><td><strong>' . $this->esc($this->config->getAiApiKeyStatus()) . '</strong><div class="small">La valeur réelle de la clé n’est jamais affichée.</div></td></tr>';
        $html .= $this->passwordRow('Clé API IA', 'ai_api_key', 'Laisser vide pour conserver la clé déjà enregistrée. Saisir une nouvelle valeur remplace la clé existante.');
        $html .= '<tr><td><label for="ai_api_key_clear">Supprimer la clé API IA</label></td><td><label><input id="ai_api_key_clear" name="ai_api_key_clear" type="checkbox" value="1"> supprimer la clé enregistrée</label><div class="small">À cocher uniquement pour retirer la clé stockée dans la configuration du plugin. Si une nouvelle clé est saisie en même temps, elle sera enregistrée.</div></td></tr>';
        $html .= '</tbody></table><p><button class="btn btn-primary" type="submit" name="itxeb_return_anchor" value="itxeb-config-ia" formaction="' . $this->esc($action . '#itxeb-config-ia') . '">Enregistrer IA</button></p></div></section>';
        return $html . '</form>';
    }




    private function renderSendActions(): string
    {
        $html = '<section class="itxeb-section"><h2>Envoi vers TRAX</h2><div class="itxeb-summary">';
        foreach (['generated' => $this->outbox->countByStatus('generated'), 'failed' => $this->outbox->countByStatus('failed'), 'retry épuisé' => $this->outbox->countRetryExhausted($this->config->getMaxRetry()), 'taille batch' => $this->config->getBatchSize(), 'max_retry' => $this->config->getMaxRetry()] as $k => $v) { $html .= '<span><strong>' . $this->esc((string) $k) . ' :</strong> ' . $this->esc((string) $v) . '</span>'; }
        return $html . '</div><p>L’envoi manuel et le cron traitent les statements <code>generated</code> ou <code>failed</code> lorsque <code>retry_count &lt; max_retry</code>.</p><p><a class="btn btn-primary" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'sendGenerated')) . '">Envoyer maintenant</a> <a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'resetFailed')) . '">Réinitialiser les failed</a></p></section>';
    }

    private function renderAdminDashboard(): string
    {
        $now = time(); $since24h = $now - 86400; $since7d = $now - 604800;
        $metrics = ['Total outbox' => $this->outbox->countAll(), 'Créés 24h' => $this->outbox->countCreatedSince($since24h), 'Créés 7j' => $this->outbox->countCreatedSince($since7d), 'Sent total' => $this->outbox->countByStatus('sent'), 'Sent 24h' => $this->outbox->countByStatusSince('sent', $since24h), 'Generated total' => $this->outbox->countByStatus('generated'), 'Failed total' => $this->outbox->countByStatus('failed'), 'Failed 24h' => $this->outbox->countByStatusSince('failed', $since24h), 'Failed/erreurs à inspecter' => $this->outbox->countFailedWithError(), 'Retry épuisé' => $this->outbox->countRetryExhausted($this->config->getMaxRetry())];
        $html = '<section class="itxeb-section"><h2>Supervision outbox</h2><div class="itxeb-dashboard-block"><h3>Exploitation / maintenance</h3><div class="itxeb-summary">';
        foreach ($metrics as $label => $value) { $html .= '<span><strong>' . $this->esc((string) $label) . ' :</strong> ' . $this->esc((string) $value) . '</span>'; }
        return $html . '</div></div></section>';
    }

    private function renderDenyLogSupervision(): string
    {
        $rows = $this->denyLog->findRecent(50);
        $statusText = $this->config->isDenyLogEnabled() ? 'activé' : 'désactivé';
        $statusClass = $this->config->isDenyLogEnabled() ? 'itxeb-badge-warn' : 'itxeb-badge-muted';
        $html = '<section class="itxeb-section"><h2>Diagnostic des traces refusées</h2><p>État actuel : <span class="itxeb-badge ' . $statusClass . '">' . $this->esc($statusText) . '</span>. À laisser désactivé en exploitation courante.</p>'
            . '<p><a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearDenyLog')) . '">Purger le diagnostic des traces refusées</a></p>'
            . '<div class="itxeb-summary"><span><strong>Total refus :</strong> ' . $this->esc((string) $this->denyLog->countAll()) . '</span></div>';
        if (count($rows) === 0) { return $html . '<p><em>Aucun refus journalisé pour le moment.</em></p></section>'; }
        $html .= '<div class="table-responsive"><table class="std itxeb-events"><thead><tr><th>ID / date</th><th>Motif</th><th>Événement</th><th>Contexte</th><th>Source</th><th>Payload</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $reason = (string)($r['reason'] ?? '');
            $html .= '<tr><td>#' . $this->esc((string)($r['id'] ?? '')) . '<br><small>' . $this->esc((string)($r['created_at'] ?? '')) . '</small></td><td><span class="itxeb-badge ' . $this->reasonBadgeClass($reason) . '">' . $this->esc($reason) . '</span></td><td>' . $this->esc((string)($r['event_type'] ?? '')) . '<br><small>' . $this->esc((string)($r['component'] ?? '')) . '<br>' . $this->esc((string)($r['event_name'] ?? '')) . '</small></td><td>user ' . $this->esc((string)($r['user_id'] ?? '')) . '<br>course ' . $this->esc((string)($r['course_ref_id'] ?? '')) . '<br>ref ' . $this->esc((string)($r['ref_id'] ?? '')) . '<br>obj ' . $this->esc((string)($r['obj_id'] ?? '')) . '<br>' . $this->esc((string)($r['obj_type'] ?? '')) . '</td><td>' . $this->esc((string)($r['source_table'] ?? '')) . '<br>#' . $this->esc((string)($r['source_id'] ?? '')) . '</td><td><details><summary>Payload</summary><pre>' . $this->esc($this->formatPayload((string)($r['payload_json'] ?? ''))) . '</pre></details></td></tr>';
        }
        return $html . '</tbody></table></div></section>';
    }

    
            private function saveConfig(): void
    {
        $this->config->setCronEnabled($this->postString('cron_enabled') === '1');
        $this->config->setDenyLogEnabled($this->postString('deny_log_enabled') === '1');
        $this->config->setAiEnabled($this->postString('ai_enabled') === '1');
        $this->config->setAiLogEnabled($this->postString('ai_log_enabled') === '1');
        foreach (['TraxEndpoint' => 'trax_endpoint', 'TraxUsername' => 'trax_username', 'XapiVersion' => 'xapi_version', 'IliasBaseUrl' => 'ilias_base_url', 'AiProvider' => 'ai_provider', 'AiApiUrl' => 'ai_api_url', 'AiModel' => 'ai_model', 'AiAnonymizationMode' => 'ai_anonymization_mode'] as $m => $k) { $this->config->{'set' . $m}($this->postString($k)); }
        $this->config->setHttpTimeout((int) $this->postString('http_timeout'));
        $this->config->setBatchSize((int) $this->postString('batch_size'));
        $this->config->setMaxRetry((int) $this->postString('max_retry'));
        $this->config->setAiTimeout((int) $this->postString('ai_timeout'));
        $this->config->setAiTraceLimit((int) $this->postString('ai_trace_limit'));
        if (method_exists($this->config, 'resetAiSystemPrompt') && $this->postString('ai_prompt_reset_default') === '1') { $this->config->resetAiSystemPrompt(); }
        elseif (method_exists($this->config, 'setAiSystemPrompt')) { $this->config->setAiSystemPrompt($this->postString('ai_system_prompt')); }
        if ($this->postString('trax_password') !== '') { $this->config->setTraxPassword($this->postString('trax_password')); }
        if ($this->postString('ai_api_key_clear') === '1') { $this->config->clearStoredAiApiKey(); }
        if ($this->postString('ai_api_key') !== '') { $this->config->setStoredAiApiKey($this->postString('ai_api_key')); }
        $this->success('Configuration enregistrée.');
        $anchor = $this->postString('itxeb_return_anchor');
        if ($this->postString('ai_prompt_reset_default') === '1') { $anchor = 'itxeb-config-ia'; }
        if ($anchor === '') { $anchor = 'itxeb-config-trax'; }
        if (method_exists($this, 'redirectConfigureAnchor')) { $this->redirectConfigureAnchor($anchor); return; }
        $this->configure();
    }




    private function testAiConfiguration(): void
    {
        $r = (new ilIliasTraxEventBridgeAiClient($this->config))->testConnection();
        $message = $r->getShortMessage();
        $this->config->setLastAiTestResult($r->isSuccess(), $r->getHttpStatus(), $message);
        if ($r->isSuccess()) { $this->success('Test IA réussi : ' . $message . '. Aucun statement xAPI réel n’a été envoyé à l’IA.'); }
        else { $this->failure('Test IA échoué : ' . $message); }
        $this->ctrl->redirect($this, 'configure');
    }

    private function testTraxConnection(): void
    {
        $r = (new ilIliasTraxEventBridgeTraxClient($this->config))->testConnection();
        $this->config->setLastTraxTestResult($r->isSuccess(), $r->getHttpStatus(), $r->getShortMessage());
        if ($r->isSuccess()) { $this->success('Connexion TRAX réussie : ' . $r->getShortMessage()); }
        else { $this->failure('Connexion TRAX échouée : ' . $r->getShortMessage()); }
        $this->ctrl->redirect($this, 'configure');
    }

    private function testLrsRead(): void
    {
        $r = (new ilIliasTraxEventBridgeLrsReadClient($this->config))->queryStatements(['limit' => 1]);
        if (!$r->isSuccess()) { $message = 'Lecture TRAX/LRS échouée : ' . $r->getShortMessage(); $this->config->setLastLrsReadResult(false, $r->getHttpStatus(), $message); $this->failure($message); $this->ctrl->redirect($this, 'configure'); return; }
        $count = 0; $decoded = json_decode($r->getBody(), true); if (is_array($decoded) && isset($decoded['statements']) && is_array($decoded['statements'])) { $count = count($decoded['statements']); }
        $message = 'Lecture TRAX/LRS réussie : HTTP ' . $r->getHttpStatus() . ' ; ' . $count . ' statement(s) retourné(s) avec limit=1.'; $this->config->setLastLrsReadResult(true, $r->getHttpStatus(), $message); $this->success($message); $this->ctrl->redirect($this, 'configure');
    }

    private function testLrsWrite(): void
    {
        $statement = $this->buildDiagnosticStatement(); $statementId = is_string($statement['id'] ?? null) ? (string) $statement['id'] : ''; $r = (new ilIliasTraxEventBridgeTraxClient($this->config))->sendStatements([$statement]);
        if ($r->isSuccess()) { $message = 'Statement test TRAX/LRS créé : HTTP ' . $r->getHttpStatus() . ' ; id ' . $statementId . '.'; $this->config->setLastLrsWriteResult(true, $r->getHttpStatus(), $message); $this->success($message); }
        else { $message = 'Création du statement test TRAX/LRS échouée : ' . $r->getShortMessage(); $this->config->setLastLrsWriteResult(false, $r->getHttpStatus(), $message); $this->failure($message); }
        $this->ctrl->redirect($this, 'configure');
    }

    /** @return array<string,mixed> */
    private function buildDiagnosticStatement(): array
    {
        $id = $this->uuidV4(); $baseUrl = trim($this->config->getIliasBaseUrl()); $homePage = preg_match('~^https?://~i', $baseUrl) ? rtrim($baseUrl, '/') : 'https://example.invalid/itxeb'; $objectId = $homePage . '/xapi/diagnostic/write-test/' . $id;
        return ['id' => $id, 'actor' => ['account' => ['homePage' => $homePage, 'name' => 'itxeb-diagnostic'], 'name' => 'IliasTraxEventBridge diagnostic'], 'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/experienced', 'display' => ['en-US' => 'experienced', 'fr-FR' => 'a testé']], 'object' => ['id' => $objectId, 'definition' => ['name' => ['fr-FR' => 'Statement de diagnostic IliasTraxEventBridge', 'en-US' => 'IliasTraxEventBridge diagnostic statement'], 'description' => ['fr-FR' => 'Statement créé volontairement par le test d’écriture du plugin IliasTraxEventBridge.', 'en-US' => 'Statement intentionally created by the write diagnostic test of IliasTraxEventBridge.'], 'type' => 'https://w3id.org/xapi/acrossx/activities/diagnostic'], 'objectType' => 'Activity'], 'context' => ['extensions' => [$homePage . '/xapi/extensions/itxeb_diagnostic' => true, $homePage . '/xapi/extensions/itxeb_version' => '0.13.0', $homePage . '/xapi/extensions/itxeb_test_type' => 'admin_write_diagnostic']], 'timestamp' => gmdate('c')];
    }

    private function uuidV4(): string { try { $data = random_bytes(16); $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4)); } catch (Throwable $e) { return str_replace('.', '-', uniqid('itxeb-', true)); } }
    private function sendGenerated(): void { $r = (new ilIliasTraxEventBridgeOutboxSender($this->config, $this->outbox))->sendBatch(); $this->config->setLastTraxSendResult((bool) $r['success'], (int) $r['http_status'], (string) $r['message']); if ($r['success']) { $this->success((string) $r['message']); } else { $this->failure((string) $r['message']); } $this->ctrl->redirect($this, 'configure'); }
    private function resetFailed(): void { $this->success($this->outbox->resetFailedToGenerated() . ' statement(s) failed réinitialisé(s).'); $this->ctrl->redirect($this, 'configure'); }

        private function renderOutbox(): string
    {
        $rows = $this->outbox->findRecent(50);
        $html = '<section id="itxeb-outbox" class="itxeb-section"><h2>Outbox xAPI locale</h2><div class="itxeb-section-body">'
            . '<p>File technique locale des statements xAPI générés avant envoi vers TRAX/LRS.</p>'
            . '<div class="itxeb-action-row"><a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearOutbox')) . '">Vider l’outbox xAPI locale</a></div>';
        if (count($rows) === 0) {
            return $html . '<p><em>Aucun statement xAPI généré pour le moment.</em></p></div></section>';
        }
        $html .= '<div class="table-responsive"><table class="std itxeb-events"><thead><tr><th>ID / date</th><th>Statut</th><th>Retry</th><th>Verb</th><th>Objet</th><th>Erreur / statement</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $err = trim((string)($r['last_error'] ?? ''));
            $html .= '<tr><td>#' . $this->esc((string)($r['id'] ?? '')) . '<br><small>' . $this->esc((string)($r['created_at'] ?? '')) . '</small></td><td><span class="itxeb-badge ' . $this->statusBadgeClass((string)($r['status'] ?? '')) . '">' . $this->esc((string)($r['status'] ?? '')) . '</span></td><td>' . $this->esc((string)($r['retry_count'] ?? '0')) . ' / ' . $this->esc((string)($r['max_retry'] ?? $this->config->getMaxRetry())) . '</td><td><code>' . $this->esc((string)($r['verb_id'] ?? '')) . '</code></td><td>user ' . $this->esc((string)($r['user_id'] ?? '')) . '<br>ref ' . $this->esc((string)($r['ref_id'] ?? '')) . '<br>obj ' . $this->esc((string)($r['obj_id'] ?? '')) . '<br>' . $this->esc((string)($r['obj_type'] ?? '')) . '</td><td>' . ($err !== '' ? '<div>' . $this->esc($err) . '</div>' : '') . '<details><summary>Statement</summary><pre>' . $this->esc($this->formatPayload((string)($r['statement_json'] ?? ''))) . '</pre></details></td></tr>';
        }
        return $html . '</tbody></table></div></div></section>';
    }


        private function renderRecentEvents(): string
    {
        $rows = $this->repo->findRecent(100);
        $html = '<section id="itxeb-recent-events" class="itxeb-section"><h2>Derniers événements ILIAS reçus</h2><div class="itxeb-section-body">'
            . '<p>Journal debug des derniers événements ILIAS reçus par le plugin.</p>'
            . '<div class="itxeb-action-row"><a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearLog')) . '">Vider le journal debug</a></div>';
        if (count($rows) === 0) {
            return $html . '<p><em>Aucun événement journalisé pour le moment.</em></p></div></section>';
        }
        $html .= '<div class="table-responsive"><table class="std itxeb-events"><thead><tr><th>ID / date</th><th>Événement</th><th>Objet</th><th>URI</th><th>Payload</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr><td>#' . $this->esc((string)($r['id'] ?? '')) . '<br><small>' . $this->esc((string)($r['created_at'] ?? '')) . '</small></td><td>' . $this->esc((string)($r['component'] ?? '')) . '<br><strong>' . $this->esc((string)($r['event_name'] ?? '')) . '</strong></td><td>user ' . $this->esc((string)($r['user_id'] ?? '')) . '<br>ref ' . $this->esc((string)($r['ref_id'] ?? '')) . '<br>obj ' . $this->esc((string)($r['obj_id'] ?? '')) . '<br>' . $this->esc((string)($r['obj_type'] ?? '')) . '</td><td><code>' . $this->esc((string)($r['request_uri'] ?? '')) . '</code></td><td><details><summary>Payload</summary><pre>' . $this->esc($this->formatPayload((string)($r['payload_json'] ?? ''))) . '</pre></details></td></tr>';
        }
        return $html . '</tbody></table></div></div></section>';
    }


    private function healthRow(string $label, bool $ok, string $detail, string $level): string { $class = $level === 'ok' ? 'itxeb-badge-ok' : ($level === 'error' ? 'itxeb-badge-error' : 'itxeb-badge-warn'); $text = $level === 'ok' ? 'OK' : ($level === 'error' ? 'ERREUR' : 'ATTENTION'); return '<tr><td>' . $this->esc($label) . '</td><td><span class="itxeb-badge ' . $class . '">' . $text . '</span></td><td>' . $this->esc($detail) . '</td></tr>'; }
    private function tableExists(string $table): bool { global $DIC, $ilDB; try { $db = null; if (isset($DIC) && method_exists($DIC, 'database')) { $db = $DIC->database(); } elseif (isset($ilDB)) { $db = $ilDB; } if (is_object($db) && method_exists($db, 'tableExists')) { return (bool) $db->tableExists($table); } } catch (Throwable $e) { return false; } return false; }
    private function pluginVersion(string $pluginPhp): string { if (!is_file($pluginPhp)) { return ''; } $content = (string) file_get_contents($pluginPhp); if (preg_match('/\$version\s*=\s*[\'\"]([^\'\"]+)[\'\"]\s*;/', $content, $m)) { return $m[1]; } return ''; }
    private function firstLine(string $file): string { if (!is_file($file)) { return ''; } $lines = file($file, FILE_IGNORE_NEW_LINES); return is_array($lines) && isset($lines[0]) ? trim((string) $lines[0]) : ''; }
    private function companionPath(string $pluginRoot): string { $search = '/Services/EventHandling/EventHook/IliasTraxEventBridge'; $replace = '/Services/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI'; if (substr($pluginRoot, -strlen($search)) === $search) { return substr($pluginRoot, 0, -strlen($search)) . $replace; } return dirname(dirname(dirname($pluginRoot))) . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI'; }
    private function inputRow(string $l, string $n, string $v, string $h): string { return '<tr><td><label for="' . $this->esc($n) . '">' . $this->esc($l) . '</label></td><td><input id="' . $this->esc($n) . '" name="' . $this->esc($n) . '" type="text" value="' . $this->esc($v) . '" class="form-control"><div class="small">' . $this->esc($h) . '</div></td></tr>'; }

    private function textareaRow(string $l, string $n, string $v, string $h, int $rows = 6): string
    {
        return '<tr><td><label for="' . $this->esc($n) . '">' . $this->esc($l) . '</label></td><td><textarea id="' . $this->esc($n) . '" name="' . $this->esc($n) . '" rows="' . $this->esc((string) max(2, $rows)) . '" class="form-control" style="width:100%;max-width:980px">' . $this->esc($v) . '</textarea><div class="small">' . $this->esc($h) . '</div></td></tr>';
    }

    private function passwordRow(string $l, string $n, string $h): string { return '<tr><td><label for="' . $this->esc($n) . '">' . $this->esc($l) . '</label></td><td><input id="' . $this->esc($n) . '" name="' . $this->esc($n) . '" type="password" value="" class="form-control"><div class="small">' . $this->esc($h) . '</div></td></tr>'; }
    private function checkboxRow(string $l, string $n, bool $c, string $h): string { return '<tr><td><label for="' . $this->esc($n) . '">' . $this->esc($l) . '</label></td><td><label><input id="' . $this->esc($n) . '" name="' . $this->esc($n) . '" type="checkbox" value="1"' . ($c ? ' checked="checked"' : '') . '> activé</label><div class="small">' . $this->esc($h) . '</div></td></tr>'; }
    private function statusBadgeClass(string $s): string { return $s === 'sent' ? 'itxeb-badge-ok' : ($s === 'failed' ? 'itxeb-badge-error' : ($s === 'sending' ? 'itxeb-badge-warn' : 'itxeb-badge-muted')); }
    private function reasonBadgeClass(string $s): string { return $s === 'resource_disabled' || $s === 'course_disabled' ? 'itxeb-badge-warn' : ($s === 'unsupported_object_type' ? 'itxeb-badge-error' : 'itxeb-badge-muted'); }
    private function formatPayload(string $j): string { $d = json_decode($j, true); if (is_array($d)) { $p = json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); if (is_string($p)) { return $p; } } return $j; }
    private function postString(string $k): string { return isset($_POST[$k]) && is_scalar($_POST[$k]) ? trim((string) $_POST[$k]) : ''; }
    private function esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    private function success(string $m): void { if (class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) { ilUtil::sendSuccess($m, true); } }
    private function failure(string $m): void { if (class_exists('ilUtil') && method_exists('ilUtil', 'sendFailure')) { ilUtil::sendFailure($m, true); } }
    private function setContent(string $html): void { if (is_object($this->tpl) && method_exists($this->tpl, 'setContent')) { $this->tpl->setContent($html); } }

            private function styles(): string
    {
        return '<style>'
            . '#itxeb-config-page{max-width:none;width:100%;margin:0 0 4rem 0;padding:0}'
            . '#itxeb-config-page h1{font-size:28px;font-weight:700;margin:0 0 6px;line-height:1.2}'
            . '#itxeb-config-page .itxeb-config-intro{border:2px solid #c8d6e5;background:#f8fbff;border-radius:6px;padding:12px 14px;margin:0 0 16px}'
            . '#itxeb-config-page .itxeb-section{display:grid;grid-template-columns:260px minmax(0,1fr);column-gap:24px;row-gap:8px;border-top:1px solid #d9d9d9;padding:16px 0;margin:0;background:#fff}'
            . '#itxeb-config-page .itxeb-section>h2{grid-column:1;margin:0!important;padding:5px 0 0!important;border:0!important;font-size:16px!important;line-height:1.35;color:#333;font-weight:700}'
            . '#itxeb-config-page .itxeb-section>:not(h2){grid-column:2;min-width:0;margin-top:0}'
            . '#itxeb-config-page .itxeb-section-body{min-width:0}'
            . '#itxeb-config-page .itxeb-section p{color:#555;margin:.1rem 0 .65rem}'
            . '#itxeb-config-page table.std{width:100%;border-collapse:collapse;background:#fff;border:2px solid #c8d6e5;box-shadow:0 1px 4px rgba(0,0,0,.08)}'
            . '#itxeb-config-page table.std th,#itxeb-config-page table.std td{padding:.55rem .7rem;vertical-align:top;line-height:1.35;border:1px solid #ddd}'
            . '#itxeb-config-page table.std th{background:#f7f7f7;font-weight:700;border-bottom:2px solid #c8d6e5}'
            . '#itxeb-config-page .itxeb-state-table,#itxeb-config-page .itxeb-form-table{max-width:none}'
            . '#itxeb-config-page .itxeb-form-table td:first-child,#itxeb-config-page .itxeb-state-table td:first-child{width:260px;font-weight:700;color:#333}'
            . '#itxeb-config-page input.form-control,#itxeb-config-page textarea.form-control{max-width:980px;width:100%}'
            . '#itxeb-config-page .small{color:#666;margin-top:.25rem;line-height:1.35}'
            . '#itxeb-config-page .itxeb-summary{display:flex;flex-wrap:wrap;gap:.5rem;margin:.5rem 0 1rem}'
            . '#itxeb-config-page .itxeb-summary span{background:#f5f5f5;border:1px solid #ddd;padding:.35rem .55rem;border-radius:4px}'
            . '#itxeb-config-page .itxeb-dashboard-block{margin:.8rem 0 1.1rem}'
            . '#itxeb-config-page .itxeb-badge{display:inline-block;padding:.2rem .45rem;border-radius:3px;background:#eee;font-weight:600}'
            . '#itxeb-config-page .itxeb-badge-ok{background:#dff0d8}'
            . '#itxeb-config-page .itxeb-badge-warn{background:#fcf8e3}'
            . '#itxeb-config-page .itxeb-badge-error{background:#f2dede}'
            . '#itxeb-config-page .itxeb-badge-muted{background:#eee}'
            . '#itxeb-config-page .itxeb-health table td:nth-child(2){width:8rem;text-align:center}'
            . '#itxeb-config-page .itxeb-action-row{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:.75rem 0}'
            . '#itxeb-config-page .itxeb-action-row form{display:inline-block;margin:0}'
            . '#itxeb-config-page .itxeb-course-ref-choice{display:inline-block;margin:.1rem 0}'
            . '#itxeb-config-page pre{max-height:320px;overflow:auto;white-space:pre-wrap;word-break:break-word;overflow-wrap:anywhere}'
            . '#itxeb-config-page code{white-space:normal;word-break:break-word;overflow-wrap:anywhere}'
            . '@media(max-width:900px){#itxeb-config-page .itxeb-section{grid-template-columns:1fr;column-gap:0}#itxeb-config-page .itxeb-section>h2,#itxeb-config-page .itxeb-section>:not(h2){grid-column:1}#itxeb-config-page .itxeb-form-table td:first-child,#itxeb-config-page .itxeb-state-table td:first-child{width:auto}}'
            . '</style>';
    }


}
