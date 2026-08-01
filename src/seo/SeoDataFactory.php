<?php

namespace iti\craftitiplugin\seo;

final class SeoDataFactory
{
    public static function fromDetailResponse(array $seo, string $currentUrl, ?string $canonicalSourceUrl = null): SeoData
    {
        return new SeoData(
            title: (string) ($seo['metaTitle'] ?? ''),
            description: (string) ($seo['metaDescription'] ?? ''),
            canonicalUrl: ($canonicalSourceUrl !== null && $canonicalSourceUrl !== '')
                ? self::replaceHost($canonicalSourceUrl, self::buildTargetOrigin($currentUrl))
                : $currentUrl,
            structuredData: self::rewriteHost($seo['structuredData'] ?? [], $currentUrl),
        );
    }

    public static function rewriteLocTags(string $xml, string $currentUrl): string
    {
        $targetOrigin = self::buildTargetOrigin($currentUrl);

        return preg_replace_callback(
            '#<loc>([^<]+)</loc>#',
            fn (array $matches) => '<loc>' . self::replaceHost($matches[1], $targetOrigin) . '</loc>',
            $xml,
        ) ?? $xml;
    }

    private static function rewriteHost(array $structuredData, string $currentUrl): array
    {
        if (empty($structuredData)) {
            return [];
        }

        $targetOrigin = self::buildTargetOrigin($currentUrl);

        return array_map(
            fn ($schemaObject) => is_array($schemaObject) ? self::rewriteSchemaObject($schemaObject, $targetOrigin) : $schemaObject,
            $structuredData
        );
    }

    private static function rewriteSchemaObject(array $object, string $targetOrigin): array
    {
        // Rewrite root url for any non-BreadcrumbList object (Events, Places in any subtype, etc.)
        if (($object['@type'] ?? null) !== 'BreadcrumbList' && isset($object['url']) && is_string($object['url'])) {
            $object['url'] = self::replaceHost($object['url'], $targetOrigin);
        }

        if (($object['@type'] ?? null) === 'BreadcrumbList' && isset($object['itemListElement']) && is_array($object['itemListElement'])) {
            $object['itemListElement'] = array_map(
                fn ($item) => is_array($item) ? self::rewriteBreadcrumbItem($item, $targetOrigin) : $item,
                $object['itemListElement']
            );
        }

        return $object;
    }

    private static function rewriteBreadcrumbItem(array $item, string $targetOrigin): array
    {
        if (isset($item['item']) && is_string($item['item'])) {
            $item['item'] = self::replaceHost($item['item'], $targetOrigin);
        }
        return $item;
    }

    public static function buildTargetOrigin(string $currentUrl): string
    {
        $port = parse_url($currentUrl, PHP_URL_PORT);

        return parse_url($currentUrl, PHP_URL_SCHEME) . '://' . parse_url($currentUrl, PHP_URL_HOST) . ($port !== null ? ':' . $port : '');
    }

    public static function replaceHost(string $url, string $targetOrigin): string
    {
        $result = $targetOrigin . (parse_url($url, PHP_URL_PATH) ?? '');

        $query = parse_url($url, PHP_URL_QUERY);
        if ($query !== null) {
            $result .= '?' . $query;
        }

        $fragment = parse_url($url, PHP_URL_FRAGMENT);
        if ($fragment !== null) {
            $result .= '#' . $fragment;
        }

        return $result;
    }
}
