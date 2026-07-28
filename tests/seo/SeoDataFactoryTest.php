<?php

namespace iti\craftitiplugin\tests\seo;

use iti\craftitiplugin\seo\SeoDataFactory;
use PHPUnit\Framework\TestCase;

class SeoDataFactoryTest extends TestCase
{
    public function testMissingSeoDataProducesEmptyValues(): void
    {
        $seoData = SeoDataFactory::fromDetailResponse([], 'https://mysite.com/events/e/123');

        $this->assertSame('', $seoData->title);
        $this->assertSame('', $seoData->description);
        $this->assertSame('https://mysite.com/events/e/123', $seoData->canonicalUrl);
        $this->assertSame([], $seoData->structuredData);
    }

    public function testTitleAndDescriptionPassThroughVerbatim(): void
    {
        $seoData = SeoDataFactory::fromDetailResponse([
            'metaTitle' => '14th Annual Richmond Hill Red Hot Chili Pepper 5k',
            'metaDescription' => 'A 5k, a 10k, and a half-mile Kids Fun Run.',
        ], 'https://mysite.com/events/e/123');

        $this->assertSame('14th Annual Richmond Hill Red Hot Chili Pepper 5k', $seoData->title);
        $this->assertSame('A 5k, a 10k, and a half-mile Kids Fun Run.', $seoData->description);
    }

    public function testCanonicalUrlIsAlwaysTheCurrentUrlNeverTheApiField(): void
    {
        $seoData = SeoDataFactory::fromDetailResponse([
            'canonicalUrl' => 'https://savannahvirtualvisitorcenter.com/events/e/some-other-slug-123',
        ], 'https://mysite.com/events/e/123');

        $this->assertSame('https://mysite.com/events/e/123', $seoData->canonicalUrl);
    }

    public function testEmptyStructuredDataInputReturnsEmptyArray(): void
    {
        $seoData = SeoDataFactory::fromDetailResponse(['structuredData' => []], 'https://mysite.com/events/e/123');

        $this->assertSame([], $seoData->structuredData);
    }

    public function testStructuredDataHostIsRewrittenToCurrentUrlHost(): void
    {
        $seoData = SeoDataFactory::fromDetailResponse([
            'structuredData' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Events',
                            'item' => 'https://savannahvirtualvisitorcenter.com/events/',
                        ],
                    ],
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'Event',
                    'name' => 'Some Event',
                    'url' => 'https://savannahvirtualvisitorcenter.com/events/e/some-other-slug-123',
                    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                    'eventStatus' => 'https://schema.org/EventScheduled',
                    'image' => 'https://cdn.example.com/event-image.jpg',
                    'organizer' => [
                        'url' => 'https://eventorganizer.example.com/profile',
                    ],
                    'offers' => [
                        'url' => 'https://runsignup.com/Race/NY/NewYork/Richmond',
                        'availability' => 'https://schema.org/InStock',
                    ],
                ],
            ],
        ], 'https://mysite.com/events/e/123');

        // BreadcrumbList item URL should be rewritten to current site
        $this->assertSame('https://mysite.com/events/', $seoData->structuredData[0]['itemListElement'][0]['item']);

        // @context should remain unchanged (vocabulary marker, not a link)
        $this->assertSame('https://schema.org', $seoData->structuredData[1]['@context']);

        // Event url should be rewritten to current site
        $this->assertSame('https://mysite.com/events/e/some-other-slug-123', $seoData->structuredData[1]['url']);

        // Enum values should remain unchanged
        $this->assertSame('https://schema.org/OfflineEventAttendanceMode', $seoData->structuredData[1]['eventAttendanceMode']);
        $this->assertSame('https://schema.org/EventScheduled', $seoData->structuredData[1]['eventStatus']);
        $this->assertSame('https://schema.org/InStock', $seoData->structuredData[1]['offers']['availability']);

        // External CDN image URL should remain unchanged
        $this->assertSame('https://cdn.example.com/event-image.jpg', $seoData->structuredData[1]['image']);

        // External organizer URL should remain unchanged
        $this->assertSame('https://eventorganizer.example.com/profile', $seoData->structuredData[1]['organizer']['url']);

        // External ticketing URL should remain unchanged
        $this->assertSame('https://runsignup.com/Race/NY/NewYork/Richmond', $seoData->structuredData[1]['offers']['url']);
    }

    public function testPlaceSubtypeUrlIsRewrittenCorrectly(): void
    {
        $seoData = SeoDataFactory::fromDetailResponse([
            'structuredData' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Places',
                            'item' => 'https://savannahvirtualvisitorcenter.com/places/',
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Hotels',
                            'item' => 'https://savannahvirtualvisitorcenter.com/places/hotels/',
                        ],
                    ],
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'LodgingBusiness',
                    'name' => 'Downtown Hotel',
                    'url' => 'https://savannahvirtualvisitorcenter.com/places/p/downtown-hotel-456',
                    'image' => 'https://s3.amazonaws.com/hotel-photos/downtown.jpg',
                    'priceRange' => '$$',
                    'address' => [
                        'addressCountry' => 'US',
                        'addressLocality' => 'Savannah',
                    ],
                ],
            ],
        ], 'https://mysite.com/places/p/downtown-hotel-456');

        // BreadcrumbList items should be rewritten to current site
        $this->assertSame('https://mysite.com/places/', $seoData->structuredData[0]['itemListElement'][0]['item']);
        $this->assertSame('https://mysite.com/places/hotels/', $seoData->structuredData[0]['itemListElement'][1]['item']);

        // LodgingBusiness (a Place subtype, not literal 'Place') url should be rewritten to current site
        $this->assertSame('https://mysite.com/places/p/downtown-hotel-456', $seoData->structuredData[1]['url']);

        // @context should remain unchanged
        $this->assertSame('https://schema.org', $seoData->structuredData[1]['@context']);

        // External CDN image URL should remain unchanged
        $this->assertSame('https://s3.amazonaws.com/hotel-photos/downtown.jpg', $seoData->structuredData[1]['image']);

        // Other fields should remain unchanged
        $this->assertSame('Downtown Hotel', $seoData->structuredData[1]['name']);
        $this->assertSame('$$', $seoData->structuredData[1]['priceRange']);
    }
}
