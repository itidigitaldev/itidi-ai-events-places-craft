<?php

namespace iti\craftitiplugin\tests\helpers;

use iti\craftitiplugin\helpers\LegacyRedirectResolver;
use PHPUnit\Framework\TestCase;

class LegacyRedirectResolverTest extends TestCase
{
    public function testReturnsNullWhenDisabled(): void
    {
        $url = LegacyRedirectResolver::resolve(false, '/events', 'event_id', ['event_id' => '123']);

        $this->assertNull($url);
    }

    public function testReturnsNullWhenListingUrlEmpty(): void
    {
        $url = LegacyRedirectResolver::resolve(true, '', 'event_id', ['event_id' => '123']);

        $this->assertNull($url);
    }

    public function testReturnsNullWhenIdParamNameEmpty(): void
    {
        $url = LegacyRedirectResolver::resolve(true, '/events', '', ['event_id' => '123']);

        $this->assertNull($url);
    }

    public function testReturnsNullWhenQueryParamMissing(): void
    {
        $url = LegacyRedirectResolver::resolve(true, '/events', 'event_id', ['other_param' => '123']);

        $this->assertNull($url);
    }

    public function testReturnsNullWhenQueryParamEmpty(): void
    {
        $url = LegacyRedirectResolver::resolve(true, '/events', 'event_id', ['event_id' => '']);

        $this->assertNull($url);
    }

    public function testBuildsEventUrlWithDefaultSuffix(): void
    {
        $url = LegacyRedirectResolver::resolve(true, '/events', 'event_id', ['event_id' => '12345']);

        $this->assertSame('/events/e/12345', $url);
    }

    public function testBuildsPlaceUrlWithExplicitSuffix(): void
    {
        $url = LegacyRedirectResolver::resolve(true, '/places', 'place_id', ['place_id' => '98765'], suffix: 'p');

        $this->assertSame('/places/p/98765', $url);
    }

    public function testStripsTrailingSlashFromListingUrl(): void
    {
        $url = LegacyRedirectResolver::resolve(true, '/events/', 'event_id', ['event_id' => '12345']);

        $this->assertSame('/events/e/12345', $url);
    }
}
