<?php

namespace iti\craftitiplugin\tests\services;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use iti\craftitiplugin\errors\ContentApiException;
use iti\craftitiplugin\services\ItinerariesApi;
use PHPUnit\Framework\TestCase;

class ItinerariesApiTest extends TestCase
{
    private function apiWithMockedResponses(array $responses, ?array &$history = null): ItinerariesApi
    {
        $stack = HandlerStack::create(new MockHandler($responses));

        if ($history !== null) {
            $stack->push(Middleware::history($history));
        }

        return new ItinerariesApi([
            'httpClient' => new Client(['handler' => $stack]),
            'trailingSlash' => false,
        ]);
    }

    private static function queryOf(array $history): array
    {
        parse_str($history[0]['request']->getUri()->getQuery(), $query);

        return $query;
    }

    public function testListingHitsSsrListingRouteWithExpectedArgs(): void
    {
        $history = [];
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['html' => '<p>list</p>'])),
        ], $history);

        $result = $api->fetchItineraryListing('demo-hub', 'real-key', 'summer');

        $this->assertSame('<p>list</p>', $result['html']);
        $this->assertSame('/api/v2.0/demo-hub/itineraries/ssr-listing', $history[0]['request']->getUri()->getPath());
        $this->assertSame([
            'format' => 'fragment',
            'includeWidget' => 'true',
            'assetMode' => 'external',
            'widgetCode' => 'summer',
            'key' => 'real-key',
        ], self::queryOf($history));
    }

    public function testDetailHitsSsrDetailRouteWithIdUnchanged(): void
    {
        $history = [];
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['html' => '<p>detail</p>'])),
        ], $history);

        $result = $api->fetchItineraryDetail('demo-hub', 'real-key', 'weekend-trip-688a099b53903247e84036d9');

        $this->assertSame('<p>detail</p>', $result['html']);
        $this->assertSame('/api/v2.0/demo-hub/itineraries/ssr-detail', $history[0]['request']->getUri()->getPath());
        $this->assertSame([
            'id' => 'weekend-trip-688a099b53903247e84036d9',
            'format' => 'fragment',
            'assetMode' => 'external',
            'key' => 'real-key',
        ], self::queryOf($history));
    }

    public function testTrailingSlashFlagIsSentWhenEnabled(): void
    {
        $history = [];
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['html' => '<p>list</p>'])),
        ], $history);
        $api->trailingSlash = true;

        $api->fetchItineraryListing('demo-hub', 'real-key', 'default');

        $this->assertSame('true', self::queryOf($history)['trailingSlash']);
    }

    public function testTrailingSlashFlagIsOmittedWhenDisabled(): void
    {
        $history = [];
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['html' => '<p>detail</p>'])),
        ], $history);

        $api->fetchItineraryDetail('demo-hub', 'real-key', 'abc');

        $this->assertArrayNotHasKey('trailingSlash', self::queryOf($history));
    }

    public function testListingThrowsOnErrorStatus(): void
    {
        $api = $this->apiWithMockedResponses([new Response(500, [], json_encode(['html' => '<p>x</p>']))]);

        $this->expectException(ContentApiException::class);

        $api->fetchItineraryListing('demo-hub', 'real-key', 'default');
    }

    public function testListingThrowsOnEmptyHtml(): void
    {
        $api = $this->apiWithMockedResponses([new Response(200, [], json_encode(['html' => '']))]);

        $this->expectException(ContentApiException::class);

        $api->fetchItineraryListing('demo-hub', 'real-key', 'default');
    }

    public function testDetailThrowsOnEmptyHtml(): void
    {
        $api = $this->apiWithMockedResponses([new Response(200, [], json_encode(['status' => 'ok']))]);

        $this->expectException(ContentApiException::class);

        $api->fetchItineraryDetail('demo-hub', 'real-key', 'abc');
    }

    public function testDetailThrowsOnErrorStatus(): void
    {
        $api = $this->apiWithMockedResponses([new Response(404, [], json_encode(['html' => '<p>gone</p>']))]);

        $this->expectException(ContentApiException::class);

        $api->fetchItineraryDetail('demo-hub', 'real-key', 'abc');
    }
}
