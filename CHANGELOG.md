# Release Notes for ITI Digital AI-Ready Events & Places

## 1.0.0 - 2026-10-15

Initial public release.

### Added
- Connect a site to an ITI Digital hub with a hub name and API key (Settings → ITI Digital AI-Ready Events & Places); only modules enabled for the hub are shown.
- Server-side rendered Events and Places listings and detail pages, available in Twig through `craft.craftIti` (`eventsList()`, `placesList()`, `eventDetail()`, `placeDetail()`).
- Clean, crawler-visible detail URLs (`/e/<id>` and `/p/<id>`), with overridable default templates and fallback single-item routes.
- Optional 301 redirects from legacy `?event_id=` / `?place_id=` links to the clean URLs.
- SEO metadata, canonical URLs and structured data on detail pages, with SEOmatic integration.
- Dynamic events/places XML sitemap, also added to SEOmatic's sitemap index when SEOmatic is installed.
- Authenticated webhook endpoint for Event, Place, Itinerary and module-status updates, with cache purging (Blitz supported).
- Itineraries module: `craft.craftIti.itinerariesList()` and `itineraryDetail()`.
- `/llms.txt` generation for Event, Place and Itinerary discovery, safe to append to an existing hand-written file, plus a `craft-iti-plugin/llms/generate` console command.
- Trip Planner widget injection with configurable placement and include/exclude path rules.
- Event and Place submission form embeds.
- Analytics-friendly `dataLayer` tracking on detail pages.
- Configurable default listing limits.
- Expired events respond with a 410 and a friendly message.
- Optional Craft search integration for Events and Places.
- Uninstall cleanup of plugin data.
