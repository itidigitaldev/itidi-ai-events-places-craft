<?php

namespace iti\craftitiplugin\tests\helpers;

use iti\craftitiplugin\helpers\SubmissionFormRouteResolver;
use PHPUnit\Framework\TestCase;

class SubmissionFormRouteResolverTest extends TestCase
{
    public function testResolveEventRoute(): void
    {
        $route = SubmissionFormRouteResolver::resolve('Savannah', 'event');

        $this->assertSame('/Savannah/events/submit-embedding', $route);
    }

    public function testResolvePlaceRoute(): void
    {
        $route = SubmissionFormRouteResolver::resolve('Savannah', 'place');

        $this->assertSame('/Savannah/places/submit-embedding', $route);
    }

    public function testResolveUrlEncodesHubnameWithSpecialCharacters(): void
    {
        $route = SubmissionFormRouteResolver::resolve('My Hub/Test', 'event');

        $this->assertSame('/My%20Hub%2FTest/events/submit-embedding', $route);
    }

    public function testResolveThrowsOnUnrecognizedType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SubmissionFormRouteResolver::resolve('Savannah', 'events');
    }
}
