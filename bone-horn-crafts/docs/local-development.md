# Local development

## Getting a store

```bash
bin/setup-demo.sh ~/wp-demo
PHP_CLI_SERVER_WORKERS=6 php -S localhost:8088 -t ~/wp-demo ~/wp-demo/router.php
```

<http://localhost:8088>, admin / admin at `/wp-admin`.

PHP's built-in server runs a single process by default, so a browser suite that
opens several pages at once queues behind itself and times out in ways that look
like real failures. `PHP_CLI_SERVER_WORKERS` gives it a small pool.

The script downloads WordPress and WooCommerce, configures a database, symlinks
this repository's theme and plugin into the install, installs Composer and npm
dependencies, builds the assets, activates everything and seeds the catalogue.
It is idempotent — re-run it any time and it skips what is already done.

**Requirements:** PHP 8.2+ with `gd`, WP-CLI, Composer, Node 18+, `curl`,
`unzip`, `git`.

**MySQL:** export `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` before running
it. Leave `DB_HOST` unset and it installs the SQLite integration plugin instead,
so you need no database server at all — with one caveat, in
[deployment.md](deployment.md#running-on-sqlite).

**Ports and hosts:** `SITE_URL=http://localhost:9000 bin/setup-demo.sh ~/other`.

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

---

## The loop

```bash
npm run test:unit                        # ~0.02s — run constantly
npm run lint:php                         # ~4s
wp eval-file bin/integration-tests.php   # ~2s, needs the store
npm run test:e2e                         # ~15s, needs the store running
```

Unit tests need nothing — no WordPress, no database, no server. Run them on
every save. The integration suite needs a seeded store; the Playwright suites
need it served.

## Useful commands

```bash
wp bhc demo status                 # what exists right now
wp bhc demo seed                   # top up after deleting something by hand
wp bhc demo seed --products=12 --orders=6 --skip-images   # fast partial seed
wp bhc demo reset --yes --orphans  # back to empty

wp bhc cache flush                 # after changing anything cached
wp bhc products sync               # rebuild the merchandising index
wp bhc health-check                # schema, cache, scheduler, index, PHP
```

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

Then `curl` a page **twice** — the second number is the warm one. Delete the
file when you are done: it appends to every non-admin response, including REST
and AJAX, which breaks JSON parsing and will fail the e2e suites in a way that
looks like a real regression.

For per-query attribution, define `SAVEQUERIES` and group `$wpdb->queries` by
`$q[2]` (the call stack). That is how the three N+1 patterns in
[performance.md](performance.md) were found.

**Logs.** `wp-content/debug.log` for PHP;
`wp-content/uploads/wc-logs/` for the plugin's structured logs (channel `bhc`).
Set `log_level` to `debug` in the settings screen to raise the volume.

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
`yourtheme/bhc-commerce-core/<path>`.

## Troubleshooting

| Symptom | Cause |
|---|---|
| "Not enough units in stock" at checkout | SQLite; the dev mu-plugin is missing. See [deployment.md](deployment.md#running-on-sqlite) |
| `/wishlist/` 404s | Rewrite rules stale — `wp rewrite flush --hard` |
| Shop shows "Great things are on the horizon" | WooCommerce coming-soon mode — `wp bhc demo seed` clears it |
| Rails render fewer cards than asked | Index points at deleted products — `wp bhc products sync` |
| Styles look unstyled after an SCSS change | `npm run build` |
| Stale prices, badges or rails | `wp bhc cache flush` |
| e2e suddenly failing on JSON | A debug mu-plugin is appending output to every response |
