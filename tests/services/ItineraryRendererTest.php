<?php

namespace iti\craftitiplugin\tests\services;

use iti\craftitiplugin\services\ItineraryRenderer;
use PHPUnit\Framework\TestCase;

class ItineraryRendererTest extends TestCase
{
    public function testRequestedWidgetCodeWins(): void
    {
        $this->assertSame('summer', ItineraryRenderer::resolveWidgetCode(' summer ', 'configured'));
    }

    public function testConfiguredDefaultUsedWhenNothingRequested(): void
    {
        $this->assertSame('configured', ItineraryRenderer::resolveWidgetCode(null, 'configured'));
        $this->assertSame('configured', ItineraryRenderer::resolveWidgetCode('   ', 'configured'));
    }

    public function testFallsBackToLiteralDefault(): void
    {
        $this->assertSame('default', ItineraryRenderer::resolveWidgetCode(null, ''));
        $this->assertSame('default', ItineraryRenderer::resolveWidgetCode('', '  '));
    }

    public function testForceHttpsRewritesHttpScheme(): void
    {
        $this->assertSame('https://cdn.example.com/a.js', ItineraryRenderer::forceHttps('http://cdn.example.com/a.js'));
        $this->assertSame('https://cdn.example.com/a.js', ItineraryRenderer::forceHttps('HTTP://cdn.example.com/a.js'));
    }

    public function testForceHttpsLeavesOtherUrlsAlone(): void
    {
        $this->assertSame('https://cdn.example.com/a.css', ItineraryRenderer::forceHttps('https://cdn.example.com/a.css'));
        $this->assertSame('//cdn.example.com/a.css', ItineraryRenderer::forceHttps('//cdn.example.com/a.css'));
    }
}
