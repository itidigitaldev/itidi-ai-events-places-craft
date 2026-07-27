<?php

namespace iti\craftitiplugin\web\assets\placeshadowdom;

use craft\web\AssetBundle;

class PlaceShadowDomAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/dist';

    public $js = [
        'place-shadow-dom.js',
    ];
}
