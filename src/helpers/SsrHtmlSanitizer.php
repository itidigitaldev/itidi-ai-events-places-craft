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
     * Attributes the hub's detail markup puts on arbitrary elements. WordPress's
     * wp_kses lets every data-*, aria-* and a few structural attributes through
     * on any element; HTML Purifier has no wildcards, so the names the hub is
     * known to emit are listed. Without `hidden` in particular, the overlays the
     * hub ships closed (reviews, image preview) render open and cover the page
     * whenever its JavaScript hasn't run.
     */
    private const GLOBAL_ATTRIBUTES = [
        'role',
        'aria-label', 'aria-hidden', 'aria-modal', 'aria-labelledby', 'aria-controls', 'aria-expanded', 'aria-haspopup',
        'data-ig-gallery-image', 'data-ig-gallery-src', 'data-ig-gallery-alt',
        'data-ig-fallback-logo', 'data-ig-fallback-alt', 'data-skip-lazy',
        'data-ig-ga-view-website',
        'data-ig-place-add-to-trip', 'data-ig-add-to-trip', 'data-ig-event-add-calendar', 'data-ig-event-calendar-payload',
        'data-ig-detail-item-type', 'data-ig-detail-item-id', 'data-ig-detail-item-title', 'data-ig-detail-item-image',
        'data-ig-hours-card', 'data-ig-hours-trigger', 'data-ig-hours-panel',
        'data-ig-review-preview', 'data-ig-review-scope', 'data-ig-review-more', 'data-ig-review-more-count',
        'data-ig-review-overlay', 'data-ig-review-close',
        'data-ig-image-preview', 'data-ig-image-preview-img', 'data-ig-image-preview-close',
        'data-ig-scroll-target', 'data-ig-scroll-dir',
        'data-ig-nearby-filter-bar', 'data-ig-nearby-range-label', 'data-ig-nearby-filter', 'data-ig-nearby-popover-toggle',
        'data-ig-nearby-popover', 'data-ig-nearby-range-input', 'data-ig-nearby-range-apply',
        'data-ig-nearby-category-label', 'data-ig-nearby-category-columns', 'data-ig-nearby-category-option', 'data-ig-nearby-category-payload',
        'data-ig-nearby-events-filter-bar', 'data-ig-nearby-events-count-label', 'data-ig-nearby-events-filter',
        'data-ig-nearby-events-popover-toggle', 'data-ig-nearby-events-popover', 'data-ig-nearby-events-range-input',
        'data-ig-nearby-events-range-apply', 'data-ig-nearby-events-date-option', 'data-ig-nearby-events-date-input',
        'data-ig-nearby-events-date-input-start', 'data-ig-nearby-events-date-input-end', 'data-ig-nearby-events-date-apply',
        'data-ig-share-facebook', 'data-ig-share-item-id', 'data-ig-share-hub-name', 'data-ig-share-item-name', 'data-ig-share-url',
        'data-ig-event-copy-link', 'data-ig-event-copy-url', 'data-ig-event-share-url',
        'data-ssr-list', 'data-ssr-detail',
        'data-account-timezone', 'data-nearby-events-in-place', 'data-place-editor-choice-label',
    ];

    /**
     * Basic inline-SVG icon markup the hub's SSR output uses (same set WordPress allows).
     * Never script, style, foreignObject, href/xlink:href or event attributes.
     */
    private const SVG_SHAPES = [
        'path' => ['d'],
        'circle' => ['cx', 'cy', 'r'],
        'rect' => ['x', 'y', 'width', 'height', 'rx', 'ry'],
        'line' => ['x1', 'y1', 'x2', 'y2'],
        'polyline' => ['points'],
        'polygon' => ['points'],
    ];

    private const SVG_COMMON_ATTRIBUTES = [
        'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit',
        'fill-rule', 'clip-rule', 'opacity', 'aria-hidden', 'aria-label', 'role', 'focusable',
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
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('Attr.AllowedRel', ['noopener', 'noreferrer', 'nofollow']);

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

            $def->info_global_attr['hidden'] = new \HTMLPurifier_AttrDef_HTML_Bool('hidden');
            $def->info_global_attr['tabindex'] = new \HTMLPurifier_AttrDef_Integer();

            foreach (self::GLOBAL_ATTRIBUTES as $attribute) {
                $def->info_global_attr[$attribute] = new \HTMLPurifier_AttrDef_Text();
            }

            $def->addElement('button', 'Inline', 'Flow', 'Common', [
                'type' => 'Enum#button,submit,reset',
                'disabled' => 'Bool#disabled',
            ]);

            $svgAttributes = array_fill_keys(array_merge(self::SVG_COMMON_ATTRIBUTES, ['xmlns', 'width', 'height', 'viewbox']), 'Text');
            $def->addElement('svg', 'Inline', 'Optional: ' . implode(' | ', array_keys(self::SVG_SHAPES)), 'Common', $svgAttributes);

            foreach (self::SVG_SHAPES as $shape => $attributes) {
                $def->addElement($shape, false, 'Empty', 'Common', array_fill_keys(array_merge(self::SVG_COMMON_ATTRIBUTES, $attributes), 'Text'));
            }

            $def->addAttribute('img', 'loading', 'Enum#lazy,eager');
        });
    }
}
