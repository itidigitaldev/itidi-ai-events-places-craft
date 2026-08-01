<?php

namespace iti\craftitiplugin\services;

class PlacesApi extends AbstractContentApi
{
    public function sitemapType(): string
    {
        return 'place';
    }

    protected function typeSegment(): string
    {
        return 'places';
    }

    protected function notFoundStatusCode(): int
    {
        return 404;
    }

    protected function ceilingLimit(): int
    {
        return 50;
    }

    protected function mapListingFilters(array $filters, int $resolvedLimit, string $listingBaseUrl, string $detailBaseUrl, int $page): array
    {
        $args = [
            'format' => 'fragment',
            'includeWidget' => 'true',
            'limit' => $resolvedLimit,
            'ssrDetail' => 'true',
            'assetMode' => 'external',
            'listingBaseUrl' => $listingBaseUrl,
            'detailBaseUrl' => $detailBaseUrl,
            'page' => $page,
        ];

        if (isset($filters['editorChoice'])) {
            $args['editorChoice'] = $filters['editorChoice'] ? 'true' : 'false';
            $args['widgetRoute'] = '/places/editor-choice';
        }

        if (!empty($filters['customCategoryId'])) {
            $args['customCategoryId'] = $filters['customCategoryId'];
        }

        if (!empty($filters['favListingId'])) {
            $args['favListingId'] = $filters['favListingId'];
        }

        if (!empty($filters['locationGroupId'])) {
            $args['locationGroupId'] = $filters['locationGroupId'];
        }

        return $args;
    }
}
