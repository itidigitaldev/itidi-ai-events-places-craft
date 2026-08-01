<?php

namespace iti\craftitiplugin\services;

use Craft;
use GuzzleHttp\ClientInterface;
use iti\craftitiplugin\errors\ContentApiException;
use yii\base\Component;

class SitemapApi extends Component
{
    private const API_BASE = 'https://api.imgoingcalendar.com/api/common/generate-sitemap';
    private const TIMEOUT = 15;
    private const JSON_FETCH_TIMEOUT = 5;

    public ?ClientInterface $httpClient = null;

    public function fetchSitemapXml(string $hubname, string $apiKey): string
    {
        $client = $this->httpClient ?? Craft::createGuzzleClient(['timeout' => self::TIMEOUT]);

        $response = ApiHttpRequest::get($client, self::API_BASE, [
            'clientName' => $hubname,
            'urlFormat' => 'slash',
            'key' => $apiKey,
        ], 'application/xml');

        $xml = (string) $response->getBody();

        if ($xml === '') {
            throw new ContentApiException('ITI Digital returned an empty sitemap.');
        }

        return $xml;
    }

    public function fetchSitemapJson(string $hubname, string $apiKey): array
    {
        $client = $this->httpClient ?? Craft::createGuzzleClient(['timeout' => self::JSON_FETCH_TIMEOUT]);

        $response = ApiHttpRequest::get($client, self::API_BASE, [
            'clientName' => $hubname,
            'urlFormat' => 'slash',
            'format' => 'json',
            'includeFields' => 'type,id,loc',
            'key' => $apiKey,
        ], 'application/json');

        $data = json_decode((string) $response->getBody(), true);
        $urlset = is_array($data) ? ($data['urlset'] ?? null) : null;

        if (!is_array($urlset)) {
            throw new ContentApiException('ITI Digital returned a malformed sitemap.');
        }

        return $urlset;
    }

    public function findCanonicalLoc(string $hubname, string $apiKey, string $sitemapType, string $rawId): ?string
    {
        $urlset = $this->fetchSitemapJson($hubname, $apiKey);

        foreach ($urlset as $entry) {
            if (!is_array($entry) || !isset($entry['type'], $entry['id']) || !is_scalar($entry['type']) || !is_scalar($entry['id'])) {
                continue;
            }

            if ((string) $entry['type'] === $sitemapType && (string) $entry['id'] === $rawId) {
                return is_string($entry['loc'] ?? null) ? $entry['loc'] : null;
            }
        }

        return null;
    }
}
