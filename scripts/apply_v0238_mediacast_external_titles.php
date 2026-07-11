<?php

declare(strict_types=1);

/**
 * V0.23.8 MediaCast external media title improvement.
 *
 * For external MediaCast videos, prefer the title coming from the ILIAS
 * MediaCast playlist item instead of the generic iframe title such as
 * "Vidéo YouTube MediaCast".
 */

$root = dirname(__DIR__);

function itxeb238_read(string $path): string
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

function itxeb238_write(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "ERREUR: écriture impossible: $path\n");
        exit(1);
    }
    echo "WRITE: $path\n";
}

function itxeb238_replace_once(string $content, string $needle, string $replacement, string $label): string
{
    $pos = strpos($content, $needle);
    if ($pos === false) {
        fwrite(STDERR, "ERREUR: bloc introuvable: $label\n");
        exit(1);
    }
    return substr($content, 0, $pos) . $replacement . substr($content, $pos + strlen($needle));
}

function itxeb238_set_version(string $path, string $version): void
{
    $content = itxeb238_read($path);
    $new = preg_replace('/\$version\s*=\s*\'[^\']*\';/', '$version = \'' . $version . '\';', $content, 1);
    if (!is_string($new) || $new === $content) {
        fwrite(STDERR, "ERREUR: version introuvable: $path\n");
        exit(1);
    }
    itxeb238_write($path, $new);
}

function itxeb238_lint(string $path): void
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

$ui = itxeb238_read($uiHookTplPath);
if (strpos($ui, 'ITXEB V0.23.8 external playlist title') === false) {
    $needle = <<<'JS'
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
JS;

    $replacement = <<<'JS'
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
JS;

    $ui = itxeb238_replace_once($ui, $needle, $replacement, 'UIHook external iframe title extraction');
    itxeb238_write($uiHookTplPath, $ui);
} else {
    echo "SKIP: UIHook déjà patché V0.23.8\n";
}

itxeb238_set_version($mainPluginPath, '0.23.8-dev');
itxeb238_set_version($companionPluginTplPath, '0.8.19');

$liveBase = dirname($root) . '/UserInterfaceHook/IliasTraxEventBridgeCourseUI';
$liveUiHook = $liveBase . '/classes/class.ilIliasTraxEventBridgeCourseUIUIHookGUI.php';
$livePlugin = $liveBase . '/plugin.php';
if (is_file($liveUiHook)) {
    copy($uiHookTplPath, $liveUiHook);
    echo "COPY: $uiHookTplPath -> $liveUiHook\n";
}
if (is_file($livePlugin)) {
    copy($companionPluginTplPath, $livePlugin);
    echo "COPY: $companionPluginTplPath -> $livePlugin\n";
}

foreach ([$uiHookTplPath, $mainPluginPath, $companionPluginTplPath] as $path) {
    itxeb238_lint($path);
}
if (is_file($liveUiHook)) { itxeb238_lint($liveUiHook); }
if (is_file($livePlugin)) { itxeb238_lint($livePlugin); }

echo "V0.23.8 MediaCast external title improvement applied.\n";
