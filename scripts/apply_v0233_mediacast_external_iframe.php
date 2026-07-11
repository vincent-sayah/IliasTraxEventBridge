<?php

declare(strict_types=1);

/**
 * V0.23.3 MediaCast external iframe detection.
 *
 * V0.23.2 validates internal video play and dedupe, but some ILIAS/YouTube
 * integrations do not trigger observable playlist click handlers. This patch
 * adds a MutationObserver/fallback scanner for external iframe/embed players
 * and emits a single external_media_opened beacon when YouTube/Vimeo content
 * is loaded in the MediaCast player.
 */

$root = dirname(__DIR__);

function itxeb233_read(string $path): string
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

function itxeb233_write(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: écriture impossible: $path\n");
        exit(1);
    }
    echo "WRITE: $path\n";
}

function itxeb233_replace_once(string $content, string $needle, string $replacement, string $label): string
{
    $pos = strpos($content, $needle);
    if ($pos === false) {
        fwrite(STDERR, "ERREUR: bloc introuvable: $label\n");
        exit(1);
    }
    return substr($content, 0, $pos) . $replacement . substr($content, $pos + strlen($needle));
}

function itxeb233_set_version(string $path, string $version): void
{
    $content = itxeb233_read($path);
    $new = preg_replace('/\$version\s*=\s*\'[^\']*\';/', '$version = \'' . $version . '\';', $content, 1);
    if (!is_string($new) || $new === $content) {
        fwrite(STDERR, "ERREUR: version introuvable: $path\n");
        exit(1);
    }
    itxeb233_write($path, $new);
}

function itxeb233_lint(string $path): void
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

$uiHook = itxeb233_read($uiHookTplPath);
if (strpos($uiHook, 'ITXEB V0.23.3 external iframe tracking') === false) {
    if (strpos($uiHook, 'ITXEB V0.23 MediaCast tracking') === false || strpos($uiHook, 'handleMediaCastTrackingBeacon(') === false) {
        fwrite(STDERR, "ERREUR: V0.23.2 doit être appliquée avant V0.23.3\n");
        exit(1);
    }

    $externalJs = <<<'JS'

    /* ITXEB V0.23.3 external iframe tracking */
    var externalFrameObserverStarted = false;

    function externalItemFromFrame(frame) {
        if (!frame) { return null; }
        var src = String(frame.getAttribute('src') || frame.src || frame.getAttribute('data-src') || '');
        if (!src) { return null; }
        var lower = src.toLowerCase();
        var isYoutube = lower.indexOf('youtube.') !== -1 || lower.indexOf('youtube-nocookie.') !== -1 || lower.indexOf('youtu.be') !== -1;
        var isVimeo = lower.indexOf('vimeo.') !== -1;
        if (!isYoutube && !isVimeo) { return null; }

        var playlistItem = selectedItem(state.selectedId);
        var title = String(frame.getAttribute('title') || frame.getAttribute('aria-label') || '');
        if ((!title || title === '') && playlistItem && isExternal(playlistItem)) {
            title = playlistItem.title || '';
        }
        if (!title || title === '') {
            title = isYoutube ? 'Vidéo YouTube MediaCast' : 'Média externe MediaCast';
        }

        return normalizeItem({
            id: (playlistItem && playlistItem.id) ? playlistItem.id : (state.selectedId || src),
            title: title,
            mime: isYoutube ? 'video/youtube' : 'video/external',
            resource: src,
            provider: isYoutube ? 'youtube' : (isVimeo ? 'vimeo' : 'external')
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
JS;

    $needle = "    var tries = 0;\n";
    $replacement = $externalJs . "\n" . $needle;
    $uiHook = itxeb233_replace_once($uiHook, $needle, $replacement, 'JS insertion external iframe tracking');

    $needle = "        patchPlaylist();\n        if (++tries < 80) { window.setTimeout(waitPatch, 100); }\n";
    $replacement = "        patchPlaylist();\n        observeExternalMediaFrames();\n        scanExternalMediaFrames();\n        if (++tries < 80) { window.setTimeout(waitPatch, 100); }\n";
    $uiHook = itxeb233_replace_once($uiHook, $needle, $replacement, 'JS waitPatch external scan');

    $needle = "    document.addEventListener('DOMContentLoaded', waitPatch);\n    window.addEventListener('load', waitPatch);\n";
    $replacement = "    document.addEventListener('DOMContentLoaded', waitPatch);\n    document.addEventListener('DOMContentLoaded', scanExternalMediaFrames);\n    window.addEventListener('load', waitPatch);\n    window.addEventListener('load', scanExternalMediaFrames);\n";
    $uiHook = itxeb233_replace_once($uiHook, $needle, $replacement, 'JS load external scan');

    itxeb233_write($uiHookTplPath, $uiHook);
} else {
    echo "SKIP: UIHook déjà patché V0.23.3\n";
}

itxeb233_set_version($mainPluginPath, '0.23.3-dev');
itxeb233_set_version($companionPluginTplPath, '0.8.14');

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
    itxeb233_lint($path);
}
if (is_file($liveHook)) { itxeb233_lint($liveHook); }
if (is_file($livePlugin)) { itxeb233_lint($livePlugin); }

echo "V0.23.3 MediaCast external iframe detection applied.\n";
