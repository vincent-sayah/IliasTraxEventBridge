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

        $this->plugin->includeClass('class.ilIliasTraxEventBridgeConfig.php');
        $this->plugin->includeClass('class.ilIliasTraxEventBridgeEventDebugRepository.php');

        $this->config = new ilIliasTraxEventBridgeConfig();
        $this->repo = new ilIliasTraxEventBridgeEventDebugRepository();
    }

    private function configure(): void
    {
        $html = '';
        $html .= '<h1>IliasTraxEventBridge — Debug événements ILIAS</h1>';
        $html .= '<p><strong>Version 0.1.2 :</strong> écran de configuration minimal, compatible avec le routage ilCtrl ILIAS 10.</p>';
        $html .= '<p>Cette version sert uniquement à observer les événements réellement émis par ILIAS 10 avant le mapping xAPI vers TRAX.</p>';

        $html .= '<h2>État</h2>';
        $html .= '<table class="std">';
        $html .= '<tr><td>Plugin actif</td><td>' . ($this->config->isEnabled() ? 'oui' : 'non') . '</td></tr>';
        $html .= '<tr><td>Mode debug</td><td>' . ($this->config->isDebugEnabled() ? 'oui' : 'non') . '</td></tr>';
        $html .= '<tr><td>Taille maximum payload</td><td>' . $this->esc((string) $this->config->getMaxPayloadChars()) . ' caractères</td></tr>';
        $html .= '<tr><td>Rétention</td><td>' . $this->esc((string) $this->config->getRetentionDays()) . ' jours</td></tr>';
        $html .= '</table>';

        $html .= '<p>';
        $html .= '<a class="btn btn-default" href="' . $this->esc($this->ctrl->getLinkTarget($this, 'clearLog')) . '">Vider le journal</a>';
        $html .= '</p>';

        $html .= $this->renderRecentEvents();
        $this->setContent($html);
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
        $rows = $this->repo->findRecent(100);

        $html = '<h2>100 derniers événements reçus</h2>';
        $html .= '<p>Effectue les actions cibles dans ILIAS : entrer dans un cours, ouvrir un objet, lancer puis terminer un test. Les événements apparaîtront ici.</p>';

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
            return;
        }

        echo $html;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
