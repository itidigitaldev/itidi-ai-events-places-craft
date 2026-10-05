<?php

namespace iti\craftitiplugin\services;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use iti\craftitiplugin\errors\ContentApiException;
use Psr\Http\Message\ResponseInterface;

final class ApiHttpRequest
{
    public static function get(ClientInterface $client, string $url, array $query, string $acceptHeader, bool $throwOnError = true): ResponseInterface
    {
        try {
            $response = $client->get($url, [
                'query' => $query,
                'headers' => ['Accept' => $acceptHeader],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new ContentApiException("Couldn't reach ITI Digital. Check your connection and try again.", 0, $e);
        }

        if ($throwOnError && ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300)) {
            throw new ContentApiException('ITI Digital returned an error response.');
        }

        return $response;
    }
}
