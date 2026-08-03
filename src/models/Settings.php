<?php

namespace iti\craftitiplugin\models;

use craft\base\Model;

class Settings extends Model
{
    public string $hubname = '';
    public string $apiKey = '';
    public bool $isConnected = false;
    public array $moduleStatus = ['events' => false, 'places' => false];
    public bool $enableEvents = false;
    public bool $enablePlaces = false;
    public bool $enableLegacyRedirects = false;
    public string $eventsListingUrl = '';
    public string $placesListingUrl = '';
    public string $legacyEventIdParam = 'event_id';
    public string $legacyPlaceIdParam = 'place_id';
    public string $canonicalUrlSource = 'ssr';
    public string $webhookSecret = '';

    protected function defineRules(): array
    {
        return [
            [['hubname', 'apiKey', 'eventsListingUrl', 'placesListingUrl', 'legacyEventIdParam', 'legacyPlaceIdParam'], 'trim'],
            [['eventsListingUrl', 'placesListingUrl'], 'required', 'when' => fn(Settings $model) => $model->enableLegacyRedirects],
            [['canonicalUrlSource'], 'in', 'range' => ['ssr', 'sitemap']],
        ];
    }
}
