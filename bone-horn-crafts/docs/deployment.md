# Deployment

This repository is a demonstration build. Nothing here has run a real store, a
real payment or a real customer's data. Treat this document as the checklist you
would work through before it did — not as evidence that it has been.

---

## Development-only code and settings that must not ship

**Demo payment gateways.** A fresh WooCommerce install has every gateway
disabled, so a seeded store fails at the last step of checkout with "Invalid
payment method". `DemoSeeder::seed_payment_gateways()` therefore enables
WooCommerce's two offline gateways — Cash on delivery, retitled "Pay on invoice
(demo)", and Bank transfer, retitled "Bank transfer (demo)". Both descriptions
say plainly that no payment is taken. That is what makes the purchase flow
demonstrable end to end; it is also two gateways that accept an order without
collecting a penny. **Disable both in WooCommerce → Settings → Payments before a
real launch**, or reconfigure them deliberately. The seeder will not overwrite a
gateway that is already enabled, so it cannot silently undo your settings on a
re-run — but it also will not turn them off for you.

### PayPal

The store takes payment through **WooCommerce PayPal Payments** (`ppcp-gateway`),
the official free extension. It works with the classic shortcode checkout this
build uses.

**Credentials live in `wp-config.php`, never in the database.** The gateway
stores its client id and secret in the `woocommerce-ppcp-settings` option by
default, which puts a live secret into every database dump, every staging clone
made from production, and every backup that gets emailed around.
`Payments\PayPalCredentials` reads them from constants instead and injects them
on read; it also strips those keys again on write, so opening the settings
screen and pressing Save cannot quietly persist to the database exactly what
this arrangement exists to keep out of it.

```php
define( 'BHC_PAYPAL_CLIENT_ID',     'your-client-id' );
define( 'BHC_PAYPAL_CLIENT_SECRET', 'your-secret' );
define( 'BHC_PAYPAL_SANDBOX',       false );
```

Define nothing and the plugin behaves normally, storing its own credentials —
the right default for a store connected through PayPal's onboarding button.

**Verify before trusting it:**

```bash
wp bhc payments verify
```

Performs a client-credentials OAuth request. Nothing is charged and nothing is
created; it exchanges the pair for a bearer token, which is what the gateway
does before taking a payment. A typo, a sandbox key in a live store, or a secret
rotated last week all look identical from inside WordPress — this is the only
check that distinguishes them, and it is far better to fail in a terminal on a
Tuesday than at a customer's checkout on a Friday.

`wp bhc health-check` reports which gateways reach checkout, but deliberately
makes no network call: a health screen that phones PayPal on every load hangs
when the network is the thing being diagnosed.

**Demo gateways are guarded, not merely documented.** The seeder skips them
entirely on production and whenever a real gateway is already live, and
`Payments\GatewayGuard` removes them from checkout on production even if they
were enabled some other way. They are matched on the seeded demo title, so bank
transfer configured by hand with real account details is left alone.

**`tools/dev-mu-plugins/bhc-sqlite-dev.php`.** `bin/setup-demo.sh` copies this
into `wp-content/mu-plugins/` **only when `DB_HOST` is unset**, i.e. only on the
SQLite build. It returns `0` from `woocommerce_order_hold_stock_minutes` because
the SQLite layer cannot execute WooCommerce's stock reservation query
(`INSERT … FROM DUAL … ON DUPLICATE KEY UPDATE`), which otherwise blocks
checkout with "Not enough units in stock". It carries an admin notice saying so.
A MySQL build never has this file — but check `wp-content/mu-plugins/` anyway
before you go live, because stock reservation is the protection against
overselling during concurrent checkouts and this file is the switch that turns
it off.

**The demo dataset.** `wp bhc demo reset --yes --orphans` before a real
catalogue is imported. The `Demo\` namespace itself is harmless in production
(its provider only loads under WP-CLI), but the seeded content is not.

**`SCRIPT_DEBUG`, `WP_DEBUG`, `WP_DEBUG_LOG`** are set by the setup script.
Turn all three off, and make sure `WP_DEBUG_DISPLAY` stays false.

**`WP_ENVIRONMENT_TYPE`** is set to `development` by the setup script, and that
is not cosmetic. Outside production, `BrandProfile::canonical_host()` rewrites
absolute SEO URLs onto the configured canonical host, and the theme versions its
assets by `filemtime()` instead of the theme version. Set it to `production` on
the real site.

---

## Pre-deployment checklist

### Environment

- [ ] PHP 8.2+ with `gd`, `intl`, `mbstring`, `curl`, `zip`, `opcache`
- [ ] MySQL 8.0+ or MariaDB 10.6+ — **not** SQLite (see below). The stack
      verified here is MariaDB 10.11 with Redis 7 on PHP 8.4, WordPress 7.0.4,
      WooCommerce 10.9.0
- [ ] `WP_DEBUG=false`, `WP_DEBUG_DISPLAY=false`, `SCRIPT_DEBUG=false`
- [ ] `WP_ENVIRONMENT_TYPE=production`
- [ ] `DISALLOW_FILE_EDIT=true`
- [ ] Fresh salts in `wp-config.php` — signed cookies derive from `wp_salt()`,
      so shipping the development salts would let anyone forge one
- [ ] `WP_MEMORY_LIMIT` at least 256 M
- [ ] OPcache enabled, `opcache.validate_timestamps=0` with a deploy-time reset
- [ ] An image editor that can write WebP (`gd` with WebP support, or Imagick)
      — see below

### Build

**Neither build step is required to run the store.** The plugin declares no
production dependencies — `composer.json` requires only `php: >=8.2`, and
everything under `require-dev` is tooling — and `bhc-commerce-core.php` falls
back to `Support\Autoloader` when `vendor/autoload.php` is absent. The compiled
`main.css` and `critical.css` are committed. A deployment that copies the plugin
and theme directories and activates them is a working store: verified by hiding
`vendor/` entirely and running the full purchase flow, which completed a real
order with all thirteen checks green.

Run them anyway when you have the tooling — the Composer autoloader is faster
than the fallback, and rebuilding the CSS guarantees it matches the SCSS in the
deployed commit — but neither is a blocker on a host without Composer or Node.

- [ ] `composer install --no-dev --optimize-autoloader` in the plugin directory
      *(optional — generates a classmap autoloader; the plugin runs without it)*
- [ ] `npm ci && npm run build` *(optional — `main.css` and `critical.css` are
      committed; rebuild only to guarantee they match the deployed SCSS)*.
      `critical.css` is built but not loaded by default; see the note on
      `bhc_async_main_stylesheet` below
- [ ] `npm run lint` clean (stylelint, eslint, phpcs)
- [ ] `npm run test:unit` green
- [ ] `wp eval-file bin/integration-tests.php` green against a staging copy
- [ ] `npm run test:e2e`, `npm run test:admin`, `npm run test:vitals`,
      `npm run test:a11y` green against staging — those are the four browser
      suites, and it is easy to run three of them and think you are done

### Database

- [ ] Activate the plugin so `Database\Installer` runs `dbDelta()`
- [ ] `wp bhc health-check --strict` exits 0
- [ ] Confirm the three custom tables exist with their indexes —
      `bhc_wishlist`, `bhc_product_affinity`, `bhc_product_stats`
- [ ] **Do not** enable "Delete data on uninstall" unless you mean it

### Caching

- [ ] Persistent object cache (Redis or Memcached) installed and confirmed by
      `wp bhc health-check`, which names Redis when Redis is what is serving.
      This is the single biggest remaining win: measured warm on the same
      catalogue, the home page renders in 6 queries on MySQL + Redis against 131
      on SQLite with no object cache, the shop page 5 against 83, a product page
      5 against 116. Those two columns differ in database *and* cache, so the
      split between the two was not measured separately — but the end-to-end gap
      is not marginal, and the plugin uses an object cache automatically when
      one is present. Full table in [performance.md](performance.md)
- [ ] Page cache in front, with cart, checkout, my-account and the wishlist
      endpoint excluded
- [ ] `wp bhc cache warm` after deploy
- [ ] `object-cache.php` drop-in present and matching the Redis version.
      `bin/setup-demo.sh` installs and enables the drop-in itself when
      `REDIS_HOST` is set and the PHP `redis` extension is loaded; it skips the
      step and says so when the extension is missing

The cost of the object cache is an extra service to run, monitor and secure, and
a second place where stale data can hide. The store works without one — it falls
back to transients — and the health screen says which path is live.

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

- [ ] `canonical_host` set to the production host, before launch. It is
      validated as a host, not as free text: input that does not parse keeps the
      previous value. Outside production it is what absolute SEO URLs are
      rewritten onto, which is how a staging copy avoids advertising itself
- [ ] Real Organization details in the settings (e-mail, phone, social handle,
      manufacturing entity). `organization_email` is validated by meaning too
- [ ] Submit `/sitemap.xml`
- [ ] Confirm `noindex` on cart, checkout, my-account, the wishlist page and
      filtered views in the **production** response, not on staging
- [ ] Validate the JSON-LD graph with the Rich Results test
- [ ] `robots.txt` does not block anything indexable

### Commerce

- [ ] Demo gateways off (see above) — payment gateways configured and tested
      with a real transaction
- [ ] Tax configuration reviewed by somebody qualified — the plugin stores HSN
      codes and GST rates but **makes no compliance claim**
- [ ] Shipping zones and rates set for the real markets
- [ ] Transactional e-mail deliverability verified (SPF, DKIM, DMARC)
- [ ] Order confirmation, invoice and packing slip output reviewed end to end

### Monitoring

- [ ] Uptime and TLS-expiry monitoring
- [ ] PHP error log shipped somewhere a person will see it
- [ ] `wp bhc health-check --strict` in the deploy pipeline, failing the deploy.
      Without `--strict` a warning is reported as a warning and the command
      still exits 0
- [ ] Core Web Vitals from field data, not just the lab suite here
- [ ] Alert on Action Scheduler failures

---

## Images and WebP

The theme maps JPEG sub-sizes to WebP through an `image_editor_output_format`
filter (`bhc_webp_subsizes()` in
`wp-content/themes/bhc-theme/inc/performance.php`). Only the derivatives change
format: the original upload is kept as uploaded, so an editor downloading the
full-size file gets the JPEG they put in, and PNG is left alone.

Measured on the 600×600 card size: 16,599 bytes as JPEG against 6,278 as WebP,
62% smaller. A 12-card shop page pulls roughly 83KB of imagery in total.

Two things to check on the server:

* The image editor must be able to write WebP. If it cannot, WordPress ignores
  the filter and keeps producing JPEGs — nothing breaks, the pages just get
  heavier, and there is nothing to feature-detect.
* Disk goes up, not down. The original and its WebP sub-sizes both live in
  `uploads/`, so back-ups grow.

Sub-sizes are generated at upload time, so a catalogue imported before the theme
was active needs `wp media regenerate` to pick this up.

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

MySQL or MariaDB is the normal path, and the primary demo is built on one:
`bin/setup-demo.sh` uses the SQLite integration plugin only when `DB_HOST` is
unset. SQLite exists here so the whole store can be installed without a database
server. **It is a development convenience, not a deployment option.**

One WooCommerce behaviour genuinely differs: stock reservation during checkout
uses SQL that the SQLite translation layer cannot execute, so checkout fails with
"Not enough units in stock" until hold-stock is disabled. The setup script
installs the dev-only mu-plugin above to do exactly that, through the supported
`woocommerce_order_hold_stock_minutes` filter. The cost is real — that build
cannot protect against overselling under concurrent checkouts, which is why the
file is not installed and must not be installed on MySQL.

The plugin, the theme and the tests themselves are database-agnostic and run
unchanged on either. The *measurements* are not interchangeable: the SQLite
build is also the build with no persistent object cache, and the query-count
table in [performance.md](performance.md) shows how far apart the two ends up.

## Asynchronous main stylesheet

`main.css` is 45,000 bytes raw, 7,920 gzipped, and is loaded normally — render
blocking. The inline-critical-CSS plus async-swap pattern was built, measured and
reverted: at this stylesheet size it bought nothing measurable on LCP and made
CLS a race, with the same page scoring 0.0000 on one run and 0.2234 on the next.

Both halves are still in the theme behind the `bhc_async_main_stylesheet`
filter, default `false`. A deployment whose stylesheet has grown well past this
one can turn it on — and should then re-run `npm run test:vitals`, which fails
above 0.1 CLS or 2.5s LCP, on the pages it actually serves.

## Classic cart and checkout

The demo store uses the classic `[woocommerce_cart]` and `[woocommerce_checkout]`
shortcodes rather than the block cart and checkout. That is a deliberate,
documented trade-off: the plugin's export notice, checkout field customisation
and server-side address validation hook into the classic templates.

Block compatibility **is** declared, and porting those three features to the
Store API and block checkout is the natural next step. Until then, a deployment
that wants block checkout should expect to reimplement them as block checkout
integrations.

## Container scaffolding

`deploy/docker-compose.yml`, `deploy/nginx.conf` and `deploy/.env.example`
describe a MySQL + Redis + PHP-FPM stack behind nginx. They were written but
**never executed** — there was no Docker daemon in the environment they were
authored in — so treat them as a starting point to debug, not a tested recipe.
Host requirements and the choices behind them are in [hosting.md](hosting.md).

## Rollback

Deployments are reversible if you keep them so:

* Tag every release; deploy tags, not branches.
* `dbDelta()` is additive, so a rollback of code does not need a rollback of
  schema — but take a database snapshot before any deploy that changes
  `Installer::DB_VERSION` (currently `3`).
* The three custom tables survive deactivation. Only `uninstall.php` drops them,
  and only when the setting says so: it reads `bhc_settings` and returns
  immediately unless `delete_data_on_uninstall` is set, which is `false` by
  default. The switch is "Delete data on uninstall" on the plugin's Settings
  screen (Bone Horn Crafts → Settings). When it is on, deleting the plugin drops
  the wishlist, affinity and stats tables and removes the plugin's options.
  Deactivation, a failed update and a host migration all leave the data alone.
* Cache group versions are integers in an option; flushing after a rollback is
  `wp bhc cache flush`.
