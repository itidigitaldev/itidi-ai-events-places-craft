<?php

namespace iti\craftitiplugin\tripplanner;

/**
 * The Trip Planner widget markup and where it goes in a finished page. The element id
 * must stay the literal "imgoingcalendar-wrapper" - the widget script looks it up by
 * that exact id, which is also why it can't share a page with other plugin widgets.
 */
final class TripPlannerSnippet
{
    public const SCRIPT_URL = 'https://imgoingcalendar.com/imgoingscript.js';
    public const POSITION_FOOTER = 'footer';
    public const POSITION_BODY_OPEN = 'bodyOpen';

    public static function html(string $hubname): string
    {
        $route = '/' . trim(trim($hubname), '/') . '/ai/travel-buddy';

        return '<div id="imgoingcalendar-wrapper" route="' . htmlspecialchars($route, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" url-change="true" version="V3">'
            . '<app-root></app-root>'
            . '<script type="text/javascript" src="' . self::SCRIPT_URL . '"></script>'
            . '</div>';
    }

    /**
     * Whether the page already contains the widget's element id - a hand-coded embed or a
     * {% cache %} hit can carry it even when this request didn't render anything itself.
     * Injecting on top of that would put two elements on the page with the same id.
     */
    public static function pageHasWidget(string $page): bool
    {
        return stripos($page, 'imgoingcalendar-wrapper') !== false;
    }

    public static function inject(string $page, string $snippet, string $position): string
    {
        if ($position === self::POSITION_BODY_OPEN) {
            if (!preg_match('/<body(?:\s[^>]*)?>/i', $page, $match, PREG_OFFSET_CAPTURE)) {
                return $page;
            }

            $at = $match[0][1] + strlen($match[0][0]);

            return substr($page, 0, $at) . $snippet . substr($page, $at);
        }

        $at = strripos($page, '</body>');

        if ($at === false) {
            return $page;
        }

        return substr($page, 0, $at) . $snippet . substr($page, $at);
    }
}
