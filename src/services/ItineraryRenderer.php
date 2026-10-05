<?php

namespace iti\craftitiplugin\services;

use Craft;
use craft\helpers\StringHelper;
use craft\web\View;
use iti\craftitiplugin\errors\ContentApiException;
use iti\craftitiplugin\helpers\SsrHtmlSanitizer;
use iti\craftitiplugin\Plugin;
use iti\craftitiplugin\web\assets\listinginit\ListingInitAsset;

/**
 * Itinerary html is rendered plainly (no shadow DOM), the same way the WordPress
 * plugin does it. Current responses carry a self-contained bootstrap.hydrationScript;
 * listings without one fall back to the shared listing initializer.
 */
final class ItineraryRenderer
{
    private const DEFAULT_WIDGET_CODE = 'default';
    private const DISABLED_MESSAGE = 'Itineraries are disabled.';

    public static function resolveWidgetCode(?string $requested, string $configuredDefault): string
    {
        $requested = trim((string) $requested);

        if ($requested !== '') {
            return $requested;
        }

        $configuredDefault = trim($configuredDefault);

        return $configuredDefault !== '' ? $configuredDefault : self::DEFAULT_WIDGET_CODE;
    }

    public static function forceHttps(string $url): string
    {
        return (string) preg_replace('#^http://#i', 'https://', $url);
    }

    public function renderListing(array $options): string
    {
        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->enableItineraries) {
            return self::DISABLED_MESSAGE;
        }

        $widgetCode = self::resolveWidgetCode($options['widgetCode'] ?? null, $settings->itineraryDefaultWidgetCode);

        try {
            $response = Plugin::getInstance()->itinerariesApi->fetchItineraryListing($settings->hubname, $settings->apiKey, $widgetCode);
        } catch (ContentApiException $e) {
            Craft::warning('Itinerary listing failed: ' . $e->getMessage(), __METHOD__);
            return '';
        }

        return $this->render($response, 'listing');
    }

    public function renderDetail(?string $id): string
    {
        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->enableItineraries) {
            return self::DISABLED_MESSAGE;
        }

        $id = trim((string) $id);

        if ($id === '') {
            return '';
        }

        try {
            $response = Plugin::getInstance()->itinerariesApi->fetchItineraryDetail($settings->hubname, $settings->apiKey, $id);
        } catch (ContentApiException $e) {
            Craft::warning('Itinerary detail failed: ' . $e->getMessage(), __METHOD__);
            return '';
        }

        return $this->render($response, 'detail');
    }

    private function render(array $response, string $context): string
    {
        Plugin::getInstance()->tripPlanner->markModuleContentRendered();

        $view = Craft::$app->getView();

        foreach ($response['assets']['css'] ?? [] as $href) {
            if (is_string($href) && $href !== '') {
                $view->registerCssFile(self::forceHttps($href));
            }
        }

        // Registered in API order so runtime.js lands before the widget scripts that need it.
        foreach ($response['assets']['js'] ?? [] as $src) {
            if (is_string($src) && $src !== '') {
                $view->registerJsFile(self::forceHttps($src), ['position' => View::POS_END]);
            }
        }

        $bootstrap = is_array($response['bootstrap'] ?? null) ? $response['bootstrap'] : [];
        $hydrationScript = $bootstrap['hydrationScript'] ?? null;
        $initMethod = null;

        if (is_string($hydrationScript) && $hydrationScript !== '') {
            // POS_END inline js renders after POS_END js files, so this runs once the API scripts have loaded.
            $view->registerJs($hydrationScript, View::POS_END);
        } elseif ($context === 'listing') {
            $view->registerAssetBundle(ListingInitAsset::class);
            $initMethod = 'initItineraryListing';
        }

        return $view->renderTemplate('craft-iti-plugin/_partials/listing', [
            'containerId' => 'craft-iti-itinerary-' . $context . '-' . StringHelper::UUID(),
            'sanitizedHtml' => SsrHtmlSanitizer::sanitize((string) $response['html']),
            'initMethod' => $initMethod,
            'bootstrap' => $bootstrap,
            // Detail can be embedded on any page, so it never claims the page's structured data.
            'structuredData' => $context === 'listing' ? ($response['seo']['structuredData'] ?? []) : [],
        ]);
    }
}
