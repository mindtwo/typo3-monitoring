# Changelog

All notable changes to `mindtwo/typo3-monitoring` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
