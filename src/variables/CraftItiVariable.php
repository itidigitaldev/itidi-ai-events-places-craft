<?php

namespace iti\craftitiplugin\variables;

use Craft;
use craft\helpers\HtmlPurifier;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use HTMLPurifier_Config;
use iti\craftitiplugin\errors\ContentApiException;
use iti\craftitiplugin\errors\ContentNotFoundException;
use iti\craftitiplugin\helpers\SubmissionFormRouteResolver;
use iti\craftitiplugin\Plugin;
use iti\craftitiplugin\seo\CanonicalUrlResolver;
use iti\craftitiplugin\seo\SeoDataFactory;
use iti\craftitiplugin\services\AbstractContentApi;
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

    public function eventSubmissionForm(): Markup
    {
        return $this->markup($this->renderSubmissionForm('event'));
    }

    public function placeSubmissionForm(): Markup
    {
        return $this->markup($this->renderSubmissionForm('place'));
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

    public function searchEvents(string $term, ?string $detailBaseUrl = null): array
    {
        return $this->search('events', $term, $detailBaseUrl);
    }

    public function searchPlaces(string $term, ?string $detailBaseUrl = null): array
    {
        return $this->search('places', $term, $detailBaseUrl);
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
                'title' => $e->getMessage(),
                'message' => $e->bodyMessage,
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

        $currentUrl = Craft::$app->getRequest()->getAbsoluteUrl();
        $seo = $response['seo'] ?? [];

        $canonicalSourceUrl = CanonicalUrlResolver::resolve(
            $settings->canonicalUrlSource,
            Plugin::getInstance()->sitemapApi,
            $settings->hubname,
            $settings->apiKey,
            $api->sitemapType(),
            AbstractContentApi::extractRawId($id),
            $seo['canonicalUrl'] ?? null,
        );

        $seoData = SeoDataFactory::fromDetailResponse($seo, $currentUrl, $canonicalSourceUrl);

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

    private function search(string $type, string $term, ?string $detailBaseUrl): array
    {
        $settings = Plugin::getInstance()->getSettings();

        if ($type === 'events' && !$settings->enableEvents) {
            return [];
        }

        if ($type === 'places' && !$settings->enablePlaces) {
            return [];
        }

        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $detailSuffix = $type === 'events' ? 'e/' : 'p/';
        // Unlike renderListing(), which is always called FROM the actual listing
        // page (so "current URL + suffix" is a sensible default), search results
        // can be rendered from any page with no relationship to where events/places
        // live - default to the site root instead, relying on the plugin's bare
        // e/<id>/p/<id> route rules to resolve correctly regardless of caller.
        $detailBaseUrl ??= rtrim(UrlHelper::stripQueryString(UrlHelper::siteUrl($detailSuffix)), '/') . '/';

        $api = $type === 'events' ? Plugin::getInstance()->eventsApi : Plugin::getInstance()->placesApi;

        try {
            $items = $api->search($settings->hubname, $settings->apiKey, $term);
        } catch (ContentApiException) {
            return [];
        }

        return array_map(fn (array $item) => $this->mapSearchItem($item, $type, $detailBaseUrl), $items);
    }

    private function renderSubmissionForm(string $type): string
    {
        $settings = Plugin::getInstance()->getSettings();

        $enabled = $type === 'event' ? $settings->enableEventSubmissionForm : $settings->enablePlaceSubmissionForm;

        if (!$enabled) {
            return $type === 'event' ? 'Event submission form is disabled.' : 'Place submission form is disabled.';
        }

        if ($settings->hubname === '') {
            return 'ITI Digital Hubname is missing. Please configure the plugin settings first.';
        }

        $route = SubmissionFormRouteResolver::resolve($settings->hubname, $type);

        Craft::$app->getView()->registerJsFile('https://imgoingcalendar.com/imgoingscript.js', ['async' => true]);

        return Craft::$app->getView()->renderTemplate('craft-iti-plugin/_partials/submission-form', [
            'uniqueId' => 'craft-iti-submission-' . StringHelper::UUID(),
            'route' => $route,
        ]);
    }

    private function mapSearchItem(array $item, string $type, string $detailBaseUrl): array
    {
        $id = $item['nameId'] ?? '';

        return [
            'type' => $type === 'events' ? 'event' : 'place',
            'title' => $item['title'] ?? ($item['name'] ?? ''),
            'url' => $id !== '' ? $detailBaseUrl . $id : '',
            'image' => $item['cover']['source'] ?? null,
            'description' => $item['descriptionText'] ?? ($item['description'] ?? ''),
        ];
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
