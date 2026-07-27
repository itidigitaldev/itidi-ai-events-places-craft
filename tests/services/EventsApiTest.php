<?php

namespace iti\craftitiplugin\tests\services;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use iti\craftitiplugin\errors\ContentNotFoundException;
use iti\craftitiplugin\services\EventsApi;
use PHPUnit\Framework\TestCase;

class EventsApiTest extends TestCase
{
    private function apiWithHistory(array &$history): EventsApi
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['html' => '<p>events</p>'])),
        ]));
        $stack->push(Middleware::history($history));

        return new EventsApi(['httpClient' => new Client(['handler' => $stack])]);
    }

    private function query(array $history): array
    {
        parse_str($history[0]['request']->getUri()->getQuery(), $query);
        return $query;
    }

    public function testListingUrlUsesEventsTypeSegment(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', [], 'https://example.com/', 'https://example.com/e/');

        $this->assertSame('/api/v2.0/demo-hub/events/ssr-listing', $history[0]['request']->getUri()->getPath());
    }

    public function testDetailUrlUsesEventsTypeSegment(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode(['html' => '<p>x</p>']))]));
        $stack->push(Middleware::history($history));
        $api = new EventsApi(['httpClient' => new Client(['handler' => $stack])]);

        $api->fetchDetail('demo-hub', 'real-key', '7');

        $this->assertSame('/api/v2.0/demo-hub/events/ssr-detail', $history[0]['request']->getUri()->getPath());
    }

    public function testNotFoundStatusCodeIs410(): void
    {
        $stack = HandlerStack::create(new MockHandler([new Response(500, [], 'error')]));
        $api = new EventsApi(['httpClient' => new Client(['handler' => $stack])]);

        try {
            $api->fetchDetail('demo-hub', 'real-key', '7');
            $this->fail('Expected ContentNotFoundException');
        } catch (ContentNotFoundException $e) {
            $this->assertSame(410, $e->statusCode);
        }
    }

    public function testCeilingLimitIs100(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', ['limit' => 500], 'https://example.com/', 'https://example.com/e/');

        $this->assertSame('100', $this->query($history)['limit']);
    }

    public function testOneRowAndEditorChoiceTogetherSetsCombinedWidgetRouteWithoutEditorChoiceKey(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', ['oneRow' => true, 'editorChoice' => true], 'https://example.com/', 'https://example.com/e/');

        $query = $this->query($history);
        $this->assertSame('/events/editor-choice/one-row', $query['widgetRoute']);
        $this->assertArrayNotHasKey('editorChoice', $query);
    }

    public function testEditorChoiceAloneSetsEditorChoiceAndWidgetRoute(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', ['editorChoice' => true], 'https://example.com/', 'https://example.com/e/');

        $query = $this->query($history);
        $this->assertSame('true', $query['editorChoice']);
        $this->assertSame('/events/editor-choice', $query['widgetRoute']);
    }

    public function testOneRowAloneSetsWidgetRouteOnly(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', ['oneRow' => true], 'https://example.com/', 'https://example.com/e/');

        $query = $this->query($history);
        $this->assertSame('/events/one-row', $query['widgetRoute']);
        $this->assertArrayNotHasKey('editorChoice', $query);
    }

    public function testNeitherOneRowNorEditorChoiceSetsNoWidgetRoute(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', [], 'https://example.com/', 'https://example.com/e/');

        $this->assertArrayNotHasKey('widgetRoute', $this->query($history));
    }

    public function testFavEventCategoryIdMapsToFavListingId(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', ['favEventCategoryId' => '9'], 'https://example.com/', 'https://example.com/e/');

        $this->assertSame('9', $this->query($history)['favListingId']);
    }

    public function testLocationGroupIdPassedThrough(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', ['locationGroupId' => '4'], 'https://example.com/', 'https://example.com/e/');

        $this->assertSame('4', $this->query($history)['locationGroupId']);
    }

    public function testStartAndEndBothSetUsesRangeNotNamedDate(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', ['start' => '2026-08-01', 'end' => '2026-08-31', 'date' => 'today'], 'https://example.com/', 'https://example.com/e/');

        $query = $this->query($history);
        $this->assertSame('2026-08-01', $query['start']);
        $this->assertSame('2026-08-31', $query['end']);
        $this->assertArrayNotHasKey('date', $query);
    }

    public function testNamedDateUsedWhenNoRange(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', ['date' => 'this-weekend'], 'https://example.com/', 'https://example.com/e/');

        $this->assertSame('this-weekend', $this->query($history)['date']);
    }

    public function testListingBaseUrlAndDetailBaseUrlAndPagePassedThrough(): void
    {
        $history = [];
        $api = $this->apiWithHistory($history);

        $api->fetchListing('demo-hub', 'real-key', [], 'https://example.com/list/', 'https://example.com/list/e/', 3);

        $query = $this->query($history);
        $this->assertSame('https://example.com/list/', $query['listingBaseUrl']);
        $this->assertSame('https://example.com/list/e/', $query['detailBaseUrl']);
        $this->assertSame('3', $query['page']);
    }
}
