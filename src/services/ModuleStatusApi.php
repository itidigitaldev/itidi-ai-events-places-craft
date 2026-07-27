<?php

namespace iti\craftitiplugin\services;

use Craft;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use iti\craftitiplugin\errors\ModuleStatusException;
use yii\base\Component;

class ModuleStatusApi extends Component
{
    private const API_BASE = 'https://api.imgoingcalendar.com/api/v2.0/';

    public ?ClientInterface $httpClient = null;

    public function fetchModuleStatus(string $hubname, string $resolvedApiKey): array
    {
        $url = self::API_BASE . rawurlencode($hubname) . '/module-status';
        $client = $this->httpClient ?? Craft::createGuzzleClient(['timeout' => 15]);

        try {
            $response = $client->get($url, [
                'query' => ['key' => $resolvedApiKey],
                'headers' => ['Accept' => 'application/json'],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new ModuleStatusException("Couldn't reach ITI Digital. Check your connection and try again.", 0, $e);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new ModuleStatusException('Invalid Hubname or API Key.');
        }

        $data = json_decode((string) $response->getBody(), true);

        if (!is_array($data) || empty($data['modules']) || !is_array($data['modules'])) {
            throw new ModuleStatusException('Invalid Hubname or API Key.');
        }

        return [
            'events' => !empty($data['modules']['events']),
            'places' => !empty($data['modules']['places']),
        ];
    }
}
