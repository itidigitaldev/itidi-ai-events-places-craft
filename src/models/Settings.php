<?php

namespace iti\craftitiplugin\models;

use craft\base\Model;
use craft\helpers\ElementHelper;

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
    public bool $enableEventSubmissionForm = false;
    public bool $enablePlaceSubmissionForm = false;
    public string $singleEventRoute = 'event';
    public string $singlePlaceRoute = 'place';

    protected function defineRules(): array
    {
        return [
            [['hubname', 'apiKey', 'eventsListingUrl', 'placesListingUrl', 'legacyEventIdParam', 'legacyPlaceIdParam'], 'trim'],
            [['eventsListingUrl', 'placesListingUrl'], 'required', 'when' => fn(Settings $model) => $model->enableLegacyRedirects],
            [['canonicalUrlSource'], 'in', 'range' => ['ssr', 'sitemap']],
            [['singleEventRoute', 'singlePlaceRoute'], 'filter', 'filter' => fn(string $value): string => self::slugify($value)],
            [['singleEventRoute'], 'default', 'value' => 'event'],
            [['singlePlaceRoute'], 'default', 'value' => 'place'],
        ];
    }

    /**
     * generateSlug() needs a booted Craft app (this plugin's own isolated test suite has no Craft class
     * on its autoloader at all) - pass the value through unchanged where there isn't one.
     */
    private static function slugify(string $value): string
    {
        if (!class_exists(\Craft::class) || \Craft::$app === null) {
            return $value;
        }

        return ElementHelper::generateSlug($value);
    }
}
