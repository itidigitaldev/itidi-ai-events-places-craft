<?php

namespace iti\craftitiplugin\tests\tripplanner;

use iti\craftitiplugin\tripplanner\TripPlannerRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TripPlannerRulesTest extends TestCase
{
    #[DataProvider('normalizeCases')]
    public function testNormalizePath(string $input, string $expected): void
    {
        $this->assertSame($expected, TripPlannerRules::normalizePath($input));
    }

    public static function normalizeCases(): array
    {
        return [
            'full url with trailing slash' => ['https://site.com/events/', '/events'],
            'full url with query' => ['https://site.com/events?x=1#top', '/events'],
            'wildcard kept' => ['/blog/*', '/blog/*'],
            'missing leading slash' => ['blog/', '/blog'],
            'root' => ['/', '/'],
            'bare wildcard' => ['*', '/*'],
            'full url root' => ['https://site.com', '/'],
            'whitespace' => ['  /contact  ', '/contact'],
            'empty' => ['   ', ''],
            'relative query dropped' => ['/contact?ref=a', '/contact'],
        ];
    }

    public function testNormalizePathListFromString(): void
    {
        $this->assertSame(['/contact', '/blog/*'], TripPlannerRules::normalizePathList("/contact\r\n\n  /blog/*  \n/contact/"));
    }

    public function testNormalizePathListFromArray(): void
    {
        $this->assertSame(['/a', '/b'], TripPlannerRules::normalizePathList(['/a', '', 'https://x.com/b/', null, '/a']));
    }

    public function testNormalizePathListRejectsOtherTypes(): void
    {
        $this->assertSame([], TripPlannerRules::normalizePathList(null));
        $this->assertSame([], TripPlannerRules::normalizePathList(42));
    }

    public function testExactMatchIgnoresTrailingSlashAndQuery(): void
    {
        $this->assertTrue(TripPlannerRules::matches('/contact/', '/contact'));
        $this->assertTrue(TripPlannerRules::matches('/contact?x=1', '/contact'));
        $this->assertFalse(TripPlannerRules::matches('/contact-us', '/contact'));
    }

    public function testWildcardMatchesBaseAndChildren(): void
    {
        $this->assertTrue(TripPlannerRules::matches('/blog', '/blog/*'));
        $this->assertTrue(TripPlannerRules::matches('/blog/post-1', '/blog/*'));
        $this->assertFalse(TripPlannerRules::matches('/blogger', '/blog/*'));
    }

    public function testRootWildcardMatchesEverything(): void
    {
        $this->assertTrue(TripPlannerRules::matches('/', '/*'));
        $this->assertTrue(TripPlannerRules::matches('/any/thing', '/*'));
    }

    public function testRootRuleMatchesOnlyHomepage(): void
    {
        $this->assertTrue(TripPlannerRules::matches('/', '/'));
        $this->assertTrue(TripPlannerRules::matches('', '/'));
        $this->assertFalse(TripPlannerRules::matches('/about', '/'));
    }

    public function testShouldLoadWithNoPaths(): void
    {
        $this->assertTrue(TripPlannerRules::shouldLoad('/x', 'exclude', []));
        $this->assertFalse(TripPlannerRules::shouldLoad('/x', 'include', []));
    }

    public function testShouldLoadIncludeMode(): void
    {
        $this->assertTrue(TripPlannerRules::shouldLoad('/blog/a', 'include', ['/contact', '/blog/*']));
        $this->assertFalse(TripPlannerRules::shouldLoad('/about', 'include', ['/contact', '/blog/*']));
    }

    public function testShouldLoadExcludeMode(): void
    {
        $this->assertFalse(TripPlannerRules::shouldLoad('/contact/', 'exclude', ['/contact']));
        $this->assertTrue(TripPlannerRules::shouldLoad('/about', 'exclude', ['/contact']));
    }

    public function testUnknownModeBehavesLikeExclude(): void
    {
        $this->assertFalse(TripPlannerRules::shouldLoad('/contact', 'bogus', ['/contact']));
        $this->assertTrue(TripPlannerRules::shouldLoad('/about', 'bogus', []));
    }
}
