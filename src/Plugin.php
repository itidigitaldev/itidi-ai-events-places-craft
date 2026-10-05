<?php

namespace iti\craftitiplugin;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\Entry;
use craft\events\ConfigEvent;
use craft\events\ExceptionEvent;
use craft\events\PluginEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Plugins;
use craft\web\ErrorHandler;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use craft\web\View;
use iti\craftitiplugin\helpers\LegacyRedirectResolver;
use iti\craftitiplugin\llms\LlmsTxtService;
use iti\craftitiplugin\models\Settings;
use iti\craftitiplugin\services\EventsApi;
use iti\craftitiplugin\services\ItinerariesApi;
use iti\craftitiplugin\services\ModuleStatusApi;
use iti\craftitiplugin\services\PlacesApi;
use iti\craftitiplugin\services\SitemapApi;
use iti\craftitiplugin\variables\CraftItiVariable;
use craft\helpers\ElementHelper;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\UrlHelper;
use nystudio107\seomatic\events\RegisterSitemapsEvent;
use nystudio107\seomatic\models\SitemapIndexTemplate;
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
            'sitemapApi' => SitemapApi::class,
            'itinerariesApi' => ItinerariesApi::class,
            'llmsTxt' => LlmsTxtService::class,
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
                $event->rules['sitemap-events-places.xml'] = 'craft-iti-plugin/sitemap/index';
                $event->rules['llms.txt'] = 'craft-iti-plugin/llms/index';

                $settings = $this->getSettings();
                // Settings can reach here unvalidated via a config/craft-iti-plugin.php override, which
                // bypasses Settings::validate()'s empty-value fallback - guard here too, since an empty
                // segment would compile into a rule matching every single-segment path on the site.
                $eventRoute = $settings->singleEventRoute ?: 'event';
                $placeRoute = $settings->singlePlaceRoute ?: 'place';
                // ??=, not =: a configured route that collides with one of the fixed routes above must
                // leave the existing rule intact rather than overwrite it.
                $event->rules[$eventRoute . '/<id:[^\/]+>'] ??= 'craft-iti-plugin/detail/event';
                $event->rules[$placeRoute . '/<id:[^\/]+>'] ??= 'craft-iti-plugin/detail/place';
            }
        );

        if (class_exists(SitemapIndexTemplate::class) && Craft::$app->getPlugins()->isPluginEnabled('seomatic')) {
            Event::on(
                SitemapIndexTemplate::class,
                SitemapIndexTemplate::EVENT_REGISTER_SITEMAPS,
                function(RegisterSitemapsEvent $event) {
                    $settings = $this->getSettings();
                    if (!$settings->enableEvents && !$settings->enablePlaces) {
                        return;
                    }

                    $event->sitemaps[] = [
                        'loc' => UrlHelper::siteUrl('sitemap-events-places.xml', null, null, $event->siteId),
                        'lastmod' => new \DateTime(),
                    ];
                }
            );
        }

        Event::on(ErrorHandler::class, ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION, [$this, 'handleLegacyRedirect']);

        Event::on(Plugins::class, Plugins::EVENT_AFTER_SAVE_PLUGIN_SETTINGS, function(PluginEvent $event) {
            try {
                if ($event->plugin === $this) {
                    $this->regenerateLlmsTxt();
                }
            } catch (\Throwable $e) {
                Craft::warning('llms.txt regeneration failed: ' . $e->getMessage(), __METHOD__);
            }
        });

        // ProjectConfig::set() inside savePluginSettings() fires this same onAdd/onUpdate
        // synchronously, which would regenerate a second time for every CP save and webhook
        // module-status save (the handler above already covers those). Only act here when
        // Craft is applying changes that came from outside this request - i.e. a deploy
        // applying project config from external storage - which never goes through
        // savePluginSettings() on that environment.
        $onConfigChange = function(ConfigEvent $event) {
            try {
                if (!Craft::$app->getProjectConfig()->getIsApplyingExternalChanges()) {
                    return;
                }
                // Project config packs associative arrays (like moduleStatus) to preserve their
                // key order - unpack before handing them to Settings, same as Craft's own
                // config-change handlers do (e.g. Fields::applyFieldSave()).
                $values = is_array($event->newValue) ? ProjectConfigHelper::unpackAssociativeArrays($event->newValue) : [];
                $settings = new Settings();
                $settings->setAttributes($values, false);
                $this->regenerateLlmsTxt($settings);
            } catch (\Throwable $e) {
                Craft::warning('llms.txt regeneration failed: ' . $e->getMessage(), __METHOD__);
            }
        };
        Craft::$app->getProjectConfig()
            ->onAdd('plugins.' . $this->handle . '.settings', $onConfigChange)
            ->onUpdate('plugins.' . $this->handle . '.settings', $onConfigChange);

        $onEntryChange = function(Event $event) {
            try {
                /** @var Entry $entry */
                $entry = $event->sender;
                $settings = $this->getSettings();
                $id = $entry->getCanonicalId();
                // Check relevance before isDraftOrRevision(): that call can query the entry's
                // owner (nested entries) and throw, and it runs for every entry save/delete
                // site-wide - skip it entirely unless this entry is actually one we care about.
                if (!in_array($id, $settings->llmsEventsEntryIds, true) && !in_array($id, $settings->llmsPlacesEntryIds, true)) {
                    return;
                }
                if (ElementHelper::isDraftOrRevision($entry)) {
                    return;
                }
                $this->regenerateLlmsTxt();
            } catch (\Throwable $e) {
                Craft::warning('llms.txt regeneration failed: ' . $e->getMessage(), __METHOD__);
            }
        };
        Event::on(Entry::class, Element::EVENT_AFTER_SAVE, $onEntryChange);
        Event::on(Entry::class, Element::EVENT_AFTER_DELETE, $onEntryChange);
    }

    public function uninstall(): void
    {
        parent::uninstall();

        // Settings are removed unconditionally by Plugins::uninstallPlugin()
        // regardless of anything this method does - nothing to gate here.
        // The 15-minute search cache (idea 1105) would self-expire on its
        // own; flushing just clears it immediately rather than waiting.
        // Note: this clears Craft's whole shared cache, not just this
        // plugin's entries - a deliberate choice (no key registry exists
        // to scope it), so uninstalling this plugin also clears other
        // plugins'/Craft core's cached data on the same install.
        Craft::$app->getCache()->flush();
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
        $settings = $this->getSettings();

        if ($settings->webhookSecret === '' && !Craft::$app->getProjectConfig()->readOnly) {
            $settings->webhookSecret = bin2hex(random_bytes(32));
            Craft::$app->getPlugins()->savePluginSettings($this, $settings->toArray());
        }

        $llmsStatus = null;

        if ($settings->enableLlmsTxt) {
            try {
                $llmsStatus = $this->llmsTxt->status($settings);
            } catch (\Throwable $e) {
                Craft::warning('llms.txt status check failed: ' . $e->getMessage(), __METHOD__);
            }
        }

        return $controller->renderTemplate('craft-iti-plugin/settings', [
            'settings' => $settings,
            'itineraryListingEntries' => self::entriesById($settings->itineraryListingEntryIds),
            'itineraryDetailEntries' => self::entriesById($settings->itineraryDetailEntryIds),
            'llmsEventsEntries' => self::entriesById($settings->llmsEventsEntryIds),
            'llmsPlacesEntries' => self::entriesById($settings->llmsPlacesEntryIds),
            'llmsStatus' => $llmsStatus,
        ]);
    }

    private static function entriesById(array $ids): array
    {
        return $ids ? Entry::find()->id($ids)->status(null)->all() : [];
    }

    /**
     * Never let a llms.txt problem break the save that triggered it.
     */
    private function regenerateLlmsTxt(?Settings $settings = null): void
    {
        try {
            $this->llmsTxt->regenerate($settings);
        } catch (\Throwable $e) {
            Craft::warning('llms.txt regeneration failed: ' . $e->getMessage(), __METHOD__);
        }
    }
}
