<?php

namespace iti\craftitiplugin\tests\services;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use iti\craftitiplugin\errors\ContentNotFoundException;
use iti\craftitiplugin\services\PlacesApi;
use PHPUnit\Framework\TestCase;

class PlacesApiTest extends TestCase
{
    private function apiWithHistory(array &$history): PlacesApi
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['html' => '<p>places</p>'])),
        ]));
        $stack->push(Middleware::history($history));

        return new PlacesApi(['httpClient' => new Client(['handler' => $stack])]);
    }

    private function query(array $history): array
    {
        parse_str($history[0]['request']->getUri()->getQuery(), $query);
        return $query;
    }

    public function testListingUrlUsesPlacesTypeSegment(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', [], 'https://example.com/', 'https://example.com/p/');

        $this->assertSame('/api/v2.0/demo-hub/places/ssr-listing', $history[0]['request']->getUri()->getPath());
    }

    public function testDetailUrlUsesPlacesTypeSegment(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode(['html' => '<p>x</p>']))]));
        $stack->push(Middleware::history($history));
        $api = new PlacesApi(['httpClient' => new Client(['handler' => $stack])]);

        $api->fetchDetail('demo-hub', 'real-key', '7');

        $this->assertSame('/api/v2.0/demo-hub/places/ssr-detail', $history[0]['request']->getUri()->getPath());
    }

    public function testNotFoundStatusCodeIs404(): void
    {
        $stack = HandlerStack::create(new MockHandler([new Response(500, [], 'error')]));
        $api = new PlacesApi(['httpClient' => new Client(['handler' => $stack])]);

        try {
            $api->fetchDetail('demo-hub', 'real-key', '7');
            $this->fail('Expected ContentNotFoundException');
        } catch (ContentNotFoundException $e) {
            $this->assertSame(404, $e->statusCode);
        }
    }

    public function testCeilingLimitIs50(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', ['limit' => 500], 'https://example.com/', 'https://example.com/p/');

        $this->assertSame('50', $this->query($history)['limit']);
    }

    public function testEditorChoiceTrueIsSentAndSetsWidgetRoute(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', ['editorChoice' => true], 'https://example.com/', 'https://example.com/p/');

        $query = $this->query($history);
        $this->assertSame('true', $query['editorChoice']);
        $this->assertSame('/places/editor-choice', $query['widgetRoute']);
    }

    public function testEditorChoiceFalseIsAlsoSentUnlikeEvents(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', ['editorChoice' => false], 'https://example.com/', 'https://example.com/p/');

        $query = $this->query($history);
        $this->assertSame('false', $query['editorChoice']);
        $this->assertSame('/places/editor-choice', $query['widgetRoute']);
    }

    public function testEditorChoiceNotSetSendsNoWidgetRoute(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', [], 'https://example.com/', 'https://example.com/p/');

        $query = $this->query($history);
        $this->assertArrayNotHasKey('editorChoice', $query);
        $this->assertArrayNotHasKey('widgetRoute', $query);
    }

    public function testCustomCategoryIdPassedThrough(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', ['customCategoryId' => '5'], 'https://example.com/', 'https://example.com/p/');

        $this->assertSame('5', $this->query($history)['customCategoryId']);
    }

    public function testFavListingIdPassedThroughUnderSameNameAsEventsApiParam(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', ['favListingId' => '11'], 'https://example.com/', 'https://example.com/p/');

        $this->assertSame('11', $this->query($history)['favListingId']);
    }

    public function testLocationGroupIdPassedThrough(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', ['locationGroupId' => '4'], 'https://example.com/', 'https://example.com/p/');

        $this->assertSame('4', $this->query($history)['locationGroupId']);
    }

    public function testListingBaseUrlAndDetailBaseUrlAndPagePassedThrough(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', [], 'https://example.com/list/', 'https://example.com/list/p/', 2);

        $query = $this->query($history);
        $this->assertSame('https://example.com/list/', $query['listingBaseUrl']);
        $this->assertSame('https://example.com/list/p/', $query['detailBaseUrl']);
        $this->assertSame('2', $query['page']);
    }

    public function testSitemapTypeReturnsPlace(): void
    {
        $api = new PlacesApi();

        $this->assertSame('place', $api->sitemapType());
    }
}
