# Architecture Guide

Single Go binary (`pkgstatsd`) serving both a JSON API and server-rendered HTML UI. SQLite database. No ORM — raw `database/sql`.

## High-Level Structure

```
main.go                  — wiring: config → DB → repos → handlers → middleware → server
internal/
  config/                — env-based config (DATABASE, PORT, GEOIP_DATABASE)
  database/              — SQLite setup, auto-migrations (golang-migrate), MonthlySamplesCache
  web/                   — HTTP server, middleware stack, error responses (RFC 7807)
  submit/                — POST /api/submit: the write path (only write endpoint)
  popularity/            — generic read-only handler+repo for entity popularity
  packages/              — /api/packages: custom handler+repo (different popularity formula)
  countries/             — /api/countries: thin wrapper around popularity
  mirrors/               — /api/mirrors: thin wrapper around popularity
  systemarchitectures/   — /api/system-architectures: thin wrapper
  operatingsystems/      — /api/operating-systems: thin wrapper
  osarchitectures/       — /api/operating-system-architectures: thin wrapper
  chartdata/             — transforms popularity series → Chart.js-ready JSON
  anomalydetection/      — CLI subcommand to detect bot/spam anomalies
  sitemap/               — /sitemap.xml
  apidoc/                — /api/doc.json (OpenAPI spec, also used by ui/apidoc)
  ui/                    — all HTML pages (templ templates)
cmd/
  fixtures/              — generate test data
```

## Database Schema

All data tables have the same shape: `(<name> TEXT, month INT, count INT)` with `PRIMARY KEY (<name>, month)`. The identifier column name varies by table (`name`, `code`, `url`, `id`). Month is encoded as `YEAR*100 + MONTH` (e.g. 202603). Each table maps 1:1 to a package in `internal/`.

The exception is `submission_log`: one row per accepted submission with client IP, HTTP headers and the raw JSON payload. It exists to analyze abusive submissions and recover the aggregate tables from data poisoning, and is pruned periodically. Payloads are plain JSON, so ad-hoc analysis works with SQLite's built-in JSON functions (e.g. `json_each(payload, '$.pacman.packages')`).

Migrations are numbered sequential SQL files run automatically on startup via `golang-migrate`. When adding a new migration, use the next number after the highest existing one.

To keep migration count low, older migrations can be squashed into the latest one after it has been deployed to production. Move the full current schema into the highest-numbered migration and delete all prior migration files. This works because production is already past the old versions, and fresh databases will start from the single remaining migration.

## The Write Path: `POST /api/submit`

The only write endpoint. Flow:

1. **Rate limiting** — by anonymized IP. SQLite-backed in production, in-memory in dev.
2. **Parse & validate** — JSON body → `Request` struct. Validates architecture combos and package names.
3. **Expected packages check** — rejects submissions missing too many expected packages (anti-spam).
4. **GeoIP** — MaxMind lookup for country code (noop fallback if DB unavailable).
5. **Mirror URL filtering** — validates and normalizes the mirror URL.
6. **Save** — single transaction: upsert into all count tables and insert the raw submission into `submission_log`, so the log contains exactly the submissions that were counted. Expired log entries are removed separately by the `prune-submission-log` command, not on the request path.

## The Read Path: API

All read endpoints follow the same three-route pattern:

```
GET /api/{entity}              → list (paginated, filterable by query and month range)
GET /api/{entity}/{id}         → single item detail
GET /api/{entity}/{id}/series  → time series for chart data
```

### Popularity: the generic layer (`internal/popularity/`)

All entities except packages use `popularity.Handler[T, L]` and `popularity.Repository[T, L]` — a generic handler+repo parameterized by response types. The repo is configured with just a table name, column name, and search mode. Popularity is `count / samples` as a percentage.

### Packages: the exception

`internal/packages/` has its own handler+repo because it needs a different sample baseline. Each submission contains many packages but only one value for mirror, country, OS arch, etc. — so for those entities `SUM(count)` equals the number of submissions, while for packages it doesn't (packages uses `MAX(count)` instead).

### MonthlySamplesCache

Both popularity and packages repos use `database.MonthlySamplesCache` — loads all `(month, samples)` pairs once, caches until start of next calendar month.

## Middleware Stack

Applied in `main.go` via `web.Chain()` (first = outermost). Includes panic recovery, security headers (CSP, nosniff), CORS, HTML error pages for non-API requests, and cache control. See `main.go` for the current stack.

## UI

Server-rendered HTML using [templ](https://templ.guide/). Each page is its own package under `internal/ui/` (e.g. `home/`, `packagedetail/`, `compare/`) with a `handler.go` and generated `*_templ.go`. Routes are registered in `internal/ui/routes.go`.

Interactive components use native [Custom Elements](https://developer.mozilla.org/en-US/docs/Web/API/Web_components/Using_custom_elements). Templ renders the custom element tag with embedded JSON data in a `<script type="application/json">` child. The element's `connectedCallback` parses that JSON and lazy-loads its dependency via dynamic `import()`.

Frontend assets (CSS/JS) built with [Vite](https://vite.dev/) from a single entry point (`src/main.ts`). Styling uses [Bootstrap](https://getbootstrap.com/) + SCSS. Vite emits hashed assets to `dist/assets/` and a `dist/manifest.json` that the layout uses to inject the correct `<script>` and `<link>` tags.

Build tags control compile-time behavior: `production` (release binary) and `development` (local dev with no-cache, text logger, in-memory rate limiter) both embed real Vite assets. Without either tag (tests), stub embeds are used.

## CLI Subcommand: Anomaly Detection

`pkgstatsd detect-anomalies [--month YYYYMM]` — detects bot-driven data inflation.

Checks for count correlations, new entity spikes, mirror/arch growth anomalies, and base package outliers. Exit codes: 0 = clean, 1 = minor, 2 = high-confidence.

## CLI Subcommand: Prune Submission Log

`pkgstatsd prune-submission-log` — deletes `submission_log` rows older than the retention window (the current plus two previous calendar months). Pruning is intentionally kept off the request path and is meant to be run periodically by an external scheduler, so retention is enforced on a schedule and its success is independently observable.

## Dev Workflow (`justfile`)

Run `just --list` for available commands. Key ones: `install`, `build`, `run`, `test`, `fixtures`, `lint`.

Key env vars: `DATABASE` (required), `PORT`, `GEOIP_DATABASE`. See `internal/config/config.go` for defaults.

## Patterns to Know

- **Route registration**: every handler implements `RegisterRoutes(*http.ServeMux)`.
- **No framework**: stdlib `net/http` + `http.NewServeMux()` throughout.
- **Generics**: `popularity` package is generic over response types, configured per entity.
- **Month encoding**: `year*100 + month` everywhere. No `time.Time` for data boundaries.
- **Logging**: `log/slog` (structured). JSON in production, text in dev.
