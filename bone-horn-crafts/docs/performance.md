# Performance

Targets, from the brief: **LCP < 2.5 s, INP < 200 ms, CLS < 0.1.**

The strategy has three parts: send less, query less, and cache what is left.

Everything below is measured on the running demo store — 60 products, 22
variations, 186 attachments, 148 reviews, 24 orders — served by PHP's built-in
server. Two stacks are measured, because they behave very differently:

* **MySQL + Redis.** The primary demo, built by `bin/setup-demo.sh`: MariaDB
  10.11, Redis 7, PHP 8.4, WordPress 7.0.4, WooCommerce 10.9.0. See
  (hosting.md).
* **SQLite, no persistent object cache.** The no-database-server option, kept
  because it is the fastest way to get the store running. It is the worst case
  rather than the best, and the query-count table below shows by how much.

---

## What the browser receives

| Asset | Raw | Gzip | Notes |
|---|---:|---:|---|
| `main.css` | 45,000 B | 7,920 B | Loaded normally (render blocking), preloaded in `<head>` |
| `theme.js` | 10,030 B | 3,026 B | ES module, `defer` |
| `storefront.js` (plugin) | 9,143 B | 3,182 B | ES module in the footer |
| `storefront.css` (plugin) | 6,887 B | 2,141 B | |
| `admin.css` + `admin-product.js` | 2,509 B | 1,151 B | Admin only |
| `critical.css` | 14,966 B | 3,544 B | Built, **not shipped** — see below |

No framework, no bundler runtime, no icon font, no webfonts. The plugin's
storefront module is only enqueued where it has work to do
(`Frontend\Assets::is_needed()`: WooCommerce pages, cart, checkout, account, the
wishlist page, search and the front page), so the blog and ordinary content
pages ship the theme module alone.

### jQuery: where it still loads

The honest version, because "no jQuery on the storefront" is the claim this
build cannot make:

* **Home, shop, category and blog pages load no jQuery.**
  `bhc_trim_woocommerce_scripts()` in `inc/performance.php` dequeues
  `wc-add-to-cart`, `woocommerce`, `jquery-blockui`, `js-cookie`,
  `wc-order-attribution` and `sourcebuster-js`, which takes jQuery itself with
  them. `jquery-migrate` is dropped from jQuery's dependency list on the whole
  front end regardless — it is a shim for jQuery 1.x-era code and nothing here
  is jQuery-era code.
* **Product pages do load jQuery.** WooCommerce's variation form and its review
  star-rating widget are built on it.
* **Cart, checkout and account keep the whole bundle.** Quantity updates, coupon
  application, shipping recalculation and the blocking overlay all run through
  it.

Deferring jQuery on product pages was tried and reverted: WooCommerce prints
inline `jQuery(...)` calls in the body, which run before a deferred script has
loaded and throw `jQuery is not defined`. The real fix is to stop loading jQuery
on product pages at all, which means re-implementing the variation form. That is
a larger change than it looks and is not attempted here.

### The critical-CSS reversal

`critical.scss` exists, builds, and is **off by default.** The
inline-critical-CSS-plus-async-swap pattern was implemented, measured, and
reverted; `main.css` now loads as an ordinary render-blocking stylesheet, with a
preload hint in the head so it is discovered as early as possible.

What the measurement showed, on a stylesheet of this size:

* **It cost layout stability.** Whether the swap landed before or after the
  first paint was a race. The same page measured CLS 0.0000 on one run and
  0.2234 on the next — the second number is over budget.
* **It bought nothing on LCP.** At 7,920 bytes over the wire, the full
  stylesheet arrives in about the time the inlined copy takes to parse.
* **It cost bytes.** Inlining ~15 KB of critical CSS into every HTML response
  while the full sheet also downloads is duplicated payload on every page view.

What blocking costs instead: the first paint waits on one small stylesheet
round trip. On the measured pages that was the cheaper trade.

Both halves — the inline `<style>` block and the `rel=preload` swap — remain in
`inc/enqueue.php` behind one filter, deliberately governed together, because
inlining critical CSS while the full sheet still blocks only duplicates bytes:

```php
add_filter( 'bhc_async_main_stylesheet', '__return_true' );
```

A site whose stylesheet grows large enough for blocking to hurt can turn it back
on. This one has not.

### What the theme removes

`inc/performance.php` dequeues what WooCommerce and WordPress add and this site
does not use:

* All three WooCommerce stylesheets (`add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' )`).
  The theme takes responsibility for that markup in `components/_woocommerce.scss`.
* The block library, global styles and classic theme styles on pages that cannot
  render block markup — the editor, block-based cart/checkout and any post whose
  content actually contains blocks keep them.
* `flexslider`, `photoswipe` and `zoom` never load, because `inc/setup.php`
  declines the three `wc-product-gallery-*` theme supports that pull them in.
  The gallery is a small piece of the theme's own ES module instead.
* `wc-cart-fragments` everywhere except WooCommerce pages. It is a blocking
  uncached AJAX request on every page load, and on a store whose header shows a
  static cart count it buys nothing.

---

## Layout stability

**Measured CLS: 0.0000** on the home, shop and product pages, at 1440 px and
390 px, and stable across consecutive runs — asserted by `npm run test:vitals`,
which fails the run above 0.1 CLS or 2500 ms LCP, or on any JS console error.
Six checks: three pages by two viewports.

* Every `<img>` carries explicit `width` and `height`; `add_image_size()`
  registrations are fixed so the aspect ratio is known before the image arrives.
* Card media reserves its box with `aspect-ratio` in CSS.
* There are **no webfonts at all** — the theme uses system stacks. No font
  request means no third-party connection, no FOIT, no swap reflow and no
  render-blocking font CSS, which is worth more to both LCP and CLS than any
  typeface choice.
* Nothing is injected above existing content after load — the announcement bar
  and the cart count are server-rendered.
* The stylesheet does not swap in after paint. That is the reversal above, and
  layout stability is most of the reason for it.

## LCP

Measured LCP on the demo store is 180–360 ms; `npm run test:vitals` fails above
2.5 s. That headroom is against PHP's built-in server, so it is a regression
guard rather than a benchmark.

* The LCP candidate — the hero image on the home page, the gallery image on a
  product page — is resolved server-side and preloaded with
  `fetchpriority="high"`, and is the one image on the page that is **not**
  `loading="lazy"`.
* **The home page preload used to never fire.** The hero template registered a
  `bhc_lcp_image_id` filter, but templates run long after `wp_head` has already
  emitted its hints, so the busiest LCP element on the site was the one that
  never got preloaded. `bhc_lcp_image_id()` in `inc/performance.php` now
  resolves the front-page hero itself, using the same cached repository call the
  hero template makes, so it costs no extra query.
* Which image counts as the LCP candidate is still filterable
  (`bhc_lcp_image_id`), for anything the built-in cases do not cover.
* `wp_calculate_image_srcset` is capped at four candidates so the browser cannot
  pick a 2000 px source for a 300 px card slot.
* `big_image_size_threshold` is disabled: WordPress's scaled intermediate adds an
  extra file per upload with no benefit at these dimensions.

## Images and WebP

`bhc_webp_subsizes()` adds an `image_editor_output_format` filter mapping
`image/jpeg` to `image/webp`, so every generated sub-size — card, gallery, hero —
is written as WebP.

Measured on the 600×600 card size: **16,599 bytes as JPEG, 6,278 bytes as WebP,
62 % smaller.** A twelve-card shop page downloads roughly 83 KB of imagery in
total.

The trade-offs are deliberate and each costs something:

* **Only derivatives change format.** The original upload stays as it was, so an
  editor downloading the full-size file gets the JPEG they put in. The cost is
  storage: the original and its WebP sub-sizes both sit on disk.
* **PNG is left alone.** It is used for flat-colour artwork and transparency,
  where WebP's gains are smaller and its lossy default is a real risk. PNGs are
  therefore the heaviest images on the site.
* If the server's image editor cannot write WebP, WordPress ignores the filter
  and carries on producing JPEGs. There is nothing to feature-detect.

Two image bugs found by reading the rendered HTML rather than the templates:

**Product cards were serving one fixed file to every viewport.** The card
template ran WooCommerce's `get_image()` output back through `wp_kses_post()`,
which silently strips `srcset`, `sizes`, `loading`, `decoding` and
`fetchpriority` — none of them are in the allowed attribute list. Every card in
the store was a single 600×600 file at every breakpoint and DPR, with the loading
hints removed too. `templates/product/card.php` now prints the core-generated
markup directly, and cards carry a four-candidate `srcset` plus an explicit
`sizes` for the 4-up / 2-up / 1-up grid.

**Archive grids loaded every image eagerly.** WooCommerce tracks the loop index
inside `wc_get_loop_class()`, which runs from `wc_product_post_class()`. This
theme's card markup does not call `post_class()`, so nothing ever incremented
the counter: it stayed at 0 and all twelve cards claimed to be the LCP
candidate. `woocommerce/content-product.php` now increments the same loop prop
WooCommerce resets at `woocommerce_product_loop_start`, so pagination and
separate loops each start again from one, and a grid renders one eager image and
the rest lazy.

## INP

No storefront page makes any third-party request. Review avatars used to reach
`secure.gravatar.com` — a blocking round trip per review, and the visitor's IP
plus a hash of each commenter's e-mail sent to a third party — and are now inline
SVG monograms rendered locally in `inc/security.php`.

The storefront runs two small ES modules and no framework. Everything
interactive — wishlist toggle, AJAX filtering, delivery estimate — is a `fetch`
to `bhc/v1` behind an optimistic UI update, so the main thread is never blocked
waiting on the network. There is no hydration step, because there is nothing to
hydrate.

---

## Query counts

Warm, same catalogue, measured on both stacks:

| Page | SQLite, no object cache | MySQL + Redis |
|---|---:|---:|
| Home (3 product rails + category grid + reviews + journal) | 131 | 6 |
| Shop (12 cards, facets, filter panel) | 83 | 5 |
| Product (gallery, tabs, reviews, 2 recommendation rails) | 116 | 5 |
| Category archive (12 cards) | 90 | 5 |
| Cart | 76 | 5 |
| Blog index | 66 | 5 |

**A persistent object cache is worth more than every other hosting decision
here.** Everything in the rest of this section — batch priming, the N+1 fixes,
the lookup-table join — matters most on the left-hand column, and the left-hand
column is the fallback, not the target. Install Redis first, then optimise.

### Batch priming

The core technique. `ProductRepository::prime()` takes the ids a page is about to
render and warms the caches in a fixed number of queries regardless of grid size:

```php
_prime_post_caches( $ids, false, false );    // the product posts
update_meta_cache( 'post', $ids );           // all their meta
update_object_term_cache( $ids, 'product' ); // categories, tags, attributes
_prime_post_caches( $attachment_ids, false, true ); // the card images
```

Thumbnail ids come out of the meta cache the third line just filled, so
collecting them costs nothing.

`bin/integration-tests.php` asserts the behaviour rather than a fixed number, so
it stays honest as the catalogue changes:

* on cold caches, a primed twelve-card render must cost **less than 60 % of** the
  unprimed render;
* on warm caches, a repeat render must cost **zero** queries.

The second assertion is the stronger claim, and it is the one an absolute
per-card budget could not express: `wp_cache_flush()` costs far more to recover
from under a persistent object cache than under the non-persistent default, so a
fixed number would mean different things on the two stacks.

### Three N+1 patterns that had to be removed

Profiling the rendered HTML — not the theory — found three. All three are fixed;
each is worth reading because they are the ones every WooCommerce build hits.

**1. WooCommerce CRUD meta reads.** `$product->get_meta()` loads an object's meta
through `WC_Data_Store_WP::read_meta()`, which is an uncached
`SELECT … WHERE post_id = %d` run once per object — and it does **not** use the
postmeta cache that `prime()` fills. A twelve-card grid asking each card for its
badge list therefore paid twelve queries no matter how well the page was primed.
`ProductMeta::read()` now reads settled products through `get_post_meta()`,
guarded by `$product->get_object_read()`; the CRUD accessor still handles
anything the database may not have yet (an unsaved product, one still being
built), so writers keep full CRUD semantics. The cost is that a caller who
mutates meta and expects to read its own uncommitted write through this facade
will not see it — read-your-writes now goes through the product object.

**2. One product hydrated per category card.** The home page's category grid
loaded a whole `WC_Product` per card just to read one attachment id when a term
had no thumbnail — a post, meta, term and attachment query for every card.
`ProductRepository::category_cover_ids()` collects the newest id per category
first, primes them together, and only then reads.

**3. One prime pass per rail.** `prime()` batches what it is given, but the home
page gave it one rail at a time, so each rail paid its own term-relationship
query. `bhc_prime_product_rails()` resolves every rail's ids first — each branch
of `bhc_product_ids_for()` is a cached, bounded repository call, so resolving
without rendering is cheap — and primes them together. Each rail's own `prime()`
then finds the caches warm.

### Bounded queries

No code path can load the whole catalogue. Every repository method takes a limit
and clamps it (`ProductRepository::clamp()`: 1 to 48). `ProductQuery` returns
`fields => ids` and lets the repository hydrate, so a query that only needs a
count never builds 60 product objects.

### The lookup table

Sorting by price, popularity or rating through `postmeta` means a join plus a
filesort over `longtext`. `ProductQuery` instead joins
`wc_product_meta_lookup`, which WooCommerce maintains with typed, indexed
columns. The join is attached through query-scoped `posts_join` / `posts_where` /
`posts_orderby` filters, guarded by a query var so it cannot affect anything
else, and detached in a `finally` so an exception cannot leak it into the next
query.

---

## Caching

### The abstraction

```
CacheInterface
     └── CacheManager
            ├── request memo          array, per request
            └── StoreInterface
                   ├── ObjectCacheStore   when a persistent object cache exists
                   ├── TransientStore     otherwise
                   └── ArrayStore         tests
```

`CacheServiceProvider` picks the store at construction:
`RedisStatus::has_persistent_object_cache()` — which is
`wp_using_ext_object_cache()` — decides between Redis/Memcached and transients.
Neither the callers nor the tests know which one they got. The choice is
filterable through `bhc_cache_store`.

### Keys and invalidation

```
bhc:{schema}:{group}:v{version}:{key}
```

`{schema}` is the plugin version, so a deploy never serves stale structures
written by an older release. `{version}` is an integer stored per group.
Flushing a group increments it, which orphans every key in that group instantly
— no `DELETE … LIKE '_transient_%'` scan, no `KEYS`/`SCAN` sweep, and it works
identically on Redis and on transients.

The six groups the plugin owns (`Invalidator::ALL_GROUPS`): `products`,
`recommendations`, `search`, `facets`, `stats`, `seo`. `Cache\Invalidator` maps
store events onto them:

| Event | Groups flushed |
|---|---|
| `woocommerce_new_product` / `update_product` / `delete_product` / `trash_product` | `products`, `recommendations`, `search`, `facets`, `seo` |
| `woocommerce_product_set_stock`, `woocommerce_variation_set_stock` | `products`, `seo` |
| `created_product_cat`, `edited_product_cat`, `delete_product_cat` | `facets`, `search` |
| `woocommerce_order_status_completed` | `stats` |
| `bhc_flush_all_caches` | all six |

Each group is flushed at most once per request. `Security\RateLimiter` also uses
a `ratelimit` group; it is deliberately outside this set, because rate-limit
counters must survive a content flush.

### A TTL of 0 means no expiry

All three stores now honour that, and one of them used to not.

`TransientStore` held a default TTL of its own and substituted it whenever a
caller passed 0. Group version numbers are written with a TTL of 0 precisely so
they outlive everything they invalidate — so on the fallback path (no object
cache, which is the default), **a group flush quietly undid itself about an hour
later and every orphaned entry came back.** The store now holds no default at
all; `CacheManager` applies one before the value reaches it, and a second
substitution at the store layer is exactly how the contract broke.

There is a second trap in the same place: `set_transient()` with an expiration of
0 updates the value but leaves any existing `_transient_timeout_*` row untouched,
so a key that once had a TTL keeps expiring. `TransientStore::write()` deletes
first, which is what actually makes "no expiry" true.

Two unit tests lock both halves of the contract.

### TTLs

The manager's default is one hour (`CacheServiceProvider`); each call site sets
its own where a different answer is right. TTL is the backstop, not the
mechanism — `Cache\Invalidator` is what keeps the cache correct, so these are
chosen to bound staleness in the cases invalidation cannot see (a scheduled sale
price starting, an order landing mid-window).

| Cached value | Group | TTL |
|---|---|---|
| Product id lists (new arrivals, on sale, category, tag, badge) | `products` | 2 h |
| Bestseller ids | `stats` | 3 h |
| Bestseller lookup for badge resolution | `stats` | 6 h |
| Low-stock ids | `products` | 15 min |
| Published product count | `products` | 1 h |
| Facet counts and price range | `facets` | 1 h (the manager default) |
| Search results | `search` | 10 min |
| Recommendations | `recommendations` | 6 h, configurable, floored at 1 min |
| Order counts | `stats` | 5 min |
| Revenue over N days | `stats` | 15 min |

Low stock is short because it follows stock, which moves on every order.
Recommendations can afford six hours because the indexer rebuilds them nightly
regardless.

### `remember()`

Every cached read goes through one method, so there is exactly one place where a
miss can be mishandled:

```php
$ids = $this->cache->for_group( 'products' )->remember(
    'new_arrivals_' . $limit,
    fn (): array => $this->query( [ 'limit' => $limit, 'orderby' => 'date' ] ),
    2 * HOUR_IN_SECONDS
);
```

A distinct `MISS` sentinel is used internally, so a legitimately cached `false`,
`null` or `[]` is not re-computed on every request — the classic transient bug.
`false` gets a further wrapper on the way into the transient store, because
`get_transient()` cannot tell a stored `false` from a miss.

### Graceful degradation

With no object cache the store falls back to transients and everything works.
`wp bhc health-check` and the admin health screen both report the fallback
explicitly rather than pretending it is fine, and the health screen distinguishes
the three cases: active and Redis, active but not Redis, and not active with the
PHP redis extension present (meaning a drop-in would enable it).

The gap is not marginal. On the home page it is 131 queries against 6. That is
the single biggest win available to any deployment of this store, and
`bin/setup-demo.sh` installs and enables the Redis drop-in automatically when
`REDIS_HOST` is set.

---

## Background work

Nothing expensive happens in a request that a visitor is waiting on.

| Job | Schedule | Work |
|---|---|---|
| `MerchandisingIndexJob` | daily | Bestseller ranks, trending scores, affinity index |
| `ViewBufferFlushJob` | 15 min | Flush buffered product views to `bhc_product_stats` |
| `WishlistPruneJob` | weekly | Drop rows for deleted products and stale guest tokens |
| `CacheWarmJob` | on demand | Re-warm the groups a flush emptied |

The three recurring schedules are registered through Action Scheduler by
`Jobs\Scheduler`, only when missing, and are filterable via `bhc_job_schedules`.
`CacheWarmJob` has no schedule; it is started on demand.

All four extend `AbstractBatchJob`, which owns batching, retry and structured
logging. Retry is **linear**, not exponential: a failed batch is rescheduled
after `30 * $attempt` seconds, up to `MAX_ATTEMPTS` of 3, after which the failure
is logged at error level and `bhc_job_failed` fires. Linear is the cheaper choice
for jobs whose failures are usually a transient lock or a timeout; a job that
fails because of a systemic problem gives up in about 90 seconds rather than
backing off into a long tail.

Jobs are idempotent: a job that runs twice produces the same state, which is what
makes retries safe. First runs are staggered by five minutes plus
`crc32( $hook ) % 600` so a fresh install does not fire everything in the same
minute.

Product views are buffered in the object cache and flushed on a schedule, so a
traffic spike costs cache writes rather than a database write per page view. On
the transient fallback that buffer is a `wp_options` row, which is one more
reason the object cache matters.

---

## Reproducing the measurements

```bash
# Query counts per page
#   Add an mu-plugin that prints get_num_queries() on shutdown, then curl each
#   page twice (the second is the warm number). See testing.md.

# Priming behaviour, both assertions
wp eval-file bin/integration-tests.php

# CLS, LCP and JS errors against the budgets
npm run test:vitals
```

Serve the store with `PHP_CLI_SERVER_WORKERS=6`. A single-process `php -S`
queues the browser suites and they time out looking like real failures rather
than the serialisation they are.
