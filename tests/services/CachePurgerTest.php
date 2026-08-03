<?php

namespace iti\craftitiplugin\tests\services;

use iti\craftitiplugin\services\CachePurger;
use PHPUnit\Framework\TestCase;

class CachePurgerTest extends TestCase
{
    public function testResolveDetailUrlsIncludesBareRouteForEvent(): void
    {
        $urls = CachePurger::resolveDetailUrls('event', 'sample-event-abc123', 'https://example.com', '');

        $this->assertSame(['https://example.com/e/sample-event-abc123'], $urls);
    }

    public function testResolveDetailUrlsIncludesBareRouteForPlace(): void
    {
        $urls = CachePurger::resolveDetailUrls('place', 'sample-place-xyz789', 'https://example.com', '');

        $this->assertSame(['https://example.com/p/sample-place-xyz789'], $urls);
    }

    public function testResolveDetailUrlsIncludesListingRelativeUrlWhenConfigured(): void
    {
        $urls = CachePurger::resolveDetailUrls('event', 'sample-event-abc123', 'https://example.com', 'https://example.com/events/');

        $this->assertSame(
            [
                'https://example.com/e/sample-event-abc123',
                'https://example.com/events/e/sample-event-abc123',
            ],
            $urls
        );
    }

    public function testResolveDetailUrlsDedupesWhenListingUrlIsSiteRoot(): void
    {
        $urls = CachePurger::resolveDetailUrls('event', 'sample-event-abc123', 'https://example.com', 'https://example.com');

        $this->assertSame(['https://example.com/e/sample-event-abc123'], $urls);
    }

    public function testResolveListingUrlsIncludesSitemapAlways(): void
    {
        $urls = CachePurger::resolveListingUrls('https://example.com', '');

        $this->assertSame(['https://example.com/sitemap-events-places.xml'], $urls);
    }

    public function testResolveListingUrlsIncludesConfiguredListingUrl(): void
    {
        $urls = CachePurger::resolveListingUrls('https://example.com', 'https://example.com/events/');

        $this->assertSame(
            [
                'https://example.com/events',
                'https://example.com/sitemap-events-places.xml',
            ],
            $urls
        );
    }

    public function testPurgeUrlsDoesNothingWhenGivenEmptyArray(): void
    {
        $this->expectNotToPerformAssertions();

        CachePurger::purgeUrls([]);
    }

    public function testPurgeUrlsDoesNothingWhenBlitzIsNotInstalled(): void
    {
        // Blitz is not a dependency of this plugin's isolated test suite, so
        // class_exists(Blitz::class) is always false here - this exercises
        // the real no-op path, not a mock of it. Assert the precondition so
        // this test fails loudly instead of silently testing nothing if
        // Blitz is ever added to composer.json's dev dependencies.
        $this->assertFalse(class_exists(\putyourlightson\blitz\Blitz::class), 'This test requires Blitz to be absent from the test environment.');

        CachePurger::purgeUrls(['https://example.com/e/sample-event-abc123']);
    }

    public function testIsSameOriginRejectsForeignUrl(): void
    {
        $this->assertFalse(CachePurger::isSameOrigin('https://evil.example.net/e/sample-event-abc123', 'https://example.com'));
    }

    public function testIsSameOriginAcceptsSameOriginUrl(): void
    {
        $this->assertTrue(CachePurger::isSameOrigin('https://example.com/e/sample-event-abc123', 'https://example.com'));
    }

    public function testIsSameOriginAcceptsSameOriginUrlWithTrailingSlashOnBase(): void
    {
        $this->assertTrue(CachePurger::isSameOrigin('https://example.com/e/sample-event-abc123', 'https://example.com/'));
    }

    public function testIsSameOriginAcceptsBareSiteBaseUrlItself(): void
    {
        $this->assertTrue(CachePurger::isSameOrigin('https://example.com', 'https://example.com'));
    }

    public function testIsSameOriginRejectsUrlThatMerelySharesAPrefix(): void
    {
        $this->assertFalse(CachePurger::isSameOrigin('https://example.com.evil.net/e/sample-event-abc123', 'https://example.com'));
    }
}
