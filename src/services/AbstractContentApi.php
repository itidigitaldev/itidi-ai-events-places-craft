<?php

namespace iti\craftitiplugin\services;

use Craft;
use GuzzleHttp\ClientInterface;
use iti\craftitiplugin\errors\ContentApiException;
use iti\craftitiplugin\errors\ContentNotFoundException;
use iti\craftitiplugin\helpers\TrailingSlashDetector;
use Psr\Http\Message\ResponseInterface;
use yii\base\Component;
use yii\caching\CacheInterface;

abstract class AbstractContentApi extends Component
{
    private const API_BASE = 'https://api.imgoingcalendar.com/api/v2.0/';
    private const TIMEOUT = 30;
    private const SEARCH_LIMIT_DEFAULT = 50;
    private const SEARCH_CACHE_TTL = 900;

    /**
     * A detail response can come back with a 2xx status and still describe
     * something that's no longer reachable (the hub marks it this way instead
     * of just 404ing) - these are the status values that count as "gone" the
     * same way a non-2xx status code does.
     */
    private const NOT_AVAILABLE_STATUSES = ['not_found', 'expired', 'gone'];

    public ?ClientInterface $httpClient = null;
    public ?CacheInterface $cache = null;

    /**
     * Null means "work it out from the live request" (see resolveTrailingSlashFromRequest()) -
     * exposed as a settable property so tests can pin the value instead of needing a booted
     * Craft app.
     */
    public ?bool $trailingSlash = null;

    abstract protected function typeSegment(): string;

    abstract public function sitemapType(): string;

    abstract protected function notFoundStatusCode(): int;

    abstract protected function ceilingLimit(): int;

    abstract protected function mapListingFilters(array $filters, int $resolvedLimit, string $listingBaseUrl, string $detailBaseUrl, int $page): array;

    public function fetchListing(string $hubname, string $resolvedApiKey, array $filters, string $listingBaseUrl, string $detailBaseUrl, int $page = 1): array
    {
        $limit = $this->resolveLimit($filters['limit'] ?? null);
        $queryArgs = $this->mapListingFilters($filters, $limit, $listingBaseUrl, $detailBaseUrl, $page);

        $data = $this->request($hubname, $resolvedApiKey, $this->typeSegment() . '/ssr-listing', $queryArgs);

        if (empty($data['html'])) {
            throw new ContentApiException('No listing content returned.');
        }

        return $data;
    }

    /**
     * Unlike fetchListing()/search(), a detail fetch has to inspect the body even when
     * the hub answers with a non-2xx status: an expired/gone event still comes back with
     * fallback html worth rendering, just flagged as unavailable rather than thrown away.
     */
    public function fetchDetail(string $hubname, string $resolvedApiKey, string $id): array
    {
        $queryArgs = ['id' => self::extractRawId($id), 'format' => 'fragment', 'assetMode' => 'external'];

        try {
            $response = $this->sendRequest($hubname, $resolvedApiKey, $this->typeSegment() . '/ssr-detail', $queryArgs, throwOnError: false);
            $data = self::decodeJsonBody($response);
        } catch (ContentApiException) {
            throw new ContentNotFoundException($this->notFoundStatusCode());
        }

        if (empty($data['html'])) {
            throw new ContentNotFoundException($this->notFoundStatusCode());
        }

        if ($this->isDetailNotAvailable($data, $response->getStatusCode())) {
            $data['notAvailable'] = true;
            $data['notAvailableStatusCode'] = $this->notFoundStatusCode();
        }

        return $data;
    }

    private function isDetailNotAvailable(array $data, int $statusCode): bool
    {
        if ($statusCode < 200 || $statusCode >= 300) {
            return true;
        }

        $status = strtolower((string) ($data['status'] ?? ''));

        if (in_array($status, self::NOT_AVAILABLE_STATUSES, true)) {
            return true;
        }

        return ($data['error'] ?? null) === 'Event not found';
    }

    public function search(string $hubname, string $resolvedApiKey, string $term, int $limit = self::SEARCH_LIMIT_DEFAULT): array
    {
        $cache = $this->cache ?? Craft::$app->getCache();
        $trailingSlash = $this->shouldAddTrailingSlash();
        $cacheKey = 'craft-iti-search-' . $this->typeSegment() . '-' . md5($hubname . '|' . $term . '|' . $limit . '|' . ($trailingSlash ? '1' : '0'));

        return $cache->getOrSet($cacheKey, function() use ($hubname, $resolvedApiKey, $term, $limit) {
            $data = $this->request($hubname, $resolvedApiKey, $this->typeSegment() . '/', [
                'search' => $term,
                'limit' => $limit,
            ]);

            $items = $data[$this->typeSegment()] ?? [];
            return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
        }, self::SEARCH_CACHE_TTL);
    }

    private function resolveLimit(?int $requested): int
    {
        $limit = $requested && $requested > 0 ? $requested : $this->ceilingLimit();
        return min($limit, $this->ceilingLimit());
    }

    /**
     * Listing links generated by the SSR widget embed a human-readable slug
     * ahead of the real id (e.g. "some-event-name-6a251e6360e8033e32950389").
     * The detail endpoint only recognizes the raw id, so strip everything
     * before the last hyphen - the same assumption the WordPress reference
     * plugin's own id extraction makes.
     */
    public static function extractRawId(string $id): string
    {
        $segments = explode('-', $id);
        return trim((string) end($segments), '/');
    }

    protected function request(string $hubname, string $resolvedApiKey, string $route, array $queryArgs): array
    {
        $response = $this->sendRequest($hubname, $resolvedApiKey, $route, $queryArgs, throwOnError: true);

        return self::decodeJsonBody($response);
    }

    private function sendRequest(string $hubname, string $resolvedApiKey, string $route, array $queryArgs, bool $throwOnError): ResponseInterface
    {
        $url = self::API_BASE . rawurlencode($hubname) . '/' . $route;
        $client = $this->httpClient ?? Craft::createGuzzleClient(['timeout' => self::TIMEOUT]);

        if ($this->shouldAddTrailingSlash()) {
            $queryArgs['trailingSlash'] = 'true';
        }

        return ApiHttpRequest::get($client, $url, array_merge($queryArgs, ['key' => $resolvedApiKey]), 'application/json', $throwOnError);
    }

    private function shouldAddTrailingSlash(): bool
    {
        if ($this->trailingSlash !== null) {
            return $this->trailingSlash;
        }

        return $this->resolveTrailingSlashFromRequest();
    }

    /**
     * this plugin's own isolated test suite has no Craft class on its autoloader at all
     * (see Settings::slugify()) - guard the same way so production call sites can leave
     * $trailingSlash null and still get a sensible default.
     */
    private function resolveTrailingSlashFromRequest(): bool
    {
        if (!class_exists(\Craft::class) || Craft::$app === null) {
            return false;
        }

        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest()) {
            return false;
        }

        $path = parse_url($request->getUrl(), PHP_URL_PATH) ?? '';

        return TrailingSlashDetector::shouldAddTrailingSlash($path, Craft::$app->getConfig()->getGeneral()->addTrailingSlashesToUrls);
    }

    private static function decodeJsonBody(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);

        if (!is_array($data)) {
            throw new ContentApiException('ITI Digital returned a malformed response.');
        }

        return $data;
    }
}
