<?php

namespace iti\craftitiplugin\helpers;

final class TrailingSlashDetector
{
    /**
     * The hub's content endpoints accept a trailingSlash flag that tells it which link
     * format to render into its SSR html/structured data - it should match whatever the
     * current page's own URL looks like, so widget-generated links don't clash with how
     * Craft itself is configured to route (and redirect) trailing slashes.
     */
    public static function shouldAddTrailingSlash(string $path, bool $addTrailingSlashesToUrls): bool
    {
        if ($path === '') {
            $path = '/';
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        if ($path === '/') {
            return $addTrailingSlashesToUrls;
        }

        return str_ends_with($path, '/');
    }
}
