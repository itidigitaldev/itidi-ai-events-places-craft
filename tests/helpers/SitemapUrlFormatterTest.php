<?php

namespace iti\craftitiplugin\tests\helpers;

use iti\craftitiplugin\helpers\SitemapUrlFormatter;
use PHPUnit\Framework\TestCase;

class SitemapUrlFormatterTest extends TestCase
{
    public function testAutoModeAddsSlashWhenCraftSettingIsOnAndUrlHasNone(): void
    {
        $url = SitemapUrlFormatter::applyTrailingSlash('https://example.com/events/e/foo-123', 'auto', true);

        $this->assertSame('https://example.com/events/e/foo-123/', $url);
    }

    public function testAutoModeStripsSlashWhenCraftSettingIsOffAndUrlHasOne(): void
    {
        $url = SitemapUrlFormatter::applyTrailingSlash('https://example.com/events/e/foo-123/', 'auto', false);

        $this->assertSame('https://example.com/events/e/foo-123', $url);
    }

    public function testAutoModeLeavesUrlUnchangedWhenAlreadyMatchingSettingOn(): void
    {
        $url = SitemapUrlFormatter::applyTrailingSlash('https://example.com/events/e/foo-123/', 'auto', true);

        $this->assertSame('https://example.com/events/e/foo-123/', $url);
    }

    public function testAutoModeLeavesUrlUnchangedWhenAlreadyMatchingSettingOff(): void
    {
        $url = SitemapUrlFormatter::applyTrailingSlash('https://example.com/events/e/foo-123', 'auto', false);

        $this->assertSame('https://example.com/events/e/foo-123', $url);
    }

    public function testAlwaysModeAddsSlashRegardlessOfCraftSetting(): void
    {
        $url = SitemapUrlFormatter::applyTrailingSlash('https://example.com/events/e/foo-123', 'always', false);

        $this->assertSame('https://example.com/events/e/foo-123/', $url);
    }

    public function testAlwaysModeDoesNotDoubleUpAnExistingSlash(): void
    {
        $url = SitemapUrlFormatter::applyTrailingSlash('https://example.com/events/e/foo-123/', 'always', false);

        $this->assertSame('https://example.com/events/e/foo-123/', $url);
    }

    public function testNeverModeStripsSlashRegardlessOfCraftSetting(): void
    {
        $url = SitemapUrlFormatter::applyTrailingSlash('https://example.com/events/e/foo-123/', 'never', true);

        $this->assertSame('https://example.com/events/e/foo-123', $url);
    }

    public function testNeverModeLeavesUrlWithoutSlashUnchanged(): void
    {
        $url = SitemapUrlFormatter::applyTrailingSlash('https://example.com/events/e/foo-123', 'never', true);

        $this->assertSame('https://example.com/events/e/foo-123', $url);
    }

    public function testAlwaysModePreservesQueryString(): void
    {
        $url = SitemapUrlFormatter::applyTrailingSlash('https://example.com/events/e/foo-123?utm=1', 'always', false);

        $this->assertSame('https://example.com/events/e/foo-123/?utm=1', $url);
    }

    public function testNeverModePreservesQueryString(): void
    {
        $url = SitemapUrlFormatter::applyTrailingSlash('https://example.com/events/e/foo-123/?utm=1', 'never', true);

        $this->assertSame('https://example.com/events/e/foo-123?utm=1', $url);
    }

    public function testAlwaysModePreservesFragment(): void
    {
        $url = SitemapUrlFormatter::applyTrailingSlash('https://example.com/events/e/foo-123#section', 'always', false);

        $this->assertSame('https://example.com/events/e/foo-123/#section', $url);
    }

    public function testNeverModeOnBareOriginRootNeverTurnsPathEmpty(): void
    {
        $url = SitemapUrlFormatter::applyTrailingSlash('https://example.com/', 'never', true);

        $this->assertSame('https://example.com/', $url);
    }

    public function testAlwaysModeOnBareOriginRootStaysRoot(): void
    {
        $url = SitemapUrlFormatter::applyTrailingSlash('https://example.com/', 'always', false);

        $this->assertSame('https://example.com/', $url);
    }

    public function testRewriteLocTagsAppliesFormatToEveryLoc(): void
    {
        $xml = '<urlset><url><loc>https://example.com/events/e/foo-123</loc></url>'
            . '<url><loc>https://example.com/places/p/bar-456</loc></url></urlset>';

        $result = SitemapUrlFormatter::rewriteLocTags($xml, 'always', false);

        $this->assertSame(
            '<urlset><url><loc>https://example.com/events/e/foo-123/</loc></url>'
            . '<url><loc>https://example.com/places/p/bar-456/</loc></url></urlset>',
            $result,
        );
    }

    public function testRewriteLocTagsWithNeverModeStripsEveryLoc(): void
    {
        $xml = '<urlset><url><loc>https://example.com/events/e/foo-123/</loc></url></urlset>';

        $result = SitemapUrlFormatter::rewriteLocTags($xml, 'never', true);

        $this->assertSame('<urlset><url><loc>https://example.com/events/e/foo-123</loc></url></urlset>', $result);
    }

    public function testRewriteLocTagsLeavesNonLocContentUntouched(): void
    {
        $xml = '<urlset><other>https://example.com/no-touch</other></urlset>';

        $result = SitemapUrlFormatter::rewriteLocTags($xml, 'always', false);

        $this->assertSame($xml, $result);
    }
}
