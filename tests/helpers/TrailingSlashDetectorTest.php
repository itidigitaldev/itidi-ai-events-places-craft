<?php

namespace iti\craftitiplugin\tests\helpers;

use iti\craftitiplugin\helpers\TrailingSlashDetector;
use PHPUnit\Framework\TestCase;

class TrailingSlashDetectorTest extends TestCase
{
    public function testRootPathFollowsSettingWhenOn(): void
    {
        $this->assertTrue(TrailingSlashDetector::shouldAddTrailingSlash('/', true));
    }

    public function testRootPathFollowsSettingWhenOff(): void
    {
        $this->assertFalse(TrailingSlashDetector::shouldAddTrailingSlash('/', false));
    }

    public function testEmptyPathIsTreatedAsRootAndFollowsSettingWhenOn(): void
    {
        $this->assertTrue(TrailingSlashDetector::shouldAddTrailingSlash('', true));
    }

    public function testEmptyPathIsTreatedAsRootAndFollowsSettingWhenOff(): void
    {
        $this->assertFalse(TrailingSlashDetector::shouldAddTrailingSlash('', false));
    }

    public function testPathWithTrailingSlashIsTrueRegardlessOfSetting(): void
    {
        $this->assertTrue(TrailingSlashDetector::shouldAddTrailingSlash('/events/', false));
        $this->assertTrue(TrailingSlashDetector::shouldAddTrailingSlash('/events/', true));
    }

    public function testPathWithoutTrailingSlashIsFalseRegardlessOfSetting(): void
    {
        $this->assertFalse(TrailingSlashDetector::shouldAddTrailingSlash('/events', false));
        $this->assertFalse(TrailingSlashDetector::shouldAddTrailingSlash('/events', true));
    }

    public function testDeepPathWithTrailingSlashIsTrue(): void
    {
        $this->assertTrue(TrailingSlashDetector::shouldAddTrailingSlash('/events/e/foo-123/', false));
    }

    public function testPathWithoutLeadingSlashIsNormalized(): void
    {
        $this->assertFalse(TrailingSlashDetector::shouldAddTrailingSlash('events', true));
        $this->assertTrue(TrailingSlashDetector::shouldAddTrailingSlash('events/', true));
    }
}
