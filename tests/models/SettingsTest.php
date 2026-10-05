<?php

namespace iti\craftitiplugin\tests\models;

use iti\craftitiplugin\models\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    public function testDefaults(): void
    {
        $settings = new Settings();

        $this->assertSame('', $settings->hubname);
        $this->assertSame('', $settings->apiKey);
        $this->assertFalse($settings->isConnected);
        $this->assertSame(['events' => false, 'places' => false, 'itineraries' => false], $settings->moduleStatus);
        $this->assertFalse($settings->enableEvents);
        $this->assertFalse($settings->enablePlaces);
    }

    public function testSubmissionFormTogglesDefaultToFalse(): void
    {
        $settings = new Settings();

        $this->assertFalse($settings->enableEventSubmissionForm);
        $this->assertFalse($settings->enablePlaceSubmissionForm);
    }

    public function testLiteralApiKeyPassesValidation(): void
    {
        $settings = new Settings();
        $settings->apiKey = 'a-real-literal-key-value';

        $settings->validate(['apiKey']);

        $this->assertFalse($settings->hasErrors('apiKey'));
    }

    public function testEnvVarReferenceApiKeyPassesValidation(): void
    {
        $settings = new Settings();
        $settings->apiKey = '$ITI_DIGITAL_API_KEY';

        $settings->validate(['apiKey']);

        $this->assertFalse($settings->hasErrors('apiKey'));
    }

    public function testEmptyApiKeyPassesValidation(): void
    {
        $settings = new Settings();
        $settings->apiKey = '';

        $settings->validate(['apiKey']);

        $this->assertFalse($settings->hasErrors('apiKey'));
    }

    public function testHubnameAndApiKeyAreTrimmedOnValidation(): void
    {
        $settings = new Settings();
        $settings->hubname = '  demo-hub  ';
        $settings->apiKey = '  $ITI_DIGITAL_API_KEY  ';

        $settings->validate();

        $this->assertSame('demo-hub', $settings->hubname);
        $this->assertSame('$ITI_DIGITAL_API_KEY', $settings->apiKey);
    }

    public function testLegacyRedirectDefaults(): void
    {
        $settings = new Settings();

        $this->assertFalse($settings->enableLegacyRedirects);
        $this->assertSame('', $settings->eventsListingUrl);
        $this->assertSame('', $settings->placesListingUrl);
        $this->assertSame('event_id', $settings->legacyEventIdParam);
        $this->assertSame('place_id', $settings->legacyPlaceIdParam);
    }

    public function testListingUrlsRequiredWhenLegacyRedirectsEnabled(): void
    {
        $settings = new Settings();
        $settings->enableLegacyRedirects = true;

        $settings->validate(['eventsListingUrl', 'placesListingUrl']);

        $this->assertTrue($settings->hasErrors('eventsListingUrl'));
        $this->assertTrue($settings->hasErrors('placesListingUrl'));
    }

    public function testListingUrlsNotRequiredWhenLegacyRedirectsDisabled(): void
    {
        $settings = new Settings();
        $settings->enableLegacyRedirects = false;

        $settings->validate(['eventsListingUrl', 'placesListingUrl']);

        $this->assertFalse($settings->hasErrors('eventsListingUrl'));
        $this->assertFalse($settings->hasErrors('placesListingUrl'));
    }

    public function testLegacyRedirectFieldsAreTrimmedOnValidation(): void
    {
        $settings = new Settings();
        $settings->eventsListingUrl = '  /events  ';
        $settings->placesListingUrl = '  /places  ';
        $settings->legacyEventIdParam = '  event_id  ';
        $settings->legacyPlaceIdParam = '  place_id  ';

        $settings->validate();

        $this->assertSame('/events', $settings->eventsListingUrl);
        $this->assertSame('/places', $settings->placesListingUrl);
        $this->assertSame('event_id', $settings->legacyEventIdParam);
        $this->assertSame('place_id', $settings->legacyPlaceIdParam);
    }

    public function testCanonicalUrlSourceDefaultsToSsr(): void
    {
        $settings = new Settings();

        $this->assertSame('ssr', $settings->canonicalUrlSource);
    }

    public function testSsrCanonicalUrlSourcePassesValidation(): void
    {
        $settings = new Settings();
        $settings->canonicalUrlSource = 'ssr';

        $settings->validate(['canonicalUrlSource']);

        $this->assertFalse($settings->hasErrors('canonicalUrlSource'));
    }

    public function testSitemapCanonicalUrlSourcePassesValidation(): void
    {
        $settings = new Settings();
        $settings->canonicalUrlSource = 'sitemap';

        $settings->validate(['canonicalUrlSource']);

        $this->assertFalse($settings->hasErrors('canonicalUrlSource'));
    }

    public function testUnrecognizedCanonicalUrlSourceFailsValidation(): void
    {
        $settings = new Settings();
        $settings->canonicalUrlSource = 'something-else';

        $settings->validate(['canonicalUrlSource']);

        $this->assertTrue($settings->hasErrors('canonicalUrlSource'));
    }

    public function testSitemapTrailingSlashDefaultsToAuto(): void
    {
        $settings = new Settings();

        $this->assertSame('auto', $settings->sitemapTrailingSlash);
    }

    public function testAutoSitemapTrailingSlashPassesValidation(): void
    {
        $settings = new Settings();
        $settings->sitemapTrailingSlash = 'auto';

        $settings->validate(['sitemapTrailingSlash']);

        $this->assertFalse($settings->hasErrors('sitemapTrailingSlash'));
    }

    public function testAlwaysSitemapTrailingSlashPassesValidation(): void
    {
        $settings = new Settings();
        $settings->sitemapTrailingSlash = 'always';

        $settings->validate(['sitemapTrailingSlash']);

        $this->assertFalse($settings->hasErrors('sitemapTrailingSlash'));
    }

    public function testNeverSitemapTrailingSlashPassesValidation(): void
    {
        $settings = new Settings();
        $settings->sitemapTrailingSlash = 'never';

        $settings->validate(['sitemapTrailingSlash']);

        $this->assertFalse($settings->hasErrors('sitemapTrailingSlash'));
    }

    public function testUnrecognizedSitemapTrailingSlashFailsValidation(): void
    {
        $settings = new Settings();
        $settings->sitemapTrailingSlash = 'something-else';

        $settings->validate(['sitemapTrailingSlash']);

        $this->assertTrue($settings->hasErrors('sitemapTrailingSlash'));
    }

    public function testWebhookSecretDefaultsToEmptyString(): void
    {
        $settings = new Settings();

        $this->assertSame('', $settings->webhookSecret);
    }

    public function testSingleItemRoutesDefaultToEventAndPlace(): void
    {
        $settings = new Settings();

        $this->assertSame('event', $settings->singleEventRoute);
        $this->assertSame('place', $settings->singlePlaceRoute);
    }

    public function testListingLimitsDefaultToCeilings(): void
    {
        $settings = new Settings();

        $this->assertSame(100, $settings->eventListingLimit);
        $this->assertSame(50, $settings->placeListingLimit);
    }

    public function testListingLimitsClampToCeilingWhenTooHigh(): void
    {
        $settings = new Settings();
        $settings->eventListingLimit = 500;
        $settings->placeListingLimit = 500;

        $settings->validate(['eventListingLimit', 'placeListingLimit']);

        $this->assertSame(100, $settings->eventListingLimit);
        $this->assertSame(50, $settings->placeListingLimit);
    }

    public function testListingLimitsFallBackToCeilingWhenZeroOrNegative(): void
    {
        $settings = new Settings();
        $settings->eventListingLimit = 0;
        $settings->placeListingLimit = -5;

        $settings->validate(['eventListingLimit', 'placeListingLimit']);

        $this->assertSame(100, $settings->eventListingLimit);
        $this->assertSame(50, $settings->placeListingLimit);
    }

    public function testListingLimitsPassThroughUnchangedWhenInRange(): void
    {
        $settings = new Settings();
        $settings->eventListingLimit = 25;
        $settings->placeListingLimit = 10;

        $settings->validate(['eventListingLimit', 'placeListingLimit']);

        $this->assertSame(25, $settings->eventListingLimit);
        $this->assertSame(10, $settings->placeListingLimit);
    }

    public function testItineraryDefaults(): void
    {
        $settings = new Settings();

        $this->assertFalse($settings->enableItineraries);
        $this->assertSame([], $settings->itineraryListingEntryIds);
        $this->assertSame([], $settings->itineraryDetailEntryIds);
        $this->assertSame('default', $settings->itineraryDefaultWidgetCode);
    }

    public function testEmptyItineraryWidgetCodeFallsBackToDefault(): void
    {
        $settings = new Settings();
        $settings->itineraryDefaultWidgetCode = '   ';

        $settings->validate(['itineraryDefaultWidgetCode']);

        $this->assertFalse($settings->hasErrors('itineraryDefaultWidgetCode'));
        $this->assertSame('default', $settings->itineraryDefaultWidgetCode);
    }

    public function testItineraryWidgetCodeIsTrimmed(): void
    {
        $settings = new Settings();
        $settings->itineraryDefaultWidgetCode = '  summer_trips-2026 ';

        $settings->validate(['itineraryDefaultWidgetCode']);

        $this->assertFalse($settings->hasErrors('itineraryDefaultWidgetCode'));
        $this->assertSame('summer_trips-2026', $settings->itineraryDefaultWidgetCode);
    }

    public function testItineraryWidgetCodeRejectsInvalidCharacters(): void
    {
        $settings = new Settings();
        $settings->itineraryDefaultWidgetCode = 'bad code<script>';

        $settings->validate(['itineraryDefaultWidgetCode']);

        $this->assertTrue($settings->hasErrors('itineraryDefaultWidgetCode'));
    }

    public function testItineraryWidgetCodeRejectsOverLongValue(): void
    {
        $settings = new Settings();
        $settings->itineraryDefaultWidgetCode = str_repeat('a', 101);

        $settings->validate(['itineraryDefaultWidgetCode']);

        $this->assertTrue($settings->hasErrors('itineraryDefaultWidgetCode'));
    }

    public function testNormalizeEntryIdsKeepsUniquePositiveInts(): void
    {
        $this->assertSame([3, 7], Settings::normalizeEntryIds(['3', 7, '7', '0', '-2', 'abc', '']));
    }

    public function testNormalizeEntryIdsTreatsNonArrayAsEmpty(): void
    {
        $this->assertSame([], Settings::normalizeEntryIds(''));
        $this->assertSame([], Settings::normalizeEntryIds(null));
    }

    public function testEntryIdListsAreNormalizedOnValidate(): void
    {
        $settings = new Settings();
        $settings->itineraryListingEntryIds = ['12', '12', '0'];
        $settings->itineraryDetailEntryIds = ['5'];

        $settings->validate(['itineraryListingEntryIds', 'itineraryDetailEntryIds']);

        $this->assertSame([12], $settings->itineraryListingEntryIds);
        $this->assertSame([5], $settings->itineraryDetailEntryIds);
    }

    public function testLlmsDefaults(): void
    {
        $settings = new Settings();

        $this->assertFalse($settings->enableLlmsTxt);
        $this->assertSame('', $settings->llmsDestinationName);
        $this->assertSame('', $settings->llmsOrganizationName);
        $this->assertSame([], $settings->llmsEventsEntryIds);
        $this->assertSame([], $settings->llmsPlacesEntryIds);
        $this->assertFalse($settings->llmsAppendToExisting);
    }

    public function testLlmsNamesAreTrimmedAndEntryIdsNormalized(): void
    {
        $settings = new Settings();
        $settings->llmsDestinationName = '  Visit Savannah ';
        $settings->llmsOrganizationName = ' Visit Savannah CVB  ';
        $settings->llmsEventsEntryIds = ['4', '4', 'x'];
        $settings->llmsPlacesEntryIds = [''];

        $settings->validate(['llmsDestinationName', 'llmsOrganizationName', 'llmsEventsEntryIds', 'llmsPlacesEntryIds']);

        $this->assertSame('Visit Savannah', $settings->llmsDestinationName);
        $this->assertSame('Visit Savannah CVB', $settings->llmsOrganizationName);
        $this->assertSame([4], $settings->llmsEventsEntryIds);
        $this->assertSame([], $settings->llmsPlacesEntryIds);
    }

    public function testLlmsNamesRejectOverLongValues(): void
    {
        $settings = new Settings();
        $settings->llmsDestinationName = str_repeat('a', 256);

        $settings->validate(['llmsDestinationName']);

        $this->assertTrue($settings->hasErrors('llmsDestinationName'));
    }
}
