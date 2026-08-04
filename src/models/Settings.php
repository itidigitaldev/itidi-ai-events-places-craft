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
    public int $eventListingLimit = 100;
    public int $placeListingLimit = 50;

    protected function defineRules(): array
    {
        return [
            [['hubname', 'apiKey', 'eventsListingUrl', 'placesListingUrl', 'legacyEventIdParam', 'legacyPlaceIdParam'], 'trim'],
            [['eventsListingUrl', 'placesListingUrl'], 'required', 'when' => fn(Settings $model) => $model->enableLegacyRedirects],
            [['canonicalUrlSource'], 'in', 'range' => ['ssr', 'sitemap']],
            [['singleEventRoute', 'singlePlaceRoute'], 'filter', 'filter' => fn(string $value): string => self::slugify($value)],
            [['singleEventRoute'], 'default', 'value' => 'event'],
            [['singlePlaceRoute'], 'default', 'value' => 'place'],
            [['eventListingLimit'], 'filter', 'filter' => fn(int $value): int => self::clampListingLimit($value, 100)],
            [['placeListingLimit'], 'filter', 'filter' => fn(int $value): int => self::clampListingLimit($value, 50)],
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

    /**
     * Matches WordPress's own absint() -> "< 1 becomes the ceiling" -> min() chain exactly:
     * an invalid, empty, zero, or negative value silently falls back to the ceiling; an
     * in-range-but-too-high value clamps to it. No validation error is ever shown for either case.
     */
    private static function clampListingLimit(int $value, int $ceiling): int
    {
        if ($value < 1) {
            return $ceiling;
        }

        return min($value, $ceiling);
    }
}
