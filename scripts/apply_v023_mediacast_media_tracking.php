<?php

declare(strict_types=1);

/**
 * V0.23 MediaCast media tracking.
 *
 * Adds client-side MediaCast media selection/play beacons and converts them into
 * local xAPI statements through the existing ReadEvent/outbox pipeline.
 */

$root = dirname(__DIR__);

function itxeb_read(string $path): string
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

function itxeb_write(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: écriture impossible: $path\n");
        exit(1);
    }
    echo "WRITE: $path\n";
}

function itxeb_replace_once(string $content, string $needle, string $replacement, string $label): string
{
    $pos = strpos($content, $needle);
    if ($pos === false) {
        fwrite(STDERR, "ERREUR: bloc introuvable: $label\n");
        exit(1);
    }
    return substr($content, 0, $pos) . $replacement . substr($content, $pos + strlen($needle));
}

function itxeb_insert_before(string $content, string $needle, string $insert, string $label): string
{
    $pos = strpos($content, $needle);
    if ($pos === false) {
        fwrite(STDERR, "ERREUR: point d'insertion introuvable: $label\n");
        exit(1);
    }
    return substr($content, 0, $pos) . $insert . substr($content, $pos);
}

function itxeb_set_version(string $path, string $version): void
{
    $content = itxeb_read($path);
    $new = preg_replace('/\$version\s*=\s*\'[^\']*\';/', '$version = \'' . $version . '\';', $content, 1);
    if (!is_string($new) || $new === $content) {
        fwrite(STDERR, "ERREUR: version introuvable: $path\n");
        exit(1);
    }
    itxeb_write($path, $new);
}

function itxeb_lint(string $path): void
{
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $out, $code);
    echo implode("\n", $out) . "\n";
    if ($code !== 0) {
        fwrite(STDERR, "ERREUR: lint PHP en échec: $path\n");
        exit(1);
    }
}

$statementFactoryPath = $root . '/classes/class.ilIliasTraxEventBridgeStatementFactory.php';
$outboxPath = $root . '/classes/class.ilIliasTraxEventBridgeOutboxRepository.php';
$uiHookTplPath = $root . '/companion/IliasTraxEventBridgeCourseUI/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php.tpl';
$mainPluginPath = $root . '/plugin.php';
$companionPluginTplPath = $root . '/companion/IliasTraxEventBridgeCourseUI/plugin.php.tpl';

$statementFactory = itxeb_read($statementFactoryPath);
if (strpos($statementFactory, 'isMediaCastClientEvent(') === false) {
    $needle = "        if (\$this->isIgnoredForXapi(\$record)) {\n            return null;\n        }\n\n";
    $replacement = $needle . "        if (\$this->isMediaCastClientEvent(\$record)) {\n            return \$this->createMediaCastMediaStatement(\$record);\n        }\n\n";
    $statementFactory = itxeb_replace_once($statementFactory, $needle, $replacement, 'StatementFactory hook MediaCast');

    $methodBlock = <<<'PHP'
    /**
     * @param array<string,mixed> $record
     */
    private function isMediaCastClientEvent(array $record): bool
    {
        if ((string) ($record['obj_type'] ?? '') !== 'mcst') {
            return false;
        }
        $params = $this->mediaCastClientEventParams($record);
        $event = (string) ($params['itxeb_mcst_event'] ?? '');
        return in_array($event, ['media_played', 'external_media_opened'], true);
    }

    /**
     * @param array<string,mixed> $record
     * @return array<string,mixed>
     */
    private function createMediaCastMediaStatement(array $record): array
    {
        $params = $this->mediaCastClientEventParams($record);
        $event = (string) ($params['itxeb_mcst_event'] ?? 'media_played');
        $isExternal = $event === 'external_media_opened'
            || stripos((string) ($params['itxeb_mcst_media_mime'] ?? ''), 'youtube') !== false
            || stripos((string) ($params['itxeb_mcst_media_url'] ?? ''), 'youtube.') !== false
            || stripos((string) ($params['itxeb_mcst_media_url'] ?? ''), 'youtu.be') !== false;

        $userId = (int) ($record['user_id'] ?? 0);
        $refId = (int) ($record['ref_id'] ?? 0);
        $objId = (int) ($record['obj_id'] ?? 0);
        $baseUrl = $this->config->getIliasBaseUrl();

        $mediaId = $this->sanitizeMediaText((string) ($params['itxeb_mcst_media_id'] ?? ''), 128);
        $mediaTitle = $this->sanitizeMediaText((string) ($params['itxeb_mcst_media_title'] ?? ''), 255);
        $mediaMime = $this->sanitizeMediaText((string) ($params['itxeb_mcst_media_mime'] ?? ''), 128);
        $mediaUrl = $this->sanitizeMediaUrl((string) ($params['itxeb_mcst_media_url'] ?? ''));
        $mediaProvider = $this->sanitizeMediaText((string) ($params['itxeb_mcst_media_provider'] ?? ''), 64);
        if ($mediaProvider === '') {
            $mediaProvider = $isExternal ? 'external' : 'ilias';
        }
        if ($mediaTitle === '') {
            $mediaTitle = $mediaId !== '' ? 'Média MediaCast ' . $mediaId : 'Média MediaCast';
        }

        $safeMediaId = $mediaId !== '' ? rawurlencode($mediaId) : substr(sha1($mediaTitle . '|' . $mediaUrl), 0, 16);
        $objectType = $isExternal ? 'mcst_media_link' : 'mcst_media';
        $sourceEvent = $isExternal ? 'mediacast_external_media_opened' : 'mediacast_media_played';

        $resultExtensions = [
            $baseUrl . '/xapi/extensions/mediacast_ref_id' => $refId,
            $baseUrl . '/xapi/extensions/mediacast_obj_id' => $objId,
            $baseUrl . '/xapi/extensions/media_id' => $mediaId,
            $baseUrl . '/xapi/extensions/media_title' => $mediaTitle,
            $baseUrl . '/xapi/extensions/media_mime' => $mediaMime,
            $baseUrl . '/xapi/extensions/media_provider' => $mediaProvider,
            $baseUrl . '/xapi/extensions/media_client_event' => $event,
        ];
        if ($mediaUrl !== '') {
            $resultExtensions[$baseUrl . '/xapi/extensions/media_url'] = $mediaUrl;
        }

        return [
            'id' => $this->uuid4(),
            'actor' => $this->actor($userId),
            'verb' => $this->mediaCastMediaVerb($isExternal),
            'object' => [
                'id' => rtrim($this->activityId('mcst', $refId, $objId), '/') . '/media/' . $safeMediaId,
                'objectType' => 'Activity',
                'definition' => $this->activityDefinition(
                    $this->mediaCastMediaActivityType($isExternal),
                    $mediaTitle,
                    $mediaUrl !== '' ? $mediaUrl : $this->objectUrl('mcst', $refId),
                    $isExternal ? 'Média externe sélectionné dans un MediaCast ILIAS' : 'Vidéo interne lancée dans un MediaCast ILIAS',
                    $isExternal ? 'External media selected in an ILIAS MediaCast' : 'Internal video played in an ILIAS MediaCast'
                )
            ],
            'result' => [
                'completion' => false,
                'extensions' => $resultExtensions,
            ],
            'context' => $this->context($record, $sourceEvent, $objectType),
            'timestamp' => $this->isoTimestamp((string) ($record['created_at'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $record
     * @return array<string,string>
     */
    private function mediaCastClientEventParams(array $record): array
    {
        $uri = (string) ($record['request_uri'] ?? '');
        $query = (string) (parse_url($uri, PHP_URL_QUERY) ?: '');
        if ($query === '') {
            return [];
        }
        parse_str($query, $values);
        $params = [];
        foreach ($values as $key => $value) {
            if (strpos((string) $key, 'itxeb_mcst_') !== 0) {
                continue;
            }
            if (is_scalar($value)) {
                $params[(string) $key] = substr((string) $value, 0, 2000);
            }
        }
        return $params;
    }

    private function sanitizeMediaText(string $value, int $maxLength): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return mb_substr($value, 0, max(1, $maxLength));
    }

    private function sanitizeMediaUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (strpos($value, '//') === 0) {
            $value = 'https:' . $value;
        }
        if (!preg_match('~^https?://~i', $value)) {
            return '';
        }
        return substr($value, 0, 2000);
    }

    /** @return array<string,mixed> */
    private function mediaCastMediaVerb(bool $external): array
    {
        $baseUrl = $this->config->getIliasBaseUrl();
        return [
            'id' => $external ? $baseUrl . '/xapi/verbs/opened-external-media' : $baseUrl . '/xapi/verbs/played-media',
            'display' => [
                'fr-FR' => $external ? 'a ouvert un média externe MediaCast' : 'a lancé une vidéo MediaCast',
                'en-US' => $external ? 'opened external MediaCast media' : 'played MediaCast video',
            ],
        ];
    }

    private function mediaCastMediaActivityType(bool $external): string
    {
        return $this->config->getIliasBaseUrl() . '/xapi/activity-type/' . ($external ? 'ilias-mediacast-external-media' : 'ilias-mediacast-media');
    }

PHP;
    $insertBefore = "    /**\n     * @param array<string,mixed> \$record\n     * @return array<string,mixed>\n     */\n    private function createFileDownloadStatement";
    $statementFactory = itxeb_insert_before($statementFactory, $insertBefore, $methodBlock, 'StatementFactory methods MediaCast');

    if (strpos($statementFactory, "mediacast_media_client") === false) {
        $needle = "        if (\$sourceEvent === 'test_question_result') {\n            return 'test_question_result';\n        }\n\n";
        $replacement = $needle . "        if (\$sourceEvent === 'mediacast_media_played' || \$sourceEvent === 'mediacast_external_media_opened') {\n            return 'mediacast_media_client';\n        }\n\n";
        $statementFactory = itxeb_replace_once($statementFactory, $needle, $replacement, 'statementFamily MediaCast');
    }

    if (strpos($statementFactory, "external_media_open") === false) {
        $needle = "        if (\$sourceEvent === 'test_question_result') {\n            return 'assessment_question';\n        }\n\n";
        $replacement = $needle . "        if (\$sourceEvent === 'mediacast_media_played') {\n            return 'media_play';\n        }\n\n        if (\$sourceEvent === 'mediacast_external_media_opened') {\n            return 'external_media_open';\n        }\n\n";
        $statementFactory = itxeb_replace_once($statementFactory, $needle, $replacement, 'interactionType MediaCast');
    }

    itxeb_write($statementFactoryPath, $statementFactory);
} else {
    echo "SKIP: StatementFactory déjà patché V0.23\n";
}

$outbox = itxeb_read($outboxPath);
if (strpos($outbox, 'mediacast_media_client_event') === false) {
    $needle = "        \$uri = (string) (\$eventRecord['request_uri'] ?? '');\n\n";
    $replacement = $needle . "        if (\$component === 'components/ILIAS/ReadEvent' && \$event === 'access' && \$type === 'mcst' && strpos(\$uri, 'itxeb_mcst_event=') !== false) {\n            return 'mediacast_media_client_event';\n        }\n\n";
    $outbox = itxeb_replace_once($outbox, $needle, $replacement, 'Outbox detectEventType MediaCast');
    itxeb_write($outboxPath, $outbox);
} else {
    echo "SKIP: Outbox déjà patché V0.23\n";
}

$uiHook = itxeb_read($uiHookTplPath);
if (strpos($uiHook, 'ITXEB V0.23 MediaCast tracking') === false) {
    $needle = "        if (\$this->isRoutedPluginRequest()) {\n            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];\n        }\n\n";
    $replacement = $needle . "        if (\$this->isMediaCastContentRequest() && \$this->containsMediaCastPlaylist(\$html)) {\n            \$newHtml = \$this->injectMediaCastTracking(\$html);\n            return \$newHtml !== \$html\n                ? ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => \$newHtml]\n                : ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];\n        }\n\n";
    $uiHook = itxeb_replace_once($uiHook, $needle, $replacement, 'UIHook insertion MediaCast');

    $methodBlock = <<<'PHP'
    private function isMediaCastContentRequest(): bool
    {
        $query = $this->currentQueryParams();
        $baseClass = strtolower((string) ($query['baseClass'] ?? $query['baseclass'] ?? ''));
        $cmdClass = strtolower((string) ($query['cmdClass'] ?? $query['cmdclass'] ?? ''));
        $cmd = strtolower((string) ($query['cmd'] ?? ''));

        return $baseClass === 'ilmediacasthandlergui'
            && $cmdClass === strtolower('ilObjMediaCastGUI')
            && $cmd === 'showcontent'
            && isset($query['ref_id'])
            && (int) $query['ref_id'] > 0;
    }

    private function containsMediaCastPlaylist(string $html): bool
    {
        return strpos($html, "il.VideoPlaylist.init('mcst_playlist'") !== false
            || strpos($html, 'il.VideoPlaylist.init("mcst_playlist"') !== false
            || (strpos($html, 'mcst_video') !== false && strpos($html, 'VideoPlaylist.toggleItem') !== false);
    }

    /** @return array<string,mixed> */
    private function currentQueryParams(): array
    {
        $query = [];
        $uri = isset($_SERVER['REQUEST_URI']) && is_scalar($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $parts = parse_url($uri);
        if (is_array($parts) && isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        } elseif (!empty($_GET)) {
            $query = $_GET;
        }
        return is_array($query) ? $query : [];
    }

    private function injectMediaCastTracking(string $html): string
    {
        if (strpos($html, 'ITXEB V0.23 MediaCast tracking') !== false) {
            return $html;
        }

        $script = <<<'HTML'
<script>
/* ITXEB V0.23 MediaCast tracking */
(function () {
    if (window.__itxebMediaCastTrackingV023) { return; }
    window.__itxebMediaCastTrackingV023 = true;

    var state = { items: {}, selectedId: '', sent: {} };

    function normalizeItem(item) {
        item = item || {};
        return {
            id: String(item.id || ''),
            title: stripHtml(String(item.title || item.linked_title || item.name || '')),
            mime: String(item.mime || item.mime_type || item.type || ''),
            resource: String(item.resource || item.url || item.src || ''),
            provider: String(item.provider || '')
        };
    }

    function stripHtml(value) {
        var div = document.createElement('div');
        div.innerHTML = value;
        return (div.textContent || div.innerText || value).replace(/\s+/g, ' ').trim().substring(0, 255);
    }

    function rememberItems(items) {
        if (!items || !items.length) { return; }
        for (var i = 0; i < items.length; i++) {
            var item = normalizeItem(items[i]);
            if (item.id) { state.items[item.id] = item; }
        }
    }

    function parseItemsFromInlineScripts() {
        if (Object.keys(state.items).length > 0) { return; }
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var text = scripts[i].textContent || '';
            if (text.indexOf('il.VideoPlaylist.init') === -1 || text.indexOf('mcst_playlist') === -1) { continue; }
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
        }
    }

    function selectedItem(id) {
        parseItemsFromInlineScripts();
        return state.items[String(id || state.selectedId || '')] || null;
    }

    function itemFromVideo(video) {
        var src = video.currentSrc || video.src || '';
        var title = video.getAttribute('title') || video.getAttribute('aria-label') || '';
        if (!title && src) {
            try { title = decodeURIComponent(src.split('/').pop().split('?')[0]); } catch (ignored) { title = src; }
        }
        return normalizeItem({ id: state.selectedId || '', title: title, mime: 'video/mp4', resource: src, provider: 'ilias' });
    }

    function isExternal(item) {
        if (!item) { return false; }
        var mime = (item.mime || '').toLowerCase();
        var resource = (item.resource || '').toLowerCase();
        return mime.indexOf('youtube') !== -1
            || mime.indexOf('vimeo') !== -1
            || resource.indexOf('youtube.') !== -1
            || resource.indexOf('youtu.be') !== -1
            || resource.indexOf('vimeo.') !== -1;
    }

    function mediaProvider(item) {
        if (item.provider) { return item.provider; }
        var data = ((item.mime || '') + ' ' + (item.resource || '')).toLowerCase();
        if (data.indexOf('youtube') !== -1 || data.indexOf('youtu.be') !== -1) { return 'youtube'; }
        if (data.indexOf('vimeo') !== -1) { return 'vimeo'; }
        return isExternal(item) ? 'external' : 'ilias';
    }

    function track(eventName, item) {
        item = normalizeItem(item || {});
        if (!item.id && !item.resource && !item.title) { return; }
        var key = eventName + ':' + (item.id || item.resource || item.title);
        if (state.sent[key]) { return; }
        state.sent[key] = true;

        try {
            var url = new URL(window.location.href);
            url.searchParams.set('itxeb_mcst_event', eventName);
            url.searchParams.set('itxeb_mcst_media_id', item.id || '');
            url.searchParams.set('itxeb_mcst_media_title', item.title || '');
            url.searchParams.set('itxeb_mcst_media_mime', item.mime || '');
            url.searchParams.set('itxeb_mcst_media_url', item.resource || '');
            url.searchParams.set('itxeb_mcst_media_provider', mediaProvider(item));
            url.searchParams.set('itxeb_mcst_v', '0.23');
            url.searchParams.set('_', String(Date.now()));
            var finalUrl = url.toString();

            if (navigator.sendBeacon) {
                try {
                    if (navigator.sendBeacon(finalUrl)) { return; }
                } catch (ignored) {}
            }
            if (window.fetch) {
                fetch(finalUrl, { method: 'GET', credentials: 'same-origin', keepalive: true, cache: 'no-store' }).catch(function () {});
                return;
            }
            (new Image()).src = finalUrl;
        } catch (e) {}
    }

    function handleSelection(id) {
        state.selectedId = String(id || '');
        var item = selectedItem(state.selectedId);
        if (item && isExternal(item)) {
            track('external_media_opened', item);
        }
    }

    function patchPlaylist() {
        parseItemsFromInlineScripts();
        if (!window.il || !il.VideoPlaylist) { return false; }

        if (typeof il.VideoPlaylist.init === 'function' && !il.VideoPlaylist.__itxebInitPatched) {
            var originalInit = il.VideoPlaylist.init;
            il.VideoPlaylist.init = function (playlistId, videoId, items) {
                if (String(playlistId) === 'mcst_playlist') { rememberItems(items || []); }
                return originalInit.apply(this, arguments);
            };
            il.VideoPlaylist.__itxebInitPatched = true;
        }

        if (typeof il.VideoPlaylist.toggleItem === 'function' && !il.VideoPlaylist.__itxebTogglePatched) {
            var originalToggle = il.VideoPlaylist.toggleItem;
            il.VideoPlaylist.toggleItem = function (playlistId, itemId) {
                var result = originalToggle.apply(this, arguments);
                if (String(playlistId) === 'mcst_playlist') { handleSelection(itemId); }
                return result;
            };
            il.VideoPlaylist.__itxebTogglePatched = true;
        }
        return true;
    }

    document.addEventListener('play', function (event) {
        var target = event.target;
        if (!target || String(target.tagName || '').toLowerCase() !== 'video') { return; }
        var item = selectedItem(state.selectedId) || itemFromVideo(target);
        if (item && !isExternal(item)) {
            track('media_played', item);
        }
    }, true);

    var tries = 0;
    function waitPatch() {
        patchPlaylist();
        if (++tries < 80) { window.setTimeout(waitPatch, 100); }
    }
    waitPatch();
    document.addEventListener('DOMContentLoaded', waitPatch);
    window.addEventListener('load', waitPatch);
}());
</script>
HTML;

        $pos = stripos($html, '</body>');
        if ($pos !== false) {
            return substr($html, 0, $pos) . $script . substr($html, $pos);
        }
        return $html . $script;
    }

PHP;
    $insertBefore = "    /** @return array<string,mixed> */\n    public function getCurrentCourseContext(): array";
    $uiHook = itxeb_insert_before($uiHook, $insertBefore, $methodBlock, 'UIHook methods MediaCast');
    itxeb_write($uiHookTplPath, $uiHook);
} else {
    echo "SKIP: UIHook déjà patché V0.23\n";
}

itxeb_set_version($mainPluginPath, '0.23.0-dev');
itxeb_set_version($companionPluginTplPath, '0.8.11');

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

foreach ([$statementFactoryPath, $outboxPath, $uiHookTplPath, $mainPluginPath, $companionPluginTplPath] as $path) {
    itxeb_lint($path);
}
if (is_file($liveHook)) { itxeb_lint($liveHook); }
if (is_file($livePlugin)) { itxeb_lint($livePlugin); }

echo "V0.23 MediaCast media tracking applied.\n";
