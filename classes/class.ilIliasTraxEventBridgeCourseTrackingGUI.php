<?php
/**
 * V0.7 course-level TRAX/xAPI configuration UI.
 *
 * @ilCtrl_IsCalledBy ilIliasTraxEventBridgeCourseTrackingGUI: ilObjCourseGUI, ilIliasTraxEventBridgeConfigGUI
 */
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseTrackingRepository.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseResourceResolver.php';

class ilIliasTraxEventBridgeCourseTrackingGUI
{
    /** @var mixed */
    private $ctrl;

    /** @var mixed */
    private $tpl;

    /** @var mixed */
    private $linkTarget;

    /** @var array<string,string> */
    private $commandMap;

    /** @var ilIliasTraxEventBridgeCourseTrackingRepository */
    private $repository;

    /** @var ilIliasTraxEventBridgeCourseResourceResolver */
    private $resolver;

    /**
     * @param mixed|null $linkTarget Optional controller target used when this UI is embedded in another GUI.
     * @param array<string,string> $commandMap Optional command aliases used by the embedding GUI.
     */
    public function __construct($linkTarget = null, array $commandMap = [])
    {
        global $DIC, $ilCtrl, $tpl;

        $this->ctrl = isset($DIC) && is_object($DIC) && method_exists($DIC, 'ctrl') ? $DIC->ctrl() : $ilCtrl;
        $this->tpl = isset($DIC) && (is_array($DIC) || $DIC instanceof ArrayAccess) && isset($DIC['tpl']) ? $DIC['tpl'] : $tpl;
        $this->linkTarget = $linkTarget;
        $this->commandMap = $commandMap;
        $this->repository = new ilIliasTraxEventBridgeCourseTrackingRepository();
        $this->resolver = new ilIliasTraxEventBridgeCourseResourceResolver($this->repository);
    }

    public function executeCommand(): void
    {
        $cmd = 'show';
        if (is_object($this->ctrl) && method_exists($this->ctrl, 'getCmd')) {
            $cmd = (string) $this->ctrl->getCmd('show');
        } elseif (isset($_GET['cmd']) && is_scalar($_GET['cmd'])) {
            $cmd = (string) $_GET['cmd'];
        }

        $this->performCommand($cmd !== '' ? $cmd : 'show');
    }

    public function performCommand(string $cmd): void
    {
        switch ($cmd) {
            case 'save':
                $this->save();
                break;
            case 'enableAll':
                $this->setAll(true);
                break;
            case 'disableAll':
                $this->setAll(false);
                break;
            case 'resetCourse':
                $this->resetCourse();
                break;
            case 'show':
            default:
                $this->show();
                break;
        }
    }

    private function show(): void
    {
        $courseRefId = $this->getCourseRefId();

        $html = $this->styles() . '<div class="itxeb-course-page">'
            . '<h1>TRAX / xAPI — configuration du cours</h1>'
            . '<p>Cette interface V0.7 permet de préparer l’activation des traces xAPI au niveau du cours et de ses ressources. Le filtrage effectif avant outbox sera ajouté dans le lot V0.7 suivant.</p>';

        if ($courseRefId <= 0) {
            $html .= $this->renderCourseRefForm();
            $this->setContent($html . '</div>');
            return;
        }

        if (!$this->repository->tablesExist()) {
            $html .= '<div class="itxeb-alert itxeb-alert-error"><strong>Tables V0.7 absentes.</strong> Exécuter la mise à jour du plugin pour créer <code>evnt_evhk_itxeb_ccfg</code> et <code>evnt_evhk_itxeb_rcfg</code>.</div>';
            $this->setContent($html . '</div>');
            return;
        }

        $course = $this->resolver->resolveCourse($courseRefId);
        if ((int) ($course['course_obj_id'] ?? 0) <= 0) {
            $html .= '<div class="itxeb-alert itxeb-alert-error"><strong>Cours introuvable.</strong> Le ref_id fourni ne correspond pas à un objet ILIAS résolu.</div>';
            $html .= $this->renderCourseRefForm();
            $this->setContent($html . '</div>');
            return;
        }

        if (!$this->canManageCourse($courseRefId)) {
            $html .= '<div class="itxeb-alert itxeb-alert-error"><strong>Accès refusé.</strong> Vous devez disposer des droits de modification/administration du cours pour modifier la configuration xAPI.</div>';
            $html .= $this->renderCourseSummary($course, false);
            $html .= $this->renderResourcesTable($course, false);
            $this->setContent($html . '</div>');
            return;
        }

        $html .= $this->renderCourseSummary($course, true)
            . $this->renderConfigForm($course)
            . $this->renderBulkActions($courseRefId)
            . '</div>';

        $this->setContent($html);
    }

    private function save(): void
    {
        $courseRefId = $this->getCourseRefId();
        if (!$this->canManageCourse($courseRefId)) {
            $this->failure('Accès refusé : configuration xAPI du cours non modifiée.');
            $this->redirectToShow($courseRefId);
            return;
        }

        $course = $this->resolver->resolveCourse($courseRefId);
        $resources = is_array($course['resources'] ?? null) ? $course['resources'] : [];
        $enabledResourceRefIds = $this->postIntArray('enabled_resources');
        $enabledLookup = array_fill_keys($enabledResourceRefIds, true);
        $updatedBy = $this->getCurrentUserId();

        $this->repository->setCourseEnabled(
            $courseRefId,
            (int) ($course['course_obj_id'] ?? 0),
            $this->postString('course_enabled') === '1',
            $updatedBy
        );

        foreach ($resources as $resource) {
            $refId = (int) ($resource['ref_id'] ?? 0);
            if ($refId <= 0) {
                continue;
            }
            $this->repository->setResourceEnabled(
                $courseRefId,
                $refId,
                (int) ($resource['obj_id'] ?? 0),
                (string) ($resource['obj_type'] ?? ''),
                isset($enabledLookup[$refId]),
                $updatedBy
            );
        }

        $this->success('Configuration TRAX / xAPI du cours enregistrée.');
        $this->redirectToShow($courseRefId);
    }

    private function setAll(bool $enabled): void
    {
        $courseRefId = $this->getCourseRefId();
        if (!$this->canManageCourse($courseRefId)) {
            $this->failure('Accès refusé : configuration xAPI du cours non modifiée.');
            $this->redirectToShow($courseRefId);
            return;
        }

        $course = $this->resolver->resolveCourse($courseRefId);
        $updatedBy = $this->getCurrentUserId();
        $this->repository->setCourseEnabled($courseRefId, (int) ($course['course_obj_id'] ?? 0), $enabled, $updatedBy);

        foreach ((array) ($course['resources'] ?? []) as $resource) {
            $this->repository->setResourceEnabled(
                $courseRefId,
                (int) ($resource['ref_id'] ?? 0),
                (int) ($resource['obj_id'] ?? 0),
                (string) ($resource['obj_type'] ?? ''),
                $enabled,
                $updatedBy
            );
        }

        $this->success($enabled ? 'Toutes les ressources traçables du cours sont activées.' : 'Toutes les ressources traçables du cours sont désactivées.');
        $this->redirectToShow($courseRefId);
    }

    private function resetCourse(): void
    {
        $courseRefId = $this->getCourseRefId();
        if (!$this->canManageCourse($courseRefId)) {
            $this->failure('Accès refusé : configuration xAPI du cours non modifiée.');
            $this->redirectToShow($courseRefId);
            return;
        }

        $this->repository->deleteCourseConfig($courseRefId);
        $this->success('Configuration TRAX / xAPI du cours réinitialisée.');
        $this->redirectToShow($courseRefId);
    }

    /** @param array<string,mixed> $course */
    private function renderCourseSummary(array $course, bool $editable): string
    {
        $configured = (bool) ($course['course_configured'] ?? false);
        $enabled = (bool) ($course['course_enabled'] ?? false);
        $resources = is_array($course['resources'] ?? null) ? $course['resources'] : [];
        $configuredCount = 0;
        $enabledCount = 0;
        foreach ($resources as $resource) {
            if (!empty($resource['configured'])) { $configuredCount++; }
            if (!empty($resource['enabled'])) { $enabledCount++; }
        }

        return '<section class="itxeb-section"><h2>Cours</h2>'
            . '<table class="std itxeb-summary-table"><tbody>'
            . $this->row('Titre', (string) ($course['course_title'] ?? ''))
            . $this->row('course_ref_id', (string) ($course['course_ref_id'] ?? ''))
            . $this->row('course_obj_id', (string) ($course['course_obj_id'] ?? ''))
            . $this->row('Configuration cours', $configured ? 'configuré' : 'non configuré')
            . $this->row('xAPI cours', $enabled ? 'activé' : 'désactivé')
            . $this->row('Ressources traçables', (string) count($resources))
            . $this->row('Ressources configurées', (string) $configuredCount)
            . $this->row('Ressources activées', (string) $enabledCount)
            . $this->row('Mode', $editable ? 'édition autorisée' : 'lecture seule')
            . '</tbody></table></section>';
    }

    /** @param array<string,mixed> $course */
    private function renderConfigForm(array $course): string
    {
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        $html = '<section class="itxeb-section"><h2>Activation xAPI</h2>'
            . '<form method="post" action="' . $this->esc($this->link('save', $courseRefId)) . '">'
            . '<input type="hidden" name="course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
            . '<div class="itxeb-alert"><strong>Important :</strong> ce lot enregistre les choix cours/ressources. Le filtrage effectif des statements sera ajouté dans le prochain lot V0.7.</div>'
            . '<p><label class="itxeb-check"><input type="checkbox" name="course_enabled" value="1"' . (!empty($course['course_enabled']) ? ' checked="checked"' : '') . '> Activer les traces xAPI pour ce cours</label></p>';

        $html .= $this->renderResourcesTable($course, true);
        $html .= '<p class="itxeb-actions"><button class="btn btn-primary" type="submit">Enregistrer la configuration xAPI</button></p></form></section>';
        return $html;
    }

    /** @param array<string,mixed> $course */
    private function renderResourcesTable(array $course, bool $editable): string
    {
        $resources = is_array($course['resources'] ?? null) ? $course['resources'] : [];
        if (count($resources) === 0) {
            return '<p><em>Aucune ressource traçable détectée dans ce cours.</em></p>';
        }

        $html = '<div class="itxeb-table-wrapper"><table class="std itxeb-resource-table"><thead><tr>'
            . '<th>xAPI</th><th>Type</th><th>Titre / chemin</th><th>ref_id</th><th>obj_id</th><th>Décision enregistrée</th>'
            . '</tr></thead><tbody>';

        foreach ($resources as $resource) {
            $refId = (int) ($resource['ref_id'] ?? 0);
            $enabled = !empty($resource['enabled']);
            $configured = !empty($resource['configured']);
            $decision = $configured ? ($enabled ? 'activée' : 'désactivée') : 'non configurée';
            $checkbox = '<input type="checkbox" name="enabled_resources[]" value="' . $this->esc((string) $refId) . '"'
                . ($enabled ? ' checked="checked"' : '')
                . ($editable ? '' : ' disabled="disabled"') . '>';

            $html .= '<tr>'
                . '<td class="itxeb-nowrap"><label class="itxeb-check">' . $checkbox . ' activer</label></td>'
                . '<td>' . $this->esc((string) ($resource['obj_type'] ?? '')) . '<br><span class="itxeb-muted">' . $this->esc((string) ($resource['resource_family'] ?? '')) . '</span></td>'
                . '<td><strong>' . $this->esc((string) ($resource['title'] ?? '')) . '</strong><br><span class="itxeb-muted">' . $this->esc((string) ($resource['path'] ?? '')) . '</span></td>'
                . '<td class="itxeb-nowrap">' . $this->esc((string) $refId) . '</td>'
                . '<td class="itxeb-nowrap">' . $this->esc((string) ($resource['obj_id'] ?? '')) . '</td>'
                . '<td><span class="itxeb-badge ' . ($enabled ? 'itxeb-badge-ok' : ($configured ? 'itxeb-badge-muted' : 'itxeb-badge-warn')) . '">' . $this->esc($decision) . '</span></td>'
                . '</tr>';
        }

        return $html . '</tbody></table></div>';
    }

    private function renderBulkActions(int $courseRefId): string
    {
        return '<section class="itxeb-section"><h2>Actions rapides</h2><p class="itxeb-actions">'
            . '<a class="btn btn-default" href="' . $this->esc($this->link('enableAll', $courseRefId)) . '">Tout activer</a> '
            . '<a class="btn btn-default" href="' . $this->esc($this->link('disableAll', $courseRefId)) . '">Tout désactiver</a> '
            . '<a class="btn btn-default" href="' . $this->esc($this->link('resetCourse', $courseRefId)) . '">Réinitialiser ce cours</a>'
            . '</p></section>';
    }

    private function renderCourseRefForm(): string
    {
        return '<section class="itxeb-section"><h2>Ouvrir un cours</h2>'
            . '<form method="post" action="' . $this->esc($this->link('show', 0)) . '"><table class="std itxeb-summary-table"><tbody>'
            . '<tr><td><label for="course_ref_id">course_ref_id</label></td><td><input class="form-control itxeb-input" id="course_ref_id" name="course_ref_id" type="number" min="1" value=""></td></tr>'
            . '</tbody></table><p class="itxeb-actions"><button class="btn btn-primary" type="submit">Ouvrir la configuration xAPI</button></p></form></section>';
    }

    private function canManageCourse(int $courseRefId): bool
    {
        if ($courseRefId <= 0) {
            return false;
        }

        foreach (['write', 'edit_permission', 'manage_members'] as $permission) {
            if ($this->checkAccess($permission, $courseRefId)) {
                return true;
            }
        }

        return false;
    }

    private function checkAccess(string $permission, int $refId): bool
    {
        try {
            if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'access')) {
                $access = $GLOBALS['DIC']->access();
                if (is_object($access) && method_exists($access, 'checkAccess')) {
                    return (bool) $access->checkAccess($permission, '', $refId);
                }
            }
        } catch (Throwable $ignored) {
            // Fallback below.
        }

        try {
            if (isset($GLOBALS['ilAccess']) && is_object($GLOBALS['ilAccess']) && method_exists($GLOBALS['ilAccess'], 'checkAccess')) {
                return (bool) $GLOBALS['ilAccess']->checkAccess($permission, '', $refId);
            }
        } catch (Throwable $ignored) {
            return false;
        }

        return false;
    }

    private function getCourseRefId(): int
    {
        foreach (['course_ref_id', 'ref_id'] as $key) {
            if (isset($_POST[$key]) && is_scalar($_POST[$key]) && (int) $_POST[$key] > 0) {
                return (int) $_POST[$key];
            }
            if (isset($_GET[$key]) && is_scalar($_GET[$key]) && (int) $_GET[$key] > 0) {
                return (int) $_GET[$key];
            }
        }

        return 0;
    }

    /** @return array<int,int> */
    private function postIntArray(string $key): array
    {
        if (!isset($_POST[$key]) || !is_array($_POST[$key])) {
            return [];
        }

        $values = [];
        foreach ($_POST[$key] as $value) {
            if (is_scalar($value) && (int) $value > 0) {
                $values[] = (int) $value;
            }
        }

        return array_values(array_unique($values));
    }

    private function postString(string $key): string
    {
        return isset($_POST[$key]) && is_scalar($_POST[$key]) ? trim((string) $_POST[$key]) : '';
    }

    private function getCurrentUserId(): int
    {
        try {
            if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'user')) {
                $user = $GLOBALS['DIC']->user();
                if (is_object($user) && method_exists($user, 'getId')) {
                    return (int) $user->getId();
                }
            }
        } catch (Throwable $ignored) {
            // Fallback below.
        }

        if (isset($GLOBALS['ilUser']) && is_object($GLOBALS['ilUser']) && method_exists($GLOBALS['ilUser'], 'getId')) {
            return (int) $GLOBALS['ilUser']->getId();
        }

        return 0;
    }

    private function link(string $cmd, int $courseRefId): string
    {
        $target = is_object($this->linkTarget) ? $this->linkTarget : $this;
        $mappedCmd = $this->commandMap[$cmd] ?? $cmd;

        if (is_object($this->ctrl)) {
            try {
                if ($courseRefId > 0 && method_exists($this->ctrl, 'setParameter')) {
                    $this->ctrl->setParameter($target, 'course_ref_id', $courseRefId);
                }
                if (method_exists($this->ctrl, 'getLinkTarget')) {
                    return (string) $this->ctrl->getLinkTarget($target, $mappedCmd);
                }
            } catch (Throwable $ignored) {
                // Fallback below.
            }
        }

        $query = '?cmd=' . rawurlencode($mappedCmd);
        if ($courseRefId > 0) {
            $query .= '&course_ref_id=' . rawurlencode((string) $courseRefId);
        }
        return $query;
    }

    private function redirectToShow(int $courseRefId): void
    {
        $target = is_object($this->linkTarget) ? $this->linkTarget : $this;
        $mappedCmd = $this->commandMap['show'] ?? 'show';

        if (is_object($this->ctrl)) {
            try {
                if ($courseRefId > 0 && method_exists($this->ctrl, 'setParameter')) {
                    $this->ctrl->setParameter($target, 'course_ref_id', $courseRefId);
                }
                if (method_exists($this->ctrl, 'redirect')) {
                    $this->ctrl->redirect($target, $mappedCmd);
                    return;
                }
            } catch (Throwable $ignored) {
                // Fallback below.
            }
        }

        header('Location: ' . $this->link('show', $courseRefId));
        exit;
    }

    private function row(string $label, string $value): string
    {
        return '<tr><td>' . $this->esc($label) . '</td><td>' . $this->esc($value) . '</td></tr>';
    }

    private function setContent(string $html): void
    {
        if (is_object($this->tpl) && method_exists($this->tpl, 'setContent')) {
            $this->tpl->setContent($html);
        } else {
            echo $html;
        }
    }

    private function success(string $message): void
    {
        if (class_exists('ilUtil') && method_exists('ilUtil', 'sendSuccess')) {
            ilUtil::sendSuccess($message, true);
        }
    }

    private function failure(string $message): void
    {
        if (class_exists('ilUtil') && method_exists('ilUtil', 'sendFailure')) {
            ilUtil::sendFailure($message, true);
        }
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function styles(): string
    {
        return '<style>'
            . '.itxeb-course-page{max-width:none;width:100%;margin:0 0 4rem 0}.itxeb-section{margin-bottom:1.5rem}.itxeb-alert{max-width:1100px;padding:.65rem .8rem;margin:.4rem 0 .9rem;border:1px solid #bce8f1;background:#eef8fc;border-radius:4px;line-height:1.4}.itxeb-alert-error{border-color:#ebccd1;background:#f2dede;color:#a94442}'
            . '.itxeb-course-page table.std{width:100%;border-collapse:collapse;background:#fff}.itxeb-course-page table.std th,.itxeb-course-page table.std td{padding:.6rem .75rem;vertical-align:top;line-height:1.35}.itxeb-course-page table.std th{white-space:nowrap;background:#f7f7f7}.itxeb-summary-table{max-width:1100px}.itxeb-summary-table td:first-child{width:230px;min-width:230px;font-weight:600;white-space:nowrap}.itxeb-input{width:100%;max-width:360px;box-sizing:border-box}.itxeb-actions{margin:.8rem 0 1.2rem}.itxeb-check{font-weight:400}.itxeb-muted{font-size:.9em;color:#666}'
            . '.itxeb-table-wrapper{width:100%;max-width:100%;overflow-x:auto;border:1px solid #ddd;border-radius:4px;background:#fff;margin:.4rem 0 1rem}.itxeb-resource-table{min-width:1050px;table-layout:fixed}.itxeb-resource-table th:nth-child(1),.itxeb-resource-table td:nth-child(1){width:120px}.itxeb-resource-table th:nth-child(2),.itxeb-resource-table td:nth-child(2){width:150px}.itxeb-resource-table th:nth-child(4),.itxeb-resource-table td:nth-child(4){width:95px}.itxeb-resource-table th:nth-child(5),.itxeb-resource-table td:nth-child(5){width:95px}.itxeb-resource-table th:nth-child(6),.itxeb-resource-table td:nth-child(6){width:170px}.itxeb-nowrap{white-space:nowrap}.itxeb-badge{display:inline-block;padding:.2rem .45rem;border-radius:3px;background:#eee;font-weight:600}.itxeb-badge-ok{background:#dff0d8}.itxeb-badge-warn{background:#fcf8e3}.itxeb-badge-muted{background:#eee}'
            . '@media (max-width:900px){.itxeb-summary-table td:first-child{width:auto;min-width:0;white-space:normal}.itxeb-resource-table{min-width:900px}}</style>';
    }
}
