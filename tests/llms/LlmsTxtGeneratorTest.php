<?php

namespace iti\craftitiplugin\tests\llms;

use iti\craftitiplugin\llms\LlmsTxtGenerator;
use PHPUnit\Framework\TestCase;

class LlmsTxtGeneratorTest extends TestCase
{
    private static function input(array $overrides = []): array
    {
        return array_merge([
            'destinationName' => 'Visit Savannah',
            'organizationName' => 'Savannah CVB',
            'eventsActive' => true,
            'placesActive' => true,
            'events' => [['title' => 'Events', 'url' => 'https://site.com/events', 'isHomepage' => false]],
            'places' => [['title' => 'Places', 'url' => 'https://site.com/places', 'isHomepage' => false]],
            'sitemapUrl' => 'https://site.com/sitemap-events-places.xml',
        ], $overrides);
    }

    public function testFullOutputWithBothModules(): void
    {
        $expected = implode("\n", [
            '# Visit Savannah',
            '',
            '> Official destination information for Visit Savannah, maintained by Savannah CVB. Verified events and business listings, updated daily.',
            '',
            '## Events',
            '',
            '- [Upcoming events](https://site.com/events): official events directory with upcoming events, dates, venues, and details',
            '',
            '- [Events and places sitemap](https://site.com/sitemap-events-places.xml): machine-readable index of canonical Event and Place detail URLs, updated daily',
            '',
            '## Places',
            '',
            '- [Business listings](https://site.com/places): verified local businesses, attractions, restaurants, shops, lodging, and visitor information',
            '',
            '## Contact',
            '',
            '- Maintained by Savannah CVB — the official destination marketing organization.',
        ]);

        $this->assertSame($expected, LlmsTxtGenerator::generate(self::input()));
    }

    public function testEventsOnly(): void
    {
        $out = LlmsTxtGenerator::generate(self::input(['placesActive' => false]));

        $this->assertStringContainsString('maintained by Savannah CVB. Verified events, updated daily.', $out);
        $this->assertStringContainsString('- [Events sitemap](https://site.com/sitemap-events-places.xml): machine-readable index of canonical Event detail URLs, updated daily', $out);
        $this->assertStringNotContainsString('## Places', $out);
    }

    public function testPlacesOnlyPutsSitemapInPlacesSection(): void
    {
        $out = LlmsTxtGenerator::generate(self::input(['eventsActive' => false]));

        $this->assertStringContainsString('Verified business listings, updated daily.', $out);
        $this->assertStringNotContainsString('## Events', $out);
        $this->assertMatchesRegularExpression(
            '/## Places\n\n- \[Business listings\]\(https:\/\/site\.com\/places\): [^\n]+\n\n- \[Places sitemap\]\(https:\/\/site\.com\/sitemap-events-places\.xml\): machine-readable index of canonical Place detail URLs, updated daily\n\n## Contact/',
            $out
        );
    }

    public function testBothActiveButNoEventLinksPutsSitemapInPlacesSection(): void
    {
        $out = LlmsTxtGenerator::generate(self::input(['events' => []]));

        $this->assertStringNotContainsString('## Events', $out);
        $this->assertStringContainsString('Verified events and business listings', $out);
        $this->assertMatchesRegularExpression('/## Places\n\n.+\n\n- \[Events and places sitemap\]/', $out);
    }

    public function testMultipleLinksUseEntryTitlesWithFallback(): void
    {
        $out = LlmsTxtGenerator::generate(self::input([
            'events' => [
                ['title' => 'Concerts', 'url' => 'https://site.com/concerts', 'isHomepage' => false],
                ['title' => '', 'url' => 'https://site.com/more', 'isHomepage' => false],
            ],
        ]));

        $this->assertStringContainsString('- [Concerts](https://site.com/concerts): official events directory', $out);
        $this->assertStringContainsString('- [Events](https://site.com/more): official events directory', $out);
    }

    public function testHomepageLabelsAndDescriptions(): void
    {
        $out = LlmsTxtGenerator::generate(self::input([
            'events' => [['title' => 'Home', 'url' => 'https://site.com/', 'isHomepage' => true]],
            'places' => [['title' => 'Home', 'url' => 'https://site.com/', 'isHomepage' => true]],
        ]));

        $this->assertStringContainsString('- [Featured events on homepage](https://site.com/): homepage entry point with featured destination content and event highlights', $out);
        $this->assertStringContainsString('- [Featured places on homepage](https://site.com/): homepage entry point with featured destination content and local business highlights', $out);
    }

    public function testNoSitemapLineWhenUrlMissing(): void
    {
        $out = LlmsTxtGenerator::generate(self::input(['sitemapUrl' => null]));

        $this->assertStringNotContainsString('sitemap', $out);
    }

    public function testInactiveModuleLinksAreIgnored(): void
    {
        $out = LlmsTxtGenerator::generate(self::input(['placesActive' => false]));

        $this->assertStringNotContainsString('https://site.com/places', $out);
    }

    public function testReturnsEmptyStringWhenNothingToList(): void
    {
        $this->assertSame('', LlmsTxtGenerator::generate(self::input(['events' => [], 'places' => []])));
        $this->assertSame('', LlmsTxtGenerator::generate(self::input(['eventsActive' => false, 'placesActive' => false])));
    }

    public function testTextIsCleaned(): void
    {
        $out = LlmsTxtGenerator::generate(self::input([
            'destinationName' => "Visit &amp; Explore\nSavannah",
            'events' => [
                ['title' => 'Music [Live]', 'url' => 'https://site.com/a', 'isHomepage' => false],
                ['title' => 'B', 'url' => 'https://site.com/b', 'isHomepage' => false],
            ],
        ]));

        $this->assertStringStartsWith('# Visit & Explore Savannah', $out);
        $this->assertStringContainsString('- [Music (Live)](https://site.com/a)', $out);
    }

    public function testHtmlTagsAreStripped(): void
    {
        $out = LlmsTxtGenerator::generate(self::input([
            'destinationName' => '<b>Visit</b> Savannah',
        ]));

        $this->assertStringStartsWith('# Visit Savannah', $out);
    }

    public function testEmptyOrganizationNameFallsBackToDestinationName(): void
    {
        $out = LlmsTxtGenerator::generate(self::input([
            'destinationName' => 'Visit Savannah',
            'organizationName' => '',
        ]));

        $this->assertStringContainsString('maintained by Visit Savannah.', $out);
        $this->assertStringContainsString('- Maintained by Visit Savannah — the official destination marketing organization.', $out);
    }
}
