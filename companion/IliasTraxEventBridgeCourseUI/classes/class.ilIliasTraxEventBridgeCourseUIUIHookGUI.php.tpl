<?php

require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIBridge.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIScreen.php';
require_once __DIR__ . '/class.ilIliasTraxEventBridgeCourseUIRouterGUI.php';

/**
 * UIHook léger : point d'entrée Suivi xAPI dans l'onglet Contenu du cours.
 */
class ilIliasTraxEventBridgeCourseUIUIHookGUI extends ilUIHookPluginGUI
{
    /** @var ilIliasTraxEventBridgeCourseUIBridge */
    private $bridge;
    private static bool $pilotageToolbarAdded = false;

    public function __construct()
    {
        $this->bridge = new ilIliasTraxEventBridgeCourseUIBridge();
    }

    /**
     * @param string $a_comp
     * @param string $a_part
     * @param array<string,mixed> $a_par
     * @return array<string,mixed>
     */
    public function getHTML($a_comp, $a_part, $a_par = []): array
    {
        if (!isset($a_par['html']) || !is_string($a_par['html'])) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $html = $a_par['html'];
        if ($this->isMediaCastTrackingBeaconRequest()) {
            $this->handleMediaCastTrackingBeacon();
            return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => '<span style="display:none">OK</span>'];
        }
        $cleanHtml = $this->removeInjectedCourseEntryBlock($html);
        if ($cleanHtml !== $html) {
            return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $cleanHtml];
        }
        if (strpos($html, 'il_center_col') === false || strpos($html, 'mainspacekeeper') === false) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        // Ne jamais réintercepter la page routée ilUIPluginRouterGUI :
        // sinon le HTML du screen est réinséré comme texte échappé.
        if ($this->isRoutedPluginRequest()) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        if ($this->isMediaCastContentRequest() && $this->containsMediaCastPlaylist($html)) {
            $newHtml = $this->injectMediaCastTracking($html);
            return $newHtml !== $html
                ? ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $newHtml]
                : ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $context = $this->getCurrentCourseContext();
        $courseRefId = (int) ($context['course_ref_id'] ?? 0);
        if ($courseRefId <= 0 || empty($context['main_plugin_available']) || empty($context['course_tracking_classes_available']) || empty($context['can_manage'])) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        // Fallback technique : si la page est appelée avec itxeb_cui_cmd, on
        // remplace le contenu central. Le chemin principal reste la page routée
        // ilUIPluginRouterGUI avec vrais onglets ILIAS.
        if ($this->isCourseUiCommandRequest()) {
            $screen = new ilIliasTraxEventBridgeCourseUIScreen($this->bridge);
            $newHtml = $this->replaceCenterColumnContent($html, $screen->handle());
            return ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $newHtml];
        }

        // Important : ne pas afficher l'encart sur Info/Membres/Paramètres.
        if (!$this->isCourseContentRequest()) {
            return ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
        }

        $url = $this->buildRouterUrl($courseRefId, 'showDashboard');
        $newHtml = $this->injectCourseEntryButton($html, $url);
        return $newHtml !== $html
            ? ['mode' => ilUIHookPluginGUI::REPLACE, 'html' => $newHtml]
            : ['mode' => ilUIHookPluginGUI::KEEP, 'html' => ''];
    }

    /** @param string $a_comp @param string $a_part @param array<string,mixed> $a_par */
    public function modifyGUI($a_comp, $a_part, $a_par = []): void
    {
        try {
            if (self::$pilotageToolbarAdded) { return; }
            if ($this->isRoutedPluginRequest() || !$this->isCourseContentRequest()) { return; }
            $context = $this->getCurrentCourseContext();
            $courseRefId = (int) ($context['course_ref_id'] ?? 0);
            if ($courseRefId <= 0 || empty($context['main_plugin_available']) || empty($context['course_tracking_classes_available']) || empty($context['can_manage'])) { return; }
            if (!isset($GLOBALS['DIC']) || !is_object($GLOBALS['DIC']) || !method_exists($GLOBALS['DIC'], 'toolbar')) { return; }
            $toolbar = $GLOBALS['DIC']->toolbar();
            if (is_object($toolbar) && method_exists($toolbar, 'addButton')) {
                $toolbar->addButton('Pilotage xAPI', $this->buildRouterUrl($courseRefId, 'showDashboard'));
                self::$pilotageToolbarAdded = true;
            }
        } catch (Throwable $ignored) {}
    }

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
    function parseItemsFromInlineScripts() {
        if (Object.keys(state.items).length > 0) { return; }
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var text = scripts[i].textContent || '';
            if (text.indexOf('il.VideoPlaylist.init') === -1 || text.indexOf('mcst_playlist') === -1) { continue; }
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
    document.addEventListener('play', function (event) {
        var target = event.target;
        if (!target || String(target.tagName || '').toLowerCase() !== 'video') { return; }
        var item = selectedItem(state.selectedId) || itemFromVideo(target);
        if (item && !isExternal(item)) {
            track('media_played', item);
        }
    }, true);


    /* ITXEB V0.23.3 external iframe tracking */
    var externalFrameObserverStarted = false;

    /* ITXEB V0.23.8 external playlist title */
    function isGenericExternalTitle(title) {
        title = String(title || '').toLowerCase().trim();
        return title === ''
            || title === 'vidéo youtube mediacast'
            || title === 'video youtube mediacast'
            || title === 'média externe mediacast'
            || title === 'media externe mediacast'
            || title === 'youtube video player'
            || title === 'youtube';
    }

    function youtubeMediaId(value) {
        value = String(value || '');
        if (value === '') { return ''; }
        var patterns = [
            /youtube(?:-nocookie)?\.com\/embed\/([A-Za-z0-9_-]{6,})/i,
            /youtube(?:-nocookie)?\.com\/watch\?[^#]*v=([A-Za-z0-9_-]{6,})/i,
            /youtu\.be\/([A-Za-z0-9_-]{6,})/i
        ];
        for (var i = 0; i < patterns.length; i++) {
            var m = value.match(patterns[i]);
            if (m && m[1]) { return m[1]; }
        }
        return '';
    }

    function vimeoMediaId(value) {
        value = String(value || '');
        var m = value.match(/vimeo\.com\/(?:video\/)?([0-9]{5,})/i);
        return m && m[1] ? m[1] : '';
    }

    function bestExternalPlaylistItemForFrame(src) {
        parseItemsFromInlineScripts();
        var selected = selectedItem(state.selectedId);
        if (selected && isExternal(selected) && !isGenericExternalTitle(selected.title || '')) {
            return selected;
        }

        var yt = youtubeMediaId(src);
        var vi = vimeoMediaId(src);
        var fallback = selected && isExternal(selected) ? selected : null;
        for (var id in state.items) {
            if (!Object.prototype.hasOwnProperty.call(state.items, id)) { continue; }
            var item = state.items[id];
            if (!item || !isExternal(item)) { continue; }
            var resource = String(item.resource || '');
            if (resource !== '' && src.indexOf(resource) !== -1) { return item; }
            if (yt !== '' && youtubeMediaId(resource) === yt) { return item; }
            if (vi !== '' && vimeoMediaId(resource) === vi) { return item; }
            if (!fallback && !isGenericExternalTitle(item.title || '')) { fallback = item; }
        }
        return fallback;
    }

    function externalItemFromFrame(frame) {
        if (!frame) { return null; }
        var src = String(frame.getAttribute('src') || frame.src || frame.getAttribute('data-src') || '');
        if (!src) { return null; }
        var lower = src.toLowerCase();
        var isYoutube = lower.indexOf('youtube.') !== -1 || lower.indexOf('youtube-nocookie.') !== -1 || lower.indexOf('youtu.be') !== -1;
        var isVimeo = lower.indexOf('vimeo.') !== -1;
        if (!isYoutube && !isVimeo) { return null; }

        var playlistItem = bestExternalPlaylistItemForFrame(src);
        var title = String(frame.getAttribute('title') || frame.getAttribute('aria-label') || '');
        if (playlistItem && isExternal(playlistItem) && !isGenericExternalTitle(playlistItem.title || '')) {
            title = playlistItem.title || title;
        }
        if (isGenericExternalTitle(title) && playlistItem && isExternal(playlistItem)) {
            title = playlistItem.title || title;
        }
        if (isGenericExternalTitle(title)) {
            title = isYoutube ? 'Vidéo YouTube MediaCast' : 'Média externe MediaCast';
        }

        return normalizeItem({
            id: (playlistItem && playlistItem.id) ? playlistItem.id : (state.selectedId || src),
            title: title,
            mime: (playlistItem && playlistItem.mime) ? playlistItem.mime : (isYoutube ? 'video/youtube' : 'video/external'),
            resource: (playlistItem && playlistItem.resource) ? playlistItem.resource : src,
            provider: (playlistItem && playlistItem.provider) ? playlistItem.provider : (isYoutube ? 'youtube' : (isVimeo ? 'vimeo' : 'external'))
        });
    }

    function scanExternalMediaFrames() {
        var frames = document.querySelectorAll('iframe, embed');
        for (var i = 0; i < frames.length; i++) {
            var item = externalItemFromFrame(frames[i]);
            if (item) {
                track('external_media_opened', item);
            }
        }
    }

    function observeExternalMediaFrames() {
        if (externalFrameObserverStarted) { return; }
        externalFrameObserverStarted = true;
        scanExternalMediaFrames();
        if (!window.MutationObserver || !document.documentElement) { return; }

        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                for (var j = 0; j < mutation.addedNodes.length; j++) {
                    var node = mutation.addedNodes[j];
                    if (!node || node.nodeType !== 1) { continue; }
                    if (String(node.tagName || '').toLowerCase() === 'iframe' || String(node.tagName || '').toLowerCase() === 'embed') {
                        var item = externalItemFromFrame(node);
                        if (item) { track('external_media_opened', item); }
                    }
                    if (node.querySelectorAll) {
                        var nestedFrames = node.querySelectorAll('iframe, embed');
                        for (var k = 0; k < nestedFrames.length; k++) {
                            var nestedItem = externalItemFromFrame(nestedFrames[k]);
                            if (nestedItem) { track('external_media_opened', nestedItem); }
                        }
                    }
                }
                if (mutation.type === 'attributes' && mutation.target) {
                    var attrItem = externalItemFromFrame(mutation.target);
                    if (attrItem) { track('external_media_opened', attrItem); }
                }
            }
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['src', 'data-src']
        });
    }
    var tries = 0;
    function waitPatch() {
        patchPlaylist();
        observeExternalMediaFrames();
        scanExternalMediaFrames();
        if (++tries < 80) { window.setTimeout(waitPatch, 100); }
    }
    waitPatch();
    document.addEventListener('DOMContentLoaded', waitPatch);
    document.addEventListener('DOMContentLoaded', scanExternalMediaFrames);
    window.addEventListener('load', waitPatch);
    window.addEventListener('load', scanExternalMediaFrames);
}());
</script>
HTML;

        $pos = stripos($html, '</body>');
        if ($pos !== false) {
            return substr($html, 0, $pos) . $script . substr($html, $pos);
        }
        return $html . $script;
    }
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

            $dedupeKey = $this->mediaCastBeaconDedupeKey();
            if ($dedupeKey !== '' && $this->mediaCastBeaconAlreadyHandledThisRequest($dedupeKey)) {
                return;
            }

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
    /** @return array<string,mixed> */
    public function getCurrentCourseContext(): array
    {
        return $this->bridge->getCourseContext();
    }

    private function isRoutedPluginRequest(): bool
    {
        $query = [];
        $uri = isset($_SERVER['REQUEST_URI']) && is_scalar($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $parts = parse_url($uri);
        if (is_array($parts) && isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        } elseif (!empty($_GET)) {
            $query = $_GET;
        }

        $baseClass = strtolower((string) ($query['baseClass'] ?? $query['baseclass'] ?? ''));
        $cmdClass = strtolower((string) ($query['cmdClass'] ?? $query['cmdclass'] ?? ''));

        return $baseClass === 'iluipluginroutergui'
            || $cmdClass === strtolower(ilIliasTraxEventBridgeCourseUIRouterGUI::class);
    }
    private function isCourseUiCommandRequest(): bool
    {
        foreach ([$_GET, $_POST] as $source) {
            if (isset($source['itxeb_cui_cmd']) && is_scalar($source['itxeb_cui_cmd']) && (string) $source['itxeb_cui_cmd'] !== '') {
                return true;
            }
        }
        return false;
    }

    private function isCourseContentRequest(): bool
    {
        $uri = isset($_SERVER['REQUEST_URI']) && is_scalar($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $query = [];
        $parts = parse_url($uri);
        if (is_array($parts) && isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        } elseif (!empty($_GET)) {
            $query = $_GET;
        }

        $baseClass = strtolower((string) ($query['baseClass'] ?? $query['baseclass'] ?? ''));
        $cmdClass = strtolower((string) ($query['cmdClass'] ?? $query['cmdclass'] ?? ''));
        $cmd = strtolower((string) ($query['cmd'] ?? ''));

        if ($baseClass !== '' && $baseClass !== 'ilrepositorygui') {
            return false;
        }
        if ($cmdClass !== '' && $cmdClass !== 'ilobjcoursegui') {
            return false;
        }
        if ($cmd !== '' && !in_array($cmd, ['show', 'view', 'render'], true)) {
            return false;
        }

        return isset($query['ref_id']) && (int) $query['ref_id'] > 0;
    }

    private function buildRouterUrl(int $courseRefId, string $cmd): string
    {
        try {
            if (isset($GLOBALS['DIC']) && is_object($GLOBALS['DIC']) && method_exists($GLOBALS['DIC'], 'ctrl')) {
                $ctrl = $GLOBALS['DIC']->ctrl();
                $ctrl->setParameterByClass(ilIliasTraxEventBridgeCourseUIRouterGUI::class, 'itxeb_course_ref_id', (string) $courseRefId);
                $url = (string) $ctrl->getLinkTargetByClass([
                    ilUIPluginRouterGUI::class,
                    ilIliasTraxEventBridgeCourseUIRouterGUI::class,
                ], $cmd);
                $ctrl->setParameterByClass(ilIliasTraxEventBridgeCourseUIRouterGUI::class, 'itxeb_course_ref_id', '');

                // Une URL ilCtrl correcte contient cmdNode. Si cmdNode est absent,
                // la structure de contrôle n'a pas encore été reconstruite.
                if ($url !== '' && strpos($url, 'cmdNode=') !== false) {
                    return $url;
                }
            }
        } catch (Throwable $ignored) {
        }

        return $this->buildCourseFallbackUrl($courseRefId);
    }

    private function buildCourseFallbackUrl(int $courseRefId): string
    {
        $script = isset($_SERVER['SCRIPT_NAME']) && is_scalar($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '/ilias.php';
        if ($script === '') {
            $script = '/ilias.php';
        }
        return $script . '?' . http_build_query([
            'baseClass' => 'ilrepositorygui',
            'cmdClass' => 'ilobjcoursegui',
            'ref_id' => (string) $courseRefId,
            'itxeb_cui_cmd' => 'showCourseDashboard',
            'itxeb_course_ref_id' => (string) $courseRefId,
        ], '', '&');
    }

    private function removeInjectedCourseEntryBlock(string $html): string
    {
        if (strpos($html, 'itxeb_course_xapi_entry') === false && strpos($html, 'itxeb-course-xapi-entry') === false && strpos($html, 'Ouvrir le suivi xAPI') === false) {
            return $html;
        }
        if (class_exists('DOMDocument')) {
            $internalErrors = libxml_use_internal_errors(true);
            $dom = new DOMDocument('1.0', 'UTF-8');
            $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
            if ($loaded) {
                $node = $dom->getElementById('itxeb_course_xapi_entry');
                if ($node instanceof DOMNode && $node->parentNode instanceof DOMNode) {
                    $node->parentNode->removeChild($node);
                    $result = $dom->saveHTML();
                    $result = preg_replace('/^<\?xml[^>]+>\s*/', '', (string) $result) ?? (string) $result;
                    libxml_clear_errors();
                    libxml_use_internal_errors($internalErrors);
                    return $result;
                }
            }
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        }
        $clean = preg_replace('/<div\s+id=("|\')itxeb_course_xapi_entry\1\b.*?<\/div>/isu', '', $html, 1);
        return is_string($clean) ? $clean : $html;
    }
    private function injectCourseEntryButton(string $html, string $url): string
    {
        return $html;
    }

    private function replaceCenterColumnContent(string $html, string $content): string
    {
        if ($html === '' || !class_exists('DOMDocument')) {
            return $content;
        }

        $internalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        if (!$loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $content;
        }

        $center = $dom->getElementById('il_center_col');
        if (!$center instanceof DOMElement) {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            return $content;
        }

        while ($center->firstChild instanceof DOMNode) {
            $center->removeChild($center->firstChild);
        }

        $fragment = $dom->createDocumentFragment();
        if (@$fragment->appendXML('<div>' . $content . '</div>') !== false) {
            while ($fragment->firstChild instanceof DOMNode) {
                $center->appendChild($fragment->firstChild);
            }
        } else {
            $center->appendChild($dom->createTextNode($content));
        }

        $result = $dom->saveHTML();
        $result = preg_replace('/^<\?xml[^>]+>\s*/', '', (string) $result) ?? (string) $result;
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
        return $result;
    }
}