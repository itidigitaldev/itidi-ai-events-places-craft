<?php

namespace iti\craftitiplugin\services;

class EventsApi extends AbstractContentApi
{
    protected function typeSegment(): string
    {
        return 'events';
    }

    protected function notFoundStatusCode(): int
    {
        return 410;
    }

    protected function ceilingLimit(): int
    {
        return 100;
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

        $oneRow = !empty($filters['oneRow']);
        $editorChoice = !empty($filters['editorChoice']);

        if ($oneRow && $editorChoice) {
            $args['widgetRoute'] = '/events/editor-choice/one-row';
        } elseif ($editorChoice) {
            $args['editorChoice'] = 'true';
            $args['widgetRoute'] = '/events/editor-choice';
        } elseif ($oneRow) {
            $args['widgetRoute'] = '/events/one-row';
        }

        if (!empty($filters['favEventCategoryId'])) {
            $args['favListingId'] = $filters['favEventCategoryId'];
        }

        if (!empty($filters['locationGroupId'])) {
            $args['locationGroupId'] = $filters['locationGroupId'];
        }

        if (!empty($filters['start']) && !empty($filters['end'])) {
            $args['start'] = $filters['start'];
            $args['end'] = $filters['end'];
        } elseif (!empty($filters['date'])) {
            $args['date'] = $filters['date'];
        }

        return $args;
    }
}
