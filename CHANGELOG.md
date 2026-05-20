# Changelog — ETechFlow Admin Reindex

All notable changes to this module. Adheres to [Semantic Versioning](https://semver.org/).

---

## [1.0.0] — 2026-05-19

### Initial commercial release

Run Magento indexers from the admin panel instead of the command line. Reduces support load for teams without SSH access to the production server.

#### Added

- **"Reindex" mass-action option** on **System → Tools → Index Management** — tick rows, click Reindex, confirm. Per-indexer success/failure messages with runtime in seconds.
- **"Reindex All" button** in the page action toolbar — one click rebuilds every indexer in sequence.
- **Per-installation HMAC license** with bundle-key support. Same licensing pattern as every other eTechFlow module: per-domain key OR bundle key activates the module on the production host. Dev/staging environments (`.test`, `.local`, `*.magento.cloud`, RFC 1918 IPs, etc.) auto-detect and bypass licensing.
- **ACL resource** `ETechFlow_AdminReindex::run` — required to see the new buttons and post to the run endpoint. Limited admin users without it see the stock Index Management page unchanged.
- **Profiler instrumentation** — wraps the mass-reindex execute path in an `ETechFlow_AR_MassReindex` Tideways span for production tracing. No-op when Tideways isn't installed.
- **Verify CLI** — `bin/magento etechflow:ar:verify` confirms classes resolve via DI, license validator evaluates, IndexerRegistry returns real indexers. Use as a smoke test in deploy pipelines.
- **Hyvä-safe** — admin-only module with zero frontend assets. Hyvä themes only re-skin the storefront; this module never touches it.

#### Compatibility

- Magento Open Source 2.4.4 – 2.4.8
- Adobe Commerce 2.4.4 – 2.4.8
- PHP 8.1 / 8.2 / 8.3 / 8.4
- All Hyvä child themes
