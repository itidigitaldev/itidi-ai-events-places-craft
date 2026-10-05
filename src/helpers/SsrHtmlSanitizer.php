<?php

namespace iti\craftitiplugin\helpers;

use craft\helpers\HtmlPurifier;
use HTMLPurifier_Config;

final class SsrHtmlSanitizer
{
    /**
     * The widget's own wrapper div carries the data-* attributes its hydration
     * script reads to find the SSR content - matches the set of attributes
     * WordPress's wp_kses lets through unconditionally on any data-* name.
     */
    private const WRAPPER_DATA_ATTRIBUTES = [
        'data-route',
        'data-imgoing-ssr-listing',
        'data-hub-name',
        'data-api-base-url',
        'data-event-detail-base-url',
        'data-event-share-base-url',
        'data-place-detail-base-url',
        'data-trip-base-url',
        'data-fallback-logo',
        'data-event-editor-choice-label',
        'data-trip-google-client-id',
        'data-trip-facebook-app-id',
        'data-trip-facebook-sdk-version',
        'data-trip-max-count',
        'data-ig-hide-detail-back',
    ];

    /**
     * The API's SSR html includes its own #imgoingcalendar-wrapper mount point
     * with a set of data-* attributes the widget's hydration script depends on.
     * HTML Purifier's default element/attribute set doesn't know about those,
     * or about the section/article tags the listing markup uses, so they need
     * to be declared explicitly - otherwise the wrapper gets stripped outright.
     */
    public static function sanitize(string $html): string
    {
        return HtmlPurifier::process($html, function(HTMLPurifier_Config $config) {
            $config->set('Attr.EnableID', true);

            $def = $config->getHTMLDefinition(true);
            $def->addElement('section', 'Block', 'Flow', 'Common');
            $def->addElement('article', 'Block', 'Flow', 'Common');
            $def->addElement('aside', 'Block', 'Flow', 'Common');
            $def->addElement('app-root', 'Block', 'Empty', 'Common');

            foreach (self::WRAPPER_DATA_ATTRIBUTES as $attribute) {
                $def->addAttribute('div', $attribute, 'CDATA');
            }

            $def->addAttribute('div', 'aria-hidden', 'CDATA');
            $def->addAttribute('section', 'aria-label', 'CDATA');
            $def->addAttribute('li', 'data-ig-card-url', 'CDATA');
            $def->addAttribute('li', 'role', 'CDATA');
            $def->addAttribute('li', 'tabindex', 'CDATA');
        });
    }
}
