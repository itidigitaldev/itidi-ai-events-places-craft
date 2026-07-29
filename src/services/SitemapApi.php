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
}
