<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIBridge.php';

/**
 * Full course-level xAPI configuration screen for the UIHook companion plugin.
 *
 * This class reuses the main plugin repositories/resolver and writes to the
 * V0.7 configuration tables: evnt_evhk_itxeb_ccfg and evnt_evhk_itxeb_rcfg.
 */
class ilIliasTraxEventBridgeCourseUIScreen
{
    /** @var ilIliasTraxEventBridgeCourseUIBridge */
    private $bridge;

    /** @var ilIliasTraxEventBridgeCourseTrackingRepository|null */
    private $repository;

    /** @var ilIliasTraxEventBridgeCourseResourceResolver|null */
    private $resolver;

    /** @var string */
    private $message = '';

    /** @var string */
    private $messageType = 'info';

    public function __construct(ilIliasTraxEventBridgeCourseUIBridge $bridge)
    {
        $this->bridge = $bridge;
        if ($this->bridge->loadCourseTrackingClasses()) {
            $this->repository = new ilIliasTraxEventBridgeCourseTrackingRepository();
            $this->resolver = new ilIliasTraxEventBridgeCourseResourceResolver($this->repository);
        }
    }

    public function handle(): string
    {
        $courseRefId = $this->getCourseRefId();
        $cmd = $this->getCommand();

        if (!$this->repository || !$this->resolver) {
            return $this->renderShell('<div class="itxeb-cui-alert itxeb-cui-error">Plugin principal ou classes de configuration indisponibles.</div>', 0, '');
        }

        if ($courseRefId <= 0) {
            return $this->renderShell('<div class="itxeb-cui-alert itxeb-cui-error">Cours introuvable : course_ref_id manquant.</div>', 0, '');
        }

        if (!$this->repository->tablesExist()) {
            return $this->renderShell('<div class="itxeb-cui-alert itxeb-cui-error">Tables V0.7 absentes : evnt_evhk_itxeb_ccfg / evnt_evhk_itxeb_rcfg.</div>', $courseRefId, '');
        }

        if (!$this->bridge->canManageCourse($courseRefId)) {
            $course = $this->resolver->resolveCourse($courseRefId);
            return $this->renderShell(
                '<div class="itxeb-cui-alert itxeb-cui-error">Accès refusé : droits de gestion du cours insuffisants.</div>'
                . $this->renderCourseSummary($course),
                $courseRefId,
                (string) ($course['course_title'] ?? '')
            );
        }

        if ($cmd === 'saveCourseTracking') {
            $this->save($courseRefId);
        } elseif ($cmd === 'enableAllCourseTracking') {
            $this->setAll($courseRefId, true);
        } elseif ($cmd === 'disableAllCourseTracking') {
            $this->setAll($courseRefId, false);
        } elseif ($cmd === 'resetCourseTracking') {
            $this->resetCourse($courseRefId);
        }

        $course = $this->resolver->resolveCourse($courseRefId);
        $html = $this->renderMessage()
            . $this->renderCourseSummary($course)
            . $this->renderConfigForm($course)
            . $this->renderBulkActions($courseRefId);

        return $this->renderShell($html, $courseRefId, (string) ($course['course_title'] ?? ''));
    }

    private function save(int $courseRefId): void
    {
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

        $this->message = 'Configuration xAPI du cours enregistrée.';
        $this->messageType = 'success';
    }

    private function setAll(int $courseRefId, bool $enabled): void
    {
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

        $this->message = $enabled
            ? 'Toutes les ressources traçables du cours sont activées.'
            : 'Toutes les ressources traçables du cours sont désactivées.';
        $this->messageType = 'success';
    }

    private function resetCourse(int $courseRefId): void
    {
        $this->repository->deleteCourseConfig($courseRefId);
        $this->message = 'Configuration xAPI du cours réinitialisée.';
        $this->messageType = 'success';
    }

    /** @param array<string,mixed> $course */
    private function renderCourseSummary(array $course): string
    {
        $resources = is_array($course['resources'] ?? null) ? $course['resources'] : [];
        $configuredCount = 0;
        $enabledCount = 0;
        foreach ($resources as $resource) {
            if (!empty($resource['configured'])) {
                $configuredCount++;
            }
            if (!empty($resource['enabled'])) {
                $enabledCount++;
            }
        }

        return '<section class="itxeb-cui-section"><h2>Cours</h2><table class="itxeb-cui-table"><tbody>'
            . $this->row('Titre', (string) ($course['course_title'] ?? ''))
            . $this->row('course_ref_id', (string) ($course['course_ref_id'] ?? ''))
            . $this->row('course_obj_id', (string) ($course['course_obj_id'] ?? ''))
            . $this->row('Configuration cours', !empty($course['course_configured']) ? 'configuré' : 'non configuré')
            . $this->row('xAPI cours', !empty($course['course_enabled']) ? 'activé' : 'désactivé')
            . $this->row('Ressources traçables', (string) count($resources))
            . $this->row('Ressources configurées', (string) $configuredCount)
            . $this->row('Ressources activées', (string) $enabledCount)
            . '</tbody></table></section>';
    }

    /** @param array<string,mixed> $course */
    private function renderConfigForm(array $course): string
    {
        $courseRefId = (int) ($course['course_ref_id'] ?? 0);
        return '<section class="itxeb-cui-section"><h2>Activation xAPI</h2>'
            . '<form method="post" action="' . $this->esc($this->currentRequestUri()) . '">'
            . '<input type="hidden" name="itxeb_cui_cmd" value="saveCourseTracking">'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
            . '<p><label class="itxeb-cui-check"><input type="checkbox" name="course_enabled" value="1"' . (!empty($course['course_enabled']) ? ' checked="checked"' : '') . '> Activer les traces xAPI pour ce cours</label></p>'
            . $this->renderResourcesTable($course)
            . '<p class="itxeb-cui-actions"><button class="btn btn-primary" type="submit">Enregistrer la configuration xAPI</button></p>'
            . '</form></section>';
    }

    /** @param array<string,mixed> $course */
    private function renderResourcesTable(array $course): string
    {
        $resources = is_array($course['resources'] ?? null) ? $course['resources'] : [];
        if (count($resources) === 0) {
            return '<p><em>Aucune ressource traçable détectée dans ce cours.</em></p>';
        }

        $html = '<div class="itxeb-cui-table-wrapper"><table class="itxeb-cui-table itxeb-cui-resource-table"><thead><tr>'
            . '<th>xAPI</th><th>Type</th><th>Titre / chemin</th><th>ref_id</th><th>obj_id</th><th>Décision</th>'
            . '</tr></thead><tbody>';

        foreach ($resources as $resource) {
            $refId = (int) ($resource['ref_id'] ?? 0);
            $enabled = !empty($resource['enabled']);
            $configured = !empty($resource['configured']);
            $decision = $configured ? ($enabled ? 'activée' : 'désactivée') : 'non configurée';

            $html .= '<tr>'
                . '<td><label class="itxeb-cui-check"><input type="checkbox" name="enabled_resources[]" value="' . $this->esc((string) $refId) . '"' . ($enabled ? ' checked="checked"' : '') . '> activer</label></td>'
                . '<td>' . $this->esc((string) ($resource['obj_type'] ?? '')) . '<br><span class="itxeb-cui-muted">' . $this->esc((string) ($resource['resource_family'] ?? '')) . '</span></td>'
                . '<td><strong>' . $this->esc((string) ($resource['title'] ?? '')) . '</strong><br><span class="itxeb-cui-muted">' . $this->esc((string) ($resource['path'] ?? '')) . '</span></td>'
                . '<td>' . $this->esc((string) $refId) . '</td>'
                . '<td>' . $this->esc((string) ($resource['obj_id'] ?? '')) . '</td>'
                . '<td><span class="itxeb-cui-badge ' . ($enabled ? 'itxeb-cui-badge-ok' : ($configured ? 'itxeb-cui-badge-muted' : 'itxeb-cui-badge-warn')) . '">' . $this->esc($decision) . '</span></td>'
                . '</tr>';
        }

        return $html . '</tbody></table></div>';
    }

    private function renderBulkActions(int $courseRefId): string
    {
        return '<section class="itxeb-cui-section"><h2>Actions rapides</h2><div class="itxeb-cui-actions">'
            . $this->actionForm('enableAllCourseTracking', $courseRefId, 'Tout activer')
            . $this->actionForm('disableAllCourseTracking', $courseRefId, 'Tout désactiver')
            . $this->actionForm('resetCourseTracking', $courseRefId, 'Réinitialiser ce cours')
            . '</div></section>';
    }

    private function actionForm(string $cmd, int $courseRefId, string $label): string
    {
        return '<form method="post" action="' . $this->esc($this->currentRequestUri()) . '" class="itxeb-cui-inline-form">'
            . '<input type="hidden" name="itxeb_cui_cmd" value="' . $this->esc($cmd) . '">'
            . '<input type="hidden" name="itxeb_course_ref_id" value="' . $this->esc((string) $courseRefId) . '">'
            . '<button class="btn btn-default" type="submit">' . $this->esc($label) . '</button>'
            . '</form>';
    }

    private function renderShell(string $content, int $courseRefId, string $courseTitle): string
    {
        $title = trim($courseTitle) !== '' ? 'Suivi xAPI — ' . $courseTitle : 'Suivi xAPI — configuration du cours';
        return $this->styles()
            . '<div id="itxeb-course-ui-screen" class="itxeb-cui-screen">'
            . '<div class="itxeb-cui-header"><h1>' . $this->esc($title) . '</h1><p>Configuration xAPI depuis l’objet cours' . ($courseRefId > 0 ? ' — course_ref_id ' . $this->esc((string) $courseRefId) : '') . '</p></div>'
            . $content
            . '</div>';
    }

    private function renderMessage(): string
    {
        if ($this->message === '') {
            return '';
        }
        $class = $this->messageType === 'success' ? 'itxeb-cui-success' : 'itxeb-cui-alert';
        return '<div class="itxeb-cui-alert ' . $class . '">' . $this->esc($this->message) . '</div>';
    }

    private function row(string $label, string $value): string
    {
        return '<tr><td><strong>' . $this->esc($label) . '</strong></td><td>' . $this->esc($value) . '</td></tr>';
    }

    private function getCommand(): string
    {
        $cmd = $this->requestValue($_POST, 'itxeb_cui_cmd');
        if ($cmd === '') {
            $cmd = $this->requestValue($_GET, 'itxeb_cui_cmd');
        }
        return $cmd !== '' ? $cmd : 'showCourseTracking';
    }

    private function getCourseRefId(): int
    {
        foreach (['itxeb_course_ref_id', 'course_ref_id', 'ref_id'] as $key) {
            $value = $this->requestValue($_POST, $key);
            if ((int) $value > 0) {
                return (int) $value;
            }
            $value = $this->requestValue($_GET, $key);
            if ((int) $value > 0) {
                return (int) $value;
            }
        }

        return $this->bridge->detectCourseRefId();
    }

    /** @return array<int,int> */
    private function postIntArray(string $key): array
    {
        $raw = $this->requestRawValue($_POST, $key);
        if (!is_array($raw)) {
            return [];
        }

        $values = [];
        foreach ($raw as $value) {
            if (is_scalar($value) && (int) $value > 0) {
                $values[] = (int) $value;
            }
        }
        return array_values(array_unique($values));
    }

    private function postString(string $key): string
    {
        return trim($this->requestValue($_POST, $key));
    }

    private function requestValue($source, string $key): string
    {
        $value = $this->requestRawValue($source, $key);
        return is_scalar($value) ? (string) $value : '';
    }

    private function requestRawValue($source, string $key)
    {
        try {
            if (is_array($source)) {
                return $source[$key] ?? null;
            }
            if ($source instanceof ArrayAccess) {
                return isset($source[$key]) ? $source[$key] : null;
            }
            if (is_object($source)) {
                if (method_exists($source, 'offsetExists') && method_exists($source, 'offsetGet')) {
                    return $source->offsetExists($key) ? $source->offsetGet($key) : null;
                }
                if (method_exists($source, 'retrieve')) {
                    return $source->retrieve($key, false);
                }
            }
        } catch (Throwable $ignored) {
            return null;
        }
        return null;
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

    private function currentRequestUri(): string
    {
        return isset($_SERVER['REQUEST_URI']) && is_scalar($_SERVER['REQUEST_URI'])
            ? (string) $_SERVER['REQUEST_URI']
            : '';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function styles(): string
    {
        return '<style>'
            . '#itxeb-course-ui-screen{margin:0;padding:0;font-family:Arial,sans-serif}'
            . '#itxeb-course-ui-screen .itxeb-cui-header{border-bottom:1px solid #d5dde5;margin-bottom:16px;padding-bottom:12px}'
            . '#itxeb-course-ui-screen h1{font-size:24px;margin:.2rem 0 .3rem}#itxeb-course-ui-screen h2{font-size:18px;margin:1rem 0 .5rem}'
            . '#itxeb-course-ui-screen .itxeb-cui-section{margin-bottom:18px}.itxeb-cui-alert{padding:.65rem .8rem;margin:.4rem 0 .9rem;border:1px solid #bce8f1;background:#eef8fc;border-radius:4px}.itxeb-cui-error{border-color:#ebccd1;background:#f2dede;color:#a94442}.itxeb-cui-success{border-color:#d6e9c6;background:#dff0d8;color:#3c763d}'
            . '#itxeb-course-ui-screen .itxeb-cui-table{width:100%;border-collapse:collapse;background:#fff}#itxeb-course-ui-screen .itxeb-cui-table th,#itxeb-course-ui-screen .itxeb-cui-table td{border:1px solid #ddd;padding:.5rem .6rem;vertical-align:top;line-height:1.35}#itxeb-course-ui-screen .itxeb-cui-table th{background:#f7f7f7}'
            . '#itxeb-course-ui-screen .itxeb-cui-table-wrapper{overflow-x:auto;background:#fff;border:1px solid #ddd;border-radius:4px}.itxeb-cui-resource-table{min-width:1050px}.itxeb-cui-muted{font-size:.9em;color:#666}.itxeb-cui-check{font-weight:400}.itxeb-cui-actions{display:flex;gap:.5rem;flex-wrap:wrap;margin:.8rem 0}.itxeb-cui-inline-form{display:inline-block;margin:0}.itxeb-cui-badge{display:inline-block;padding:.2rem .45rem;border-radius:3px;background:#eee;font-weight:600}.itxeb-cui-badge-ok{background:#dff0d8}.itxeb-cui-badge-warn{background:#fcf8e3}.itxeb-cui-badge-muted{background:#eee}'
            . '</style>';
    }
}
