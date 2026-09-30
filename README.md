# mindtwo/typo3-monitoring

[![Tests](https://github.com/mindtwo/typo3-monitoring/actions/workflows/tests.yml/badge.svg)](https://github.com/mindtwo/typo3-monitoring/actions/workflows/tests.yml)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](phpstan.neon.dist)
[![TYPO3 12.4–13.4](https://img.shields.io/badge/TYPO3-12.4%20%7C%2013.4-orange)](composer.json)
[![License: MIT](https://img.shields.io/badge/license-MIT-lightgrey)](LICENSE.md)

TYPO3 extension of the mindtwo monitoring suite. On top of
[`mindtwo/base-monitoring`](https://github.com/mindtwo/base-monitoring) — which collects OS,
web server, database, Node.js, system stats, Composer/npm packages, security audits, licenses
and git status — this extension adds:

- **TYPO3 collectors** — the core version (matched against endoflife.date as `typo3`), a raw
  inventory of the active extensions with versions (system vs. third-party), the **live
  database server version** from the default connection (MariaDB-aware), and operational
  state (application context, composer mode, debug flags).
- **Push** — `vendor/bin/typo3 monitoring:push` for cron, or as a TYPO3 scheduler task.
- **Pull** — a signed `GET /api/m2-monitoring` endpoint served by a PSR-15 middleware ahead of
  site routing, with rate limiting, optional IP allow-listing and cached snapshots.
- **Extension configuration** — non-secret settings in the TYPO3 extension configuration;
  the project key and the secret come from the environment.

Composer mode only (TYPO3 12.4 and 13.4, PHP 8.1+).

## Installation

```bash
composer require mindtwo/typo3-monitoring
vendor/bin/typo3 cache:flush
```

The extension is active as soon as Composer installs it. It creates no database tables, and
removal leaves nothing behind. Run `vendor/bin/typo3 extension:setup` in your deploy step
anyway: TYPO3 writes the extension's (empty) configuration defaults into
`config/system/settings.php` on first access, and that belongs in the deploy, not in the first
request or the first scheduler run. If `settings.php` is versioned, commit the resulting
`mindtwo_monitoring` block.

Configure the credentials in the environment (`.env` via phpdotenv, the web server, or the
hosting panel):

```dotenv
MONITORING_PROJECT_KEY=prj_live_8f3a…
MONITORING_SECRET=base64-encoded-shared-secret
```

Priority chain per the suite spec: **extension configuration** (blank counts as unset) →
`MONITORING_*` environment variables → secure defaults. The project key and the secret are
deliberately **not** part of the extension configuration: `config/system/settings.php` is
usually versioned and deployed to every stage, and the configuration form has no password
field. Keep them in the environment.

### Environment variables

| Variable | Extension setting | Default | Purpose |
| --- | --- | --- | --- |
| `MONITORING_ENABLED` | `enabled` | `1` | Master switch for push and pull |
| `MONITORING_PROJECT_KEY` | — | – | Project key from the dashboard |
| `MONITORING_SECRET` | — | – | Shared secret (never transmitted) |
| `MONITORING_ENDPOINT` | `endpoint` | central endpoint | Push target |
| `MONITORING_ENVIRONMENT` | `environment` | derived | Overrides the environment name (see below) |
| `MONITORING_IP_ALLOW_LIST` | `ipAllowList` | – | Comma-separated IPs / CIDR ranges for the pull endpoint |
| `MONITORING_ROUTE_ENABLED` | `routeEnabled` | `1` | Expose the pull endpoint |
| `MONITORING_ROUTE_CACHE` | `cacheSeconds` | `300` | Pull snapshot cache seconds (`0` disables) |
| `MONITORING_RATE_LIMIT` | `rateLimitPerMinute` | `10` | Pull requests per minute per IP |
| `MONITORING_SIGNATURE_TOLERANCE` | `signatureTolerance` | `300` | Signature timestamp window (seconds) |
| `MONITORING_TIMEOUT` | `timeout` | `15` | Push HTTP timeout (seconds) |

The snapshot's `environment` is derived from `TYPO3_CONTEXT`: `Production` → `production`,
`Production/Staging` (or `…/Stage`) → `staging`, `Development/*` → `development`,
`Testing/*` → `testing`. Set `MONITORING_ENVIRONMENT` when your contexts follow another
convention.

### Extension configuration

*Admin Tools → Settings → Extension Configuration → mindtwo_monitoring* offers the non-secret
settings in two tabs (Push, Pull endpoint). Every field is empty by default, and **empty means
inherit** the matching environment variable, then the built-in default. TYPO3 writes template
defaults into `settings.php`, which is why the template ships no values of its own.

## Scheduled push

Pick **one** of the two, never both — otherwise the snapshot is delivered twice.

**System cron:**

```cron
0 3 * * * /usr/bin/php /var/www/example/vendor/bin/typo3 monitoring:push >> /dev/null 2>&1
```

**TYPO3 scheduler:** *System → Scheduler → New task → "Execute console commands" →
`monitoring:push`*, frequency `0 3 * * *`. This requires the site's regular
`vendor/bin/typo3 scheduler:run` cron. The scheduler discards command output and only records
the exit code, so a failed delivery shows up as a failed task run.

Flags: `--dry-run` prints the payload instead of sending it (`--compact` switches it from
pretty to compact JSON). With `MONITORING_ENABLED=0` the command exits `0` without pushing —
the clean per-environment kill switch for a scheduler task that is identical on every stage.

DDEV has no cron: run `ddev exec vendor/bin/typo3 monitoring:push` by hand.

## Local inspection

```bash
vendor/bin/typo3 monitoring:show          # metric/status/details table (--json for the payload)
vendor/bin/typo3 monitoring:collectors    # registered collectors and their support status
```

## The pull endpoint

`GET /api/m2-monitoring` returns the current snapshot as JSON. The middleware is registered
after `typo3/cms-core/normalized-params-attribute` and before `typo3/cms-frontend/site`, so it
answers on every configured host, needs no site configuration and keeps working in maintenance
mode. Guards, in this order: method (405) → route disabled (404) → rate limit (429) → IP
allow-list (403) → configuration guard (503) → HMAC signature with replay window (401) →
cached snapshot (200).

```text
X-Monitoring-Key:       <project key>
X-Monitoring-Timestamp: <unix timestamp>
X-Monitoring-Signature: hex( hmac_sha256( "<timestamp>.<raw request body>", secret ) )
```

The client IP is taken from TYPO3's normalized request, so `SYS/reverseProxyIP` (plus
`reverseProxyHeaderMultiValue`) applies behind a proxy or load balancer. The endpoint path is
matched exactly (a trailing slash is tolerated); installations served from a sub-directory are
not supported.

Throttle counters and the cached snapshot live in the `mindtwo_monitoring` cache (a
`FileBackend` without groups, registered in `ext_localconf.php` with `??=`). A plain
`cache:flush` empties it too, which resets the throttle window and drops the cached snapshot —
harmless, and on deploy even desirable. On multi-node hosting override it in
`config/system/additional.php` with a shared backend such as `RedisBackend`.

## Architecture note

All decision logic is unit-tested against a `Typo3Api` interface — versions, packages, the DB
connection, the application context, configuration, environment and cache access pass through
it, with a guarded native implementation built by the DI container. The TYPO3 glue (commands,
middleware, `ext_localconf.php`) stays thin and is covered by PHPStan level 8 against both
supported TYPO3 majors.

## Development

```bash
composer install
composer check    # pint --test + phpstan (level 8) + pest
```

## Security

If you discover a security issue, please email [info@mindtwo.de](mailto:info@mindtwo.de)
instead of opening a public issue.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
