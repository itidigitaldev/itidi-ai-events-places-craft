# ITI Digital AI-Ready Events & Places for Craft CMS

SEO-friendly Events, Places, Itineraries and Trip Planner integration for Craft CMS, powered by the [ITI Digital](https://iti-digital.com) platform. It is the Craft counterpart of the [WordPress plugin](https://wordpress.org/plugins/itidi-ai-events-places/).

Tourism content is rendered server-side so search engines and AI crawlers see real HTML, with clean URLs, structured data, a sitemap and `llms.txt` generated for you.

## Features

- API key authentication against your ITI Digital hub
- Server-side rendered Events, Places and Itineraries
- Clean detail URLs under `/e/` (Events) and `/p/` (Places), with overridable templates
- Optional 301 redirects for legacy `?event_id=` / `?place_id=` links
- SEO metadata, canonical URLs and structured data (SEOmatic integration)
- Dynamic events/places sitemap, added to SEOmatic's sitemap index automatically
- Authenticated webhook for real-time cache invalidation (Blitz supported)
- `/llms.txt` generation for AI crawler discovery
- Trip Planner widget with placement and path rules
- Event and Place submission forms
- `dataLayer` analytics events on detail pages
- Optional Craft search integration

## Requirements

- Craft CMS 5.9.0 or later
- PHP 8.2 or later
- An active ITI Digital hub and API key. The plugin does not work without one.

## Installation

From the Craft Plugin Store, search for "ITI Digital AI-Ready Events & Places", or with Composer:

```bash
composer require iti/craft-iti-plugin
php craft plugin/install craft-iti-plugin
```

## Setup

1. Open **Settings → ITI Digital AI-Ready Events & Places**.
2. Enter the hub name and API key from ITI Digital and save. Modules enabled for your hub then appear.
3. Enable the Events, Places, Itineraries, llms.txt and Trip Planner modules you need.
4. Pick the entries that hold your listing pages and save.

## Templates

Render content from any Twig template:

```twig
{{ craft.craftIti.eventsList() }}
{{ craft.craftIti.placesList() }}
{{ craft.craftIti.itinerariesList({ widgetCode: 'default' }) }}
```

Detail pages are served at `<listing page>/e/<id>` and `<listing page>/p/<id>`. Override the default templates by placing your own at the same relative path in your site's `templates/` directory.

## llms.txt

When enabled, `web/llms.txt` is generated from the listing pages you pick, between `<!-- BEGIN/END ITI Digital llms.txt -->` markers so an existing file can be appended to safely. Regenerate manually with:

```bash
php craft craft-iti-plugin/llms/generate
```

Run it as the web server's user so the file stays writable by PHP.

## Webhook

Configure the webhook URL and secret shown in the plugin settings in your ITI Digital hub. Content, itinerary and module-status changes then purge the relevant caches immediately.

## Development

```bash
composer install
vendor/bin/phpunit
```

## Support

- Issues: https://github.com/Stellites-WP-Dept/ITI-Craft-plugin/issues
- ITI Digital: https://iti-digital.com

## License

MIT, see [LICENSE.md](LICENSE.md).
