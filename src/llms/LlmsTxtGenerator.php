<?php

namespace iti\craftitiplugin\llms;

/**
 * Builds the plugin's llms.txt section. Wording and structure deliberately match the
 * WordPress plugin's ITIDI_AI_LLMS::generate_llms_txt_content() so both CMSes publish
 * the same thing to AI crawlers.
 */
final class LlmsTxtGenerator
{
    public static function generate(array $input): string
    {
        $eventsActive = !empty($input['eventsActive']);
        $placesActive = !empty($input['placesActive']);
        $events = $eventsActive ? self::links($input['events'] ?? []) : [];
        $places = $placesActive ? self::links($input['places'] ?? []) : [];

        if ($events === [] && $places === []) {
            return '';
        }

        $destination = self::clean((string) ($input['destinationName'] ?? ''));
        $organization = self::clean((string) ($input['organizationName'] ?? '')) ?: $destination;
        $sitemapUrl = is_string($input['sitemapUrl'] ?? null) && $input['sitemapUrl'] !== '' ? $input['sitemapUrl'] : null;
        $sitemapLine = $sitemapUrl !== null
            ? '- [' . self::sitemapLabel($eventsActive, $placesActive) . '](' . $sitemapUrl . '): ' . self::sitemapDescription($eventsActive, $placesActive)
            : null;

        $lines = [
            '# ' . $destination,
            '',
            '> Official destination information for ' . $destination . ', maintained by ' . $organization . '. ' . self::verifiedDescription($eventsActive, $placesActive) . ', updated daily.',
            '',
        ];

        if ($events !== []) {
            $lines[] = '## Events';
            $lines[] = '';

            foreach ($events as $link) {
                $lines[] = '- [' . self::eventLabel($link, count($events)) . '](' . $link['url'] . '): ' . self::eventDescription($link);
            }

            if ($sitemapLine !== null) {
                $lines[] = '';
                $lines[] = $sitemapLine;
            }

            $lines[] = '';
        }

        if ($places !== []) {
            $lines[] = '## Places';
            $lines[] = '';

            foreach ($places as $link) {
                $lines[] = '- [' . self::placeLabel($link, count($places)) . '](' . $link['url'] . '): ' . self::placeDescription($link);
            }

            if ($events === [] && $sitemapLine !== null) {
                $lines[] = '';
                $lines[] = $sitemapLine;
            }

            $lines[] = '';
        }

        $lines[] = '## Contact';
        $lines[] = '';
        $lines[] = '- Maintained by ' . $organization . ' — the official destination marketing organization.';

        return implode("\n", $lines);
    }

    private static function links(mixed $links): array
    {
        if (!is_array($links)) {
            return [];
        }

        $clean = [];

        foreach ($links as $link) {
            if (is_array($link) && is_string($link['url'] ?? null) && $link['url'] !== '') {
                $clean[] = [
                    'title' => str_replace(['[', ']'], ['(', ')'], self::clean((string) ($link['title'] ?? ''))),
                    'url' => $link['url'],
                    'isHomepage' => !empty($link['isHomepage']),
                ];
            }
        }

        return $clean;
    }

    private static function clean(string $text): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private static function verifiedDescription(bool $events, bool $places): string
    {
        return match (true) {
            $events && $places => 'Verified events and business listings',
            $events => 'Verified events',
            $places => 'Verified business listings',
            default => 'Verified destination information',
        };
    }

    private static function sitemapLabel(bool $events, bool $places): string
    {
        return match (true) {
            $events && $places => 'Events and places sitemap',
            $events => 'Events sitemap',
            $places => 'Places sitemap',
            default => 'ITI sitemap',
        };
    }

    private static function sitemapDescription(bool $events, bool $places): string
    {
        return match (true) {
            $events && $places => 'machine-readable index of canonical Event and Place detail URLs, updated daily',
            $events => 'machine-readable index of canonical Event detail URLs, updated daily',
            $places => 'machine-readable index of canonical Place detail URLs, updated daily',
            default => 'machine-readable index of canonical URLs, updated daily',
        };
    }

    private static function eventLabel(array $link, int $total): string
    {
        if ($link['isHomepage']) {
            return 'Featured events on homepage';
        }

        if ($total === 1) {
            return 'Upcoming events';
        }

        return $link['title'] !== '' ? $link['title'] : 'Events';
    }

    private static function eventDescription(array $link): string
    {
        return $link['isHomepage']
            ? 'homepage entry point with featured destination content and event highlights'
            : 'official events directory with upcoming events, dates, venues, and details';
    }

    private static function placeLabel(array $link, int $total): string
    {
        if ($link['isHomepage']) {
            return 'Featured places on homepage';
        }

        if ($total === 1) {
            return 'Business listings';
        }

        return $link['title'] !== '' ? $link['title'] : 'Places';
    }

    private static function placeDescription(array $link): string
    {
        return $link['isHomepage']
            ? 'homepage entry point with featured destination content and local business highlights'
            : 'verified local businesses, attractions, restaurants, shops, lodging, and visitor information';
    }
}
