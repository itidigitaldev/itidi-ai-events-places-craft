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
        if (!$enabled || $listingUrl === '' || $idParam === '') {
            return null;
        }

        $value = $queryParams[$idParam] ?? null;

        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $value = (string) $value;

        if ($value === '') {
            return null;
        }

        return rtrim($listingUrl, '/') . '/' . $suffix . '/' . rawurlencode($value);
    }
}
