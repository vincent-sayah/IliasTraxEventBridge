<?php

declare(strict_types=1);

/**
 * V0.23.2 MediaCast beacon hardening.
 *
 * Fixes two issues seen during validation:
 * - several outbox rows were created for one browser beacon because getHTML() can
 *   be invoked multiple times during the same ILIAS request;
 * - external MediaCast entries such as YouTube need a stronger playlist/click
 *   detection than the initial VideoPlaylist monkey patch.
 */

$root = dirname(__DIR__);

function itxeb232_read(string $path): string
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

function itxeb232_write(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: écriture impossible: $path\n");
        exit(1);
    }
    echo "WRITE: $path\n";
}

function itxeb232_replace_once(string $content, string $needle, string $replacement, string $label): string
{
    $pos = strpos($content, $needle);
    if ($pos === false) {
        fwrite(STDERR, "ERREUR: bloc introuvable: $label\n");
        exit(1);
    }
    return substr($content, 0, $pos) . $replacement . substr($content, $pos + strlen($needle));
}

function itxeb232_insert_before(string $content, string $needle, string $insert, string $label): string
{
    $pos = strpos($content, $needle);
    if ($pos === false) {
        fwrite(STDERR, "ERREUR: point d'insertion introuvable: $label\n");
        exit(1);
    }
    return substr($content, 0, $pos) . $insert . substr($content, $pos);
}

function itxeb232_set_version(string $path, string $version): void
{
    $content = itxeb232_read($path);
    $new = preg_replace('/\$version\s*=\s*\'[^\']*\';/', '$version = \'' . $version . '\';', $content, 1);
    if (!is_string($new) || $new === $content) {
        fwrite(STDERR, "ERREUR: version introuvable: $path\n");
        exit(1);
    }
    itxeb232_write($path, $new);
}

function itxeb232_lint(string $path): void
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
$statementFactoryPath = $root . '/classes/class.ilIliasTraxEventBridgeStatementFactory.php';
$mainPluginPath = $root . '/plugin.php';
$companionPluginTplPath = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';

$uiHook = itxeb232_read($uiHookTplPath);
if (strpos($uiHook, 'handleMediaCastTrackingBeacon(') === false || strpos($uiHook, 'ITXEB V0.23 MediaCast tracking') === false) {
    fwrite(STDERR, "ERREUR: V0.23.1 doit être appliquée avant V0.23.2\n");
    exit(1);
}

if (strpos($uiHook, 'mediaCastBeaconAlreadyHandledThisRequest(') === false) {
    $needle = "            \$record = [\n                'component' => 'components/ILIAS/ReadEvent',\n                'event_name' => 'access',\n                'user_id' => \$this->currentUserIdForMediaCastBeacon(),\n                'ref_id' => \$refId,\n                'obj_id' => \$objId,\n                'obj_type' => 'mcst',\n                'param_keys' => 'itxeb_mcst_event,itxeb_mcst_media_id,itxeb_mcst_media_title,itxeb_mcst_media_mime,itxeb_mcst_media_url,itxeb_mcst_media_provider,itxeb_mcst_v',\n                'payload_json' => '{}',\n                'created_at' => date('Y-m-d H:i:s'),\n                'created_ts' => time(),\n                'request_uri' => isset(\$_SERVER['REQUEST_URI']) && is_scalar(\$_SERVER['REQUEST_URI']) ? (string) \$_SERVER['REQUEST_URI'] : '',\n                'http_method' => isset(\$_SERVER['REQUEST_METHOD']) && is_scalar(\$_SERVER['REQUEST_METHOD']) ? (string) \$_SERVER['REQUEST_METHOD'] : 'GET',\n            ];\n\n";
    $replacement = $needle . "            \$dedupeKey = \$this->mediaCastBeaconDedupeKey();\n            if (\$dedupeKey !== '' && \$this->mediaCastBeaconAlreadyHandledThisRequest(\$dedupeKey)) {\n                return;\n            }\n\n";
    $uiHook = itxeb232_replace_once($uiHook, $needle, $replacement, 'UIHook beacon in-request dedupe call');

    $methodBlock = <<<'PHP'
    private function mediaCastBeaconDedupeKey(): string
    {
        $query = $this->currentQueryParams();
        $event = substr((string) ($query['itxeb_mcst_event'] ?? ''), 0, 64);
        $refId = (int) ($query['ref_id'] ?? 0);
        $userId = $this->currentUserIdForMediaCastBeacon();
        $mediaId = trim((string) ($query['itxeb_mcst_media_id'] ?? ''));
        $mediaUrl = trim((string) ($query['itxeb_mcst_media_url'] ?? ''));
        $mediaTitle = trim((string) ($query['itxeb_mcst_media_title'] ?? ''));
        $mediaKey = $mediaId !== '' ? $mediaId : ($mediaUrl !== '' ? $mediaUrl : $mediaTitle);
        if ($event === '' || $refId <= 0 || $mediaKey === '') {
            return '';
        }
        return sha1(implode('|', [$event, $refId, $userId, $mediaKey]));
    }

    private function mediaCastBeaconAlreadyHandledThisRequest(string $key): bool
    {
        static $seen = [];
        if ($key === '') {
            return false;
        }
        if (isset($seen[$key])) {
            return true;
        }
        $seen[$key] = true;
        return false;
    }

PHP;
    $uiHook = itxeb232_insert_before($uiHook, "    private function lookupObjectIdForMediaCastRefId(int \$refId): int", $methodBlock, 'UIHook beacon dedupe methods');
}

if (strpos($uiHook, 'extractPlaylistArrayLiteral(') === false) {
    $methodBlock = <<<'PHP'
    function extractPlaylistArrayLiteral(text) {
        var initPos = text.indexOf('il.VideoPlaylist.init');
        if (initPos === -1) { return ''; }
        var playlistPos = text.indexOf('mcst_playlist', initPos);
        if (playlistPos === -1) { return ''; }
        var start = text.indexOf('[', playlistPos);
        if (start === -1) { return ''; }
        var depth = 0;
        var quote = '';
        var escaped = false;
        for (var i = start; i < text.length; i++) {
            var ch = text.charAt(i);
            if (quote) {
                if (escaped) { escaped = false; continue; }
                if (ch === '\\') { escaped = true; continue; }
                if (ch === quote) { quote = ''; }
                continue;
            }
            if (ch === '"' || ch === "'") { quote = ch; continue; }
            if (ch === '[') { depth++; }
            if (ch === ']') {
                depth--;
                if (depth === 0) { return text.substring(start, i + 1); }
            }
        }
        return '';
    }

PHP;
    $uiHook = itxeb232_insert_before($uiHook, "    function parseItemsFromInlineScripts() {", $methodBlock, 'JS robust playlist literal extractor');

    $old = <<<'JS'
            var match = text.match(/il\.VideoPlaylist\.init\(\s*['"]mcst_playlist['"]\s*,\s*['"]mcst_video['"]\s*,\s*(\[[\s\S]*?\])\s*\)/);
            if (!match) { continue; }
            try {
                rememberItems(JSON.parse(match[1]));
                return;
            } catch (ignored) {}
            try {
                /* ILIAS emits a JavaScript array literal here. */
                rememberItems((new Function('return ' + match[1] + ';'))());
                return;
            } catch (ignored2) {}
JS;
    $new = <<<'JS'
            var arrayLiteral = extractPlaylistArrayLiteral(text);
            if (!arrayLiteral) { continue; }
            try {
                rememberItems(JSON.parse(arrayLiteral));
                return;
            } catch (ignored) {}
            try {
                /* ILIAS emits a JavaScript array literal here. */
                rememberItems((new Function('return ' + arrayLiteral + ';'))());
                return;
            } catch (ignored2) {}
JS;
    $uiHook = itxeb232_replace_once($uiHook, $old, $new, 'JS playlist parsing');
}

if (strpos($uiHook, 'nearestMediaCastToggleElement(') === false) {
    $methodBlock = <<<'PHP'
    function nearestMediaCastToggleElement(node) {
        while (node && node !== document) {
            if (node.getAttribute) {
                var onclick = node.getAttribute('onclick') || '';
                if (onclick.indexOf('VideoPlaylist.toggleItem') !== -1 && onclick.indexOf('mcst_playlist') !== -1) {
                    return node;
                }
            }
            node = node.parentNode;
        }
        return null;
    }

    function mediaCastToggleId(element) {
        if (!element || !element.getAttribute) { return ''; }
        var onclick = String(element.getAttribute('onclick') || '');
        var match = onclick.match(/VideoPlaylist\.toggleItem\(\s*['"]mcst_playlist['"]\s*,\s*['"]([^'"]+)['"]/);
        return match ? String(match[1]) : '';
    }

    document.addEventListener('click', function (event) {
        var element = nearestMediaCastToggleElement(event.target);
        if (!element) { return; }
        parseItemsFromInlineScripts();
        var itemId = mediaCastToggleId(element);
        if (!itemId) { return; }
        state.selectedId = itemId;
        var item = selectedItem(itemId);
        if (!item) {
            item = normalizeItem({ id: itemId, title: element.textContent || '', mime: '', resource: '', provider: '' });
            if (item.id) { state.items[item.id] = item; }
        }
        if (item && isExternal(item)) {
            track('external_media_opened', item);
        }
    }, true);

PHP;
    $uiHook = itxeb232_insert_before($uiHook, "    document.addEventListener('play', function (event) {", $methodBlock, 'JS MediaCast click listener');
}

itxeb232_write($uiHookTplPath, $uiHook);

$statementFactory = itxeb232_read($statementFactoryPath);
if (strpos($statementFactory, "return 'media_play';") === false) {
    $needle = "        if (\$sourceEvent === 'test_question_result') {\n            return 'assessment_question';\n        }\n\n";
    $replacement = $needle . "        if (\$sourceEvent === 'mediacast_media_played') {\n            return 'media_play';\n        }\n\n        if (\$sourceEvent === 'mediacast_external_media_opened') {\n            return 'external_media_open';\n        }\n\n";
    $statementFactory = itxeb232_replace_once($statementFactory, $needle, $replacement, 'StatementFactory interactionType MediaCast');
    itxeb232_write($statementFactoryPath, $statementFactory);
} else {
    echo "SKIP: StatementFactory interactionType déjà patché V0.23.2\n";
}

itxeb232_set_version($mainPluginPath, '0.23.2-dev');
itxeb232_set_version($companionPluginTplPath, '0.8.13');

$liveBase = dirname($root, 3) . '/UIComponent/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
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

foreach ([$uiHookTplPath, $statementFactoryPath, $mainPluginPath, $companionPluginTplPath] as $path) {
    itxeb232_lint($path);
}
if (is_file($liveHook)) { itxeb232_lint($liveHook); }
if (is_file($livePlugin)) { itxeb232_lint($livePlugin); }

echo "V0.23.2 MediaCast dedupe and external detection applied.\n";
