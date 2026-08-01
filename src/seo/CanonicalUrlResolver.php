<?php

namespace iti\craftitiplugin\seo;

use iti\craftitiplugin\services\SitemapApi;
use Yii;

final class CanonicalUrlResolver
{
    public static function resolve(
        string $source,
        SitemapApi $sitemapApi,
        string $hubname,
        string $apiKey,
        string $sitemapType,
        string $rawId,
        ?string $ssrCanonicalUrl,
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
                return $loc;
            }
        }

        return $ssrCanonicalUrl;
    }
}
