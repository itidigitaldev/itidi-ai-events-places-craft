<?php

namespace iti\craftitiplugin\variables;

use Craft;
use craft\helpers\HtmlPurifier;
use craft\helpers\StringHelper;
use HTMLPurifier_Config;
use iti\craftitiplugin\errors\ContentApiException;
use iti\craftitiplugin\errors\ContentNotFoundException;
use iti\craftitiplugin\Plugin;
use iti\craftitiplugin\seo\SeoDataFactory;
use iti\craftitiplugin\web\assets\eventshadowdom\EventShadowDomAsset;
use iti\craftitiplugin\web\assets\listinginit\ListingInitAsset;
use iti\craftitiplugin\web\assets\placeshadowdom\PlaceShadowDomAsset;
use Twig\Markup;

class CraftItiVariable
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

    public function eventsList(array $filters = []): Markup
    {
        return $this->markup($this->renderListing('events', $filters));
    }

    public function placesList(array $filters = []): Markup
    {
        return $this->markup($this->renderListing('places', $filters));
    }

    public function eventDetail(string $id): Markup
    {
        return $this->markup($this->renderDetail('events', $id)['html']);
    }

    public function placeDetail(string $id): Markup
    {
        return $this->markup($this->renderDetail('places', $id)['html']);
    }

    public function eventDetailWithSeo(string $id): array
    {
        $result = $this->renderDetail('events', $id);

        return ['html' => $this->markup($result['html']), 'seo' => $result['seo'], 'contentType' => 'events'];
    }

    public function placeDetailWithSeo(string $id): array
    {
        $result = $this->renderDetail('places', $id);

        return ['html' => $this->markup($result['html']), 'seo' => $result['seo'], 'contentType' => 'places'];
    }

    private function renderDetail(string $type, string $id): array
    {
        $settings = Plugin::getInstance()->getSettings();

        if ($type === 'events' && !$settings->enableEvents) {
            return ['html' => 'Events are disabled.', 'seo' => null];
        }

        if ($type === 'places' && !$settings->enablePlaces) {
            return ['html' => 'Places are disabled.', 'seo' => null];
        }

        $api = $type === 'events' ? Plugin::getInstance()->eventsApi : Plugin::getInstance()->placesApi;

        try {
            $response = $api->fetchDetail($settings->hubname, $settings->apiKey, $id);
        } catch (ContentNotFoundException $e) {
            Craft::$app->getResponse()->setStatusCode($e->statusCode);

            $html = Craft::$app->getView()->renderTemplate('craft-iti-plugin/_partials/detail-not-found', [
                'message' => $e->getMessage(),
            ]);

            return ['html' => $html, 'seo' => null];
        }

        $this->registerCssFiles($response['assets']['css'] ?? []);
        $this->registerJsFiles($response['assets']['js'] ?? []);
        Craft::$app->getView()->registerAssetBundle(
            $type === 'events' ? EventShadowDomAsset::class : PlaceShadowDomAsset::class
        );

        $prefix = 'craft-iti-' . $type;
        $uuid = StringHelper::UUID();

        $html = Craft::$app->getView()->renderTemplate('craft-iti-plugin/_partials/detail', [
            'sourceId' => $prefix . '-source-' . $uuid,
            'shadowId' => $prefix . '-shadow-' . $uuid,
            'sanitizedHtml' => $this->sanitizeSsrHtml($response['html']),
            'globalName' => $type === 'events' ? 'itiEventShadowDom' : 'itiPlaceShadowDom',
            'bootstrap' => $response['bootstrap'] ?? [],
            'cssAssets' => $response['assets']['css'] ?? [],
        ]);

        $seoData = SeoDataFactory::fromDetailResponse($response['seo'] ?? [], Craft::$app->getRequest()->getAbsoluteUrl());

        return ['html' => $html, 'seo' => $seoData];
    }

    private function renderListing(string $type, array $filters): string
    {
        $settings = Plugin::getInstance()->getSettings();

        if ($type === 'events' && !$settings->enableEvents) {
            return 'Events are disabled.';
        }

        if ($type === 'places' && !$settings->enablePlaces) {
            return 'Places are disabled.';
        }

        $detailSuffix = $type === 'events' ? 'e/' : 'p/';

        $listingBaseUrl = $filters['listingBaseUrl'] ?? null;
        $detailBaseUrl = $filters['detailBaseUrl'] ?? null;

        if ($listingBaseUrl === null || $detailBaseUrl === null) {
            $currentUrl = Craft::$app->getRequest()->getAbsoluteUrl();
            $listingBaseUrl ??= $currentUrl;
            $detailBaseUrl ??= rtrim($currentUrl, '/') . '/' . $detailSuffix;
        }

        $page = $filters['page'] ?? 1;

        $api = $type === 'events' ? Plugin::getInstance()->eventsApi : Plugin::getInstance()->placesApi;

        try {
            $response = $api->fetchListing($settings->hubname, $settings->apiKey, $filters, $listingBaseUrl, $detailBaseUrl, $page);
        } catch (ContentApiException) {
            return Craft::$app->getView()->renderTemplate('craft-iti-plugin/_partials/widget-fallback', [
                'hubname' => $settings->hubname,
                'typeSegment' => $type,
            ]);
        }

        $this->registerCssFiles($response['assets']['css'] ?? []);
        $this->registerJsFiles($response['assets']['js'] ?? []);
        Craft::$app->getView()->registerAssetBundle(ListingInitAsset::class);

        return Craft::$app->getView()->renderTemplate('craft-iti-plugin/_partials/listing', [
            'containerId' => 'craft-iti-' . $type . '-' . StringHelper::UUID(),
            'sanitizedHtml' => $this->sanitizeSsrHtml($response['html']),
            'initMethod' => $type === 'events' ? 'initEventListing' : 'initPlaceListing',
            'bootstrap' => $response['bootstrap'] ?? [],
            'structuredData' => $response['seo']['structuredData'] ?? [],
        ]);
    }

    /**
     * The API's SSR html includes its own #imgoingcalendar-wrapper mount point
     * with a set of data-* attributes the widget's hydration script depends on.
     * HTML Purifier's default element/attribute set doesn't know about those,
     * or about the section/article tags the listing markup uses, so they need
     * to be declared explicitly - otherwise the wrapper gets stripped outright.
     */
    private function sanitizeSsrHtml(string $html): string
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

    private function registerCssFiles(array $urls): void
    {
        foreach ($urls as $url) {
            Craft::$app->getView()->registerCssFile($url);
        }
    }

    private function registerJsFiles(array $urls): void
    {
        foreach ($urls as $url) {
            Craft::$app->getView()->registerJsFile($url);
        }
    }

    private function markup(string $text): Markup
    {
        return new Markup($text, 'UTF-8');
    }
}
