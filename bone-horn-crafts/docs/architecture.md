# Architecture

Two components, with a hard line between them.

**`bhc-commerce-core`** (the plugin) owns every commerce decision: what a
bestseller is, how a tier price is calculated, what a delivery window looks
like, what goes in the JSON-LD graph. It is namespaced, PSR-4 autoloaded, and
would keep working under a different theme.

**`bhc-theme`** owns presentation only. It renders what the plugin gives it and
asks the plugin questions through a small set of template tags. Deactivating the
plugin degrades the theme to a plain WooCommerce storefront; it does not break
it.

The rule this enforces in practice: **no business logic in the theme, and no
markup decisions in the plugin's services.** The plugin does ship templates, but
they live in `templates/` and are overridable by the theme (see
[Template resolution](#template-resolution)).

---

## Plugin architecture

`src/` holds 152 PHP files across 31 namespaces: 137 final classes, 5 abstract
classes and 10 interfaces. There are no non-final concrete classes — extension
happens through the container, the provider filter and the interfaces below, not
through subclassing a shipped service.

### Boot sequence

```
bhc-commerce-core.php
  ├── requirement gate            PHP 8.2+, WordPress 6.5+, WooCommerce 8.0+
  ├── HPOS + Cart/Checkout blocks compatibility declarations
  ├── autoloader                  Composer's, or the bundled PSR-4 fallback
  └── Plugin::instance()->boot()
        ├── Container            built once, empty
        ├── providers            filtered through `bhc_service_providers`
        │     ├── register()     bindings only — nothing is constructed
        │     └── boot()         hooks, but only for providers that should_load()
        └── do_action( 'bhc_booted', $container )
```

`Plugin` is the only singleton in the codebase, and it exists for one reason:
WordPress gives a plugin file no bootstrap of its own, so something has to be a
well-known entry point. Everything downstream is constructor injected.

### The container

`Container` implements `ContainerInterface` and supports:

| Method | Behaviour |
|---|---|
| `bind( $id, $factory )` | A new instance per resolution |
| `singleton( $id, $factory )` | Built on first resolution, memoised after |
| `instance( $id, $object )` | An already-built object |
| `alias( $alias, $id )` | Interface name → concrete binding |
| `make( $class )` | Reflection autowiring for unbound classes |
| `get( $id )` | Resolve, with circular-dependency detection |

Bindings are **lazy factory closures**. A provider registering ten services
constructs none of them; a request that only renders a blog post never builds the
checkout services at all. Circular dependencies throw with the resolution chain
in the message rather than exhausting the stack.

### Service providers

Every namespace has one provider extending `AbstractServiceProvider`:

```php
public function should_load( Context $context ): bool;   // is this request relevant?
public function register( ContainerInterface $c ): void; // bindings only
public function boot( ContainerInterface $c ): void;     // hooks
```

`should_load()` is the request-context gate. `Support\Context` answers
`is_admin()`, `is_ajax()`, `is_rest()`, `is_cli()` and `is_frontend()` once per
request and memoises the answer, so:

* the admin provider registers no metaboxes on a front-end request,
* the CLI provider registers nothing unless `WP_CLI` is defined,
* the SEO provider skips REST requests entirely.

The provider list itself is filterable (`bhc_service_providers`), which is what
makes the plugin extensible without editing it.

### Layers

```
API/          REST controllers. Validate, delegate, present. No queries.
Admin/        Screens, metaboxes, the health report. No queries.
Analytics/    Product stats and the merchandising indexer.
CLI/          WP-CLI commands, each one a thin call into a service.
Cache/        Cache abstraction and the three stores.
Checkout/     Address, postcode and phone validation; delivery estimation.
Contracts/    8 of the 10 interfaces; the other two sit beside their stores.
Customer/     Roles, wholesale eligibility, account endpoints.
Database/     Schema, installer, AbstractRepository.
Demo/         Seeder, catalogue, imagery, content. Isolated from everything else.
Frontend/     Asset enqueueing and shortcodes.
Jobs/         Action Scheduler jobs and the scheduler.
Logging/      Structured logger with redaction.
Order/        Order meta, operations service, repository, admin metabox.
Pricing/      Tier rules, discount calculation, formatting.
Product/      Query builder, repository, meta, attributes, badges.
Recommendations/  Strategies and the service that blends them.
SEO/          Meta tags, canonicals, robots, breadcrumbs, the schema graph.
Search/       RequestParser, FilterRequest, facets, search service, filter panel.
Security/     Sanitiser, capabilities, REST guard, rate limiter, headers, cookies.
Support/      Context, options, templates, small helpers.
Wishlist/     Storage strategies, repository, service, renderer.
```

`Support/` is the one directory whose name differs from the original brief, which
called it `Utilities/`. It was renamed because "utilities" invites a junk drawer;
`Support/` holds `Context`, `Options`, `Template`, `Requirements`, the PSR-4
fallback autoloader and two small `Arr`/`Str` helpers, and nothing else has been
allowed in. Every other directory matches the brief's name, so anyone reading the
brief against the tree has exactly one rename to absorb.

### The dependency rule

Dependencies point **inwards**, and the arrows never reverse:

```
Controllers / Renderers / CLI commands
        ↓
     Services              (business rules)
        ↓
   Repositories            (data access)
        ↓
  WordPress / WooCommerce
```

A controller never touches `$wpdb`. A repository never echoes. A service never
reads `$_GET` or `$_POST`. The one place all three would otherwise meet — the
admin save handler — reads and sanitises input, then hands typed values to a
service.

### Where the request is read: `Search\RequestParser`

The storefront filter panel was the exception to the rule above.
`SearchService` (a service) and `FilterPanelRenderer` (a renderer) both needed to
know what the visitor had filtered by, and both reached into `$_GET` themselves.
That put request parsing inside two layers that are not supposed to know a
request exists, and it meant neither class could be exercised in a unit test
without faking a superglobal.

`Search\RequestParser` is now the only class in `src/Search/` that touches a
superglobal — `RequestParser::current()` and `RequestParser::orderby()` are the
two methods that read `$_GET`, and nothing else in the namespace does.
`SearchService` and `FilterPanelRenderer` take it by constructor injection from
`SearchServiceProvider`, which binds it as a singleton:

```php
$container->singleton( RequestParser::class, static fn (): RequestParser => new RequestParser() );
```

Parsing happens once and is memoised, so the service and the renderer cannot
disagree about what was asked for. `set()` replaces the parsed request outright,
which is how tests inject a `FilterRequest` and how the REST controllers hand
over parameters that came from `WP_REST_Request` rather than the query string.

What it costs: one more object in the graph, and one more constructor argument on
two classes. What it bought: `$_GET` appears in exactly one file in the
namespace, and the filter logic became testable without global state.

`orderby()` is deliberately not routed through `FilterRequest`'s allow-list —
WooCommerce's own sort dropdown posts values this plugin does not define, so the
value is `sanitize_key()`ed and passed on rather than rejected.

### Interfaces

Ten interfaces exist, each because there is (or plausibly will be) more than one
implementation. Eight live in `src/Contracts/`; `Cache\StoreInterface` and
`SEO\Schema\SchemaPieceInterface` live beside their implementations because
nothing outside those namespaces depends on them.

| Interface | Implementations |
|---|---|
| `CacheInterface` | `CacheManager` |
| `Cache\StoreInterface` | `ObjectCacheStore`, `TransientStore`, `ArrayStore` |
| `ContainerInterface` | `Container` |
| `HookableInterface` | 31 classes that register WordPress hooks |
| `LoggerInterface` | `Logger` |
| `PricingRuleInterface` | `WholesaleTierRule` |
| `RecommendationStrategyInterface` | 5 strategies, over one abstract base |
| `SchemaPieceInterface` | `OrganizationSchema`, `WebSiteSchema`, `ProductSchema`, `ArticleSchema`, `BreadcrumbListSchema` |
| `ServiceProviderInterface` | 20 providers |
| `WishlistStorageInterface` | `UserWishlistStorage`, `GuestWishlistStorage` |

### Patterns, and what each one is actually for

**Strategy** — `Recommendations\Strategies\*`. Five ways to find related
products (same category, shared attribute, shared tag, price band, bought
together). `RecommendationService` asks each in priority order and stops when it
has enough. Adding a sixth is a new class plus one line in the provider.

**Repository** — `ProductRepository`, `OrderRepository`, `WishlistRepository`,
`AffinityRepository`, `ProductStatsRepository`, `FacetRepository`. Every query
in the plugin lives in one of these. They are bounded (no code path can load the
whole catalogue), cached at the id-list level, and batch-prime what the caller is
about to render.

**Builder** — `ProductQuery` builds `WP_Query` arguments and, when ordering by
price or popularity, attaches scoped `posts_join` / `posts_where` /
`posts_orderby` filters that join `wc_product_meta_lookup`. The filters are
guarded by a query var and always detached in a `finally`, so nothing leaks into
the next query.

**Registry** — `BadgeRegistry` holds `Badge` value objects; `AttributeCatalog`
holds attribute definitions. Both are filterable.

**Value object** — `Badge`, `PriceContext`, `RecommendationContext`,
`FilterRequest`, `CountryProfile`. Immutable, constructed once, no setters.
`FilterRequest::from_array()` is the only place a filter query string is turned
into a validated object; `RequestParser` is the only place that array comes from
a superglobal.

**Template method** — `AbstractBatchJob` owns the batching loop, retry with
backoff, and structured logging; subclasses implement `collect()` and
`process()`.

**Facade** — `ProductMeta` and `OrderMeta` are the single source of truth for
meta keys. Nothing else in the codebase writes a `_bhc_` key.

### Template resolution

`Support\Template::render()` resolves a template in this order:

1. `wp-content/themes/<child>/bhc-commerce-core/<path>` — via `locate_template()`,
   which checks the child theme then the parent
2. the plugin's own `templates/<path>`

The requested name is stripped of `..` and null bytes before it is used, resolved
paths are memoised per request, and the final path is filterable through
`bhc_locate_template`. This mirrors WooCommerce's own override contract, so it is
what an agency integrator will already expect.

### Extension points

27 filters and 5 actions. The ones worth knowing:

| Hook | Use |
|---|---|
| `bhc_service_providers` | Add or remove a whole subsystem |
| `bhc_cache_store` | Swap the cache backend |
| `bhc_product_query_args` | Change every catalogue query in one place |
| `bhc_registered_badges` / `bhc_product_badges` | Add a badge, or change resolution |
| `bhc_recommendation_ids` | Post-process recommendations |
| `bhc_schema_graph` | Add or edit a JSON-LD node |
| `bhc_security_headers` | Add a CSP once the gateways are audited |
| `bhc_job_schedules` | Re-time background work |
| `bhc_booted` | Anything that needs the container |

---

## Theme architecture

`bhc-theme` is a classic PHP theme. No page builder, no block theme, and no
jQuery of its own — but WooCommerce's jQuery bundle is not gone everywhere. See
[jQuery](#jquery) below.

### `functions.php` and `inc/`

`functions.php` does nothing but require seven focused files:

| File | Responsibility |
|---|---|
| `inc/setup.php` | `add_theme_support`, image sizes, menus, sidebar |
| `inc/enqueue.php` | Stylesheet and module enqueueing; the critical-CSS/async path, off by default |
| `inc/performance.php` | Dequeues, cart-fragment limiting, LCP preload, srcset capping, WebP derivatives |
| `inc/security.php` | Front-end hardening that belongs to presentation |
| `inc/seo.php` | Bridges the plugin's SEO services into `wp_head`; defines `bhc_breadcrumbs()` |
| `inc/woocommerce.php` | Wrapper markup, hook re-ordering, template routing |
| `inc/template-tags.php` | The theme's vocabulary: `bhc_service()`, `bhc_products_for()`, `bhc_product_cards()`, `bhc_section_header()`, `bhc_prime_product_rails()` |

### How the theme talks to the plugin

Through exactly two mechanisms:

1. **`bhc_service( Class::class )`** — resolves a service from the container, and
   returns `null` when the plugin is inactive (it catches `Throwable` and returns
   `null` rather than letting a resolution failure white-screen a page). Every
   call site handles `null`.
2. **CSS custom properties** — the plugin's `storefront.css` styles its own
   components using variables (`--bhc-color-ink`, `--bhc-space-4`, …) that the
   theme defines. The theme can restyle a plugin component without the plugin
   knowing.

### WooCommerce template overrides

The theme overrides eight WooCommerce templates, in
`wp-content/themes/bhc-theme/woocommerce/`:

```
archive-product.php              content-product.php
content-single-product.php       cart/cart-empty.php
loop/loop-start.php              loop/loop-end.php
single-product/product-image.php single-product/tabs/tabs.php
```

`single-product/tabs/tabs.php` is the one that exists for a correctness reason
rather than a styling one. WooCommerce's own template emits `role="tab"` and
`role="tabpanel"` but never `aria-selected` — its jQuery sets the state after
load, so before scripts run a screen-reader user hears five tabs with no
indication of which is showing. The theme drives the tabs itself in `theme.js`,
so the override puts the state in the markup:

* `aria-selected` on every tab, `true` on the first;
* roving `tabindex` (`0` on the active tab, `-1` on the rest), so Tab reaches the
  strip once and the arrow keys move within it instead of tabbing through all
  five;
* `hidden` on inactive panels, so they leave the accessibility tree rather than
  merely going out of sight.

`theme.js` then adds ArrowLeft/ArrowRight wrap-around, Home and End, and deep
links — `/product/x/#tab-reviews` opens that tab on load, falling back to the
first tab when the hash matches nothing.

The cost of the override is the usual one: it is a copy of a core template, so it
has to be re-checked against WooCommerce on upgrade. The header comment records
the WooCommerce template version it was taken from.

### SCSS

```
abstracts/    variables, functions, mixins  (no output)
base/         reset, base elements, typography
components/   buttons, cards, forms, header, footer, modal, product, woocommerce
layout/       container, grid, header, footer
pages/        home, shop, product, checkout
```

Two entry points compile: `main.scss` → `main.css` (44,999 bytes raw, 7,920
gzip) and `critical.scss` → `critical.css` (14,966 raw, 3,534 gzip).
`components/_woocommerce.scss` exists because the theme dequeues WooCommerce's
three stylesheets and takes responsibility for the markup they styled.

`main.css` is loaded **normally**, as a render-blocking stylesheet. The
critical-CSS-inline plus async-swap pattern is built and both halves are still in
`inc/enqueue.php`, behind the `bhc_async_main_stylesheet` filter, defaulting to
`false`. It was measured and reverted: at this stylesheet size it bought nothing
on LCP, and it made CLS a race — the same page scored 0.0000 on one run and
0.2234 on the next. The trade is deliberate: ~8KB gzip of render-blocking CSS in
exchange for a layout that does not shift. A site whose stylesheet grows past the
point where that trade flips can turn the filter on without writing any code.
Numbers and method in (performance.md).

### JavaScript

No framework. `theme.js` is emitted with `type="module"` and the `defer`
strategy via a `script_loader_tag` filter; `storefront.js` is enqueued in the
footer by the plugin's `Frontend\Assets`.

* `theme.js` (10,030 bytes raw, 3,026 gzip) — navigation, the gallery, sticky
  add-to-cart, the mobile filter drawer, the product tabs state machine.
* `storefront.js` (9,143 raw, 3,182 gzip) — wishlist toggles, AJAX filtering with
  `history.pushState`, the delivery estimator, recently-viewed tracking.

Both talk to `bhc/v1` with `fetch`, send the REST nonce, and degrade to a normal
form submission when JavaScript is unavailable. No storefront page makes a
third-party request; review avatars are inline SVG monograms rather than
Gravatar.

### jQuery

Home, shop, category and blog pages load no jQuery: `bhc_trim_woocommerce_scripts()`
in `inc/performance.php` dequeues `wc-add-to-cart`, `woocommerce`,
`jquery-blockui`, `js-cookie` and the order-attribution pair everywhere except
cart, checkout and account, and drops `jquery-migrate` from jQuery's dependencies
across the whole front end.

**Product pages do load jQuery**, because WooCommerce's variation form and its
review star-rating widget are built on it. Deferring it there was tried and
reverted: WooCommerce prints inline `jQuery(...)` calls in the body, which run
before a deferred script has loaded and throw "jQuery is not defined". Removing
it entirely would mean re-implementing the variation form, which is not attempted
here. Any claim that this storefront ships no jQuery is wrong.

---

## The OOP inventory

The files worth reading if you want to see the design, not the feature list:

| File | Why |
|---|---|
| `src/Container.php` | Lazy factories, autowiring, circular-dependency detection |
| `src/Plugin.php` | Boot orchestration and provider gating |
| `src/AbstractServiceProvider.php` | The provider contract |
| `src/Contracts/*.php` | 8 of the 10 interfaces the code depends on |
| `src/Cache/CacheManager.php` | Group versioning, memoisation, `remember()` |
| `src/Cache/StoreInterface.php` + 3 stores | Strategy, chosen at runtime |
| `src/Product/ProductQuery.php` | Query builder with scoped, self-detaching filters |
| `src/Product/ProductRepository.php` | Bounded queries, id-level caching, batch priming |
| `src/Database/AbstractRepository.php` | Prepared statements, table naming, bounded deletes |
| `src/Search/RequestParser.php` | The one class in `Search/` that reads a superglobal |
| `src/Search/FilterRequest.php` | Immutable, validated filter state; builds its own cache key |
| `src/Recommendations/Strategies/AbstractQueryStrategy.php` | Template method + strategy |
| `src/Pricing/TieredPricingService.php` | Rule chain over `PricingRuleInterface` |
| `src/Jobs/AbstractBatchJob.php` | Batching, retry with backoff, structured logging |
| `src/SEO/Schema/SchemaGraph.php` | Composite over `SchemaPieceInterface` |
| `src/Wishlist/WishlistService.php` | Strategy selection: user table vs signed cookie |
| `src/Security/RestGuard.php` | Every permission callback in one auditable file |
| `src/Support/Context.php` | Memoised request classification |
| `src/Support/Template.php` | Theme-first resolution, path hardening, per-request memoisation |
