<?php

namespace iti\craftitiplugin\models;

use craft\base\Model;
use craft\helpers\ElementHelper;

class Settings extends Model
{
    public string $hubname = '';
    public string $apiKey = '';
    public bool $isConnected = false;
    public array $moduleStatus = ['events' => false, 'places' => false, 'itineraries' => false];
    public bool $enableEvents = false;
    public bool $enablePlaces = false;
    public bool $enableLegacyRedirects = false;
    public string $eventsListingUrl = '';
    public string $placesListingUrl = '';
    public string $legacyEventIdParam = 'event_id';
    public string $legacyPlaceIdParam = 'place_id';
    public string $canonicalUrlSource = 'ssr';
    public string $sitemapTrailingSlash = 'auto';
    public string $webhookSecret = '';
    public bool $enableEventSubmissionForm = false;
    public bool $enablePlaceSubmissionForm = false;
    public string $singleEventRoute = 'event';
    public string $singlePlaceRoute = 'place';
    public int $eventListingLimit = 100;
    public int $placeListingLimit = 50;
    public bool $enableItineraries = false;
    public array $itineraryListingEntryIds = [];
    public array $itineraryDetailEntryIds = [];
    public string $itineraryDefaultWidgetCode = 'default';
    public bool $enableLlmsTxt = false;
    public string $llmsDestinationName = '';
    public string $llmsOrganizationName = '';
    public array $llmsEventsEntryIds = [];
    public array $llmsPlacesEntryIds = [];
    public bool $llmsAppendToExisting = false;

    protected function defineRules(): array
    {
        return [
            [['hubname', 'apiKey', 'eventsListingUrl', 'placesListingUrl', 'legacyEventIdParam', 'legacyPlaceIdParam'], 'trim'],
            [['eventsListingUrl', 'placesListingUrl'], 'required', 'when' => fn(Settings $model) => $model->enableLegacyRedirects],
            [['canonicalUrlSource'], 'in', 'range' => ['ssr', 'sitemap']],
            [['sitemapTrailingSlash'], 'in', 'range' => ['auto', 'always', 'never']],
            [['singleEventRoute', 'singlePlaceRoute'], 'filter', 'filter' => fn(string $value): string => self::slugify($value)],
            [['singleEventRoute'], 'default', 'value' => 'event'],
            [['singlePlaceRoute'], 'default', 'value' => 'place'],
            [['eventListingLimit'], 'filter', 'filter' => fn(int $value): int => self::clampListingLimit($value, 100)],
            [['placeListingLimit'], 'filter', 'filter' => fn(int $value): int => self::clampListingLimit($value, 50)],
            [['itineraryDefaultWidgetCode'], 'trim'],
            [['itineraryDefaultWidgetCode'], 'default', 'value' => 'default'],
            [['itineraryDefaultWidgetCode'], 'string', 'max' => 100],
            [['itineraryDefaultWidgetCode'], 'match', 'pattern' => '/^[A-Za-z0-9_-]+$/', 'message' => 'Widget code may only contain letters, numbers, hyphens and underscores.'],
            [['itineraryListingEntryIds', 'itineraryDetailEntryIds'], 'filter', 'filter' => fn($value): array => self::normalizeEntryIds($value), 'skipOnArray' => false],
            [['llmsDestinationName', 'llmsOrganizationName'], 'trim'],
            [['llmsDestinationName', 'llmsOrganizationName'], 'string', 'max' => 255],
            [['llmsEventsEntryIds', 'llmsPlacesEntryIds'], 'filter', 'filter' => fn($value): array => self::normalizeEntryIds($value), 'skipOnArray' => false],
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
     * Craft's element select field posts an array of id strings, or '' when nothing is
     * picked - collapse either into a clean list of unique positive entry ids.
     */
    public static function normalizeEntryIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $id = (int) $id;

                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
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
