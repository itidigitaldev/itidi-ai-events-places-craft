<?php

namespace iti\craftitiplugin\llms;

use Craft;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use iti\craftitiplugin\models\Settings;
use iti\craftitiplugin\Plugin;
use yii\base\Component;

class LlmsTxtService extends Component
{
    private const ERROR_CACHE_KEY = 'craft-iti-llms-last-error';
    private const EMPTY_MESSAGE = 'Select at least one Events or Places page to generate llms.txt.';
    private const MODULES_INACTIVE_MESSAGE = 'The Events/Places modules for the picked pages aren\'t enabled, so llms.txt has nothing to list.';
    private const CONFLICT_MESSAGE = 'An llms.txt file that this plugin didn\'t create already exists. Turn on "Append to an existing llms.txt" to add this plugin\'s section to it.';
    private const UNWRITABLE_MESSAGE = 'The web root isn\'t writable, so /llms.txt is served dynamically instead.';
    private const STALE_UNWRITABLE_MESSAGE = 'llms.txt exists but can\'t be updated, so crawlers may be seeing an outdated copy. Check its ownership and permissions.';
    private const REMOVE_FAILED_MESSAGE = 'Couldn\'t remove this plugin\'s section from llms.txt. Check the web root\'s file permissions.';

    public function filePath(): string
    {
        return Craft::getAlias('@webroot') . DIRECTORY_SEPARATOR . 'llms.txt';
    }

    public function buildContent(Settings $settings): string
    {
        [$eventsActive, $placesActive] = $this->activeModules($settings);
        $destination = $settings->llmsDestinationName !== '' ? $settings->llmsDestinationName : (string) Craft::$app->getSites()->getCurrentSite()->getName();

        return LlmsTxtGenerator::generate([
            'destinationName' => $destination,
            'organizationName' => $settings->llmsOrganizationName !== '' ? $settings->llmsOrganizationName : $destination,
            'eventsActive' => $eventsActive,
            'placesActive' => $placesActive,
            'events' => $eventsActive ? $this->links($settings->llmsEventsEntryIds) : [],
            'places' => $placesActive ? $this->links($settings->llmsPlacesEntryIds) : [],
            'sitemapUrl' => ($eventsActive || $placesActive) ? UrlHelper::siteUrl('sitemap-events-places.xml') : null,
        ]);
    }

    public function regenerate(?Settings $settings = null): string
    {
        $settings ??= Plugin::getInstance()->getSettings();
        $path = $this->filePath();

        if (!$settings->enableLlmsTxt) {
            $this->setError(LlmsTxtWriter::removeFromFile($path) ? null : self::REMOVE_FAILED_MESSAGE);
            return 'disabled';
        }

        $content = $this->buildContent($settings);

        if ($content === '') {
            $emptyMessage = $this->emptyContentMessage($settings);
            $this->setError(LlmsTxtWriter::removeFromFile($path) ? $emptyMessage : self::REMOVE_FAILED_MESSAGE);
            return 'empty';
        }

        $status = LlmsTxtWriter::writeFile($path, LlmsTxtWriter::wrap($content), $settings->llmsAppendToExisting);

        $this->setError(match (true) {
            $status === LlmsTxtWriter::STATUS_CONFLICT => self::CONFLICT_MESSAGE,
            $status === LlmsTxtWriter::STATUS_UNWRITABLE && is_file($path) => self::STALE_UNWRITABLE_MESSAGE,
            $status === LlmsTxtWriter::STATUS_UNWRITABLE => self::UNWRITABLE_MESSAGE,
            default => null,
        });

        return $status;
    }

    public function lastError(): ?string
    {
        $error = Craft::$app->getCache()->get(self::ERROR_CACHE_KEY);

        return is_string($error) && $error !== '' ? $error : null;
    }

    public function status(?Settings $settings = null): array
    {
        $settings ??= Plugin::getInstance()->getSettings();
        $path = $this->filePath();
        $exists = is_file($path);
        $raw = $exists ? @file_get_contents($path) : '';
        $readable = $raw !== false;
        $content = $readable ? (string) $raw : '';
        $ours = $exists && $readable && LlmsTxtWriter::hasBlock($content);

        $state = match (true) {
            $exists && !$readable => 'unreadable',
            !$exists && $this->buildContent($settings) === '' => 'none',
            !$exists => 'dynamic',
            !$ours => 'conflict',
            trim(LlmsTxtWriter::remove($content)) !== '' => 'appended',
            default => 'generated',
        };

        $lastGenerated = null;

        if ($ours) {
            $mtime = @filemtime($path);
            $lastGenerated = $mtime !== false ? (int) $mtime : null;
        }

        return [
            'state' => $state,
            'url' => UrlHelper::siteUrl('llms.txt'),
            'lastGenerated' => $lastGenerated,
            'lastError' => $this->lastError(),
        ];
    }

    /**
     * The message to show when buildContent() comes back empty. A picked list can
     * survive a module going unavailable (the picker just stops rendering), so "nothing
     * picked" and "picked for a module that's off" need different messages.
     */
    private function emptyContentMessage(Settings $settings): string
    {
        [$eventsActive, $placesActive] = $this->activeModules($settings);
        $anyPicked = $settings->llmsEventsEntryIds !== [] || $settings->llmsPlacesEntryIds !== [];
        $pickedForActive = ($eventsActive && $settings->llmsEventsEntryIds !== [])
            || ($placesActive && $settings->llmsPlacesEntryIds !== []);

        return (!$anyPicked || $pickedForActive) ? self::EMPTY_MESSAGE : self::MODULES_INACTIVE_MESSAGE;
    }

    /**
     * @return array{0: bool, 1: bool} [eventsActive, placesActive]
     */
    private function activeModules(Settings $settings): array
    {
        return [
            $settings->enableEvents && !empty($settings->moduleStatus['events']),
            $settings->enablePlaces && !empty($settings->moduleStatus['places']),
        ];
    }

    private function links(array $entryIds): array
    {
        if ($entryIds === []) {
            return [];
        }

        $links = [];

        foreach (Entry::find()->id($entryIds)->status('live')->all() as $entry) {
            $url = $entry->getUrl();

            if ($url !== null) {
                $links[] = [
                    'title' => (string) $entry->title,
                    'url' => $url,
                    'isHomepage' => $entry->uri === '__home__',
                ];
            }
        }

        return $links;
    }

    private function setError(?string $message): void
    {
        if ($message === null) {
            Craft::$app->getCache()->delete(self::ERROR_CACHE_KEY);
            return;
        }

        Craft::$app->getCache()->set(self::ERROR_CACHE_KEY, $message, 0);
    }
}
