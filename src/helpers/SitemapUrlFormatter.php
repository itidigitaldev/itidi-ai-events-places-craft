<?php

namespace iti\craftitiplugin\helpers;

final class SitemapUrlFormatter
{
    /**
     * SitemapApi always requests urlFormat=slash from the hub, so every loc it hands back
     * already ends in a slash - this is what turns that into whatever the CP setting (or,
     * in "auto" mode, Craft's own addTrailingSlashesToUrls) actually calls for.
     *
     * Splits on the first ? or # rather than round-tripping through parse_url()/rebuilding
     * the URL, so a port, userinfo, or already-encoded path segment passes through exactly
     * as it came in - only the bare path before the first ? or # is ever touched.
     */
    public static function applyTrailingSlash(string $url, string $mode, bool $addTrailingSlashesToUrls): string
    {
        $shouldAddSlash = match ($mode) {
            'always' => true,
            'never' => false,
            default => $addTrailingSlashesToUrls,
        };

        $splitAt = strcspn($url, '?#');
        $path = substr($url, 0, $splitAt);
        $rest = substr($url, $splitAt);

        if ($shouldAddSlash) {
            if (!str_ends_with($path, '/')) {
                $path .= '/';
            }
        } elseif (str_ends_with($path, '/') && self::pathPortion($path) !== '/') {
            // Stripping is never allowed to reduce a bare origin's root path ("/") to
            // nothing - that would turn "https://example.com/" into "https://example.com",
            // which technically still means the same thing, but isn't what "never add a
            // trailing slash" is asking for here.
            $path = rtrim($path, '/');
        }

        return $path . $rest;
    }

    /**
     * Everything from (and including) the first "/" after the scheme+host, so a root URL's
     * path portion comes back as exactly "/" rather than the empty string rtrim() would leave.
     */
    private static function pathPortion(string $urlWithoutQueryOrFragment): string
    {
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $urlWithoutQueryOrFragment, $matches)) {
            $afterScheme = strlen($matches[0]);
            $slashPos = strpos($urlWithoutQueryOrFragment, '/', $afterScheme);

            return $slashPos === false ? '' : substr($urlWithoutQueryOrFragment, $slashPos);
        }

        return $urlWithoutQueryOrFragment;
    }

    public static function rewriteLocTags(string $xml, string $mode, bool $addTrailingSlashesToUrls): string
    {
        return preg_replace_callback(
            '#<loc>([^<]+)</loc>#',
            fn (array $matches) => '<loc>' . self::applyTrailingSlash($matches[1], $mode, $addTrailingSlashesToUrls) . '</loc>',
            $xml,
        ) ?? $xml;
    }
}
