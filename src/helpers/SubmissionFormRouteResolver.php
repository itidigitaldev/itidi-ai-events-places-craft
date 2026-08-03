<?php

namespace iti\craftitiplugin\helpers;

final class SubmissionFormRouteResolver
{
    public static function resolve(string $hubname, string $type): string
    {
        $segment = $type === 'event' ? 'events' : 'places';

        return '/' . rawurlencode($hubname) . '/' . $segment . '/submit-embedding';
    }
}
