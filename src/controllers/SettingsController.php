<?php

namespace iti\craftitiplugin\controllers;

use Craft;
use craft\web\Controller;
use iti\craftitiplugin\errors\ModuleStatusException;
use iti\craftitiplugin\Plugin;
use yii\web\Response;

class SettingsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireAdmin();

        return true;
    }

    public function actionConnect(): ?Response
    {
        $this->requirePostRequest();

        $settings = Plugin::getInstance()->getSettings();
        $settings->hubname = trim((string) $this->request->getBodyParam('hubname'));
        $settings->apiKey = trim((string) $this->request->getBodyParam('apiKey'));

        if ($settings->hubname === '') {
            $settings->addError('hubname', 'Hubname is required.');
            return $this->asModelFailure($settings, 'Hubname is required.', 'settings');
        }

        if ($settings->apiKey === '') {
            $settings->addError('apiKey', 'API key is required.');
            return $this->asModelFailure($settings, 'API key is required.', 'settings');
        }

        try {
            $modules = Plugin::getInstance()->moduleStatusApi->fetchModuleStatus($settings->hubname, $settings->apiKey);
        } catch (ModuleStatusException $e) {
            $settings->addError('hubname', $e->getMessage());
            return $this->asModelFailure($settings, $e->getMessage(), 'settings');
        }

        $saved = Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), [
            'hubname' => $settings->hubname,
            'apiKey' => $settings->apiKey,
            'isConnected' => true,
            'moduleStatus' => $modules,
            'enableEvents' => false,
            'enablePlaces' => false,
            'enableEventSubmissionForm' => false,
            'enablePlaceSubmissionForm' => false,
            'singleEventRoute' => $settings->singleEventRoute,
            'singlePlaceRoute' => $settings->singlePlaceRoute,
            'eventListingLimit' => $settings->eventListingLimit,
            'placeListingLimit' => $settings->placeListingLimit,
            'enableLegacyRedirects' => $settings->enableLegacyRedirects,
            'eventsListingUrl' => $settings->eventsListingUrl,
            'placesListingUrl' => $settings->placesListingUrl,
            'legacyEventIdParam' => $settings->legacyEventIdParam,
            'legacyPlaceIdParam' => $settings->legacyPlaceIdParam,
            'canonicalUrlSource' => $settings->canonicalUrlSource,
            'sitemapTrailingSlash' => $settings->sitemapTrailingSlash,
            'webhookSecret' => $settings->webhookSecret,
            'enableItineraries' => false,
            'itineraryListingEntryIds' => $settings->itineraryListingEntryIds,
            'itineraryDetailEntryIds' => $settings->itineraryDetailEntryIds,
            'itineraryDefaultWidgetCode' => $settings->itineraryDefaultWidgetCode,
            'enableLlmsTxt' => $settings->enableLlmsTxt,
            'llmsDestinationName' => $settings->llmsDestinationName,
            'llmsOrganizationName' => $settings->llmsOrganizationName,
            'llmsEventsEntryIds' => $settings->llmsEventsEntryIds,
            'llmsPlacesEntryIds' => $settings->llmsPlacesEntryIds,
            'llmsAppendToExisting' => $settings->llmsAppendToExisting,
        ]);

        if (!$saved) {
            $message = "Couldn't save the connection.";
            $settings->addError('hubname', $message);
            return $this->asModelFailure($settings, $message, 'settings');
        }

        return $this->asSuccess('Connected to ITI Digital.', redirect: 'settings/plugins/craft-iti-plugin');
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->isConnected) {
            $this->setFailFlash('Connect to a hub before enabling modules.');
            return $this->redirect('settings/plugins/craft-iti-plugin');
        }

        $settings->enableEvents = (bool) $this->request->getBodyParam('enableEvents', false) && !empty($settings->moduleStatus['events']);
        $settings->enablePlaces = (bool) $this->request->getBodyParam('enablePlaces', false) && !empty($settings->moduleStatus['places']);
        $settings->enableEventSubmissionForm = (bool) $this->request->getBodyParam('enableEventSubmissionForm', false) && !empty($settings->moduleStatus['events']);
        $settings->enablePlaceSubmissionForm = (bool) $this->request->getBodyParam('enablePlaceSubmissionForm', false) && !empty($settings->moduleStatus['places']);
        $settings->enableLegacyRedirects = (bool) $this->request->getBodyParam('enableLegacyRedirects', false);
        $settings->eventsListingUrl = trim((string) $this->request->getBodyParam('eventsListingUrl', ''));
        $settings->placesListingUrl = trim((string) $this->request->getBodyParam('placesListingUrl', ''));
        $settings->legacyEventIdParam = trim((string) $this->request->getBodyParam('legacyEventIdParam', 'event_id'));
        $settings->legacyPlaceIdParam = trim((string) $this->request->getBodyParam('legacyPlaceIdParam', 'place_id'));
        $settings->canonicalUrlSource = trim((string) $this->request->getBodyParam('canonicalUrlSource', 'ssr'));
        $settings->sitemapTrailingSlash = trim((string) $this->request->getBodyParam('sitemapTrailingSlash', 'auto'));
        $settings->singleEventRoute = trim((string) $this->request->getBodyParam('singleEventRoute', 'event'));
        $settings->singlePlaceRoute = trim((string) $this->request->getBodyParam('singlePlaceRoute', 'place'));
        $settings->eventListingLimit = (int) $this->request->getBodyParam('eventListingLimit', 100);
        $settings->placeListingLimit = (int) $this->request->getBodyParam('placeListingLimit', 50);
        $settings->enableItineraries = (bool) $this->request->getBodyParam('enableItineraries', false) && !empty($settings->moduleStatus['itineraries']);

        // The CP only renders these three inputs when the Itineraries section is visible
        // (moduleStatus['itineraries'] true) - if the hub's module flag flips off and an
        // admin saves some other setting, the form won't post them at all. Only read them
        // here when the section could actually have been on the submitted form, otherwise
        // leave the previously saved values untouched instead of wiping them to empty/default.
        if (!empty($settings->moduleStatus['itineraries'])) {
            $settings->itineraryListingEntryIds = (array) $this->request->getBodyParam('itineraryListingEntryIds', []);
            $settings->itineraryDetailEntryIds = (array) $this->request->getBodyParam('itineraryDetailEntryIds', []);
            $settings->itineraryDefaultWidgetCode = (string) $this->request->getBodyParam('itineraryDefaultWidgetCode', 'default');
        }

        $settings->enableLlmsTxt = (bool) $this->request->getBodyParam('enableLlmsTxt', false);
        $settings->llmsDestinationName = (string) $this->request->getBodyParam('llmsDestinationName', '');
        $settings->llmsOrganizationName = (string) $this->request->getBodyParam('llmsOrganizationName', '');
        $settings->llmsAppendToExisting = (bool) $this->request->getBodyParam('llmsAppendToExisting', false);

        // Each picker is only rendered while its module is available - don't wipe a saved
        // list just because the hub has temporarily turned that module off.
        if (!empty($settings->moduleStatus['events'])) {
            $settings->llmsEventsEntryIds = (array) $this->request->getBodyParam('llmsEventsEntryIds', []);
        }
        if (!empty($settings->moduleStatus['places'])) {
            $settings->llmsPlacesEntryIds = (array) $this->request->getBodyParam('llmsPlacesEntryIds', []);
        }

        if (!$settings->validate(['eventsListingUrl', 'placesListingUrl', 'canonicalUrlSource', 'sitemapTrailingSlash', 'singleEventRoute', 'singlePlaceRoute', 'eventListingLimit', 'placeListingLimit', 'itineraryDefaultWidgetCode', 'itineraryListingEntryIds', 'itineraryDetailEntryIds', 'llmsDestinationName', 'llmsOrganizationName', 'llmsEventsEntryIds', 'llmsPlacesEntryIds'])) {
            return $this->asModelFailure($settings, 'Fix the errors below and try again.', 'settings');
        }

        Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), [
            'hubname' => $settings->hubname,
            'apiKey' => $settings->apiKey,
            'isConnected' => $settings->isConnected,
            'moduleStatus' => $settings->moduleStatus,
            'enableEvents' => $settings->enableEvents,
            'enablePlaces' => $settings->enablePlaces,
            'enableEventSubmissionForm' => $settings->enableEventSubmissionForm,
            'enablePlaceSubmissionForm' => $settings->enablePlaceSubmissionForm,
            'singleEventRoute' => $settings->singleEventRoute,
            'singlePlaceRoute' => $settings->singlePlaceRoute,
            'eventListingLimit' => $settings->eventListingLimit,
            'placeListingLimit' => $settings->placeListingLimit,
            'enableLegacyRedirects' => $settings->enableLegacyRedirects,
            'eventsListingUrl' => $settings->eventsListingUrl,
            'placesListingUrl' => $settings->placesListingUrl,
            'legacyEventIdParam' => $settings->legacyEventIdParam,
            'legacyPlaceIdParam' => $settings->legacyPlaceIdParam,
            'canonicalUrlSource' => $settings->canonicalUrlSource,
            'sitemapTrailingSlash' => $settings->sitemapTrailingSlash,
            'webhookSecret' => $settings->webhookSecret,
            'enableItineraries' => $settings->enableItineraries,
            'itineraryListingEntryIds' => $settings->itineraryListingEntryIds,
            'itineraryDetailEntryIds' => $settings->itineraryDetailEntryIds,
            'itineraryDefaultWidgetCode' => $settings->itineraryDefaultWidgetCode,
            'enableLlmsTxt' => $settings->enableLlmsTxt,
            'llmsDestinationName' => $settings->llmsDestinationName,
            'llmsOrganizationName' => $settings->llmsOrganizationName,
            'llmsEventsEntryIds' => $settings->llmsEventsEntryIds,
            'llmsPlacesEntryIds' => $settings->llmsPlacesEntryIds,
            'llmsAppendToExisting' => $settings->llmsAppendToExisting,
        ]);

        return $this->asSuccess('Settings saved.', redirect: 'settings/plugins/craft-iti-plugin');
    }

    public function actionDisconnect(): ?Response
    {
        $this->requirePostRequest();

        $settings = Plugin::getInstance()->getSettings();

        Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), [
            'hubname' => '',
            'apiKey' => '',
            'isConnected' => false,
            'moduleStatus' => ['events' => false, 'places' => false, 'itineraries' => false],
            'enableEvents' => false,
            'enablePlaces' => false,
            'enableEventSubmissionForm' => false,
            'enablePlaceSubmissionForm' => false,
            'singleEventRoute' => $settings->singleEventRoute,
            'singlePlaceRoute' => $settings->singlePlaceRoute,
            'eventListingLimit' => $settings->eventListingLimit,
            'placeListingLimit' => $settings->placeListingLimit,
            'enableLegacyRedirects' => $settings->enableLegacyRedirects,
            'eventsListingUrl' => $settings->eventsListingUrl,
            'placesListingUrl' => $settings->placesListingUrl,
            'legacyEventIdParam' => $settings->legacyEventIdParam,
            'legacyPlaceIdParam' => $settings->legacyPlaceIdParam,
            'canonicalUrlSource' => $settings->canonicalUrlSource,
            'sitemapTrailingSlash' => $settings->sitemapTrailingSlash,
            'webhookSecret' => $settings->webhookSecret,
            'enableItineraries' => false,
            'itineraryListingEntryIds' => $settings->itineraryListingEntryIds,
            'itineraryDetailEntryIds' => $settings->itineraryDetailEntryIds,
            'itineraryDefaultWidgetCode' => $settings->itineraryDefaultWidgetCode,
            'enableLlmsTxt' => $settings->enableLlmsTxt,
            'llmsDestinationName' => $settings->llmsDestinationName,
            'llmsOrganizationName' => $settings->llmsOrganizationName,
            'llmsEventsEntryIds' => $settings->llmsEventsEntryIds,
            'llmsPlacesEntryIds' => $settings->llmsPlacesEntryIds,
            'llmsAppendToExisting' => $settings->llmsAppendToExisting,
        ]);

        return $this->asSuccess('Disconnected.', redirect: 'settings/plugins/craft-iti-plugin');
    }
}
