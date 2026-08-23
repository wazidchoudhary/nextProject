# Bone Horn Crafts — WooCommerce reference implementation

A complete, running WooCommerce storefront for **Bone Horn Crafts**, a fictional
exporter of handcrafted bone, horn and wood materials for knifemakers, luthiers
and pen turners.

> **This is a demonstration and reference build.** Bone Horn Crafts is not a real
> company. Every product, customer, review, order and article in it is invented
> for this repository, and all product photography is generated procedurally by
> the seeder — no third-party brands, imagery, reviews or trading data appear
> anywhere in it. The engineering is real and production-shaped; the business is
> not.

The point of the repository is to show what a senior WordPress/WooCommerce build
looks like end to end: a namespaced OOP plugin that owns the commerce logic, a
thin classic theme that owns only presentation, measured performance work,
structured data, a security posture you can audit in one place, and tests at
three levels.

---

## What is in the box

| | |
|---|---|
| **Plugin** | `bhc-commerce-core` — 152 files in `src/`: 137 final classes, 5 abstract classes and 10 interfaces across 31 namespaces. PSR-4, DI container, service providers |
| **Theme** | `bhc-theme` — classic PHP theme, SCSS, one ES module, no page builder |
| **Custom tables** | 3, each with a written justification (see [docs/database.md](docs/database.md)) |
| **REST API** | 9 endpoints across 8 registered routes under `bhc/v1`, every one with a real permission callback |
| **WP-CLI** | `wp bhc` with 8 subcommands |
| **Background jobs** | 4 Action Scheduler jobs, idempotent, with retry and structured logging |
| **Extension points** | 27 filters and 5 actions |
| **Tests** | 73 PHPUnit tests / 136 assertions, 62 integration assertions, 4 Playwright suites |
| **Accessibility** | axe-core over 24 renders (12 pages × 2 viewports): zero violations at any impact level |
| **Standards** | WPCS + PHPCompatibility, stylelint and eslint — `npm run lint` clean |
| **Demo catalogue** | 60 products, 22 variations, 186 attachments, 20 terms, 24 orders, 8 customers, 18 pages, 6 journal articles, 2 menus, 148 reviews, 4 shipping zones |

One thing the theme does **not** claim: it is not jQuery-free. Home, shop,
category and blog pages load no jQuery — `inc/performance.php` dequeues
WooCommerce's bundle there. Product pages do load it, because WooCommerce's
variation form and review star widget are built on it, and cart, checkout and
account keep the whole bundle because they genuinely use it. Deferring jQuery on
product pages was tried and reverted: WooCommerce prints inline `jQuery(...)`
calls in the body that then throw.

## Documentation

| Document | Covers |
|---|---|
| [docs/architecture.md](docs/architecture.md) | Plugin and theme architecture, the container, service providers, the OOP inventory |
| [docs/local-development.md](docs/local-development.md) | Day-to-day loop, debugging, conventions, how to add a service/route/job |
| [docs/database.md](docs/database.md) | Custom tables, why each exists, indexes, and what deliberately stays in core tables |
| [docs/performance.md](docs/performance.md) | Caching layers, the N+1 work, measured query counts, asset budget, Core Web Vitals |
| [docs/seo.md](docs/seo.md) | Structured data, canonicals, robots policy, sitemaps |
| [docs/security.md](docs/security.md) | Capabilities, nonces, sanitisation, escaping, REST guards, rate limiting, cookies |
| [docs/testing.md](docs/testing.md) | The three test layers, what each is for, how to run them |
| [docs/demo-data.md](docs/demo-data.md) | The seeder, the generated imagery, and how to reset |
| [docs/woocommerce-features.md](docs/woocommerce-features.md) | Every custom commerce feature and where it lives |
| [docs/hosting.md](docs/hosting.md) | Choosing a host, provisioning a VPS, moving this demo onto a real server |
| [docs/deployment.md](docs/deployment.md) | Production checklist, and what in this repo is development-only |

---

## Quick start

One command builds the whole store — WordPress, WooCommerce, a database, the
theme and plugin, the built assets and the demo catalogue:

```bash
DB_HOST=127.0.0.1 DB_NAME=bhc_demo DB_USER=root DB_PASSWORD=secret \
REDIS_HOST=127.0.0.1 \
bin/setup-demo.sh ~/wp-demo

PHP_CLI_SERVER_WORKERS=6 php -S localhost:8088 -t ~/wp-demo ~/wp-demo/router.php
```

Then open <http://localhost:8088> (admin / admin at `/wp-admin`). The script
prints a `wp bhc health-check` command at the end; run it to confirm the schema,
object cache, Action Scheduler and merchandising index are all in the state it
expects.

The script is idempotent: re-running it skips whatever is already done. It also
sets `WP_ENVIRONMENT_TYPE=development` in the install.

`PHP_CLI_SERVER_WORKERS=6` is not decoration. A single-process `php -S` serialises
requests, which queues the browser suites behind themselves until they time out
and look like real failures.

**Requirements:** PHP 8.2+ with `gd`, WP-CLI, Composer, Node 18+, `curl` and
`unzip`. `git` is needed for the SQLite path and for the WordPress mirror
fallback when wordpress.org is unreachable.

### The database

**MySQL/MariaDB is the primary path.** Set `DB_HOST`, `DB_NAME`, `DB_USER` and
`DB_PASSWORD`. The stack the demo was last built and measured on is MariaDB
10.11 + Redis 7 + PHP 8.4, with WordPress 7.0.4 and WooCommerce 10.9.0 (override
with `WP_VERSION` and `WC_VERSION`).

Setting `REDIS_HOST` installs and enables the Redis object-cache drop-in. It is
optional, and it is also the single largest difference in this build. Measured
warm query counts over the same catalogue:

| Page | SQLite, no object cache | MySQL + Redis |
|---|---|---|
| Home | 131 | 6 |
| Shop | 83 | 5 |
| Product | 116 | 5 |
| Category | 90 | 5 |
| Cart | 76 | 5 |
| Blog | 66 | 5 |

**SQLite is the optional, no-database-server path.** Leave `DB_HOST` unset and
the script installs the [SQLite integration
plugin](https://github.com/WordPress/sqlite-database-integration) instead, so the
store runs with nothing else installed. What that costs: the SQLite translation
layer cannot execute WooCommerce's stock-reservation statement, so the setup
script also installs `tools/dev-mu-plugins/bhc-sqlite-dev.php`, which turns stock
reservation off. That is a real protection against overselling during concurrent
checkouts, which is why the mu-plugin is never installed on a MySQL build and
must never be deployed. See
[docs/deployment.md](docs/deployment.md#running-on-sqlite) for the detail.

## Project structure

```
bone-horn-crafts/
├── bin/
│   ├── setup-demo.sh                  Builds a working store from nothing
│   └── integration-tests.php          Assertion suite, run inside the real store
├── deploy/
│   ├── docker-compose.yml             WordPress + MariaDB + Redis + nginx
│   ├── nginx.conf                     Server block, caching and static rules
│   └── .env.example                   Copy to deploy/.env and fill in
├── docs/                              The documents listed above
├── tests/e2e/
│   ├── browser.mjs                    Shared Chromium launcher and base URL
│   ├── purchase-flow.mjs              Playwright: shop → filter → cart → checkout, 13 checks
│   ├── admin-screens.mjs              Playwright: admin screens, PHP and JS notices
│   ├── web-vitals.mjs                 Playwright: CLS and LCP budgets, 3 pages × 2 viewports
│   └── accessibility.mjs              Playwright: axe-core, 12 pages × 2 viewports
├── tools/
│   ├── dev-mu-plugins/                Development-only mu-plugins (never deployed)
│   ├── router.php                     Router for PHP's built-in server
│   └── screenshots.mjs                Page capture helper
├── eslint.config.mjs                  Flat eslint config for theme, plugin and tooling JS
├── .stylelintrc.json                  SCSS rules
├── phpcs.xml.dist                     WPCS ruleset; every exclusion is justified
├── package.json                       SCSS build, linting, i18n, Playwright suites
└── wp-content/
    ├── plugins/bhc-commerce-core/
    │   ├── bhc-commerce-core.php      Header, requirement gate, HPOS declaration
    │   ├── uninstall.php              Drops custom tables, off by default
    │   ├── src/                       152 files — see docs/architecture.md
    │   ├── templates/                 Overridable plugin-owned markup
    │   ├── assets/                    Storefront and admin CSS/JS
    │   ├── languages/                 bhc-commerce-core.pot
    │   └── tests/Unit/                PHPUnit suite (no WordPress required)
    └── themes/bhc-theme/
        ├── inc/                       setup, enqueue, performance, security, seo,
        │                              woocommerce, template-tags
        ├── template-parts/            Home, content and product partials
        ├── woocommerce/               WooCommerce template overrides
        ├── languages/                 bhc-theme.pot
        └── assets/scss/               abstracts / base / components / layout / pages
```

The `deploy/` directory was written against this build but **has not been
executed** — there is no Docker daemon in the environment it was authored in.
Treat it as a starting point to verify, not a tested artefact.
[docs/hosting.md](docs/hosting.md) covers the paths that were reasoned through in
more detail.

## Everyday commands

All npm scripts run from `bone-horn-crafts/`.

```bash
# Assets
npm run build              # compile main.css and critical.css
npm run watch              # rebuild main.css on change

# Tests
npm run test:unit          # 73 PHPUnit tests / 136 assertions, no WordPress needed
npm run test:e2e           # Playwright purchase flow, 13 checks
npm run test:admin         # Playwright admin screens
npm run test:vitals        # Playwright CLS/LCP budgets (fails above 0.1 CLS or 2.5s LCP)
npm run test:a11y          # axe-core over 12 pages × 2 viewports
wp eval-file bin/integration-tests.php   # 62 assertions against the live store

# Standards
npm run lint               # stylelint + eslint + phpcs
npm run lint:css           # stylelint on its own    (:fix to autofix)
npm run lint:js            # eslint on its own       (:fix to autofix)
npm run lint:php           # phpcs on its own
npm run lint:php:fix       # phpcbf

# Translations
npm run i18n               # regenerate bhc-commerce-core.pot and bhc-theme.pot

# Store operations
wp bhc demo seed           # idempotent catalogue seed
wp bhc demo reset --yes    # remove all demo content
wp bhc demo status         # what is currently seeded (products and variations separately)
wp bhc products sync       # rebuild the merchandising index
wp bhc cache warm|flush|status
wp bhc health-check        # schema, object cache, Action Scheduler, index, versions, environment
```

The four browser suites need a running store; point them somewhere else with
`BHC_BASE_URL`. They default to <http://localhost:8088>.

## Licence

GPL-2.0-or-later, matching WordPress and WooCommerce.
