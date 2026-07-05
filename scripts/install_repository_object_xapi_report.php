<?php
/**
 * Installe un vrai RepositoryObjectPlugin ILIAS 10 : IliasTraxReport.
 *
 * Objectif : remplacer les tentatives d'onglet de cours UIHook par un objet
 * ILIAS propre, ajoutable dans un cours, avec ses propres onglets natifs ILIAS :
 * - Tableau de bord
 * - Analyse
 * - Expert
 * - Configuration
 *
 * À lancer depuis la racine du plugin EventHook IliasTraxEventBridge :
 * php scripts/install_repository_object_xapi_report.php
 */

function write_file_checked(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, "ERREUR: création répertoire impossible: {$dir}\n");
        exit(1);
    }
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: écriture impossible: {$path}\n");
        exit(1);
    }
    echo "WRITE: {$path}\n";
}

$mainRoot = getcwd();
if (!is_file($mainRoot . '/plugin.php') || !is_dir($mainRoot . '/classes')) {
    fwrite(STDERR, "ERREUR: lance ce script depuis la racine du plugin EventHook IliasTraxEventBridge.\n");
    exit(1);
}

$eventHookSuffix = '/Services/EventHandling/EventHook/IliasTraxEventBridge';
if (substr($mainRoot, -strlen($eventHookSuffix)) !== $eventHookSuffix) {
    fwrite(STDERR, "ERREUR: chemin inattendu pour le plugin principal: {$mainRoot}\n");
    exit(1);
}

$customizingRoot = substr($mainRoot, 0, -strlen($eventHookSuffix));
$target = $customizingRoot . '/Services/Repository/RepositoryObject/IliasTraxReport';

$pluginPhp = <<<'PHP'
<?php

$id = 'xtrp';
$version = '0.1.0';
$ilias_min_version = '10.0.0';
$ilias_max_version = '10.999.999';
$responsible = 'TRAX / ILIAS integration';
$responsible_mail = 'noreply@localhost';
PHP;

$pluginClass = <<<'PHP'
<?php

class ilIliasTraxReportPlugin extends ilRepositoryObjectPlugin
{
    public const PLUGIN_NAME = 'IliasTraxReport';
    public const OBJECT_TYPE = 'xtrp';
    public const MAIN_PLUGIN_NAME = 'IliasTraxEventBridge';

    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }

    public function getMainPluginPath(): string
    {
        return dirname(__DIR__, 5)
            . '/EventHandling/EventHook/'
            . self::MAIN_PLUGIN_NAME;
    }
}
PHP;

$objectClass = <<<'PHP'
<?php

class ilObjIliasTraxReport extends ilObjectPlugin
{
    public function initType(): void
    {
        $this->setType(ilIliasTraxReportPlugin::OBJECT_TYPE);
    }

    protected function doCreate(bool $clone_mode = false): void
    {
    }

    protected function doRead(): void
    {
    }

    protected function doUpdate(): void
    {
    }

    protected function doDelete(): void
    {
    }

    protected function doCloneObject($new_obj, int $a_target_id, ?int $a_copy_id = null): void
    {
    }
}
PHP;

$listClass = <<<'PHP'
<?php

class ilObjIliasTraxReportListGUI extends ilObjectPluginListGUI
{
    public function initType(): void
    {
        $this->setType(ilIliasTraxReportPlugin::OBJECT_TYPE);
    }

    public function getGuiClass(): string
    {
        return ilObjIliasTraxReportGUI::class;
    }

    public function initCommands(): array
    {
        return [
            [
                'permission' => 'read',
                'cmd' => 'showDashboard',
                'default' => true,
            ],
            [
                'permission' => 'write',
                'cmd' => 'showConfig',
                'default' => false,
            ],
        ];
    }

    public function getProperties(): array
    {
        return [];
    }
}
PHP;

$accessClass = <<<'PHP'
<?php

class ilObjIliasTraxReportAccess extends ilObjectPluginAccess
{
}
PHP;

$guiClass = <<<'PHP'
<?php

class ilObjIliasTraxReportGUI extends ilObjectPluginGUI
{
    private ?int $parentCourseRefId = null;

    public function getAfterCreationCmd(): string
    {
        return 'showDashboard';
    }

    public function getStandardCmd(): string
    {
        return 'showDashboard';
    }

    public function performCommand(string $cmd): void
    {
        $this->parentCourseRefId = $this->detectParentCourseRefId();
        $this->setTabs($cmd);

        switch ($cmd) {
            case 'showAnalysis':
                $this->renderPage('analysis');
                break;
            case 'showExpert':
                $this->renderPage('expert');
                break;
            case 'showConfig':
                $this->renderPage('config');
                break;
            case 'showDashboard':
            default:
                $this->renderPage('dashboard');
                break;
        }
    }

    protected function setTabs(string $activeCmd): void
    {
        global $DIC;

        $tabs = $DIC->tabs();
        $ctrl = $DIC->ctrl();

        $tabs->addTab('itxrp_dashboard', 'Tableau de bord', $ctrl->getLinkTarget($this, 'showDashboard'));
        $tabs->addTab('itxrp_analysis', 'Analyse', $ctrl->getLinkTarget($this, 'showAnalysis'));
        $tabs->addTab('itxrp_expert', 'Expert', $ctrl->getLinkTarget($this, 'showExpert'));
        $tabs->addTab('itxrp_config', 'Configuration', $ctrl->getLinkTarget($this, 'showConfig'));

        $map = [
            'showAnalysis' => 'itxrp_analysis',
            'showExpert' => 'itxrp_expert',
            'showConfig' => 'itxrp_config',
            'showDashboard' => 'itxrp_dashboard',
        ];
        $tabs->activateTab($map[$activeCmd] ?? 'itxrp_dashboard');
    }

    private function renderPage(string $view): void
    {
        global $DIC;

        $tpl = $DIC->ui()->mainTemplate();
        $courseRefId = (int) ($this->parentCourseRefId ?? 0);
        $courseTitle = $courseRefId > 0 ? $this->lookupTitleByRefId($courseRefId) : '';

        $title = [
            'dashboard' => 'Suivi xAPI - Tableau de bord',
            'analysis' => 'Suivi xAPI - Analyse',
            'expert' => 'Suivi xAPI - Expert',
            'config' => 'Suivi xAPI - Configuration',
        ][$view] ?? 'Suivi xAPI';

        $html = '<div class="itxrp-page">';
        $html .= '<h2>' . $this->esc($title) . '</h2>';
        $html .= '<p>Ce suivi xAPI est fourni par un objet ILIAS de type RepositoryObjectPlugin. Les onglets affichés ici sont les onglets natifs ILIAS de cet objet, pas une injection HTML dans les onglets du cours.</p>';
        if ($courseRefId > 0) {
            $html .= '<p><strong>Cours parent détecté :</strong> ' . $this->esc($courseTitle) . ' <span style="color:#666;">ref_id=' . $courseRefId . '</span></p>';
        } else {
            $html .= '<p><strong>Attention :</strong> aucun cours parent n’a été détecté. Place cet objet dans un cours pour obtenir le contexte xAPI.</p>';
        }

        if ($view === 'dashboard') {
            $html .= '<div class="ilInfoMessage">Le tableau de bord xAPI sera raccordé ici aux mêmes données que le plugin IliasTraxEventBridge.</div>';
        } elseif ($view === 'analysis') {
            $html .= '<div class="ilInfoMessage">L’analyse pédagogique et l’analyse IA seront raccordées ici, dans un onglet ILIAS natif.</div>';
        } elseif ($view === 'expert') {
            $html .= '<div class="ilInfoMessage">La vue expert affichera les détails techniques des traces et ressources.</div>';
        } else {
            $html .= '<div class="ilInfoMessage">La configuration locale de l’objet sera ajoutée ici si nécessaire.</div>';
        }

        $html .= '</div>';
        $tpl->setContent($html);
    }

    private function detectParentCourseRefId(): int
    {
        global $DIC;

        try {
            $refId = (int) ($_GET['ref_id'] ?? 0);
            if ($refId <= 0) {
                return 0;
            }
            $tree = $DIC->repositoryTree();
            $current = $refId;
            for ($i = 0; $i < 20; $i++) {
                $parent = (int) $tree->getParentId($current);
                if ($parent <= 0 || $parent === $current) {
                    return 0;
                }
                if (class_exists('ilObject') && method_exists('ilObject', '_lookupType')) {
                    if ((string) ilObject::_lookupType($parent, true) === 'crs') {
                        return $parent;
                    }
                }
                $current = $parent;
            }
        } catch (Throwable $ignored) {
        }

        return 0;
    }

    private function lookupTitleByRefId(int $refId): string
    {
        if ($refId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupObjId') || !method_exists('ilObject', '_lookupTitle')) {
            return '';
        }
        try {
            $objId = (int) ilObject::_lookupObjId($refId);
            return $objId > 0 ? (string) ilObject::_lookupTitle($objId) : '';
        } catch (Throwable $ignored) {
            return '';
        }
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
PHP;

$dbUpdate = <<<'PHP'
<?php
// Version initiale : aucune table spécifique. L'objet s'appuie sur le plugin
// principal IliasTraxEventBridge pour les données xAPI.
?>
PHP;

$langFr = <<<'TEXT'
<#1#> rep_robj_xtrp#:#Suivi xAPI
<#1#> rep_robj_xtrp_description#:#Tableau de bord et analyse xAPI pour un cours ILIAS
<#1#> obj_xtrp#:#Suivi xAPI
<#1#> obj_xtrp_description#:#Tableau de bord et analyse xAPI pour un cours ILIAS
TEXT;

$langEn = <<<'TEXT'
<#1#> rep_robj_xtrp#:#xAPI Tracking
<#1#> rep_robj_xtrp_description#:#xAPI dashboard and analysis for an ILIAS course
<#1#> obj_xtrp#:#xAPI Tracking
<#1#> obj_xtrp_description#:#xAPI dashboard and analysis for an ILIAS course
TEXT;

write_file_checked($target . '/plugin.php', $pluginPhp);
write_file_checked($target . '/classes/class.ilIliasTraxReportPlugin.php', $pluginClass);
write_file_checked($target . '/classes/class.ilObjIliasTraxReport.php', $objectClass);
write_file_checked($target . '/classes/class.ilObjIliasTraxReportGUI.php', $guiClass);
write_file_checked($target . '/classes/class.ilObjIliasTraxReportListGUI.php', $listClass);
write_file_checked($target . '/classes/class.ilObjIliasTraxReportAccess.php', $accessClass);
write_file_checked($target . '/sql/dbupdate.php', $dbUpdate);
write_file_checked($target . '/lang/ilias_fr.lang', $langFr);
write_file_checked($target . '/lang/ilias_en.lang', $langEn);

$files = [
    $target . '/plugin.php',
    $target . '/classes/class.ilIliasTraxReportPlugin.php',
    $target . '/classes/class.ilObjIliasTraxReport.php',
    $target . '/classes/class.ilObjIliasTraxReportGUI.php',
    $target . '/classes/class.ilObjIliasTraxReportListGUI.php',
    $target . '/classes/class.ilObjIliasTraxReportAccess.php',
    $target . '/sql/dbupdate.php',
];

foreach ($files as $file) {
    $cmd = 'php -l ' . escapeshellarg($file);
    passthru($cmd, $code);
    if ($code !== 0) {
        fwrite(STDERR, "ERREUR: syntaxe PHP invalide: {$file}\n");
        exit($code);
    }
}

@chmod($target, 0775);
@chown($target, 'apache');
@chgrp($target, 'apache');

echo "\nInstallation fichier terminée.\n";
echo "Plugin RepositoryObject : {$target}\n";
echo "Étape suivante dans ILIAS : Administration > Plugins > IliasTraxReport > Update puis Activate.\n";
echo "Ensuite dans un cours : Ajouter un nouvel objet > Suivi xAPI.\n";
