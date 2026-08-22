# Deployment

This repository is a demonstration build. Nothing here has run a real store, a
real payment or a real customer's data. Treat this document as the checklist you
would work through before it did — not as evidence that it has been.

---

## Development-only code that must not ship

**`tools/dev-mu-plugins/bhc-sqlite-dev.php`.** Installed by `bin/setup-demo.sh`
only when the store runs on SQLite. It returns `0` from
`woocommerce_order_hold_stock_minutes` because the SQLite layer cannot execute
WooCommerce's stock reservation query (`INSERT … FROM DUAL … ON DUPLICATE KEY
UPDATE`), which otherwise blocks checkout with "Not enough units in stock". It
carries an admin notice saying so. **Delete it on any MySQL deployment** —
leaving it in disables stock holds during checkout, which is exactly what you
want under load and exactly what this file switches off.

**The demo dataset.** `wp bhc demo reset --yes --orphans` before a real
catalogue is imported. The `Demo\` namespace itself is harmless in production
(its provider only loads under WP-CLI), but the seeded content is not.

**`SCRIPT_DEBUG`, `WP_DEBUG`, `WP_DEBUG_LOG`** are set by the setup script.
Turn all three off, and make sure `WP_DEBUG_DISPLAY` stays false.

---

## Pre-deployment checklist

### Environment

- [ ] PHP 8.2+ with `gd`, `intl`, `mbstring`, `curl`, `zip`, `opcache`
- [ ] MySQL 8.0+ or MariaDB 10.6+ — **not** SQLite (see below)
- [ ] `WP_DEBUG=false`, `WP_DEBUG_DISPLAY=false`, `SCRIPT_DEBUG=false`
- [ ] `DISALLOW_FILE_EDIT=true`
- [ ] Fresh salts in `wp-config.php` — signed cookies derive from `wp_salt()`,
      so shipping the development salts would let anyone forge one
- [ ] `WP_MEMORY_LIMIT` at least 256 M
- [ ] OPcache enabled, `opcache.validate_timestamps=0` with a deploy-time reset

### Build

- [ ] `composer install --no-dev --optimize-autoloader` in the plugin directory
- [ ] `npm ci && npm run build` — `main.css` and `critical.css` are committed,
      but rebuild so they match the SCSS in the deployed commit
- [ ] `npm run lint` clean
- [ ] `npm run test:unit` green
- [ ] `wp eval-file bin/integration-tests.php` green against a staging copy
- [ ] `npm run test:e2e`, `npm run test:admin`, `npm run test:vitals` green
      against staging

### Database

- [ ] Activate the plugin so `Database\Installer` runs `dbDelta()`
- [ ] `wp bhc health-check --strict` exits 0
- [ ] Confirm the three custom tables exist with their indexes
- [ ] **Do not** enable "Delete data on uninstall" unless you mean it

### Caching

- [ ] Persistent object cache (Redis or Memcached) installed and confirmed by
      `wp bhc health-check`. This is the single biggest remaining win: it removes
      roughly 30 queries per page — the WooCommerce CRUD meta reads and the
      transient option reads — and the plugin uses it automatically when present
- [ ] Page cache in front, with cart, checkout, my-account and the wishlist
      endpoint excluded
- [ ] `wp bhc cache warm` after deploy
- [ ] `object-cache.php` drop-in present and matching the Redis version

### Background work

- [ ] WordPress pseudo-cron disabled (`DISABLE_WP_CRON=true`) and a real cron
      entry calling `wp cron event run --due-now` every minute
- [ ] Action Scheduler present and processing — check the admin screen after the
      first deploy, not a week later
- [ ] `wp bhc products sync` once after the real catalogue is imported

### Security

- [ ] TLS everywhere, HSTS at the edge, `FORCE_SSL_ADMIN=true`
- [ ] Admin accounts on 2FA
- [ ] File permissions: 644 files, 755 directories, `wp-config.php` 600
- [ ] `xmlrpc.php` blocked at the edge as well as by the theme filter
- [ ] Consider a **Content-Security-Policy** — see below
- [ ] Rate limiting at the edge as well as in the application
- [ ] A backup that has actually been restored at least once

### SEO

- [ ] `canonical_host` set to the production host, before launch. A staging
      deployment that emits canonicals pointing at itself is how staging sites
      end up in an index
- [ ] Real Organization details in the settings (e-mail, phone, social handle,
      manufacturing entity)
- [ ] Submit `/sitemap.xml`
- [ ] Confirm `noindex` on cart, checkout, my-account and filtered views in the
      **production** response, not on staging
- [ ] Validate the JSON-LD graph with the Rich Results test
- [ ] `robots.txt` does not block anything indexable

### Commerce

- [ ] Payment gateways configured and tested with a real transaction
- [ ] Tax configuration reviewed by somebody qualified — the plugin stores HSN
      codes and GST rates but **makes no compliance claim**
- [ ] Shipping zones and rates set for the real markets
- [ ] Transactional e-mail deliverability verified (SPF, DKIM, DMARC)
- [ ] Order confirmation, invoice and packing slip output reviewed end to end

### Monitoring

- [ ] Uptime and TLS-expiry monitoring
- [ ] PHP error log shipped somewhere a person will see it
- [ ] `wp bhc health-check --strict` in the deploy pipeline, failing the deploy
- [ ] Core Web Vitals from field data, not just the lab suite here
- [ ] Alert on Action Scheduler failures

---

## Content-Security-Policy

`Security\Headers` deliberately emits **no CSP by default**. Payment gateways
inject their own scripts and iframes, and a wrong CSP breaks checkout silently —
a store loses orders before anybody notices.

To add one safely:

1. Deploy `Content-Security-Policy-Report-Only` first, with a report endpoint.
2. Complete real transactions through every configured gateway.
3. Collect violations for at least a full business cycle.
4. Only then switch to the enforcing header, through `bhc_security_headers`:

```php
add_filter( 'bhc_security_headers', function ( array $headers ): array {
    $headers['Content-Security-Policy'] = "default-src 'self'; …";

    return $headers;
} );
```

---

## Running on SQLite

The demo runs on the [SQLite integration
plugin](https://github.com/WordPress/sqlite-database-integration) so it needs no
database server. **This is a development convenience, not a deployment option.**

One WooCommerce behaviour genuinely differs: stock reservation during checkout
uses SQL that the SQLite translation layer cannot execute, so checkout fails with
"Not enough units in stock" until hold-stock is disabled. `bin/setup-demo.sh`
installs a dev-only mu-plugin that does exactly that, through the supported
`woocommerce_order_hold_stock_minutes` filter. On MySQL, delete it and let
WooCommerce hold stock normally.

Everything else in this repository — the plugin, the theme, the tests, the
measurements — is database-agnostic and runs unchanged on MySQL.

## Classic cart and checkout

The demo store uses the classic `[woocommerce_cart]` and `[woocommerce_checkout]`
shortcodes rather than the block cart and checkout. That is a deliberate,
documented trade-off: the plugin's export notice, checkout field customisation
and server-side address validation hook into the classic templates.

Block compatibility **is** declared, and porting those three features to the
Store API and block checkout is the natural next step. Until then, a deployment
that wants block checkout should expect to reimplement them as block checkout
integrations.

## Rollback

Deployments are reversible if you keep them so:

* Tag every release; deploy tags, not branches.
* `dbDelta()` is additive, so a rollback of code does not need a rollback of
  schema — but take a database snapshot before any deploy that changes
  `Installer::DB_VERSION`.
* The three custom tables survive deactivation. Only `uninstall.php` drops them,
  and only when the setting says so.
* Cache group versions are integers in an option; flushing after a rollback is
  `wp bhc cache flush`.
