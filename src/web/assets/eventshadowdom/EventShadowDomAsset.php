<?php

namespace iti\craftitiplugin\web\assets\eventshadowdom;

use craft\web\AssetBundle;

class EventShadowDomAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';

    public $js = [
        'event-shadow-dom.js',
    ];
}
