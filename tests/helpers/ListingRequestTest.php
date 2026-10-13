<?php

namespace iti\craftitiplugin\tests\helpers;

use iti\craftitiplugin\helpers\ListingRequest;
use PHPUnit\Framework\TestCase;

class ListingRequestTest extends TestCase
{
    public function testBaseUrlsIgnoreTheQueryString(): void
    {
        [$listing, $detail] = ListingRequest::baseUrls('https://example.com/events?page_no=2&x=1', 'e');

        $this->assertSame('https://example.com/events', $listing);
        $this->assertSame('https://example.com/events/e/', $detail);
    }

    public function testBaseUrlsKeepATrailingSlashOnTheListingWithoutDoublingIt(): void
    {
        [$listing, $detail] = ListingRequest::baseUrls('https://example.com/places/', 'p');

        $this->assertSame('https://example.com/places/', $listing);
        $this->assertSame('https://example.com/places/p/', $detail);
    }

    public function testBaseUrlsDropAFragment(): void
    {
        [$listing] = ListingRequest::baseUrls('https://example.com/events#top', 'e');

        $this->assertSame('https://example.com/events', $listing);
    }

    public function testPageNumberDefaultsToOne(): void
    {
        $this->assertSame(1, ListingRequest::pageNumber(null));
        $this->assertSame(1, ListingRequest::pageNumber(''));
    }

    public function testPageNumberRejectsNonsense(): void
    {
        $this->assertSame(1, ListingRequest::pageNumber('abc'));
        $this->assertSame(1, ListingRequest::pageNumber('0'));
        $this->assertSame(1, ListingRequest::pageNumber('-3'));
        $this->assertSame(1, ListingRequest::pageNumber(['2']));
    }

    public function testPageNumberAcceptsPositiveIntegers(): void
    {
        $this->assertSame(2, ListingRequest::pageNumber('2'));
        $this->assertSame(12, ListingRequest::pageNumber(12));
    }
}
