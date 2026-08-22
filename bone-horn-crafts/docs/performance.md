# Performance

Targets, from the brief: **LCP < 2.5 s, INP < 200 ms, CLS < 0.1.**

The strategy has three parts: send less, query less, and cache what is left.
Everything below is measured on the running demo store — 60 products, 22
variations, 180 images, 148 reviews, 26 orders — served by PHP's built-in server
over SQLite with **no persistent object cache**, which is the worst case rather
than the best.

---

## What the browser receives

| Asset | Raw | Gzip | Notes |
|---|---:|---:|---|
| `critical.css` | 11.5 KB | 3.1 KB | Inlined in `<head>` |
| `main.css` | 44.1 KB | 7.8 KB | Preloaded, applied on load |
| `theme.js` | 7.9 KB | 2.4 KB | ES module, `defer` |
| `storefront.js` (plugin) | 9.0 KB | 3.1 KB | ES module, `defer` |
| `storefront.css` (plugin) | 5.8 KB | 1.7 KB | |
| `admin.css` + `admin-product.js` | 2.5 KB | 1.2 KB | Admin only |

**No jQuery on the storefront.** No framework, no bundler runtime, no icon font.

### Critical CSS

`critical.scss` is hand-curated to cover exactly what is above the fold — reset,
typography, header, hero, and the first row of cards — and is inlined by
`inc/enqueue.php`. `main.css` then loads without blocking:

```php
<link rel="preload" as="style" href="main.css" onload="this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="main.css"></noscript>
```

It is hand-written rather than machine-extracted deliberately: an extractor has
to be re-run on every change and drifts silently when nobody notices.

### What the theme removes

`inc/performance.php` dequeues what WooCommerce and WordPress add and this site
does not use:

* All three WooCommerce stylesheets (`add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' )`).
  The theme takes responsibility for that markup in `components/_woocommerce.scss`.
* The block library, global styles and classic theme styles on non-block pages.
* `flexslider`, `photoswipe` and `zoom` never load, because `inc/setup.php`
  declines the three `wc-product-gallery-*` theme supports that pull them in
  (~90 KB of JavaScript and CSS). The gallery is a small piece of the theme's own
  ES module instead.
* `wc-cart-fragments` everywhere except WooCommerce pages. It is a blocking
  uncached AJAX request on every page load, and on a store whose header shows a
  static cart count it buys nothing.

---

## Layout stability

**Measured CLS: 0.0000** on the home, shop and product pages, at 1440 px and
390 px — asserted by `npm run test:vitals`, which fails the run above 0.1.

* Every `<img>` carries explicit `width` and `height`; `add_image_size()`
  registrations are fixed so the aspect ratio is known before the image arrives.
* Card media reserves its box with `aspect-ratio` in CSS.
* There are **no webfonts at all** — the theme uses system stacks. No font
  request means no third-party connection, no FOIT, no swap reflow and no
  render-blocking font CSS, which is worth more to both LCP and CLS than any
  typeface choice.
* Nothing is injected above existing content after load — the announcement bar
  and the cart count are server-rendered.

## LCP

Measured LCP on the demo store is 240–304 ms; `npm run test:vitals` fails above
2.5 s. That headroom is against PHP's built-in single-process server, so it is a
regression guard rather than a benchmark.

* The LCP candidate — the hero image on the home page, the gallery image on a
  product page — is resolved server-side and preloaded with
  `fetchpriority="high"`, and is the one image on the page that is **not**
  `loading="lazy"`. Lazy-loading the LCP image is the most common way to lose a
  second on this metric.
* Which image counts as the LCP candidate is filterable (`bhc_lcp_image_id`).
* `wp_calculate_image_srcset` is capped so the browser cannot pick a 2000 px
  source for a 300 px card slot.
* `big_image_size_threshold` is disabled: WordPress's scaled intermediate adds an
  extra file per upload with no benefit at these dimensions.

## INP

No third-party requests are made from any storefront page. Review avatars used
to reach `secure.gravatar.com` — a blocking round trip per review, and the
visitor's IP plus a hash of each commenter's e-mail sent to a third party — and
are now inline SVG monograms rendered locally.

The storefront runs two small ES modules and no framework. Everything
interactive — wishlist toggle, AJAX filtering, delivery estimate — is a `fetch`
to `bhc/v1` behind an optimistic UI update, so the main thread is never blocked
waiting on the network. There is no hydration step, because there is nothing to
hydrate.

---

## Query counts

Measured with `SAVEQUERIES`, warm caches, no persistent object cache:

| Page | Queries | Server time |
|---|---:|---:|
| Home (3 product rails + category grid + reviews + journal) | 116 | 157 ms |
| Shop (12 cards, facets, filter panel) | 83 | 116 ms |
| Product (gallery, tabs, reviews, 2 recommendation rails) | 116 | 147 ms |
| Category archive (12 cards) | 88 | 116 ms |
| Cart | 76 | 98 ms |
| Search results | 83 | 110 ms |
| Blog index | 64 | 83 ms |
| Static page | 61 | 78 ms |

A stock WordPress + WooCommerce install with no content is already around 45–55
queries before a theme renders anything, so the marginal cost of a fully
merchandised home page here is roughly 60 queries.

### Batch priming

The core technique. `ProductRepository::prime()` takes the ids a page is about to
render and warms four caches in four queries:

```php
_prime_post_caches( $ids, false, false );   // the product posts
update_meta_cache( 'post', $ids );          // all their meta
update_object_term_cache( $ids, 'product' );// categories, tags, attributes
_prime_post_caches( $attachment_ids, ... ); // the card images
```

Measured on a 12-card rail, cold:

| | Queries | Per card |
|---|---:|---:|
| Unprimed | 102 | 8.5 |
| Primed | 36 | 3.0 |

**A 65 % saving**, and it grows with the size of the grid.

### Three N+1 patterns that had to be removed

Profiling the rendered HTML — not the theory — found three. All three are fixed;
each is worth reading because they are the ones every WooCommerce build hits.

**1. WooCommerce CRUD meta reads (worth 20 queries on the home page).**
`$product->get_meta()` loads an object's meta through
`WC_Data_Store_WP::read_meta()`, which is an uncached `SELECT … WHERE post_id = %d`
run once per object — and it does **not** use the postmeta cache that `prime()`
fills. A twelve-card grid asking each card for its badge list therefore paid
twelve queries no matter how well the page was primed. `ProductMeta` now reads
settled products through `get_post_meta()`; the CRUD accessor still handles
anything the database may not have yet (an unsaved product, one still being
built), so writers keep full CRUD semantics.

**2. One product hydrated per category card (worth 10).** The home page's
category grid loaded a whole `WC_Product` per card just to read one attachment id
when a term had no thumbnail. `ProductRepository::category_cover_ids()` now
resolves every card's fallback in one batch.

**3. One prime pass per rail (worth 18).** `prime()` batches what it is given,
but the home page gave it one rail at a time, so each rail paid its own
term-relationship query. `bhc_prime_product_rails()` resolves every rail's ids
first — each is a cached repository call, so resolving without rendering is
cheap — and primes them together. Each rail's own `prime()` then finds the caches
warm.

Home page across the three: **164 → 116 queries**.

### Bounded queries

No code path can load the whole catalogue. Every repository method takes a limit
and clamps it. `ProductQuery` returns `fields => ids` and lets the repository
hydrate, so a query that only needs a count never builds 60 product objects.

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

`CacheManager` picks its store at construction: `wp_using_ext_object_cache()`
decides between Redis/Memcached and transients. Neither the callers nor the tests
know which one they got. The choice is filterable through `bhc_cache_store`.

### Keys and invalidation

```
bhc:{schema}:{group}:v{version}:{key}
```

`{version}` is an integer stored per group. Flushing a group increments it, which
orphans every key in that group instantly — no `DELETE … LIKE '_transient_%'`
scan, no key enumeration, and it works identically on Redis and on transients.
`Cache\Invalidator` bumps the right groups on `save_post`, `woocommerce_update_product`,
`woocommerce_order_status_changed` and term edits.

The six groups: `products`, `search`, `recommendations`, `seo`, `facets`, `stats`.

### TTLs

The default TTL is one hour; each call site sets its own where a different answer
is right. TTL is the backstop, not the mechanism — `Cache\Invalidator` is what
keeps the cache correct, so these are chosen to bound staleness in the cases
invalidation cannot see (a scheduled sale price starting, an order landing
mid-window).

| Cached value | TTL | Why |
|---|---|---|
| Product id lists (new, bestsellers, on sale, category, tag, price band) | 2 h | Bounded, and invalidated on product save anyway |
| Low-stock ids | 15 min | Follows stock, which moves on every order |
| Badge resolution | 6 h | Depends on meta that only changes on save |
| Facet counts and price range | 2 h | Attribute distribution moves slowly |
| Search results | 10 min | Short enough that a new product appears quickly |
| Recommendations | 6 h, configurable | Rebuilt nightly by the indexer regardless |
| Published product count | 1 h | Used for the dashboard and the sitemap |

### `remember()`

Every cached read goes through one method, so there is exactly one place where a
miss can be mishandled:

```php
$ids = $cache->for_group( 'products' )->remember(
    'bestsellers_12',
    fn (): array => $this->query( [...] ),
    2 * HOUR_IN_SECONDS
);
```

A distinct `MISS` sentinel is used internally, so a legitimately cached `false`,
`null` or `[]` is not re-computed on every request — the classic transient bug.

### Graceful degradation

With no object cache the store falls back to transients and everything works;
`wp bhc health-check` and the admin health screen both report the fallback
explicitly rather than pretending it is fine. Installing Redis removes roughly 30
queries a page (the WooCommerce CRUD meta reads and the transient option reads),
which is the single biggest remaining win available to a production deployment.

---

## Background work

Nothing expensive happens in a request that a visitor is waiting on.

| Job | Schedule | Work |
|---|---|---|
| `MerchandisingIndexJob` | daily | Bestseller ranks, trending scores, affinity index |
| `ViewBufferFlushJob` | 15 min | Flush buffered product views to `bhc_product_stats` |
| `WishlistPruneJob` | weekly | Drop rows for deleted products and stale guest tokens |
| `CacheWarmJob` | on demand | Re-warm the groups a flush emptied |

All four extend `AbstractBatchJob`, which owns batching, retry with exponential
backoff, and structured logging. They are idempotent: a job that runs twice
produces the same state, which is what makes retries safe. First runs are
staggered by `crc32( $hook ) % 600` so a fresh install does not fire everything
in the same minute.

Product views are buffered in the object cache and flushed on a schedule, so a
traffic spike costs cache writes rather than a database write per page view.

---

## Reproducing the measurements

```bash
# Query counts and server time per page
#   Add an mu-plugin that prints get_num_queries() on shutdown, then curl each
#   page twice (the second is the warm number). See docs/testing.md.

# Card priming comparison
wp eval-file bin/integration-tests.php     # includes the primed/unprimed assertion

# CLS, LCP and JS errors against the budgets
npm run test:vitals
```

The integration suite asserts the priming behaviour rather than a fixed number,
so it stays honest as the catalogue changes: *primed must cost less than three
queries per card, and materially less than unprimed.*
