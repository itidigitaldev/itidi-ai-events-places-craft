<?php

namespace iti\craftitiplugin\helpers;

final class SubmissionFormRouteResolver
{
    public static function resolve(string $hubname, string $type): string
    {
        $segment = match ($type) {
            'event' => 'events',
            'place' => 'places',
            default => throw new \InvalidArgumentException("Unrecognized submission form type: {$type}"),
        };

        return '/' . rawurlencode($hubname) . '/' . $segment . '/submit-embedding';
    }
}
