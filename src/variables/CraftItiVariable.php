<?php

namespace iti\craftitiplugin\variables;

use Craft;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use iti\craftitiplugin\errors\ContentApiException;
use iti\craftitiplugin\errors\ContentNotFoundException;
use iti\craftitiplugin\helpers\SsrHtmlSanitizer;
use iti\craftitiplugin\helpers\SubmissionFormRouteResolver;
use iti\craftitiplugin\Plugin;
use iti\craftitiplugin\seo\CanonicalUrlResolver;
use iti\craftitiplugin\seo\SeoDataFactory;
use iti\craftitiplugin\services\AbstractContentApi;
use iti\craftitiplugin\services\ItineraryRenderer;
use iti\craftitiplugin\web\assets\eventshadowdom\EventShadowDomAsset;
use iti\craftitiplugin\web\assets\listinginit\ListingInitAsset;
use iti\craftitiplugin\web\assets\placeshadowdom\PlaceShadowDomAsset;
use Twig\Markup;

class CraftItiVariable
{
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

    public function itinerariesList(array $options = []): Markup
    {
        return $this->markup((new ItineraryRenderer())->renderListing($options));
    }

    public function itineraryDetail(?string $id = null): Markup
    {
        return $this->markup((new ItineraryRenderer())->renderDetail($id));
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

        Plugin::getInstance()->tripPlanner->markModuleContentRendered();

        $api = $type === 'events' ? Plugin::getInstance()->eventsApi : Plugin::getInstance()->placesApi;

        try {
            // The detail page lives at <listing>/e/<id> (or /p/<id>); derive the listing
            // and detail bases from the current URL by dropping that trailing segment pair.
            $detailSuffix = $type === 'events' ? 'e' : 'p';
            $currentUrl = UrlHelper::stripQueryString(Craft::$app->getRequest()->getAbsoluteUrl());
            $listingBaseUrl = preg_replace('#/' . $detailSuffix . '/[^/]+/?$#', '', $currentUrl);
            // The hub expects the detail base to end in "/e/" or "/p/", trailing slash included.
            $detailBaseUrl = $listingBaseUrl . '/' . $detailSuffix . '/';

            $response = $api->fetchDetail($settings->hubname, $settings->apiKey, $id, $listingBaseUrl, $detailBaseUrl);
        } catch (ContentNotFoundException $e) {
            Craft::$app->getResponse()->setStatusCode($e->statusCode);

            $html = Craft::$app->getView()->renderTemplate('craft-iti-plugin/_partials/detail-not-found', [
                'title' => $e->getMessage(),
                'message' => $e->bodyMessage,
            ]);

            return ['html' => $html, 'seo' => null];
        }

        $notAvailable = !empty($response['notAvailable']);

        if ($notAvailable) {
            Craft::$app->getResponse()->setStatusCode($response['notAvailableStatusCode'] ?? $this->notFoundStatusCode($type));
        }

        $this->registerCssFiles($response['assets']['css'] ?? []);
        $this->registerJsFiles($response['assets']['js'] ?? []);
        Craft::$app->getView()->registerAssetBundle(
            $type === 'events' ? EventShadowDomAsset::class : PlaceShadowDomAsset::class
        );

        $prefix = 'craft-iti-' . $type;
        $uuid = StringHelper::UUID();

        $currentUrl = Craft::$app->getRequest()->getAbsoluteUrl();
        $seo = $response['seo'] ?? [];
        $rawId = AbstractContentApi::extractRawId($id);

        $seoData = null;

        if (!$notAvailable) {
            $canonicalSourceUrl = CanonicalUrlResolver::resolve(
                $settings->canonicalUrlSource,
                Plugin::getInstance()->sitemapApi,
                $settings->hubname,
                $settings->apiKey,
                $api->sitemapType(),
                $rawId,
                $seo['canonicalUrl'] ?? null,
                $settings->sitemapTrailingSlash,
                Craft::$app->getConfig()->getGeneral()->addTrailingSlashesToUrls,
            );

            $seoData = SeoDataFactory::fromDetailResponse($seo, $currentUrl, $canonicalSourceUrl);
        }

        $html = Craft::$app->getView()->renderTemplate('craft-iti-plugin/_partials/detail', [
            'sourceId' => $prefix . '-source-' . $uuid,
            'shadowId' => $prefix . '-shadow-' . $uuid,
            'sanitizedHtml' => SsrHtmlSanitizer::sanitize($response['html']),
            'globalName' => $type === 'events' ? 'itiEventShadowDom' : 'itiPlaceShadowDom',
            'bootstrap' => $response['bootstrap'] ?? [],
            'cssAssets' => $response['assets']['css'] ?? [],
            'analyticsData' => [
                'itidi_type' => $type === 'events' ? 'event' : 'place',
                'itidi_id' => $rawId,
                'itidi_name_id' => $id,
                'itidi_canonical_url' => $seoData->canonicalUrl ?? $currentUrl,
                'itidi_visited_url' => $currentUrl,
            ],
        ]);

        return ['html' => $html, 'seo' => $seoData];
    }

    /**
     * Fallback for the (should-never-happen) case where a "not available" marker comes
     * back without the status code the service layer normally carries alongside it.
     */
    private function notFoundStatusCode(string $type): int
    {
        return $type === 'events' ? 410 : 404;
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

        Plugin::getInstance()->tripPlanner->markModuleContentRendered();

        $detailSuffix = $type === 'events' ? 'e/' : 'p/';

        $listingBaseUrl = $filters['listingBaseUrl'] ?? null;
        $detailBaseUrl = $filters['detailBaseUrl'] ?? null;

        if ($listingBaseUrl === null || $detailBaseUrl === null) {
            $currentUrl = Craft::$app->getRequest()->getAbsoluteUrl();
            $listingBaseUrl ??= $currentUrl;
            $detailBaseUrl ??= rtrim($currentUrl, '/') . '/' . $detailSuffix;
        }

        $page = $filters['page'] ?? 1;

        $filters['limit'] ??= $type === 'events' ? $settings->eventListingLimit : $settings->placeListingLimit;

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
            'sanitizedHtml' => SsrHtmlSanitizer::sanitize($response['html']),
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

        Plugin::getInstance()->tripPlanner->markModuleContentRendered();

        $route = SubmissionFormRouteResolver::resolve($settings->hubname, $type);

        Craft::$app->getView()->registerJsFile('https://imgoingcalendar.com/imgoingscript.js', ['async' => true]);

        return Craft::$app->getView()->renderTemplate('craft-iti-plugin/_partials/submission-form', [
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
