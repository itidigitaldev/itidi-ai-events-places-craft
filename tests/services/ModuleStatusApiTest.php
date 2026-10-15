<?php

namespace iti\craftitiplugin\tests\services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use iti\craftitiplugin\errors\ModuleStatusException;
use iti\craftitiplugin\services\ModuleStatusApi;
use PHPUnit\Framework\TestCase;

class ModuleStatusApiTest extends TestCase
{
    private function apiWithMockedResponses(array $responses): ModuleStatusApi
    {
        $stack = HandlerStack::create(new MockHandler($responses));

        return new ModuleStatusApi([
            'httpClient' => new Client(['handler' => $stack]),
        ]);
    }

    public function testSuccessfulResponseReturnsModuleFlags(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['modules' => ['events' => true, 'places' => false, 'itineraries' => true, 'travelBuddy' => true]])),
        ]);

        $result = $api->fetchModuleStatus('demo-hub', 'real-key');

        $this->assertSame(['events' => true, 'places' => false, 'itineraries' => true, 'tripPlanner' => true], $result);
    }

    public function testMissingItinerariesFlagReportsUnavailable(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['modules' => ['events' => true, 'places' => true]])),
        ]);

        $result = $api->fetchModuleStatus('demo-hub', 'real-key');

        $this->assertFalse($result['itineraries']);
    }

    public function testFalseItinerariesFlagReportsUnavailable(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['modules' => ['events' => true, 'itineraries' => false]])),
        ]);

        $result = $api->fetchModuleStatus('demo-hub', 'real-key');

        $this->assertFalse($result['itineraries']);
    }

    public function testNetworkFailureThrowsConnectionMessage(): void
    {
        $api = $this->apiWithMockedResponses([
            new ConnectException('Connection refused', new Request('GET', 'test')),
        ]);

        $this->expectException(ModuleStatusException::class);
        $this->expectExceptionMessage("Couldn't reach ITI Digital. Check your connection and try again.");

        $api->fetchModuleStatus('demo-hub', 'real-key');
    }

    public function testNonSuccessStatusThrowsInvalidCredentialsMessage(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(401, [], 'Unauthorized'),
        ]);

        $this->expectException(ModuleStatusException::class);
        $this->expectExceptionMessage('Invalid Hubname or API Key.');

        $api->fetchModuleStatus('demo-hub', 'bad-key');
    }

    public function testMissingModulesKeyThrowsInvalidCredentialsMessage(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['status' => 'ok'])),
        ]);

        $this->expectException(ModuleStatusException::class);
        $this->expectExceptionMessage('Invalid Hubname or API Key.');

        $api->fetchModuleStatus('demo-hub', 'real-key');
    }

    public function testMalformedJsonThrowsInvalidCredentialsMessage(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], 'not json'),
        ]);

        $this->expectException(ModuleStatusException::class);
        $this->expectExceptionMessage('Invalid Hubname or API Key.');

        $api->fetchModuleStatus('demo-hub', 'real-key');
    }

    public function testMissingTravelBuddyFlagReportsTripPlannerUnavailable(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['modules' => ['events' => true]])),
        ]);

        $this->assertFalse($api->fetchModuleStatus('demo-hub', 'real-key')['tripPlanner']);
    }

    public function testFalseTravelBuddyFlagReportsTripPlannerUnavailable(): void
    {
        $api = $this->apiWithMockedResponses([
            new Response(200, [], json_encode(['modules' => ['events' => true, 'travelBuddy' => false]])),
        ]);

        $this->assertFalse($api->fetchModuleStatus('demo-hub', 'real-key')['tripPlanner']);
    }
}
