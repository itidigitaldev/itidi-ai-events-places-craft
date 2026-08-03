<?php

namespace iti\craftitiplugin\services;

use Craft;
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

    public static function isSameOrigin(string $url, string $siteBaseUrl): bool
    {
        $siteBaseUrl = rtrim($siteBaseUrl, '/');

        return $url === $siteBaseUrl || str_starts_with($url, $siteBaseUrl . '/');
    }

    public static function purgeUrls(array $urls): void
    {
        if (empty($urls)) {
            return;
        }

        if (!class_exists(Blitz::class) || !Craft::$app->getPlugins()->isPluginEnabled('blitz')) {
            return;
        }

        Blitz::$plugin->refreshCache->refreshCachedUrls($urls);
    }
}
