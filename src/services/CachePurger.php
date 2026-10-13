<?php

namespace iti\craftitiplugin\services;

use Craft;
use nystudio107\seomatic\Seomatic;
use putyourlightson\blitz\Blitz;

final class CachePurger
{
    public static function resolveDetailUrls(string $type, string $nameId, string $siteBaseUrl, string $listingUrl): array
    {
        $marker = $type === 'event' ? 'e' : 'p';
        $urls = [rtrim($siteBaseUrl, '/') . '/' . $marker . '/' . $nameId];

        if ($listingUrl !== '') {
            $urls[] = rtrim($listingUrl, '/') . '/' . $marker . '/' . $nameId;
        }

        return array_values(array_unique($urls));
    }

    public static function resolveListingUrls(string $siteBaseUrl, string $listingUrl): array
    {
        $urls = [];

        if ($listingUrl !== '') {
            $urls[] = rtrim($listingUrl, '/');
        }

        $urls[] = rtrim($siteBaseUrl, '/') . '/sitemap-events-places.xml';

        return array_values(array_unique($urls));
    }

    /**
     * Itineraries have no clean detail routes of their own - they live on whichever
     * entries the admin picked in the CP, so those entries' URLs are what get purged.
     */
    public static function resolveItineraryUrls(array $entryUrls, string $payloadUrl, string $siteBaseUrl): array
    {
        $urls = [];

        foreach ($entryUrls as $url) {
            if (is_string($url) && $url !== '') {
                $urls[] = rtrim($url, '/');
            }
        }

        if ($payloadUrl !== '' && self::isSameOrigin($payloadUrl, $siteBaseUrl)) {
            $urls[] = rtrim($payloadUrl, '/');
        }

        return array_values(array_unique($urls));
    }

    public static function isSameOrigin(string $url, string $siteBaseUrl): bool
    {
        $siteBaseUrl = rtrim($siteBaseUrl, '/');

        return $url === $siteBaseUrl || str_starts_with($url, $siteBaseUrl . '/');
    }

    public static function purgeUrls(array $urls): void
    {
        // Before the empty check: a purge with no resolvable URLs (listing scope on a
        // site with no configured listing page) must still refresh the head tags.
        self::purgeSeomaticMeta();

        if (empty($urls)) {
            return;
        }

        if (!class_exists(Blitz::class) || !Craft::$app->getPlugins()->isPluginEnabled('blitz')) {
            return;
        }

        Blitz::$plugin->refreshCache->refreshCachedUrls($urls);
    }

    /**
     * SEOmatic caches each page's head tags (canonical, title, description, OG) by URI.
     * Without this, a title or description changed at the hub keeps showing the old
     * tags - or the empty ones cached during a hub outage - until that cache expires.
     * All of it is invalidated rather than per URL: a detail page can live under any
     * listing path and the webhook can't know which, and the tag bump is cheap.
     */
    private static function purgeSeomaticMeta(): void
    {
        if (!class_exists(Seomatic::class) || !Craft::$app->getPlugins()->isPluginEnabled('seomatic')) {
            return;
        }

        Seomatic::$plugin->metaContainers->invalidateCaches();
    }
}
