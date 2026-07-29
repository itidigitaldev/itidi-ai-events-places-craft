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
use iti\craftitiplugin\errors\ContentNotFoundException;
use iti\craftitiplugin\services\AbstractContentApi;
use PHPUnit\Framework\TestCase;
use yii\caching\ArrayCache;

class StubContentApi extends AbstractContentApi
{
    protected function typeSegment(): string
    {
        return 'stub-type';
    }

    protected function notFoundStatusCode(): int
    {
        return 499;
    }

    protected function ceilingLimit(): int
    {
        return 10;
    }

    protected function mapListingFilters(array $filters, int $resolvedLimit, string $listingBaseUrl, string $detailBaseUrl, int $page): array
    {
        return [
            'limit' => $resolvedLimit,
            'listingBaseUrl' => $listingBaseUrl,
            'detailBaseUrl' => $detailBaseUrl,
            'page' => $page,
        ];
    }
}

class AbstractContentApiTest extends TestCase
{
    private function apiWithMockedResponses(array $responses, ?array &$history = null): StubContentApi
    {
        $stack = HandlerStack::create(new MockHandler($responses));

        if ($history !== null) {
            $stack->push(Middleware::history($history));
        }

        return new StubContentApi([
            'httpClient' => new Client(['handler' => $stack]),
        ]);
    }

    public function testFetchListingSucceedsWithValidHtml(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['html' => '<p>hi</p>', 'assets' => [], 'bootstrap' => []])),
        ]);

        $result = $api->fetchListing('demo-hub', 'real-key', [], 'https://example.com/', 'https://example.com/e/');

        $this->assertSame('<p>hi</p>', $result['html']);
    }

    public function testFetchListingUrlIncludesTypeSegmentAndRoute(): void
    {
        $history = [];
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['html' => '<p>hi</p>'])),
        ], $history);

        $api->fetchListing('demo-hub', 'real-key', [], 'https://example.com/', 'https://example.com/e/');

        $uri = $history[0]['request']->getUri();
        $this->assertSame('/api/v2.0/demo-hub/stub-type/ssr-listing', $uri->getPath());
    }

    public function testLimitDefaultsToCeilingWhenNotRequested(): void
    {
        $history = [];
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['html' => '<p>hi</p>'])),
        ], $history);

        $api->fetchListing('demo-hub', 'real-key', [], 'https://example.com/', 'https://example.com/e/');

        parse_str($history[0]['request']->getUri()->getQuery(), $query);
        $this->assertSame('10', $query['limit']);
    }

    public function testLimitClampsToCeilingWhenRequestExceedsIt(): void
    {
        $history = [];
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['html' => '<p>hi</p>'])),
        ], $history);

        $api->fetchListing('demo-hub', 'real-key', ['limit' => 500], 'https://example.com/', 'https://example.com/e/');

        parse_str($history[0]['request']->getUri()->getQuery(), $query);
        $this->assertSame('10', $query['limit']);
    }

    public function testLimitUsesRequestedValueWhenBelowCeiling(): void
    {
        $history = [];
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['html' => '<p>hi</p>'])),
        ], $history);

        $api->fetchListing('demo-hub', 'real-key', ['limit' => 3], 'https://example.com/', 'https://example.com/e/');

        parse_str($history[0]['request']->getUri()->getQuery(), $query);
        $this->assertSame('3', $query['limit']);
    }

    public function testFetchListingThrowsContentApiExceptionOnNetworkFailure(): void
    {
        $api = $this->apiWithMockedResponses([
            new ConnectException('Connection refused', new Request('GET', 'test')),
        ]);

        $this->expectException(ContentApiException::class);

        $api->fetchListing('demo-hub', 'real-key', [], 'https://example.com/', 'https://example.com/e/');
    }

    public function testFetchListingThrowsContentApiExceptionOnNonSuccessStatus(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(500, [], 'server error'),
        ]);

        $this->expectException(ContentApiException::class);

        $api->fetchListing('demo-hub', 'real-key', [], 'https://example.com/', 'https://example.com/e/');
    }

    public function testFetchListingThrowsContentApiExceptionOnMalformedJson(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], 'not json'),
        ]);

        $this->expectException(ContentApiException::class);

        $api->fetchListing('demo-hub', 'real-key', [], 'https://example.com/', 'https://example.com/e/');
    }

    public function testFetchListingThrowsContentApiExceptionWhenNoUsableHtml(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['status' => 'ok'])),
        ]);

        $this->expectException(ContentApiException::class);

        $api->fetchListing('demo-hub', 'real-key', [], 'https://example.com/', 'https://example.com/e/');
    }

    public function testFetchDetailSucceedsWithValidHtml(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['html' => '<p>detail</p>'])),
        ]);

        $result = $api->fetchDetail('demo-hub', 'real-key', '42');

        $this->assertSame('<p>detail</p>', $result['html']);
    }

    public function testFetchDetailConvergesNetworkFailureToContentNotFoundException(): void
    {
        $api = $this->apiWithMockedResponses([
            new ConnectException('Connection refused', new Request('GET', 'test')),
        ]);

        try {
            $api->fetchDetail('demo-hub', 'real-key', '42');
            $this->fail('Expected ContentNotFoundException');
        } catch (ContentNotFoundException $e) {
            $this->assertSame(499, $e->statusCode);
        }
    }

    public function testFetchDetailConvergesNonSuccessStatusToContentNotFoundException(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(500, [], 'server error'),
        ]);

        $this->expectException(ContentNotFoundException::class);

        $api->fetchDetail('demo-hub', 'real-key', '42');
    }

    public function testFetchDetailConvergesEmptyHtmlToContentNotFoundException(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['error' => 'Event not found'])),
        ]);

        $this->expectException(ContentNotFoundException::class);

        $api->fetchDetail('demo-hub', 'real-key', '42');
    }

    public function testFetchDetailUrlIncludesTypeSegmentAndRoute(): void
    {
        $history = [];
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['html' => '<p>detail</p>'])),
        ], $history);

        $api->fetchDetail('demo-hub', 'real-key', '42');

        $uri = $history[0]['request']->getUri();
        $this->assertSame('/api/v2.0/demo-hub/stub-type/ssr-detail', $uri->getPath());
        parse_str($uri->getQuery(), $query);
        $this->assertSame('42', $query['id']);
        $this->assertSame('fragment', $query['format']);
        $this->assertSame('external', $query['assetMode']);
    }

    public function testFetchDetailStripsSlugPrefixFromId(): void
    {
        $history = [];
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['html' => '<p>detail</p>'])),
        ], $history);

        $api->fetchDetail('demo-hub', 'real-key', 'my-event-name-6a251e6360e8033e32950389');

        parse_str($history[0]['request']->getUri()->getQuery(), $query);
        $this->assertSame('6a251e6360e8033e32950389', $query['id']);
    }

    public function testFetchDetailLeavesABareIdUnchanged(): void
    {
        $history = [];
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['html' => '<p>detail</p>'])),
        ], $history);

        $api->fetchDetail('demo-hub', 'real-key', '6a251e6360e8033e32950389');

        parse_str($history[0]['request']->getUri()->getQuery(), $query);
        $this->assertSame('6a251e6360e8033e32950389', $query['id']);
    }

    public function testSearchUrlIncludesTypeSegmentAndSearchParams(): void
    {
        $history = [];
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['stub-type' => [['title' => 'Result']]])),
        ], $history);
        $api->cache = new ArrayCache();

        $api->search('demo-hub', 'real-key', 'jazz');

        $uri = $history[0]['request']->getUri();
        $this->assertSame('/api/v2.0/demo-hub/stub-type/', $uri->getPath());

        parse_str($uri->getQuery(), $query);
        $this->assertSame('jazz', $query['search']);
        $this->assertSame('50', $query['limit']);
    }

    public function testSearchReturnsTypeKeyedArrayUnwrapped(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['stub-type' => [['title' => 'A'], ['title' => 'B']]])),
        ]);
        $api->cache = new ArrayCache();

        $result = $api->search('demo-hub', 'real-key', 'jazz');

        $this->assertSame([['title' => 'A'], ['title' => 'B']], $result);
    }

    public function testSearchReturnsEmptyArrayWhenTypeKeyMissing(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['paging' => ['totalItems' => 0]])),
        ]);
        $api->cache = new ArrayCache();

        $result = $api->search('demo-hub', 'real-key', 'nonsense-term-xyz');

        $this->assertSame([], $result);
    }

    public function testSearchThrowsContentApiExceptionOnNetworkFailure(): void
    {
        $api = $this->apiWithMockedResponses([
            new ConnectException('Connection refused', new Request('GET', 'test')),
        ]);
        $api->cache = new ArrayCache();

        $this->expectException(ContentApiException::class);

        $api->search('demo-hub', 'real-key', 'jazz');
    }

    public function testSearchThrowsContentApiExceptionOnNonSuccessStatus(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(500, [], 'server error'),
        ]);
        $api->cache = new ArrayCache();

        $this->expectException(ContentApiException::class);

        $api->search('demo-hub', 'real-key', 'jazz');
    }

    public function testSearchThrowsContentApiExceptionOnMalformedJson(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], 'not json'),
        ]);
        $api->cache = new ArrayCache();

        $this->expectException(ContentApiException::class);

        $api->search('demo-hub', 'real-key', 'jazz');
    }

    public function testSearchCachesResultAndDoesNotReRequestWithinTtl(): void
    {
        $history = [];
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['stub-type' => [['title' => 'Cached']]])),
        ], $history);
        $api->cache = new ArrayCache();

        $first = $api->search('demo-hub', 'real-key', 'jazz');
        $second = $api->search('demo-hub', 'real-key', 'jazz');

        $this->assertSame($first, $second);
        $this->assertCount(1, $history);
    }

    public function testSearchWithDifferentTermIssuesNewRequest(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['stub-type' => [['title' => 'Jazz result']]])),
            new Response(200, [], json_encode(['stub-type' => [['title' => 'Blues result']]])),
        ]));
        $stack->push(Middleware::history($history));
        $api = new StubContentApi(['httpClient' => new Client(['handler' => $stack])]);
        $api->cache = new ArrayCache();

        $api->search('demo-hub', 'real-key', 'jazz');
        $api->search('demo-hub', 'real-key', 'blues');

        $this->assertCount(2, $history);
    }
}
