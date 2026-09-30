# Changelog

All notable changes to `mindtwo/typo3-monitoring` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.1 - 2026-09-30

### Fixed

- The extension configuration is read once per process: `ExtensionConfiguration::get()`
  synchronises every extension template into `settings.php` while the extension is not yet
  configured, and that ran once per setting lookup.
- CI now tests released TYPO3 12.4 tags instead of silently resolving to `12.4.x-dev`: every
  12.4 release carries Packagist security advisories, which Composer blocks by default.

### Changed

- README: recommend `extension:setup` in the deploy step, document the tolerated trailing slash
  and the effect of `cache:flush` on the throttle window; clearer `monitoring:push` message when
  monitoring is disabled.
- Added a unit test for the PSR-15 middleware (pass-through, 401/405 JSON, client IP source).

## 1.0.0 - 2026-09-30

Initial release.

### Added

- TYPO3 extension (TYPO3 12.4 / 13.4, composer mode): `typo3` core version collector matched
  against endoflife.date, `typo3_extensions` inventory (system vs. third-party, normalized
  composer versions), `typo3_environment` (application context, derived environment name,
  composer mode, debug flags) and a live-connection `database` collector replacing the base
  CLI detection.
- `monitoring:push` console command (`--dry-run`, `--compact`), registered as schedulable for
  the TYPO3 scheduler; `monitoring:show` and `monitoring:collectors` for local inspection.
- Signed `GET /api/m2-monitoring` pull endpoint as a PSR-15 frontend middleware ahead of site
  resolution, with throttling and snapshot caching on the TYPO3 caching framework.
- Extension configuration for the non-secret settings with the suite's precedence
  (extension configuration → `MONITORING_*` environment → defaults); credentials are
  environment-only.
- Fully testable `Typo3Api` adapter; HMAC-SHA256 request authentication with replay
  protection, shared with the whole suite.
