<?php

namespace iti\craftitiplugin\tests\seo;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use iti\craftitiplugin\seo\CanonicalUrlResolver;
use iti\craftitiplugin\services\SitemapApi;
use PHPUnit\Framework\TestCase;

class CanonicalUrlResolverTest extends TestCase
{
    private function sitemapApiReturning(array $responses): SitemapApi
    {
        $stack = HandlerStack::create(new MockHandler($responses));

        return new SitemapApi(['httpClient' => new Client(['handler' => $stack])]);
    }

    public function testSsrSourceAlwaysReturnsSsrCanonicalUrlUnchanged(): void
    {
        $sitemapApi = $this->sitemapApiReturning([
            new Response(200, [], json_encode(['urlset' => [['type' => 'event', 'id' => 'abc123', 'loc' => 'https://example.com/events/e/some-event-abc123/']]])),
        ]);

        $result = CanonicalUrlResolver::resolve('ssr', $sitemapApi, 'demo-hub', 'real-key', 'event', 'abc123', 'https://savannahvirtualvisitorcenter.com/events/e/some-event-abc123');

        $this->assertSame('https://savannahvirtualvisitorcenter.com/events/e/some-event-abc123', $result);
    }

    public function testSitemapSourceReturnsMatchingLoc(): void
    {
        $sitemapApi = $this->sitemapApiReturning([
            new Response(200, [], json_encode(['urlset' => [['type' => 'event', 'id' => 'abc123', 'loc' => 'https://example.com/events/e/some-event-abc123/']]])),
        ]);

        $result = CanonicalUrlResolver::resolve('sitemap', $sitemapApi, 'demo-hub', 'real-key', 'event', 'abc123', 'https://fallback.example.com/events/e/some-event-abc123');

        $this->assertSame('https://example.com/events/e/some-event-abc123/', $result);
    }

    public function testSitemapSourceFallsBackToSsrCanonicalUrlWhenNoMatch(): void
    {
        $sitemapApi = $this->sitemapApiReturning([
            new Response(200, [], json_encode(['urlset' => []])),
        ]);

        $result = CanonicalUrlResolver::resolve('sitemap', $sitemapApi, 'demo-hub', 'real-key', 'event', 'no-such-id', 'https://fallback.example.com/events/e/some-event-abc123');

        $this->assertSame('https://fallback.example.com/events/e/some-event-abc123', $result);
    }

    public function testSitemapSourceFallsBackToSsrCanonicalUrlOnApiFailure(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new ConnectException('Connection refused', new Request('GET', 'https://api.imgoingcalendar.com/api/common/generate-sitemap')),
        ]));
        $sitemapApi = new SitemapApi(['httpClient' => new Client(['handler' => $stack])]);

        $result = CanonicalUrlResolver::resolve('sitemap', $sitemapApi, 'demo-hub', 'real-key', 'event', 'abc123', 'https://fallback.example.com/events/e/some-event-abc123');

        $this->assertSame('https://fallback.example.com/events/e/some-event-abc123', $result);
    }

    public function testSitemapSourceWithNullSsrFallbackReturnsNullOnFailure(): void
    {
        $sitemapApi = $this->sitemapApiReturning([
            new Response(500, [], 'error'),
        ]);

        $result = CanonicalUrlResolver::resolve('sitemap', $sitemapApi, 'demo-hub', 'real-key', 'event', 'abc123', null);

        $this->assertNull($result);
    }

    public function testSitemapSourceLeavesLocUnchangedWhenTrailingSlashModeNotProvided(): void
    {
        $sitemapApi = $this->sitemapApiReturning([
            new Response(200, [], json_encode(['urlset' => [['type' => 'event', 'id' => 'abc123', 'loc' => 'https://example.com/events/e/some-event-abc123/']]])),
        ]);

        $result = CanonicalUrlResolver::resolve('sitemap', $sitemapApi, 'demo-hub', 'real-key', 'event', 'abc123', 'https://fallback.example.com/events/e/some-event-abc123');

        $this->assertSame('https://example.com/events/e/some-event-abc123/', $result);
    }

    public function testSitemapSourceAppliesNeverTrailingSlashModeToLoc(): void
    {
        $sitemapApi = $this->sitemapApiReturning([
            new Response(200, [], json_encode(['urlset' => [['type' => 'event', 'id' => 'abc123', 'loc' => 'https://example.com/events/e/some-event-abc123/']]])),
        ]);

        $result = CanonicalUrlResolver::resolve('sitemap', $sitemapApi, 'demo-hub', 'real-key', 'event', 'abc123', null, 'never', true);

        $this->assertSame('https://example.com/events/e/some-event-abc123', $result);
    }

    public function testSitemapSourceAppliesAutoTrailingSlashModeFollowingCraftSetting(): void
    {
        $sitemapApi = $this->sitemapApiReturning([
            new Response(200, [], json_encode(['urlset' => [['type' => 'event', 'id' => 'abc123', 'loc' => 'https://example.com/events/e/some-event-abc123/']]])),
        ]);

        $result = CanonicalUrlResolver::resolve('sitemap', $sitemapApi, 'demo-hub', 'real-key', 'event', 'abc123', null, 'auto', false);

        $this->assertSame('https://example.com/events/e/some-event-abc123', $result);
    }

    public function testSitemapSourceFallsBackToSsrCanonicalUrlOnNonContentApiExceptionThrowable(): void
    {
        $sitemapApi = new class extends SitemapApi {
            public function findCanonicalLoc(string $hubname, string $apiKey, string $sitemapType, string $rawId): ?string
            {
                // Simulates the ErrorException a warning-promoted-to-exception cast
                // would previously have thrown on a malformed sitemap entry.
                throw new \ErrorException('Simulated malformed sitemap entry cast failure.');
            }
        };

        $result = CanonicalUrlResolver::resolve('sitemap', $sitemapApi, 'demo-hub', 'real-key', 'event', 'abc123', 'https://fallback.example.com/events/e/some-event-abc123');

        $this->assertSame('https://fallback.example.com/events/e/some-event-abc123', $result);
    }

    public function testSsrSourceAppliesAlwaysTrailingSlashModeToSsrCanonicalUrl(): void
    {
        $sitemapApi = $this->sitemapApiReturning([]);

        $result = CanonicalUrlResolver::resolve('ssr', $sitemapApi, 'demo-hub', 'real-key', 'event', 'abc123', 'https://example.com/events/e/some-event-abc123', 'always', false);

        $this->assertSame('https://example.com/events/e/some-event-abc123/', $result);
    }

    public function testSsrSourceAppliesAutoTrailingSlashModeFollowingCraftSetting(): void
    {
        $sitemapApi = $this->sitemapApiReturning([]);

        $result = CanonicalUrlResolver::resolve('ssr', $sitemapApi, 'demo-hub', 'real-key', 'event', 'abc123', 'https://example.com/events/e/some-event-abc123/', 'auto', false);

        $this->assertSame('https://example.com/events/e/some-event-abc123', $result);
    }

    public function testSsrSourceLeavesMissingCanonicalUrlNullWhenFormatting(): void
    {
        $sitemapApi = $this->sitemapApiReturning([]);

        $result = CanonicalUrlResolver::resolve('ssr', $sitemapApi, 'demo-hub', 'real-key', 'event', 'abc123', null, 'always', false);

        $this->assertNull($result);
    }
}
