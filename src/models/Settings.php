<?php

namespace iti\craftitiplugin\models;

use craft\base\Model;

class Settings extends Model
{
    public string $hubname = '';
    public string $apiKey = '';
    public bool $isConnected = false;
    public array $moduleStatus = ['events' => false, 'places' => false];
    public bool $enableEvents = false;
    public bool $enablePlaces = false;

    protected function defineRules(): array
    {
        return [
            [['hubname', 'apiKey'], 'trim'],
        ];
    }
}
