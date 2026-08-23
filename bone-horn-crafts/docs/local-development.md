# Local development

## Getting a store

**Prerequisites:** PHP 8.2+ with `gd`, WP-CLI, Composer, Node 18+, `curl`,
`unzip`, `git`. Add the PHP `redis` extension for the object cache — without it
the script skips the Redis step with a message rather than failing.

**Start the services first.** MySQL (or MariaDB) and Redis have to be running
before the script does anything, and the error you get otherwise reads like bad
credentials rather than a stopped server:

```bash
sudo service mariadb start     # or: mysql, mysqld
sudo service redis-server start
```

Then, in one command:

```bash
DB_HOST=127.0.0.1 DB_NAME=bhc_demo DB_USER=root DB_PASSWORD=secret \
REDIS_HOST=127.0.0.1 \
bin/setup-demo.sh ~/wp-demo

PHP_CLI_SERVER_WORKERS=6 php -S localhost:8088 -t ~/wp-demo ~/wp-demo/router.php
```

<http://localhost:8088>, admin / admin at `/wp-admin`.

The script creates the database itself if the user you give it has `CREATE`
rights. If it does not, the script says so and prints the `CREATE DATABASE` and
`GRANT` to run — it does not fail two steps later with a connection error.

Expect roughly **four to six minutes**, most of it seeding: 60 products with
generated imagery, 8 customers, 24 orders and 148 reviews. It is idempotent —
re-run it any time and it skips what is already done.

That is the documented default: MySQL or MariaDB for the database, Redis for the
object cache. It is what the reference build was measured on — MariaDB 10.11,
Redis 7, PHP 8.4, WordPress 7.0.4, WooCommerce 10.9.0. The difference the object
cache makes is not a rounding error: the home page issues 131 queries warm on
SQLite with no object cache and 6 on MySQL + Redis, the shop page 83 against 5.
Every other hosting decision in this repository matters less than that one.
[performance.md](performance.md) has the full table.

PHP's built-in server runs a single process by default, so a browser suite that
opens several pages at once queues behind itself and times out in ways that look
like real failures. `PHP_CLI_SERVER_WORKERS` gives it a small pool. The setup
script prints this exact command, and a `wp bhc health-check` line, when it
finishes.

The script downloads WordPress and WooCommerce, configures a database, symlinks
this repository's theme and plugin into the install, installs Composer and npm
dependencies, builds the assets, activates everything and seeds the catalogue.
It is idempotent — re-run it any time and it skips what is already done.

It also sets `WP_ENVIRONMENT_TYPE=development` in `wp-config.php`. That is what
tells `BrandProfile` to rewrite absolute SEO URLs onto the canonical host;
without it a local install emits `localhost` URLs in its schema and Open Graph
tags, because on production `home_url()` is already correct.

**Redis:** set `REDIS_HOST` (and `REDIS_PORT`, default `6379`). The script clones
the `redis-cache` plugin, sets `WP_REDIS_HOST`, `WP_REDIS_PORT` and a
`WP_CACHE_KEY_SALT` derived from the install directory name — so two installs can
share one Redis instance — activates it and runs `wp redis enable`.
`wp bhc health-check` names Redis when Redis is what is actually serving.

**SQLite:** leave `DB_HOST` unset and the script installs the SQLite integration
plugin instead, so you need no database server at all. This is the fallback, not
the default. It costs you the query counts above, and it needs a workaround:
`tools/dev-mu-plugins/bhc-sqlite-dev.php` is copied into `mu-plugins/` only on
this path, because the SQLite translation layer cannot execute WooCommerce's
stock-reservation SQL and checkout fails without it. Never deploy that file. The
detail is in [deployment.md](deployment.md#running-on-sqlite).

**Ports and hosts:** `SITE_URL=http://localhost:9000 bin/setup-demo.sh ~/other`.
The browser suites read `BHC_BASE_URL` (default `http://localhost:8088`), so
point them at the same place.

### The symlink

The theme and plugin are **symlinked** from this repository into the WordPress
install, not copied. Edit a file here and reload — there is no sync step, no
watcher, no build for PHP.

Only SCSS needs compiling:

```bash
npm run watch      # rebuilds main.css on save
npm run build      # both stylesheets, compressed, before committing
```

`main.css` and `critical.css` are committed so a fresh clone runs without a Node
toolchain. Rebuild and commit them with any SCSS change.

`critical.css` is still built, but nothing loads it by default: the inline
critical CSS plus async stylesheet swap was measured and reverted, and both
halves now sit behind the `bhc_async_main_stylesheet` filter, default `false`.
See [performance.md](performance.md) for why.

---

## The loop

```bash
npm run test:unit                        # ~0.02s — run constantly
npm run lint                             # ~7s — stylelint, eslint, phpcs
wp eval-file bin/integration-tests.php   # ~2s, needs the store
npm run test:e2e                         # needs the store running
```

Unit tests need nothing — no WordPress, no database, no server: 73 tests, 136
assertions, and they finish faster than the shell that started them. Run them on
every save. The integration suite (73 assertions) needs a seeded store; the
Playwright suites need it served.

`npm run lint` is `lint:css` (stylelint over the SCSS), `lint:js` (eslint over
the theme and plugin JS, `tests/` and `tools/`) and `lint:php` (phpcs against
`phpcs.xml.dist`), in that order. The configs are `.stylelintrc.json` and
`eslint.config.mjs`. `npm run lint:css:fix`, `lint:js:fix` and `lint:php:fix`
handle the mechanical failures.

There are four browser suites, and `test:e2e` is only the first of them:

```bash
npm run test:e2e      # purchase-flow.mjs — 13 checks, catalogue to order received
npm run test:admin    # admin-screens.mjs — plugin and WooCommerce admin screens
npm run test:vitals   # web-vitals.mjs — 6 measurements, fails above 0.1 CLS or 2500ms LCP
npm run test:a11y     # accessibility.mjs — 24 renders through axe-core
```

They share `tests/e2e/browser.mjs`, which resolves Chromium from `BHC_CHROMIUM`
or `PLAYWRIGHT_BROWSERS_PATH` when either points at a real binary and otherwise
lets Playwright find its own. `test:a11y` fails on serious and critical
violations; the reference build reports zero at any impact level across all 24
renders.

`npm run i18n` regenerates the two `.pot` catalogues. Run it when you add or
change a translatable string.

## Useful commands

```bash
wp bhc demo status                 # products and variations, counted separately
wp bhc demo seed                   # top up after deleting something by hand
wp bhc demo seed --products=12 --orders=6 --skip-images   # fast partial seed
wp bhc demo reset --yes --orphans  # back to empty

wp bhc cache flush                 # after changing anything cached
wp bhc products sync               # rebuild the merchandising index
wp bhc health-check                # schema, object cache, scheduler, index, versions
```

`health-check` warns rather than claiming success when a check needs attention,
and exits non-zero on a failure. `--strict` makes a warning non-zero too, which
is what you want in a deploy gate. `--format=json` gives you the full report.

Set `wp --path=~/wp-demo --allow-root` once as a shell alias and drop it from
everything above.

## Debugging

**Query counts.** Drop an mu-plugin into the install:

```php
<?php
// wp-content/mu-plugins/query-probe.php
add_action( 'shutdown', function (): void {
    if ( is_admin() ) {
        return;
    }
    printf( "\n<!-- queries=%d time=%.3f -->\n", get_num_queries(), timer_stop( 0, 3 ) );
}, 999 );
```

Then `curl` a page **twice** — the second number is the warm one. On MySQL +
Redis the warm baselines are 6 on the home page and 5 on shop, product, category,
cart and blog, so anything in double figures is a regression worth chasing. On
SQLite with no object cache the same pages sit between 66 and 131 and the probe
tells you almost nothing about your own code.

Delete the file when you are done: it appends to every non-admin response,
including REST and AJAX, which breaks JSON parsing and will fail the e2e suites
in a way that looks like a real regression.

For per-query attribution, define `SAVEQUERIES` and group `$wpdb->queries` by
`$q[2]` (the call stack). That is how the three N+1 patterns in
[performance.md](performance.md) were found.

**Logs.** `wp-content/debug.log` for PHP;
`wp-content/uploads/wc-logs/` for the plugin's structured logs, written through
WooCommerce's logger under the source `bhc-core`. Everything at `info` and above
is written, and there is no setting that changes that — `Logging\Logger` takes
its minimum level as a constructor argument and `CoreServiceProvider` builds it
as `new Logger( 'bhc-core' )`, which leaves the default `info` in place. To go
quieter, filter `bhc_log_payload` and return an empty array for the events you
do not want. To go louder you have to change that construction.

Event names keep their dots (`job.batch_complete`, `wishlist.add`) — the logger
deliberately does not run them through `sanitize_key()`, which strips dots and
destroys the namespace an aggregator would group on.

**Container.** `wp eval 'var_dump( BoneHornCrafts\Core\Plugin::resolve( SomeClass::class ) );'`
resolves anything the way the application would.

**A page that will not render.** Check `wp bhc health-check` first — a missing
table or an un-flushed rewrite explains most of it. `wp rewrite flush --hard`
after touching an endpoint or a permalink.

## Conventions

* **PHP 8.2+.** Constructor promotion, `match`, enums, readonly, named arguments
  — all fair game. `declare( strict_types = 1 )` in every file except
  `bin/integration-tests.php`, where `wp eval-file` makes it illegal.
* **WPCS**, with the documented exclusions in `phpcs.xml.dist`. `npm run
  lint:php:fix` handles the mechanical ones.
* **Comments explain why, not what.** A comment restating the line below it is
  noise; a comment explaining the WooCommerce behaviour that forced the line is
  the reason the file is maintainable.
* **No business logic in the theme.** If a template needs to decide something,
  the decision belongs in a service and the template asks for the answer.
* **No new custom table** without writing down why WordPress's own storage does
  not fit. [database.md](database.md) has the three that earned one.
* **No superglobal reads outside a parser.** `Search\RequestParser` is the only
  class in `src/Search` that touches `$_GET`; `SearchService` and
  `FilterPanelRenderer` take it by injection.

## Adding things

**A service.** Bind it in the relevant provider's `register()` as a closure; add
hooks in `boot()`. Constructor-inject its dependencies — never call
`Plugin::resolve()` from inside a service.

**A REST route.** Extend `API\AbstractController`, register in
`ApiServiceProvider`, and point `permission_callback` at a `Security\RestGuard`
method. Declare `args` with types and `sanitize_callback` so WordPress rejects
bad input before your handler runs. The integration suite will fail the build if
the route has no permission callback.

**A recommendation strategy.** Implement `RecommendationStrategyInterface` (or
extend `AbstractQueryStrategy`), register it in `RecommendationsServiceProvider`
in priority order. Nothing else changes.

**A badge.** Add a `Badge` to `BadgeRegistry`, and a rule to `BadgeResolver` if
it should be automatic.

**A background job.** Extend `AbstractBatchJob`, implement `collect()` and
`process()`, register the hook in `JobsServiceProvider` and add a schedule in
`Scheduler::schedules()`. Make it idempotent — it will be retried.

**A template.** Put it in the plugin's `templates/` and render it through
`Support\Template`. A theme can then override it at
`yourtheme/bhc-commerce-core/<path>`. Shared helpers live in `src/Support/`, not
`src/Utilities/`.

## Troubleshooting

| Symptom | Cause |
|---|---|
| "Error establishing a database connection" | The database server is not running, or the credentials are wrong. Start it before the script — `sudo service mariadb start` |
| Setup stops asking you to create the database | The DB user has no `CREATE` right. The script prints the `CREATE DATABASE` and `GRANT` to run |
| Orders open at `post.php` instead of `admin.php?page=wc-orders` | HPOS is off. Setup enables it; an install predating that runs `wp wc hpos sync && wp wc hpos enable` |
| "Not enough units in stock" at checkout | SQLite; the dev mu-plugin is missing. See [deployment.md](deployment.md#running-on-sqlite) |
| Query counts in the dozens on every page | No persistent object cache — `wp bhc health-check` says so on the "Persistent object cache" row |
| Health check says "Active. Not Redis." | The drop-in is some other cache, or `wp redis enable` never ran |
| `/wishlist/` 404s | Rewrite rules stale — `wp rewrite flush --hard` |
| Shop shows "Great things are on the horizon" | WooCommerce coming-soon mode — `wp bhc demo seed` clears it |
| Rails render fewer cards than asked | Index points at deleted products — `wp bhc products sync` |
| Styles look unstyled after an SCSS change | `npm run build` |
| Stale prices, badges or rails | `wp bhc cache flush` |
| Browser suites time out on a served store | `php -S` running single-process — set `PHP_CLI_SERVER_WORKERS=6` |
| e2e suddenly failing on JSON | A debug mu-plugin is appending output to every response |
