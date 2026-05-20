# ETechFlow_AdminReindex

Run Magento indexers from the admin browser instead of CLI. Adds a **"Reindex"** mass-action option and a **"Reindex All"** button to **System → Tools → Index Management** — for teams without SSH/CLI access to the production server.

Commercial eTechFlow module. Per-domain HMAC license or eTechFlow bundle key activates the module on your production host. Dev / staging / `*.magento.cloud` / `localhost` etc. auto-detect and bypass licensing — no key needed for local work.

## What it adds to the admin

1. **"Reindex"** option in the existing mass-action dropdown on the Index Management grid — tick rows, click Submit, confirm. Per-indexer green/red messages with runtime in seconds.
2. **"Reindex All"** button in the page action toolbar — rebuilds every indexer in sequence.

Limited admin users without the `ETechFlow_AdminReindex::run` ACL resource see the stock Index Management page unchanged.

## Features

| | |
|---|---|
| Reindex selected indexers from the admin grid | ✓ |
| Reindex every indexer with one button click | ✓ |
| Per-indexer success/failure messages with runtime in seconds | ✓ |
| Form-key + ACL protected (no CSRF, granular role permissions) | ✓ |
| Per-domain HMAC licensing + bundle key support | ✓ |
| Tideways span instrumentation (`ETechFlow_AR_MassReindex`) | ✓ |
| Verify CLI (`etechflow:ar:verify`) | ✓ |
| No SSH / no CLI / no cron knowledge needed | ✓ |
| Works on any Magento install — no DB changes, no frontend assets | ✓ |

## Compatibility

| Platform | Status |
|---|---|
| Magento Open Source 2.4.4 – 2.4.8 | ✓ |
| Adobe Commerce 2.4.4 – 2.4.8 | ✓ |
| Hyvä-themed storefronts | ✓ (admin-only module — Hyvä doesn't touch admin) |
| PHP 8.1 / 8.2 / 8.3 / 8.4 | ✓ |

## Installation

```bash
# Option A — Composer
composer require etechflow/module-admin-reindex:^1.0
bin/magento module:enable ETechFlow_AdminReindex
bin/magento setup:upgrade
bin/magento setup:di:compile      # production mode only
bin/magento cache:flush

# Option B — Manual drop-in
cp -r ETechFlow/AdminReindex app/code/ETechFlow/AdminReindex
bin/magento module:enable ETechFlow_AdminReindex
bin/magento setup:upgrade
bin/magento setup:di:compile      # production mode only
bin/magento cache:flush
```

No database tables, no config rows beyond admin settings.

## Licensing

**Admin → Stores → Configuration → eTechFlow → Admin Reindex → License**

| Field | Default | What it does |
|---|---|---|
| **Production Environment** | Yes | Yes = check the license key. No = run at full features without a key (use on dev/staging on non-standard domains). |
| **License Key** | (empty) | Paste the per-domain key from your purchase email. |

If you bought the eTechFlow bundle, enter the bundle key under any module's *License* section — it activates all eTechFlow modules at once.

Dev / staging hosts (`.test`, `.local`, `localhost`, RFC 1918 IPs, `*.magento.cloud`, `*.ngrok.io`, common `staging.`/`dev.`/`qa.` prefixes) auto-detect and bypass licensing — engineers don't need a real key locally.

## Permissions

Two new ACL resources appear under **Stores → Permissions → User Roles → Role Resources**:

- `ETechFlow_AdminReindex::run` — required to see the *Reindex* mass action and *Reindex All* button, and to POST to the run endpoint.
- `ETechFlow_AdminReindex::config` — required to see the admin config section.

Both are granted to the *Administrators* role by default. Assign granularly to limited roles as needed.

## Smoke test

After installing, confirm the module is healthy:

```bash
bin/magento etechflow:ar:verify
```

Should print `✅ ALL CHECKS PASSED. v1.0.0 verified.`

## Uninstall

```bash
bin/magento module:disable ETechFlow_AdminReindex
bin/magento cache:flush

# Composer:
composer remove etechflow/module-admin-reindex
# Manual:
rm -rf app/code/ETechFlow/AdminReindex
bin/magento setup:upgrade
bin/magento cache:flush
```

Nothing to clean from the database — the module doesn't add tables.

## License

Proprietary — see `LICENSE.txt`. Commercial licenses available at <https://etechflow.com>.
