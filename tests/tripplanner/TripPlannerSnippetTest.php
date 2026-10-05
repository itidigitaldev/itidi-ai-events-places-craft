<?php

namespace iti\craftitiplugin\tests\tripplanner;

use iti\craftitiplugin\tripplanner\TripPlannerSnippet;
use PHPUnit\Framework\TestCase;

class TripPlannerSnippetTest extends TestCase
{
    public function testHtmlMarkup(): void
    {
        $this->assertSame(
            '<div id="imgoingcalendar-wrapper" route="/demo-hub/ai/travel-buddy" url-change="true" version="V3"><app-root></app-root><script type="text/javascript" src="https://imgoingcalendar.com/imgoingscript.js"></script></div>',
            TripPlannerSnippet::html('demo-hub')
        );
    }

    public function testHubnameIsTrimmedOfSlashesAndWhitespace(): void
    {
        $this->assertStringContainsString('route="/demo-hub/ai/travel-buddy"', TripPlannerSnippet::html(' /demo-hub/ '));
    }

    public function testHubnameIsEscaped(): void
    {
        $html = TripPlannerSnippet::html('bad"<hub');

        $this->assertStringContainsString('route="/bad&quot;&lt;hub/ai/travel-buddy"', $html);
    }

    public function testInjectFooterBeforeLastClosingBody(): void
    {
        $page = '<html><body><p>x</p><!-- </BODY> in a comment --></BODY></html>';

        $this->assertSame(
            '<html><body><p>x</p><!-- </BODY> in a comment -->SNIP</BODY></html>',
            TripPlannerSnippet::inject($page, 'SNIP', 'footer')
        );
    }

    public function testInjectBodyOpenAfterBodyTagWithAttributes(): void
    {
        $page = '<html><head></head><body class="home" data-x="1"><p>x</p></body></html>';

        $this->assertSame(
            '<html><head></head><body class="home" data-x="1">SNIP<p>x</p></body></html>',
            TripPlannerSnippet::inject($page, 'SNIP', 'bodyOpen')
        );
    }

    public function testInjectBodyOpenDoesNotMatchTagsStartingWithBody(): void
    {
        $page = '<bodyguard></bodyguard><BODY><p>x</p></BODY>';

        $this->assertSame('<bodyguard></bodyguard><BODY>SNIP<p>x</p></BODY>', TripPlannerSnippet::inject($page, 'SNIP', 'bodyOpen'));
    }

    public function testInjectLeavesPageAloneWithoutTag(): void
    {
        $this->assertSame('{"json":true}', TripPlannerSnippet::inject('{"json":true}', 'SNIP', 'footer'));
        $this->assertSame('<p>fragment</p>', TripPlannerSnippet::inject('<p>fragment</p>', 'SNIP', 'bodyOpen'));
    }

    public function testUnknownPositionFallsBackToFooter(): void
    {
        $this->assertSame('<body>xSNIP</body>', TripPlannerSnippet::inject('<body>x</body>', 'SNIP', 'bogus'));
    }

    public function testPageHasWidgetDetectsLowercaseId(): void
    {
        $this->assertTrue(TripPlannerSnippet::pageHasWidget('<div id="imgoingcalendar-wrapper"></div>'));
    }

    public function testPageHasWidgetIsCaseInsensitive(): void
    {
        $this->assertTrue(TripPlannerSnippet::pageHasWidget('<div ID="IMGOINGCALENDAR-WRAPPER"></div>'));
    }

    public function testPageHasWidgetReturnsFalseWhenAbsent(): void
    {
        $this->assertFalse(TripPlannerSnippet::pageHasWidget('<html><body><p>hi</p></body></html>'));
    }

    public function testPageHasWidgetDetectsItsOwnOutput(): void
    {
        $this->assertTrue(TripPlannerSnippet::pageHasWidget(TripPlannerSnippet::html('demo-hub')));
    }
}
