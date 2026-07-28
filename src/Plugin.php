<?php

namespace iti\craftitiplugin;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\ExceptionEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\ErrorHandler;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use iti\craftitiplugin\helpers\LegacyRedirectResolver;
use iti\craftitiplugin\models\Settings;
use iti\craftitiplugin\services\EventsApi;
use iti\craftitiplugin\services\ModuleStatusApi;
use iti\craftitiplugin\services\PlacesApi;
use iti\craftitiplugin\variables\CraftItiVariable;
use yii\base\Event;
use yii\web\HttpException;

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

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // The wildcard-prefix pair matches a listing nested anywhere
                // ("events/e/123"). The bare pair matches a listing at the site
                // root ("e/123") - the wildcard pair can't, since it requires a
                // literal "/" before "e/"/"p/", which a root-level path has no
                // room for. Both pairs are needed; don't collapse back to two.
                $event->rules['<prefix:.*>/e/<id:[^\/]+>'] = 'craft-iti-plugin/detail/event';
                $event->rules['<prefix:.*>/p/<id:[^\/]+>'] = 'craft-iti-plugin/detail/place';
                $event->rules['e/<id:[^\/]+>'] = 'craft-iti-plugin/detail/event';
                $event->rules['p/<id:[^\/]+>'] = 'craft-iti-plugin/detail/place';
            }
        );

        Event::on(ErrorHandler::class, ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION, [$this, 'handleLegacyRedirect']);
    }

    public function handleLegacyRedirect(ExceptionEvent $event): void
    {
        $exception = $event->exception;
        if (!$exception instanceof HttpException || $exception->statusCode !== 404) {
            return;
        }

        $request = Craft::$app->getRequest();
        if (!$request->getIsSiteRequest()) {
            return;
        }

        $settings = $this->getSettings();
        $query = $request->getQueryParams();

        $url = LegacyRedirectResolver::resolve(
            $settings->enableLegacyRedirects,
            $settings->eventsListingUrl,
            $settings->legacyEventIdParam,
            $query,
            suffix: 'e',
        ) ?? LegacyRedirectResolver::resolve(
            $settings->enableLegacyRedirects,
            $settings->placesListingUrl,
            $settings->legacyPlaceIdParam,
            $query,
            suffix: 'p',
        );

        if ($url === null) {
            return;
        }

        Craft::$app->getResponse()->redirect($url, 301);
        Craft::$app->end();
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
