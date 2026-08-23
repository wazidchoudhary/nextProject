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
| **Plugin** | `bhc-commerce-core` — 151 classes across 25 namespaces, PSR-4, DI container, service providers |
| **Theme** | `bhc-theme` — classic PHP theme, SCSS, one ES module, no page builder, no jQuery |
| **Custom tables** | 3, each with a written justification (see [docs/database.md](docs/database.md)) |
| **REST API** | 9 routes under `bhc/v1`, every one with a real permission callback |
| **WP-CLI** | `wp bhc` with 9 subcommands |
| **Background jobs** | 4 Action Scheduler jobs, idempotent, with retry and structured logging |
| **Tests** | 71 unit tests, 62 integration assertions, 4 Playwright suites, zero axe-core violations |
| **Standards** | WPCS + PHPCompatibility, zero violations |

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
| [docs/deployment.md](docs/deployment.md) | Production checklist, and what in this repo is development-only |

---

## Quick start

One command builds the whole store — WordPress, WooCommerce, a database, the
theme and plugin, the built assets and the demo catalogue:

```bash
bin/setup-demo.sh ~/wp-demo
PHP_CLI_SERVER_WORKERS=6 php -S localhost:8088 -t ~/wp-demo ~/wp-demo/router.php
```

Then open <http://localhost:8088> (admin / admin at `/wp-admin`).

The script is idempotent: re-running it skips whatever is already done.

**Requirements:** PHP 8.2+ with `gd`, WP-CLI, Composer, Node 18+, `curl`,
`unzip`, and `git`.

**Database:** set `DB_HOST`, `DB_NAME`, `DB_USER` and `DB_PASSWORD` to use MySQL.
Leave `DB_HOST` unset and the script installs the [SQLite integration
plugin](https://github.com/WordPress/sqlite-database-integration) instead, so the
store runs with no database server at all. See
[docs/deployment.md](docs/deployment.md#running-on-sqlite) for the one
WooCommerce behaviour that differs under SQLite.

## Project structure

```
bone-horn-crafts/
├── bin/
│   ├── setup-demo.sh                  Builds a working store from nothing
│   └── integration-tests.php          Assertion suite, run inside the real store
├── docs/                              The documents listed above
├── tests/e2e/
│   ├── purchase-flow.mjs              Playwright: shop → filter → cart → checkout
│   ├── admin-screens.mjs              Playwright: admin screens, PHP and JS notices
│   ├── web-vitals.mjs                 Playwright: CLS and LCP budgets
│   └── accessibility.mjs              Playwright: axe-core, 12 pages × 2 viewports
├── tools/
│   ├── dev-mu-plugins/                Development-only mu-plugins (never deployed)
│   ├── router.php                     Router for PHP's built-in server
│   └── screenshots.mjs                Page capture helper
├── phpcs.xml.dist                     WPCS ruleset; every exclusion is justified
├── package.json                       SCSS build, linting, Playwright suites
└── wp-content/
    ├── plugins/bhc-commerce-core/
    │   ├── bhc-commerce-core.php      Header, requirement gate, HPOS declaration
    │   ├── src/                       151 classes — see docs/architecture.md
    │   ├── templates/                 Overridable plugin-owned markup
    │   ├── assets/                    Storefront and admin CSS/JS
    │   └── tests/Unit/                PHPUnit suite (no WordPress required)
    └── themes/bhc-theme/
        ├── inc/                       setup, enqueue, performance, security, seo,
        │                              woocommerce, template-tags
        ├── template-parts/            Home, content and product partials
        ├── woocommerce/               WooCommerce template overrides
        └── assets/scss/               abstracts / base / components / layout / pages
```

## Everyday commands

All npm scripts run from `bone-horn-crafts/`.

```bash
# Assets
npm run build              # compile main.css and critical.css
npm run watch              # rebuild main.css on change

# Tests
npm run test:unit          # 71 PHPUnit tests, no WordPress needed
npm run test:e2e           # Playwright purchase flow
npm run test:admin         # Playwright admin screens
npm run test:vitals        # Playwright CLS/LCP budgets
npm run test:a11y          # axe-core over 12 pages × 2 viewports
wp eval-file bin/integration-tests.php   # 62 assertions against the live store

# Standards
npm run lint               # stylelint + eslint + phpcs
npm run lint:php           # phpcs on its own
npm run lint:php:fix       # phpcbf

# Store operations
wp bhc demo seed           # idempotent catalogue seed
wp bhc demo reset --yes    # remove all demo content
wp bhc demo status         # what is currently seeded
wp bhc products sync       # rebuild the merchandising index
wp bhc cache warm|flush|status
wp bhc health-check        # schema, object cache, Action Scheduler, index, PHP
```

## Licence

GPL-2.0-or-later, matching WordPress and WooCommerce.
