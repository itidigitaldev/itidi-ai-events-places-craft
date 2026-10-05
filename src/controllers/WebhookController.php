<?php

namespace iti\craftitiplugin\controllers;

use Craft;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use iti\craftitiplugin\Plugin;
use iti\craftitiplugin\services\CachePurger;
use yii\base\NotSupportedException;
use yii\web\Response;

class WebhookController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;
    public $enableCsrfValidation = false;

    public function actionPurgeCache(): Response
    {
        $this->requirePostRequest();

        $settings = Plugin::getInstance()->getSettings();

        $providedSecret = Craft::$app->getRequest()->getHeaders()->get('X-DXP-Signature-256') ?? '';

        if ($settings->webhookSecret === '' || !hash_equals($settings->webhookSecret, (string) $providedSecret)) {
            Craft::warning('Webhook request rejected: invalid or missing signature.', __METHOD__);
            Craft::$app->getResponse()->setStatusCode(401);
            return $this->asJson(['success' => false, 'message' => 'Invalid webhook signature.']);
        }

        $payload = json_decode((string) Craft::$app->getRequest()->getRawBody(), true);

        if (!is_array($payload)) {
            Craft::$app->getResponse()->setStatusCode(400);
            return $this->asJson(['success' => false, 'message' => 'Invalid webhook payload.']);
        }

        $event = is_string($payload['event'] ?? null) ? $payload['event'] : '';

        if ($event === 'ssr_modules.status_update') {
            return $this->handleModuleStatusUpdate($payload);
        }

        return $this->handleCacheInvalidate($event, $payload);
    }

    private function handleCacheInvalidate(string $event, array $payload): Response
    {
        $scope = is_string($payload['scope'] ?? null) ? $payload['scope'] : '';
        $type = is_string($payload['type'] ?? null) ? $payload['type'] : '';
        $itemId = is_string($payload['id'] ?? null) ? $payload['id'] : '';
        $nameId = is_string($payload['nameId'] ?? null) ? $payload['nameId'] : '';
        $url = is_string($payload['url'] ?? null) ? $payload['url'] : '';
        $deliveryId = is_string($payload['deliveryId'] ?? null) ? $payload['deliveryId'] : '';

        if (
            $event !== 'ssr_cache.invalidate'
            || !in_array($scope, ['detail', 'listing'], true)
            || !in_array($type, ['event', 'place', 'itinerary'], true)
        ) {
            Craft::$app->getResponse()->setStatusCode(400);
            return $this->asJson(['success' => false, 'message' => 'Invalid webhook payload.']);
        }

        $settings = Plugin::getInstance()->getSettings();
        $siteBaseUrl = rtrim(UrlHelper::siteUrl(), '/');

        if ($type === 'itinerary') {
            $entryIds = $scope === 'listing' ? $settings->itineraryListingEntryIds : $settings->itineraryDetailEntryIds;
            $entryUrls = $entryIds
                ? array_map(fn(Entry $entry) => $entry->getUrl(), Entry::find()->id($entryIds)->status(null)->all())
                : [];
            $urls = CachePurger::resolveItineraryUrls($entryUrls, $scope === 'detail' ? $url : '', $siteBaseUrl);
        } else {
            $listingUrl = $type === 'event' ? $settings->eventsListingUrl : $settings->placesListingUrl;

            if ($scope === 'detail') {
                $slug = $nameId !== '' ? $nameId : $this->lastUrlSegment($url);
                $urls = $slug !== '' ? CachePurger::resolveDetailUrls($type, $slug, $siteBaseUrl, $listingUrl) : [];

                if ($url !== '' && CachePurger::isSameOrigin($url, $siteBaseUrl)) {
                    $urls[] = $url;
                }

                $urls = array_values(array_unique($urls));
            } else {
                $urls = CachePurger::resolveListingUrls($siteBaseUrl, $listingUrl);
            }
        }

        CachePurger::purgeUrls($urls);
        Craft::info(sprintf('Webhook purged %d URL(s) for %s scope=%s type=%s.', count($urls), $event, $scope, $type), __METHOD__);

        return $this->asJson([
            'success' => true,
            'message' => 'Cache purge request processed.',
            'event' => $event,
            'type' => $type,
            'itemId' => $itemId,
            'nameId' => $nameId,
            'deliveryId' => $deliveryId,
            'url' => $url,
        ]);
    }

    private function handleModuleStatusUpdate(array $payload): Response
    {
        $modules = is_array($payload['modules'] ?? null) ? $payload['modules'] : null;

        if ($modules === null) {
            Craft::$app->getResponse()->setStatusCode(400);
            return $this->asJson(['success' => false, 'message' => 'Missing or invalid modules payload.']);
        }

        $settings = Plugin::getInstance()->getSettings();

        if (array_key_exists('events', $modules)) {
            $eventsAvailable = (bool) $modules['events'];
            $settings->moduleStatus['events'] = $eventsAvailable;
            $settings->enableEvents = $eventsAvailable;
            $settings->enableEventSubmissionForm = $settings->enableEventSubmissionForm && $eventsAvailable;
        }

        if (array_key_exists('places', $modules)) {
            $placesAvailable = (bool) $modules['places'];
            $settings->moduleStatus['places'] = $placesAvailable;
            $settings->enablePlaces = $placesAvailable;
            $settings->enablePlaceSubmissionForm = $settings->enablePlaceSubmissionForm && $placesAvailable;
        }

        if (array_key_exists('itineraries', $modules)) {
            $itinerariesAvailable = (bool) $modules['itineraries'];
            $settings->moduleStatus['itineraries'] = $itinerariesAvailable;
            $settings->enableItineraries = $itinerariesAvailable;
        }

        try {
            $saved = Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), $settings->toArray());
        } catch (NotSupportedException) {
            Craft::warning('Webhook module-status update failed: project config is read-only in this environment.', __METHOD__);
            Craft::$app->getResponse()->setStatusCode(500);
            return $this->asJson(['success' => false, 'message' => 'Unable to persist module status: project config is read-only in this environment.']);
        }

        if (!$saved) {
            Craft::warning('Webhook module-status update failed: settings failed validation.', __METHOD__);
            Craft::$app->getResponse()->setStatusCode(500);
            return $this->asJson(['success' => false, 'message' => 'Unable to persist module status: settings failed validation.']);
        }

        return $this->asJson([
            'success' => true,
            'message' => 'Module status updated.',
            'modules' => $settings->moduleStatus,
        ]);
    }

    private function lastUrlSegment(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($path === '') {
            return '';
        }

        $parts = explode('/', $path);

        return (string) end($parts);
    }
}
