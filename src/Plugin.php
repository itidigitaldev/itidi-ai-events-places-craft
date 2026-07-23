<?php

namespace iti\craftitiplugin;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\TemplateEvent;
use craft\web\View;
use yii\base\Event;

/**
 * Craft ITI Plugin
 *
 * @method static Plugin getInstance()
 */
class Plugin extends BasePlugin
{
    private const MARKER_PATTERN = '/<div\s+id=(["\'])(craftitiplugin|craft-iti-plugin|craft_iti_plugin)\1\s*>\s*<\/div>/i';

    private const PLACEHOLDER_HTML = '<div class="craft-iti-plugin-placeholder" style="border:2px dashed #2563eb;background:#eff6ff;padding:1.5rem;border-radius:0.5rem;text-align:center;color:#1e3a8a;"><strong>🔌 Craft ITI Plugin</strong><br>This section is rendered by the Craft ITI Plugin (placeholder — real content coming later).</div>';

    public function init(): void
    {
        parent::init();

        Event::on(
            View::class,
            View::EVENT_AFTER_RENDER_PAGE_TEMPLATE,
            function(TemplateEvent $event) {
                if (!Craft::$app->getRequest()->getIsSiteRequest()) {
                    return;
                }

                $event->output = preg_replace(
                    self::MARKER_PATTERN,
                    '<div id="$2">' . self::PLACEHOLDER_HTML . '</div>',
                    $event->output
                ) ?? $event->output;
            }
        );
    }
}
