<?php

namespace iti\craftitiplugin\errors;

use Exception;

class ContentNotFoundException extends Exception
{
    public function __construct(public readonly int $statusCode)
    {
        parent::__construct($statusCode === 410 ? 'Event has already happened!' : 'Place not available');
    }
}
