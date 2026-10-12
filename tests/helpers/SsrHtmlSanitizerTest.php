<?php

namespace iti\craftitiplugin\tests\helpers;

use iti\craftitiplugin\helpers\SsrHtmlSanitizer;
use PHPUnit\Framework\TestCase;
use Yii;

class SsrHtmlSanitizerTest extends TestCase
{
    private mixed $originalApp = null;

    protected function setUp(): void
    {
        $this->originalApp = Yii::$app;

        // craft\helpers\HtmlPurifier only needs a runtime path for its cache.
        Yii::$app = new class {
            public function getRuntimePath(): string
            {
                return sys_get_temp_dir();
            }
        };
    }

    protected function tearDown(): void
    {
        Yii::$app = $this->originalApp;
    }

    public function testHiddenAttributeSurvivesSoOverlaysStayClosedWithoutJavaScript(): void
    {
        $html = SsrHtmlSanitizer::sanitize('<div class="ig-place-detail-review-overlay" data-ig-review-overlay hidden><p>Reviews</p></div>');

        $this->assertStringContainsString('hidden', $html);
        $this->assertMatchesRegularExpression('/<div[^>]*\bhidden\b/', $html);
    }

    public function testButtonsKeepTheirTypeAndLabel(): void
    {
        $html = SsrHtmlSanitizer::sanitize('<button type="button" class="close" aria-label="Close reviews" data-ig-review-close="true">&times;</button>');

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('aria-label="Close reviews"', $html);
        $this->assertStringContainsString('data-ig-review-close="true"', $html);
    }

    public function testSvgIconsKeepTheirShapes(): void
    {
        $html = SsrHtmlSanitizer::sanitize('<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M1 1h2" fill="none" stroke="currentColor"></path><circle cx="1" cy="1" r="2"></circle></svg>');

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('d="M1 1h2"', $html);
        $this->assertStringContainsString('<circle', $html);
    }

    public function testRoleAriaAndTabindexAreKept(): void
    {
        $html = SsrHtmlSanitizer::sanitize('<div role="dialog" aria-modal="true" aria-labelledby="t" tabindex="-1">x</div>');

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="t"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
    }

    public function testScriptsAndEventHandlersAreStillStripped(): void
    {
        $html = SsrHtmlSanitizer::sanitize('<div onclick="alert(1)">a</div><script>alert(1)</script><button type="button" onclick="alert(2)">b</button><svg onload="alert(3)"><script>alert(4)</script></svg>');

        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('onload', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert', $html);
    }
}
