<?php

namespace iti\craftitiplugin\services;

use iti\craftitiplugin\errors\ContentApiException;

/**
 * Itineraries only have an embeddable listing and detail - no clean detail routes,
 * no sitemap entries and no "gone" handling - so the events/places listing/detail
 * hooks the base class requires are stubbed and the two fetches here go straight
 * through the shared request() (which still adds the API key and trailingSlash).
 */
class ItinerariesApi extends AbstractContentApi
{
    public function fetchItineraryListing(string $hubname, string $apiKey, string $widgetCode): array
    {
        return $this->requireHtml($this->request($hubname, $apiKey, 'itineraries/ssr-listing', [
            'format' => 'fragment',
            'includeWidget' => 'true',
            'assetMode' => 'external',
            'widgetCode' => $widgetCode,
        ]));
    }

    public function fetchItineraryDetail(string $hubname, string $apiKey, string $id): array
    {
        return $this->requireHtml($this->request($hubname, $apiKey, 'itineraries/ssr-detail', [
            'id' => $id,
            'format' => 'fragment',
            'assetMode' => 'external',
        ]));
    }

    public function sitemapType(): string
    {
        return 'itinerary';
    }

    protected function typeSegment(): string
    {
        return 'itineraries';
    }

    protected function notFoundStatusCode(): int
    {
        return 404;
    }

    protected function ceilingLimit(): int
    {
        return 100;
    }

    protected function mapListingFilters(array $filters, int $resolvedLimit, string $listingBaseUrl, string $detailBaseUrl, int $page): array
    {
        return [];
    }

    private function requireHtml(array $data): array
    {
        if (empty($data['html']) || !is_string($data['html'])) {
            throw new ContentApiException('No itinerary content returned.');
        }

        return $data;
    }
}
