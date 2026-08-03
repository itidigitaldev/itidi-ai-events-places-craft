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
            'enableLegacyRedirects' => $settings->enableLegacyRedirects,
            'eventsListingUrl' => $settings->eventsListingUrl,
            'placesListingUrl' => $settings->placesListingUrl,
            'legacyEventIdParam' => $settings->legacyEventIdParam,
            'legacyPlaceIdParam' => $settings->legacyPlaceIdParam,
            'canonicalUrlSource' => $settings->canonicalUrlSource,
            'webhookSecret' => $settings->webhookSecret,
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
        $settings->enableLegacyRedirects = (bool) $this->request->getBodyParam('enableLegacyRedirects', false);
        $settings->eventsListingUrl = trim((string) $this->request->getBodyParam('eventsListingUrl', ''));
        $settings->placesListingUrl = trim((string) $this->request->getBodyParam('placesListingUrl', ''));
        $settings->legacyEventIdParam = trim((string) $this->request->getBodyParam('legacyEventIdParam', 'event_id'));
        $settings->legacyPlaceIdParam = trim((string) $this->request->getBodyParam('legacyPlaceIdParam', 'place_id'));
        $settings->canonicalUrlSource = trim((string) $this->request->getBodyParam('canonicalUrlSource', 'ssr'));

        if (!$settings->validate(['eventsListingUrl', 'placesListingUrl', 'canonicalUrlSource'])) {
            return $this->asModelFailure($settings, 'Fix the errors below and try again.', 'settings');
        }

        Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), [
            'hubname' => $settings->hubname,
            'apiKey' => $settings->apiKey,
            'isConnected' => $settings->isConnected,
            'moduleStatus' => $settings->moduleStatus,
            'enableEvents' => $settings->enableEvents,
            'enablePlaces' => $settings->enablePlaces,
            'enableLegacyRedirects' => $settings->enableLegacyRedirects,
            'eventsListingUrl' => $settings->eventsListingUrl,
            'placesListingUrl' => $settings->placesListingUrl,
            'legacyEventIdParam' => $settings->legacyEventIdParam,
            'legacyPlaceIdParam' => $settings->legacyPlaceIdParam,
            'canonicalUrlSource' => $settings->canonicalUrlSource,
            'webhookSecret' => $settings->webhookSecret,
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
            'moduleStatus' => ['events' => false, 'places' => false],
            'enableEvents' => false,
            'enablePlaces' => false,
            'enableLegacyRedirects' => $settings->enableLegacyRedirects,
            'eventsListingUrl' => $settings->eventsListingUrl,
            'placesListingUrl' => $settings->placesListingUrl,
            'legacyEventIdParam' => $settings->legacyEventIdParam,
            'legacyPlaceIdParam' => $settings->legacyPlaceIdParam,
            'canonicalUrlSource' => $settings->canonicalUrlSource,
            'webhookSecret' => $settings->webhookSecret,
        ]);

        return $this->asSuccess('Disconnected.', redirect: 'settings/plugins/craft-iti-plugin');
    }
}
