<?php

namespace iti\craftitiplugin\errors;

use Exception;

class ContentNotFoundException extends Exception
{
    public readonly string $bodyMessage;

    public function __construct(public readonly int $statusCode)
    {
        parent::__construct($statusCode === 410 ? 'Event has already happened!' : 'Place not available');
        $this->bodyMessage = $statusCode === 410
            ? 'Uh-oh! Looks like this event has already happened!'
            : 'Uh-oh! This place could not be loaded.';
    }
}
