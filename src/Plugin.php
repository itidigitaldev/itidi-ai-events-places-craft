<?php

namespace iti\craftitiplugin;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use iti\craftitiplugin\models\Settings;
use iti\craftitiplugin\services\EventsApi;
use iti\craftitiplugin\services\ModuleStatusApi;
use iti\craftitiplugin\services\PlacesApi;
use iti\craftitiplugin\variables\CraftItiVariable;
use yii\base\Event;

/**
 * Craft ITI Plugin
 *
 * @method static Plugin getInstance()
 */
class Plugin extends BasePlugin
{
    public function init(): void
    {
        $this->hasCpSettings = true;

        parent::init();

        $this->setComponents([
            'moduleStatusApi' => ModuleStatusApi::class,
            'eventsApi' => EventsApi::class,
            'placesApi' => PlacesApi::class,
        ]);

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('craftIti', CraftItiVariable::class);
            }
        );

        // craft\base\Plugin only registers src/templates/ as a CP template
        // root. CraftItiVariable renders its partials from site requests, so
        // the same directory needs to be a site root too.
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $baseDir = $this->getBasePath() . DIRECTORY_SEPARATOR . 'templates';
                if (is_dir($baseDir)) {
                    $event->roots[$this->id] = $baseDir;
                }
            }
        );
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    public function getSettingsResponse(): mixed
    {
        /** @var \craft\web\Controller $controller */
        $controller = Craft::$app->controller;

        return $controller->renderTemplate('craft-iti-plugin/settings', [
            'settings' => $this->getSettings(),
        ]);
    }
}
