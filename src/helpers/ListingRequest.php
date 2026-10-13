<?php

namespace iti\craftitiplugin\helpers;

final class ListingRequest
{
    /**
     * Listing and detail base URLs for the page being rendered. The query string
     * (e.g. ?page_no=2) must not leak into them, or every card link ends up as
     * ".../events?page_no=2/e/<id>".
     *
     * @return array{0: string, 1: string} [listingBaseUrl, detailBaseUrl]
     */
    public static function baseUrls(string $currentUrl, string $detailSuffix): array
    {
        $listing = preg_replace('/[?#].*$/', '', $currentUrl) ?? $currentUrl;

        return [$listing, rtrim($listing, '/') . '/' . $detailSuffix . '/'];
    }

    public static function pageNumber(mixed $raw): int
    {
        $page = filter_var($raw, FILTER_VALIDATE_INT);

        return $page !== false && $page >= 1 ? $page : 1;
    }
}
