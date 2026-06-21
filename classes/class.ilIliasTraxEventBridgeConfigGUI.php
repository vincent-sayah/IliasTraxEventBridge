<?php

/**
 * Minimal admin configuration GUI for V0.1.
 */
class ilIliasTraxEventBridgeConfigGUI
{
    /** @var ilIliasTraxEventBridgePlugin */
    private $plugin;

    /** @var ilCtrl|mixed */
    private $ctrl;

    /** @var ilGlobalTemplateInterface|mixed */
    private $tpl;

    /** @var ilLanguage|mixed */
    private $lng;

    /** @var ilIliasTraxEventBridgeConfig */
    private $config;

    /** @var ilIliasTraxEventBridgeEventDebugRepository */
    private $repo;

    public function __construct()
    {
        global $DIC, $ilCtrl, $tpl, $lng;

        $this->ctrl = isset($DIC) && method_exists($DIC, 'ctrl') ? $DIC->ctrl() : $ilCtrl;
        $this->tpl = $tpl;
        $this->lng = isset($DIC) && method_exists($DIC, 'language') ? $DIC->language() : $lng;

        $this->plugin = ilPlugin::getPluginObject(
            IL_COMP_SERVICE,
            'EventHandling',
            'evhk',
            'IliasTraxEventBridge'
        );

        $this->plugin->includeClass('class.ilIliasTraxEventBridgeConfig.php');
        $this->plugin->includeClass('class.ilIliasTraxEventBridgeEventDebugRepository.php');

        $this->config = new ilIliasTraxEventBridgeConfig();
        $this->repo = new ilIliasTraxEventBridgeEventDebugRepository();
    }

    public function performCommand(string $cmd): void
    {
        switch ($cmd) {
            case 'save':
                $this->save();
                break;
            case 'clearLog':
                $this->clearLog();
                break;
            case 'configure':
            default:
                $this->configure();
                break;
        }
    }

    private function configure(): void
    {
        $form = $this->buildForm();
        $html = $form->getHTML();
        $html .= $this->renderRecentEvents();
        $this->setContent($html);
    }

    private function save(): void
    {
        $form = $this->buildForm();

        if ($form->checkInput()) {
            $this->config->setEnabled((bool) $form->getInput('enabled'));
            $this->config->setDebugEnabled((bool) $form->getInput('debug_enabled'));
            $this->config->setMaxPayloadChars((int) $form->getInput('max_payload_chars'));
            $this->config->setRetentionDays((int) $form->getInput('retention_days'));
            $this->repo->deleteOlderThanDays($this->config->getRetentionDays());

            $this->message('success', 'Configuration enregistrée.');
            $this->ctrl->redirect($this, 'configure');
            return;
        }

        $form->setValuesByPost();
        $this->setContent($form->getHTML() . $this->renderRecentEvents());
    }

    private function clearLog(): void
    {
        $this->repo->clear();
        $this->message('success', 'Journal des événements vidé.');
        $this->ctrl->redirect($this, 'configure');
    }

    private function buildForm(): ilPropertyFormGUI
    {
        include_once './Services/Form/classes/class.ilPropertyFormGUI.php';

        $form = new ilPropertyFormGUI();
        $form->setTitle('IliasTraxEventBridge — Debug événements ILIAS');
        $form->setFormAction($this->ctrl->getFormAction($this));

        $enabled = new ilCheckboxInputGUI('Activer le plugin', 'enabled');
        $enabled->setChecked($this->config->isEnabled());
        $enabled->setInfo('Si désactivé, aucun événement n’est journalisé.');
        $form->addItem($enabled);

        $debug = new ilCheckboxInputGUI('Mode debug événements', 'debug_enabled');
        $debug->setChecked($this->config->isDebugEnabled());
        $debug->setInfo('En V0.1, ce mode doit rester activé pour identifier les événements ILIAS 10 à mapper vers xAPI.');
        $form->addItem($debug);

        $maxPayload = new ilNumberInputGUI('Taille maximum du payload journalisé', 'max_payload_chars');
        $maxPayload->setRequired(true);
        $maxPayload->setMinValue(500);
        $maxPayload->setMaxValue(30000);
        $maxPayload->setValue((string) $this->config->getMaxPayloadChars());
        $maxPayload->setInfo('Valeur recommandée : 10000 caractères. Les payloads trop longs sont tronqués.');
        $form->addItem($maxPayload);

        $retention = new ilNumberInputGUI('Rétention du journal en jours', 'retention_days');
        $retention->setRequired(true);
        $retention->setMinValue(1);
        $retention->setMaxValue(365);
        $retention->setValue((string) $this->config->getRetentionDays());
        $form->addItem($retention);

        $form->addCommandButton('save', 'Enregistrer');
        $form->addCommandButton('clearLog', 'Vider le journal');

        return $form;
    }

    private function renderRecentEvents(): string
    {
        $rows = $this->repo->findRecent(100);

        $html = '<h2>100 derniers événements reçus</h2>';
        $html .= '<p>Objectif : entrer dans un cours, ouvrir des objets, lancer/terminer un test, puis relever les couples component/event utiles.</p>';

        if (count($rows) === 0) {
            return $html . '<p><em>Aucun événement journalisé pour le moment.</em></p>';
        }

        $html .= '<table class="std"><thead><tr>'
            . '<th>ID</th><th>Date</th><th>Component</th><th>Event</th><th>User</th><th>ref_id</th><th>obj_id</th><th>type</th><th>Params</th><th>URI</th>'
            . '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>'
                . '<td>' . $this->esc((string) $row['id']) . '</td>'
                . '<td>' . $this->esc((string) $row['created_at']) . '</td>'
                . '<td>' . $this->esc((string) $row['component']) . '</td>'
                . '<td><strong>' . $this->esc((string) $row['event_name']) . '</strong></td>'
                . '<td>' . $this->esc((string) $row['user_id']) . '</td>'
                . '<td>' . $this->esc((string) $row['ref_id']) . '</td>'
                . '<td>' . $this->esc((string) $row['obj_id']) . '</td>'
                . '<td>' . $this->esc((string) $row['obj_type']) . '</td>'
                . '<td>' . $this->esc((string) $row['param_keys']) . '</td>'
                . '<td>' . $this->esc((string) $row['request_uri']) . '</td>'
                . '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function setContent(string $html): void
    {
        if (is_object($this->tpl) && method_exists($this->tpl, 'setContent')) {
            $this->tpl->setContent($html);
        } else {
            echo $html;
        }
    }

    private function message(string $type, string $text): void
    {
        if (class_exists('ilUtil')) {
            if ($type === 'success' && method_exists('ilUtil', 'sendSuccess')) {
                ilUtil::sendSuccess($text, true);
            } elseif (method_exists('ilUtil', 'sendInfo')) {
                ilUtil::sendInfo($text, true);
            }
        }
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
