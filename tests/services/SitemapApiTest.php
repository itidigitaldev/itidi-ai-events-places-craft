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
}
