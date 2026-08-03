<?php

namespace iti\craftitiplugin\tests\errors;

use iti\craftitiplugin\errors\ContentNotFoundException;
use PHPUnit\Framework\TestCase;

class ContentNotFoundExceptionTest extends TestCase
{
    public function testEventStatusCodeProducesEventTitleAndBodyMessage(): void
    {
        $exception = new ContentNotFoundException(410);

        $this->assertSame('Event has already happened!', $exception->getMessage());
        $this->assertSame('Uh-oh! Looks like this event has already happened!', $exception->bodyMessage);
    }

    public function testNonEventStatusCodeProducesPlaceTitleAndBodyMessage(): void
    {
        $exception = new ContentNotFoundException(404);

        $this->assertSame('Place not available', $exception->getMessage());
        $this->assertSame('Uh-oh! This place could not be loaded.', $exception->bodyMessage);
    }
}
