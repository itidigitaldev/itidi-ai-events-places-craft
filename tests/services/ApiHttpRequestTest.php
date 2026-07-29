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
use iti\craftitiplugin\services\ApiHttpRequest;
use PHPUnit\Framework\TestCase;

class ApiHttpRequestTest extends TestCase
{
    public function testGetReturnsResponseOnSuccess(): void
    {
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], 'body')]));
        $client = new Client(['handler' => $stack]);

        $response = ApiHttpRequest::get($client, 'https://example.com/x', ['a' => '1'], 'application/json');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('body', (string) $response->getBody());
    }

    public function testGetSendsQueryAndAcceptHeader(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], 'body')]));
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);

        ApiHttpRequest::get($client, 'https://example.com/x', ['a' => '1'], 'application/xml');

        $request = $history[0]['request'];
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame('1', $query['a']);
        $this->assertSame('application/xml', $request->getHeaderLine('Accept'));
    }

    public function testGetThrowsContentApiExceptionOnNetworkFailure(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new ConnectException('Connection refused', new Request('GET', 'https://example.com/x')),
        ]));
        $client = new Client(['handler' => $stack]);

        $this->expectException(ContentApiException::class);
        ApiHttpRequest::get($client, 'https://example.com/x', [], 'application/json');
    }

    public function testGetThrowsContentApiExceptionOnNonTwoXxStatus(): void
    {
        $stack = HandlerStack::create(new MockHandler([new Response(500, [], 'error')]));
        $client = new Client(['handler' => $stack]);

        $this->expectException(ContentApiException::class);
        ApiHttpRequest::get($client, 'https://example.com/x', [], 'application/json');
    }
}
