<?php

namespace iti\craftitiplugin\helpers;

final class LegacyRedirectResolver
{
    public static function resolve(
        bool $enabled,
        string $listingUrl,
        string $idParam,
        array $queryParams,
        string $suffix = 'e',
    ): ?string {
        if (!$enabled || $listingUrl === '' || $idParam === '' || empty($queryParams[$idParam])) {
            return null;
        }

        return rtrim($listingUrl, '/') . '/' . $suffix . '/' . $queryParams[$idParam];
    }
}
