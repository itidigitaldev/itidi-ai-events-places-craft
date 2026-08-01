<?php

namespace iti\craftitiplugin\tests\services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use iti\craftitiplugin\errors\ContentApiException;
use iti\craftitiplugin\services\SitemapApi;
use PHPUnit\Framework\TestCase;

class SitemapApiTest extends TestCase
{
    private function apiWithHistory(array &$history, array $responses): SitemapApi
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new SitemapApi(['httpClient' => new Client(['handler' => $stack])]);
    }

    public function testFetchSitemapXmlHitsCommonSitemapEndpointWithExpectedQuery(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(200, [], '<urlset></urlset>')]);

        $api->fetchSitemapXml('demo-hub', 'real-key');

        $request = $history[0]['request'];
        $this->assertSame('/api/common/generate-sitemap', $request->getUri()->getPath());
        $this->assertSame('api.imgoingcalendar.com', $request->getUri()->getHost());

        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame('demo-hub', $query['clientName']);
        $this->assertSame('slash', $query['urlFormat']);
        $this->assertSame('real-key', $query['key']);
    }

    public function testFetchSitemapXmlReturnsRawBodyOnSuccess(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(200, [], '<urlset><url><loc>x</loc></url></urlset>')]);

        $xml = $api->fetchSitemapXml('demo-hub', 'real-key');

        $this->assertSame('<urlset><url><loc>x</loc></url></urlset>', $xml);
    }

    public function testFetchSitemapXmlThrowsOnNonTwoXxStatus(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(500, [], 'error')]);

        $this->expectException(ContentApiException::class);
        $api->fetchSitemapXml('demo-hub', 'real-key');
    }

    public function testFetchSitemapXmlThrowsOnEmptyBody(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(200, [], '')]);

        $this->expectException(ContentApiException::class);
        $api->fetchSitemapXml('demo-hub', 'real-key');
    }

    public function testFetchSitemapXmlThrowsOnNetworkError(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new ConnectException('Connection refused', new Request('GET', 'https://api.imgoingcalendar.com/api/common/generate-sitemap')),
        ]));
        $api = new SitemapApi(['httpClient' => new Client(['handler' => $stack])]);

        $this->expectException(ContentApiException::class);
        $api->fetchSitemapXml('demo-hub', 'real-key');
    }

    public function testFetchSitemapJsonHitsCommonSitemapEndpointWithJsonQuery(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(200, [], json_encode(['urlset' => []]))]);

        $api->fetchSitemapJson('demo-hub', 'real-key');

        $request = $history[0]['request'];
        $this->assertSame('/api/common/generate-sitemap', $request->getUri()->getPath());

        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame('demo-hub', $query['clientName']);
        $this->assertSame('slash', $query['urlFormat']);
        $this->assertSame('json', $query['format']);
        $this->assertSame('type,id,loc', $query['includeFields']);
        $this->assertSame('real-key', $query['key']);
    }

    public function testFetchSitemapJsonReturnsDecodedUrlset(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(200, [], json_encode([
            'urlset' => [
                ['type' => 'event', 'id' => 'abc123', 'loc' => 'https://example.com/events/e/some-event-abc123/'],
            ],
        ]))]);

        $urlset = $api->fetchSitemapJson('demo-hub', 'real-key');

        $this->assertSame([
            ['type' => 'event', 'id' => 'abc123', 'loc' => 'https://example.com/events/e/some-event-abc123/'],
        ], $urlset);
    }

    public function testFetchSitemapJsonThrowsOnNonTwoXxStatus(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(500, [], 'error')]);

        $this->expectException(ContentApiException::class);
        $api->fetchSitemapJson('demo-hub', 'real-key');
    }

    public function testFetchSitemapJsonThrowsOnMalformedBody(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(200, [], 'not json')]);

        $this->expectException(ContentApiException::class);
        $api->fetchSitemapJson('demo-hub', 'real-key');
    }

    public function testFetchSitemapJsonThrowsWhenUrlsetKeyMissing(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(200, [], json_encode(['somethingElse' => []]))]);

        $this->expectException(ContentApiException::class);
        $api->fetchSitemapJson('demo-hub', 'real-key');
    }

    public function testFindCanonicalLocReturnsMatchingLoc(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(200, [], json_encode([
            'urlset' => [
                ['type' => 'event', 'id' => 'abc123', 'loc' => 'https://example.com/events/e/some-event-abc123/'],
                ['type' => 'place', 'id' => 'xyz789', 'loc' => 'https://example.com/places/p/some-place-xyz789/'],
            ],
        ]))]);

        $loc = $api->findCanonicalLoc('demo-hub', 'real-key', 'event', 'abc123');

        $this->assertSame('https://example.com/events/e/some-event-abc123/', $loc);
    }

    public function testFindCanonicalLocReturnsNullWhenNoMatch(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(200, [], json_encode([
            'urlset' => [
                ['type' => 'event', 'id' => 'abc123', 'loc' => 'https://example.com/events/e/some-event-abc123/'],
            ],
        ]))]);

        $loc = $api->findCanonicalLoc('demo-hub', 'real-key', 'event', 'no-such-id');

        $this->assertNull($loc);
    }

    public function testFindCanonicalLocIgnoresMalformedEntries(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(200, [], json_encode([
            'urlset' => [
                'not-an-array',
                ['type' => 'event', 'id' => 'abc123', 'loc' => 'https://example.com/events/e/some-event-abc123/'],
            ],
        ]))]);

        $loc = $api->findCanonicalLoc('demo-hub', 'real-key', 'event', 'abc123');

        $this->assertSame('https://example.com/events/e/some-event-abc123/', $loc);
    }

    public function testFindCanonicalLocMatchesIntegerIdAsString(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(200, [], json_encode([
            'urlset' => [
                ['type' => 'event', 'id' => 12345, 'loc' => 'https://example.com/events/e/event-12345/'],
            ],
        ]))]);

        $loc = $api->findCanonicalLoc('demo-hub', 'real-key', 'event', '12345');

        $this->assertSame('https://example.com/events/e/event-12345/', $loc);
    }

    public function testFindCanonicalLocSkipsEntryWithArrayValuedTypeOrId(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(200, [], json_encode([
            'urlset' => [
                ['type' => ['event'], 'id' => 'abc123', 'loc' => 'https://example.com/events/e/bad-type-abc123/'],
                ['type' => 'event', 'id' => ['abc123'], 'loc' => 'https://example.com/events/e/bad-id-abc123/'],
                ['type' => 'event', 'id' => 'abc123', 'loc' => 'https://example.com/events/e/good-abc123/'],
            ],
        ]))]);

        // Promote the "Array to string conversion" warning a naive cast would raise
        // into an exception, so this test actually fails if the array/scalar entries
        // aren't skipped before casting (rather than merely happening to not match).
        set_error_handler(static function (int $errno, string $errstr): bool {
            throw new \ErrorException($errstr, 0, $errno);
        });

        try {
            $loc = $api->findCanonicalLoc('demo-hub', 'real-key', 'event', 'abc123');
        } finally {
            restore_error_handler();
        }

        $this->assertSame('https://example.com/events/e/good-abc123/', $loc);
    }

    public function testFindCanonicalLocSkipsEntryMissingTypeKey(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history, [new Response(200, [], json_encode([
            'urlset' => [
                ['id' => 'abc123', 'loc' => 'https://example.com/events/e/no-type-abc123/'],
            ],
        ]))]);

        $loc = $api->findCanonicalLoc('demo-hub', 'real-key', '', 'abc123');

        $this->assertNull($loc);
    }
}
