# Dromos

Dromos is a Laravel web application for planning multi-stop driving routes in Greece. It geocodes full addresses, calculates road-following routes, displays them on Google Maps, stores completed results, and exports routes as Google Maps links.

## Route modes

- **Optimized route:** supports up to 25 destinations and asks Google Routes to reorder the stops primarily by driving time. It uses traffic-aware routing.
- **Sequential route:** supports up to 100 destinations and preserves the user's exact order. Dromos divides large routes into overlapping requests with no more than 10 intermediate waypoints, avoiding waypoint optimization and live-traffic routing.

Both modes support toll avoidance, return to start, bulk address import, drag-and-drop ordering, address addition/editing/deletion, automatic recalculation, multiple route polylines, and segmented Google Maps sharing links.

## API usage and stored data

Dromos keeps Google integrations behind the `Geocoder` and `RouteOptimizer` contracts. Geocoding and routing credentials remain server-side; the separately restricted browser key is used only to render Google Maps.

The main database tables have distinct API-saving responsibilities:

| Table | Responsibility | API-call reduction |
| --- | --- | --- |
| `route_plans` | Stores route mode, options, total distance/duration, encoded polylines, and the provider response. | Reopening or refreshing an unchanged saved route does not call the Geocoding or Routes API again. The browser map may still count as a Maps JavaScript load. |
| `stops` | Stores the start and destinations, verified coordinates, Place IDs, order, and leg estimates. | Reordering, deleting, or recalculating an existing route reuses stored coordinates and does not geocode unchanged stops. A Routes API call is still required to calculate the changed road route. |
| `geocoded_address_cache` | Stores normalized addresses and their verified coordinates across all routes. | Reusing the same address in another route avoids another Geocoding API request. `hit_count` measures these cross-route/cache lookups, not ordinary route recalculations. |

Laravel also creates framework tables for users, sessions, password resets, cache, queued jobs, failed jobs, job batches, and migration history.

## Architecture

- Models persist route plans, stops, and cached geocoding results.
- Form requests validate optimized and sequential route limits.
- Actions own transactional workflows for creating, reordering, adding, updating, and deleting stops.
- `CachedGeocoder` decorates the configured geocoder and transparently handles persistent cache hits and misses.
- `CalculateOrderedRoute` batches large sequential routes and aggregates their legs, totals, provider responses, and polylines.
- Google and demo implementations are selected through `ROUTING_PROVIDER`.

PlantUML sources for the system architecture, data model, core route workflows, and authentication flow are documented in [docs/diagrams](docs/diagrams/README.md).

## Local setup

```bash
composer install
npm install
php artisan migrate
composer run dev
```

The application currently uses MySQL/MariaDB. Configure a database in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dromos
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

## Google Maps configuration

Enable **Geocoding API**, **Routes API**, and **Maps JavaScript API** in Google Cloud, then configure:

```dotenv
ROUTING_PROVIDER=google
GOOGLE_MAPS_SERVER_KEY=your_server_only_key
GOOGLE_MAPS_BROWSER_KEY=your_browser_key
```

Restrict the server key to the required APIs and production server IPs. Restrict the browser key to Maps JavaScript API and approved HTTP referrers. Never expose the server key in frontend code.

Use `ROUTING_PROVIDER=demo` for deterministic local development and automated tests without Google API calls.
