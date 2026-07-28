<?php

namespace iti\craftitiplugin\seo;

final class SeoDataFactory
{
    public static function fromDetailResponse(array $seo, string $currentUrl): SeoData
    {
        return new SeoData(
            title: (string) ($seo['metaTitle'] ?? ''),
            description: (string) ($seo['metaDescription'] ?? ''),
            canonicalUrl: $currentUrl,
            structuredData: self::rewriteHost($seo['structuredData'] ?? [], $currentUrl),
        );
    }

    private static function rewriteHost(array $structuredData, string $currentUrl): array
    {
        if (empty($structuredData)) {
            return [];
        }

        $targetOrigin = parse_url($currentUrl, PHP_URL_SCHEME) . '://' . parse_url($currentUrl, PHP_URL_HOST);

        return array_map(fn (array $schemaObject) => self::rewriteSchemaObject($schemaObject, $targetOrigin), $structuredData);
    }

    private static function rewriteSchemaObject(array $object, string $targetOrigin): array
    {
        // Rewrite root url for any non-BreadcrumbList object (Events, Places in any subtype, etc.)
        if (($object['@type'] ?? null) !== 'BreadcrumbList' && isset($object['url']) && is_string($object['url'])) {
            $object['url'] = self::replaceHost($object['url'], $targetOrigin);
        }

        if (($object['@type'] ?? null) === 'BreadcrumbList' && isset($object['itemListElement']) && is_array($object['itemListElement'])) {
            $object['itemListElement'] = array_map(function (array $item) use ($targetOrigin) {
                if (isset($item['item']) && is_string($item['item'])) {
                    $item['item'] = self::replaceHost($item['item'], $targetOrigin);
                }
                return $item;
            }, $object['itemListElement']);
        }

        return $object;
    }

    private static function replaceHost(string $url, string $targetOrigin): string
    {
        return preg_replace('#^https?://[^/]+#', $targetOrigin, $url);
    }
}
