<?php

namespace iti\craftitiplugin\tripplanner;

/**
 * WP-equivalent path rules for where the Trip Planner loads: a mode (exclude/include)
 * plus a list of paths, each optionally ending in * to also cover everything under it.
 * Trailing slashes are ignored on both sides, so /events and /events/ are the same page.
 */
final class TripPlannerRules
{
    public const MODE_EXCLUDE = 'exclude';
    public const MODE_INCLUDE = 'include';

    public static function normalizePath(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $wildcard = str_ends_with($value, '*');

        if ($wildcard) {
            $value = substr($value, 0, -1);
        }

        if (preg_match('#^https?://#i', $value)) {
            $value = (string) (parse_url($value, PHP_URL_PATH) ?? '');
        }

        $value = (string) preg_replace('/[?#].*$/', '', $value);
        $value = '/' . trim($value, '/');

        if ($wildcard) {
            return $value === '/' ? '/*' : $value . '/*';
        }

        return $value;
    }

    public static function normalizePathList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        }

        if (!is_array($value)) {
            return [];
        }

        $paths = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $path = self::normalizePath($item);

            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    public static function matches(string $requestPath, string $rule): bool
    {
        $path = self::normalizePath($requestPath === '' ? '/' : $requestPath);
        $rule = self::normalizePath($rule);

        if ($rule === '') {
            return false;
        }

        if ($rule === '/*') {
            return true;
        }

        if (str_ends_with($rule, '/*')) {
            $base = substr($rule, 0, -2);

            return $path === $base || str_starts_with($path, $base . '/');
        }

        return $path === $rule;
    }

    public static function shouldLoad(string $requestPath, string $mode, array $paths): bool
    {
        $include = $mode === self::MODE_INCLUDE;

        if ($paths === []) {
            return !$include;
        }

        $matched = false;

        foreach ($paths as $rule) {
            if (is_string($rule) && self::matches($requestPath, $rule)) {
                $matched = true;
                break;
            }
        }

        return $include ? $matched : !$matched;
    }
}
