<?php

declare(strict_types=1);

/**
 * V0.23.1 MediaCast beacon handling.
 *
 * V0.23 sends browser beacons correctly, but relying on a new ILIAS ReadEvent
 * for those beacon requests is not reliable. This patch makes the companion
 * UIHook directly convert MediaCast client beacons into outbox statements while
 * still using the existing StatementFactory and OutboxRepository.
 */

$root = dirname(__DIR__);

function itxeb231_read(string $path): string
{
    if (!is_file($path)) {
        fwrite(STDERR, "ERREUR: fichier introuvable: $path\n");
        exit(1);
    }
    $content = file_get_contents($path);
    if (!is_string($content)) {
        fwrite(STDERR, "ERREUR: lecture impossible: $path\n");
        exit(1);
    }
    return $content;
}

function itxeb231_write(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: écriture impossible: $path\n");
        exit(1);
    }
    echo "WRITE: $path\n";
}

function itxeb231_replace_once(string $content, string $needle, string $replacement, string $label): string
{
    $pos = strpos($content, $needle);
    if ($pos === false) {
        fwrite(STDERR, "ERREUR: bloc introuvable: $label\n");
        exit(1);
    }
    return substr($content, 0, $pos) . $replacement . substr($content, $pos + strlen($needle));
}

function itxeb231_insert_before(string $content, string $needle, string $insert, string $label): string
{
    $pos = strpos($content, $needle);
    if ($pos === false) {
        fwrite(STDERR, "ERREUR: point d'insertion introuvable: $label\n");
        exit(1);
    }
    return substr($content, 0, $pos) . $insert . substr($content, $pos);
}

function itxeb231_set_version(string $path, string $version): void
{
    $content = itxeb231_read($path);
    $new = preg_replace('/\$version\s*=\s*\'[^\']*\';/', '$version = \'' . $version . '\';', $content, 1);
    if (!is_string($new) || $new === $content) {
        fwrite(STDERR, "ERREUR: version introuvable: $path\n");
        exit(1);
    }
    itxeb231_write($path, $new);
}

function itxeb231_lint(string $path): void
{
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $out, $code);
    echo implode("\n", $out) . "\n";
    if ($code !== 0) {
        fwrite(STDERR, "ERREUR: lint PHP en échec: $path\n");
        exit(1);
    }
}

$uiHookTplPath = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl';
$mainPluginPath = $root . '/plugin.php';
$companionPluginTplPath = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';

$uiHook = itxeb231_read($uiHookTplPath);
if (strpos($uiHook, 'handleMediaCastTrackingBeacon(') === false) {
    if (strpos($uiHook, 'ITXEB V0.23 MediaCast tracking') === false) {
        fwrite(STDERR, "ERREUR: V0.23 doit être appliquée avant V0.23.1\n");
        exit(1);
    }

    $needle = "        \$html = \$a_par['html'];\n";
    $replacement = $needle . "        if (\$this->isMediaCastTrackingBeaconRequest()) {\n            \$this->handleMediaCastTrackingBeacon();\n            return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => '<span style=\"display:none\">OK</span>'];\n        }\n";
    $uiHook = itxeb231_replace_once($uiHook, $needle, $replacement, 'UIHook beacon early handling');

    $methodBlock = <<<'PHP'
    private function isMediaCastTrackingBeaconRequest(): bool
    {
        $query = $this->currentQueryParams();
        $event = (string) ($query['itxeb_mcst_event'] ?? '');
        return in_array($event, ['media_played', 'external_media_opened'], true)
            && isset($query['ref_id'])
            && (int) $query['ref_id'] > 0;
    }

    private function handleMediaCastTrackingBeacon(): void
    {
        try {
            $mainPath = rtrim($this->bridge->getMainPluginPath(), '/');
            foreach ([
                'class.ilIliasTraxEventBridgeConfig.php',
                'class.ilIliasTraxEventBridgeStatementFactory.php',
                'class.ilIliasTraxEventBridgeOutboxRepository.php',
                'class.ilIliasTraxEventBridgeCourseContextResolver.php',
                'class.ilIliasTraxEventBridgeCourseTrackingRepository.php',
            ] as $file) {
                $path = $mainPath . '/classes/' . $file;
                if (!is_file($path)) {
                    error_log('[IliasTraxEventBridge] MediaCast beacon ignored, missing class file: ' . $path);
                    return;
                }
                require_once $path;
            }

            if (!class_exists('ilIliasTraxEventBridgeConfig')
                || !class_exists('ilIliasTraxEventBridgeStatementFactory')
                || !class_exists('ilIliasTraxEventBridgeOutboxRepository')
                || !class_exists('ilIliasTraxEventBridgeCourseContextResolver')
                || !class_exists('ilIliasTraxEventBridgeCourseTrackingRepository')
            ) {
                return;
            }

            $config = new ilIliasTraxEventBridgeConfig();
            if (!$config->isEnabled() || !$config->isLocalXapiGenerationEnabled()) {
                return;
            }

            $query = $this->currentQueryParams();
            $refId = (int) ($query['ref_id'] ?? 0);
            if ($refId <= 0) {
                return;
            }

            $objId = $this->lookupObjectIdForMediaCastRefId($refId);
            $record = [
                'component' => 'components/ILIAS/ReadEvent',
                'event_name' => 'access',
                'user_id' => $this->currentUserIdForMediaCastBeacon(),
                'ref_id' => $refId,
                'obj_id' => $objId,
                'obj_type' => 'mcst',
                'param_keys' => 'itxeb_mcst_event,itxeb_mcst_media_id,itxeb_mcst_media_title,itxeb_mcst_media_mime,itxeb_mcst_media_url,itxeb_mcst_media_provider,itxeb_mcst_v',
                'payload_json' => '{}',
                'created_at' => date('Y-m-d H:i:s'),
                'created_ts' => time(),
                'request_uri' => isset($_SERVER['REQUEST_URI']) && is_scalar($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '',
                'http_method' => isset($_SERVER['REQUEST_METHOD']) && is_scalar($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET',
            ];

            $contextResolver = new ilIliasTraxEventBridgeCourseContextResolver();
            $courseContext = $contextResolver->resolve($record);
            if (empty($courseContext['is_in_course'])) {
                return;
            }
            if ((int) ($courseContext['ref_id'] ?? 0) > 0) {
                $record['ref_id'] = (int) $courseContext['ref_id'];
            }
            $record['course_ref_id'] = (int) ($courseContext['course_ref_id'] ?? 0);
            $record['course_obj_id'] = (int) ($courseContext['course_obj_id'] ?? 0);

            $trackingRepository = new ilIliasTraxEventBridgeCourseTrackingRepository();
            $courseRefId = (int) $record['course_ref_id'];
            $resourceRefId = (int) $record['ref_id'];
            if ($courseRefId <= 0 || $resourceRefId <= 0) {
                return;
            }
            if (!$trackingRepository->isCourseConfigured($courseRefId)
                || !$trackingRepository->isCourseEnabled($courseRefId)
                || !$trackingRepository->isResourceConfigured($courseRefId, $resourceRefId)
                || !$trackingRepository->isResourceEnabled($courseRefId, $resourceRefId)
            ) {
                return;
            }

            $factory = new ilIliasTraxEventBridgeStatementFactory($config);
            $statement = $factory->createFromEventRecord($record);
            if (!is_array($statement)) {
                return;
            }

            $outbox = new ilIliasTraxEventBridgeOutboxRepository();
            $outbox->enqueue($record, $statement, 0);
        } catch (Throwable $e) {
            error_log('[IliasTraxEventBridge] MediaCast beacon handling failed: ' . $e->getMessage());
        }
    }

    private function lookupObjectIdForMediaCastRefId(int $refId): int
    {
        if ($refId <= 0 || !class_exists('ilObject') || !method_exists('ilObject', '_lookupObjId')) {
            return 0;
        }
        try {
            return (int) ilObject::_lookupObjId($refId);
        } catch (Throwable $ignored) {
            return 0;
        }
    }

    private function currentUserIdForMediaCastBeacon(): int
    {
        try {
            if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'user')) {
                $user = $GLOBALS['DIC']->user();
                if (is_object($user) && method_exists($user, 'getId')) {
                    return (int) $user->getId();
                }
            }
        } catch (Throwable $ignored) {
        }

        try {
            if (isset($GLOBALS['ilUser']) && is_object($GLOBALS['ilUser']) && method_exists($GLOBALS['ilUser'], 'getId')) {
                return (int) $GLOBALS['ilUser']->getId();
            }
        } catch (Throwable $ignored) {
        }

        return 0;
    }

PHP;
    $insertBefore = "    /** @return array<string,mixed> */\n    public function getCurrentCourseContext(): array";
    $uiHook = itxeb231_insert_before($uiHook, $insertBefore, $methodBlock, 'UIHook methods beacon direct outbox');
    itxeb231_write($uiHookTplPath, $uiHook);
} else {
    echo "SKIP: UIHook déjà patché V0.23.1\n";
}

itxeb231_set_version($mainPluginPath, '0.23.1-dev');
itxeb231_set_version($companionPluginTplPath, '0.8.12');

$liveBase = dirname($root) . '/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$liveHook = $liveBase . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php';
$livePlugin = $liveBase . '/plugin.php';
if (is_file($liveHook)) {
    copy($uiHookTplPath, $liveHook);
    echo "COPY: $uiHookTplPath -> $liveHook\n";
}
if (is_file($livePlugin)) {
    copy($companionPluginTplPath, $livePlugin);
    echo "COPY: $companionPluginTplPath -> $livePlugin\n";
}

foreach ([$uiHookTplPath, $mainPluginPath, $companionPluginTplPath] as $path) {
    itxeb231_lint($path);
}
if (is_file($liveHook)) { itxeb231_lint($liveHook); }
if (is_file($livePlugin)) { itxeb231_lint($livePlugin); }

echo "V0.23.1 MediaCast beacon outbox handling applied.\n";
