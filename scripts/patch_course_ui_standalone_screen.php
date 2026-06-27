<?php

/**
 * Force the xAPI course screen to behave as an autonomous screen even if ILIAS
 * renders it through a native course support page such as Content or Members.
 *
 * This patch adds a CSS/JS guard to hide native subtabs/toolbars/actions and to
 * keep only the xAPI inner navigation visible.
 */

$file = $argv[1] ?? '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "Usage: php patch_course_ui_standalone_screen.php /path/to/class.ilIliasTraxEventBridgeCourseUIScreen.php\n");
    exit(1);
}

$code = file_get_contents($file);
if (!is_string($code) || $code === '') {
    fwrite(STDERR, "Unable to read target file: {$file}\n");
    exit(1);
}

if (strpos($code, 'itxebStandaloneCourseChromeFix') !== false) {
    echo "Standalone xAPI screen patch already present in {$file}\n";
    exit(0);
}

$oldReturn = <<<'PHP'
        return $this->styles() . '<div id="itxeb-course-ui-screen"><h1>' . $this->esc($title) . '</h1><p>' . $this->esc($subtitle) . ($courseRefId > 0 ? ' — course_ref_id ' . $this->esc((string) $courseRefId) : '') . '</p>' . $content . '</div>';
PHP;

$newReturn = <<<'PHP'
        return $this->itxebStandaloneCourseChromeFix() . $this->styles() . '<div id="itxeb-course-ui-screen"><h1>' . $this->esc($title) . '</h1><p>' . $this->esc($subtitle) . ($courseRefId > 0 ? ' — course_ref_id ' . $this->esc((string) $courseRefId) : '') . '</p>' . $content . '</div>';
PHP;

$updated = str_replace($oldReturn, $newReturn, $code);
if ($updated === $code) {
    fwrite(STDERR, "Patch failed: unable to replace renderShell return in {$file}\n");
    exit(1);
}

$method = <<<'PHP'
    private function itxebStandaloneCourseChromeFix(): string
    {
        return <<<'HTML'
<style id="itxeb-standalone-course-chrome-css">
body:has(#itxeb-course-ui-screen) #ilSubTab,
body:has(#itxeb-course-ui-screen) #ilSubTabs,
body:has(#itxeb-course-ui-screen) #il_sub_tab,
body:has(#itxeb-course-ui-screen) #il_sub_tabs,
body:has(#itxeb-course-ui-screen) #ilToolbar,
body:has(#itxeb-course-ui-screen) #il_toolbar,
body:has(#itxeb-course-ui-screen) .ilSubTab,
body:has(#itxeb-course-ui-screen) .ilSubTabs,
body:has(#itxeb-course-ui-screen) .il_SubTab,
body:has(#itxeb-course-ui-screen) .il_SubTabs,
body:has(#itxeb-course-ui-screen) .ilToolbar,
body:has(#itxeb-course-ui-screen) .ilToolbarContainer,
body:has(#itxeb-course-ui-screen) .ilToolbarStickyItems,
body:has(#itxeb-course-ui-screen) .il-viewcontrol-section,
body:has(#itxeb-course-ui-screen) .ilViewControl,
body:has(#itxeb-course-ui-screen) .ilAdminRowCommands,
body:has(#itxeb-course-ui-screen) .il-item-commands,
body:has(#itxeb-course-ui-screen) .il-item-buttons {
    display: none !important;
}
body:has(#itxeb-course-ui-screen) #tab_itxeb_course_xapi_main,
body:has(#itxeb-course-ui-screen) #itxeb_course_xapi_main_tab {
    display: inline-block !important;
}
body:has(#itxeb-course-ui-screen) #itxeb_course_xapi_main_tab {
    font-weight: 700 !important;
}
</style>
<script id="itxeb-standalone-course-chrome-js">
(function () {
    function hasItxebMarker(element) {
        if (!element || !element.getAttribute) {
            return false;
        }
        var id = element.getAttribute('id') || '';
        var cls = element.getAttribute('class') || '';
        if (id.indexOf('itxeb') !== -1 || cls.indexOf('itxeb') !== -1) {
            return true;
        }
        return !!element.querySelector('[id*="itxeb"],[class*="itxeb"]');
    }

    function normalizeText(element) {
        return (element && element.textContent ? element.textContent : '').replace(/\s+/g, ' ').trim();
    }

    function hideNativeChrome() {
        var root = document.getElementById('itxeb-course-ui-screen');
        if (!root) {
            return;
        }

        var labels = [
            'Editer Participants',
            'Éditer Participants',
            'Répartition par groupes',
            'Galerie des Membres du Cours',
            'Voir',
            'Gérer',
            'Ajouter un nouvel objet',
            'Editer la page',
            'Éditer la page',
            'Vue des membres',
            'Rechercher utilisateurs',
            'Feuille de Présence',
            'Envoyer un message aux membres',
            'Informations sur le cours',
            'Général'
        ];

        Array.prototype.forEach.call(document.querySelectorAll('nav,ul,ol,form,div,section'), function (element) {
            if (!element || element.contains(root) || hasItxebMarker(element)) {
                return;
            }
            var text = normalizeText(element);
            if (text === '' || text.indexOf('Suivi xAPI') !== -1) {
                return;
            }
            var hit = labels.some(function (label) {
                return text.indexOf(label) !== -1;
            });
            if (!hit) {
                return;
            }
            var rect = element.getBoundingClientRect ? element.getBoundingClientRect() : {height: 0};
            if (rect.height === 0 || rect.height < 420) {
                element.style.setProperty('display', 'none', 'important');
                element.setAttribute('data-itxeb-hidden-native-chrome', '1');
            }
        });

        var xapiTab = document.getElementById('tab_itxeb_course_xapi_main');
        if (xapiTab && xapiTab.parentElement) {
            Array.prototype.forEach.call(xapiTab.parentElement.children, function (sibling) {
                if (sibling === xapiTab) {
                    return;
                }
                sibling.classList.remove('active');
                Array.prototype.forEach.call(sibling.querySelectorAll('a.active,[aria-selected="true"]'), function (link) {
                    link.classList.remove('active');
                    if (link.hasAttribute('aria-selected')) {
                        link.setAttribute('aria-selected', 'false');
                    }
                });
            });
            xapiTab.classList.add('active');
        }

        var xapiLink = document.getElementById('itxeb_course_xapi_main_tab');
        if (xapiLink) {
            xapiLink.classList.add('active');
            if (xapiLink.hasAttribute('aria-selected')) {
                xapiLink.setAttribute('aria-selected', 'true');
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideNativeChrome);
    } else {
        hideNativeChrome();
    }
    window.setTimeout(hideNativeChrome, 50);
    window.setTimeout(hideNativeChrome, 250);
    window.setTimeout(hideNativeChrome, 750);
})();
</script>
HTML;
    }

PHP;

$marker = "    private function renderMessage(): string\n";
$updated2 = str_replace($marker, $method . $marker, $updated);
if ($updated2 === $updated) {
    fwrite(STDERR, "Patch failed: unable to insert itxebStandaloneCourseChromeFix method in {$file}\n");
    exit(1);
}

if (file_put_contents($file, $updated2) === false) {
    fwrite(STDERR, "Unable to write target file: {$file}\n");
    exit(1);
}

echo "Standalone xAPI screen patch applied to {$file}\n";
