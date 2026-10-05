<?php

namespace iti\craftitiplugin\tripplanner;

use Craft;
use craft\events\TemplateEvent;
use iti\craftitiplugin\Plugin;
use yii\base\Component;

class TripPlannerService extends Component
{
    /**
     * Set by anything in this plugin that puts ITI widget markup on the page. That markup
     * already carries the imgoingcalendar-wrapper element the Trip Planner also needs, so
     * such pages are skipped automatically (the WP plugin does the same).
     */
    public bool $moduleContentRendered = false;

    public function markModuleContentRendered(): void
    {
        $this->moduleContentRendered = true;
    }

    public function shouldInject(): bool
    {
        $settings = Plugin::getInstance()->getSettings();

        if (
            !$settings->isConnected
            || !$settings->enableTripPlanner
            || empty($settings->moduleStatus['tripPlanner'])
            || trim($settings->hubname) === ''
            || $this->moduleContentRendered
        ) {
            return false;
        }

        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest() || !$request->getIsSiteRequest() || $request->getIsActionRequest() || $request->getIsPreview()) {
            return false;
        }

        return TripPlannerRules::shouldLoad('/' . $request->getFullPath(), $settings->tripPlannerLoadingMode, $settings->tripPlannerPaths);
    }

    public function handleAfterRenderPageTemplate(TemplateEvent $event): void
    {
        try {
            if (
                stripos($event->output, '<body') === false
                || TripPlannerSnippet::pageHasWidget($event->output)
                || !$this->shouldInject()
            ) {
                return;
            }

            $settings = Plugin::getInstance()->getSettings();
            $event->output = TripPlannerSnippet::inject($event->output, TripPlannerSnippet::html($settings->hubname), $settings->tripPlannerPosition);
        } catch (\Throwable $e) {
            Craft::warning('Trip Planner injection failed: ' . $e->getMessage(), __METHOD__);
        }
    }
}
