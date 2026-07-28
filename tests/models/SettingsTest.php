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
        $this->assertSame(['events' => false, 'places' => false], $settings->moduleStatus);
        $this->assertFalse($settings->enableEvents);
        $this->assertFalse($settings->enablePlaces);
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
}
