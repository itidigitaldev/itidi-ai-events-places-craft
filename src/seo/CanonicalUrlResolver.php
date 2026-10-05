<?php

namespace iti\craftitiplugin\seo;

use iti\craftitiplugin\helpers\SitemapUrlFormatter;
use iti\craftitiplugin\services\SitemapApi;
use Yii;

final class CanonicalUrlResolver
{
    /**
     * Whichever source wins, the URL is run through the sitemap trailing-slash setting so
     * canonical tags always match the sitemap's link format. $sitemapTrailingSlash left
     * null skips formatting entirely - callers that don't have a settings value to pass
     * get the raw URL back unchanged.
     */
    public static function resolve(
        string $source,
        SitemapApi $sitemapApi,
        string $hubname,
        string $apiKey,
        string $sitemapType,
        string $rawId,
        ?string $ssrCanonicalUrl,
        ?string $sitemapTrailingSlash = null,
        bool $addTrailingSlashesToUrls = false,
    ): ?string {
        if ($source === 'sitemap') {
            try {
                $loc = $sitemapApi->findCanonicalLoc($hubname, $apiKey, $sitemapType, $rawId);
            } catch (\Throwable $e) {
                // Yii::warning (not Craft::warning) deliberately - Craft itself extends Yii and
                // shares its logger/log targets, so this still reaches Craft's logs in a running
                // app, but doesn't require Craft's own class to be bootstrapped to run.
                Yii::warning('Canonical URL sitemap lookup failed: ' . $e->getMessage(), __METHOD__);
                $loc = null;
            }

            if ($loc !== null) {
                return self::format($loc, $sitemapTrailingSlash, $addTrailingSlashesToUrls);
            }
        }

        return self::format($ssrCanonicalUrl, $sitemapTrailingSlash, $addTrailingSlashesToUrls);
    }

    private static function format(?string $url, ?string $sitemapTrailingSlash, bool $addTrailingSlashesToUrls): ?string
    {
        if ($url === null || $url === '' || $sitemapTrailingSlash === null) {
            return $url;
        }

        return SitemapUrlFormatter::applyTrailingSlash($url, $sitemapTrailingSlash, $addTrailingSlashesToUrls);
    }
}
