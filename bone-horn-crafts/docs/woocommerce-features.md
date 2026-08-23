# Custom WooCommerce features

Everything below is built on WooCommerce's own extension points — hooks, CRUD
data stores, template overrides, the REST framework, Action Scheduler. **No
WooCommerce core file is modified, and no core class is monkey-patched.** An
upgrade is a normal upgrade.

---

## Catalogue and merchandising

**Faceted filtering with AJAX and real URLs.**
`Search\FilterRequest` parses category, material, finish, application, colour,
product type, price range, stock and sort into a validated value object.
`Search\FacetRepository` counts each facet against the current result set, so a
filter that would return nothing is shown as unavailable rather than as a dead
end. The panel updates by `fetch` and `history.pushState`, so a filtered view is
a shareable URL and the back button works. With JavaScript off it is a normal
`GET` form.

`Search\RequestParser` is the only class in `src/Search` that reads a
superglobal. `SearchService` and `FilterPanelRenderer` take it by injection and
share one memoised parse, so the panel and the query cannot disagree about what
was asked for, and a test swaps the object instead of mutating `$_GET`.

**Category facet.** A **Category** facet leads the panel — "show me horn scales"
is a more natural first filter than any attribute. It is built separately from
`AttributeCatalog` because `product_cat` is a WooCommerce taxonomy rather than a
product attribute; only the term counting is shared. Only top-level terms are
offered, and `Uncategorized` is skipped. Selecting a parent includes its
children (`include_children`).

The cost of that decision: child terms are not selectable in the panel at all,
so a shopper who wants one shelf inside a category has to go to the category
page. Nesting the whole tree into a checkbox list turns a filter into a sitemap,
which was judged the worse trade.

The category selection is expressed in `FilterRequest::tax_query()`, which both
paths consume — the shop archive merges it into the main query on
`woocommerce_product_query`, and the AJAX/REST grid passes it through
`ProductQuery`. It previously existed only in `to_query_args()`, which the
archive never reads, so the panel offered the facet, the URL carried it, and
`/shop/?category=horn-scales` returned the whole catalogue. One implementation
now serves both.

Filter rows are 44px tall rather than 32px, because on mobile the panel is a
full-screen drawer and these are the primary touch targets.

**Lookup-table sorting.** Price, popularity and rating sorts join
`wc_product_meta_lookup` instead of `postmeta`. The join is attached by
query-scoped filters guarded by a query var, and detached in a `finally`.

**Merchandising badges.** `Product\Badges\*` — a registry of `Badge` value
objects, a resolver that combines automatic rules (new arrival, bestseller,
limited batch, wholesale, low stock, on sale) with manually assigned ones from
the product editor, and a renderer. The sale badge carries the percentage in its
label when there is one. Both the registry and the resolution are filterable.

**Recommendations with strategy fallback.** Five strategies behind
`RecommendationStrategyInterface` — bought together (from the precomputed
affinity index), same category, shared attribute, shared tag, price band.
`RecommendationService` asks them in priority order, dedupes, excludes the seed
product and anything out of stock, and stops once it has enough. A brand-new
store with no order history still shows sensible rails, because the later
strategies do not need orders.

**Complete Your Build.** The home page runs the store-window version of the same
index. The product page asks "what goes with this one?"; this asks "what do
makers add to finish a build?" — `AffinityRepository::most_paired_ids()` groups
the affinity table by `related_id` and ranks by summed score, so it is one
aggregate over the precomputed index rather than a scan across order items.
When it returns fewer than four products — a new store has no order history to
learn from — the rail falls back to the `workshop-essential` tag.

Unlike the other home rails, `most_paired_ids()` is not cached: the repository
leaves caching to the caller and the template calls it directly, so the section
costs one extra `GROUP BY` per home-page render. The rest of the rails are
primed in a single pass by `bhc_prime_product_rails()` before the first one
renders.

**Precomputed merchandising index.** Bestseller ranks, trending scores and
product affinity are built nightly by an Action Scheduler job reading
`wc_order_product_lookup`, and read at request time as one indexed lookup. See
[database.md](database.md).

**Recently viewed.** Stored in a signed cookie, capped, rendered from the same
card template as everything else.

---

## Pricing

**Quantity price tiers.** Per-product breakpoints (`min_qty` → `price`), edited
in the product data panel, applied in `woocommerce_before_calculate_totals`.
Prices are always recalculated server-side from the stored tiers — a posted price
is ignored.

**Wholesale gating.** `Customer\WholesaleService` decides eligibility from a
capability (`bhc_view_wholesale_pricing`), not a role name, so an administrator
can grant it to an existing customer. `Pricing\WholesaleTierRule` implements
`PricingRuleInterface`; adding a second rule is a new class.

**Tier table on the product page.** Rendered from the same data the cart uses, so
the advertised break and the charged break cannot drift apart.

**Sale price display.** `Pricing\PriceFormatter` replaces WooCommerce's sale
markup on `woocommerce_format_sale_price` with a `<del>` / `<ins>` pair plus a
screen-reader-only "Original price:" label, so the old price is announced as
what it is rather than read as the price.

That filter receives the regular and sale values as WooCommerce received them,
which for a product price is a bare number like `28.99` — WooCommerce runs each
through `wc_price()` itself while building the markup the filter replaces.
Re-rendering the raw values dropped the currency symbol, the thousands separator
and the decimal precision from every sale price in the store. Numeric values are
now run through `wc_price()` here; anything that arrives already formatted (a
variable product's price range, for instance) passes through untouched.

**Discount chip.** Above a 5% saving (`MIN_DISCOUNT_SHOWN`), a "Save N%" chip is
appended. Below that it is not: a 1% chip is noise. The chip is storefront-only
and needs both values to be numeric, so it is skipped in the admin and on price
ranges. `PriceFormatter::discount_percentage()` exposes the same number to
callers that want the value rather than the markup — the REST presenter, chiefly
— computing a variable product's discount against its cheapest variation, which
is the price the archive advertises.

**Unit suffix.** A unit of sale ("per matched pair", "set of 6") is appended to
the price so a number is never ambiguous.

---

## Product data

A **Bone Horn Crafts** tab in the WooCommerce product data metabox, added through
`woocommerce_product_data_tabs` and `woocommerce_product_data_panels`, holding:
unit of sale, lead time, care instructions, HSN code, GST rate, batch/lot
reference, country of origin, pair-matched flag, wholesale flag, manual badges,
and the price tier repeater.

Saving goes through `woocommerce_admin_process_product_object`, so values land on
the CRUD object and are written by WooCommerce's own save — which keeps HPOS,
caches and CRUD hooks correct. `Product\ProductMeta` is the only place these meta
keys appear.

Reads take the postmeta cache rather than `$product->get_meta()`, for the reason
in [performance.md](performance.md#three-n1-patterns-that-had-to-be-removed).

---

## Orders and export operations

**Order operations metabox** (HPOS-compatible, registered for both the legacy and
the new order screen): export type (zero-rated export or domestic GST), wholesale
flag, packing notes, and a read-only summary of the HSN codes, lot references and
declared value for the whole order.

**Per-line capture.** When an order is placed, each line item stores the HSN
code, GST rate, lot reference and country of origin **as they were at the time of
sale**. A product edited later does not rewrite the history of an order that
already shipped.

**Order list column** showing the export classification at a glance.

> These are data-modelling features for an export business. They are **not** a
> tax or customs compliance system and must not be relied on as one.

---

## Checkout

**Buy now.** A second submit button beside Add to cart on the product page,
rendered on `woocommerce_after_add_to_cart_button` for purchasable, in-stock
simple and variable products only. External products link off-site and grouped
products post `quantity[<id>]` per child with no single id to send, so neither
gets the button. `woocommerce_add_to_cart_redirect` sends the submission to
checkout when the button's own field is present.

On simple products the button used to add nothing at all. WooCommerce's
`simple.php` carries `add-to-cart` on the Add to cart button itself, and a
browser submits only the name and value of the button that was clicked — so the
request arrived with no `add-to-cart` and the shopper landed on an empty
checkout. A hidden `add-to-cart` field is now printed for simple products.
Variable products already ship that field in
`variation-add-to-cart-button.php`, so adding a second one there would post the
value twice; they are deliberately left alone.

**Country-aware postcode validation.** `Checkout\PostcodeValidator` with
`CountryProfile` definitions for the store's markets (US, UK, CA, AU, DE and
others). Validated server-side in `woocommerce_after_checkout_validation`, so a
client-side bypass changes nothing — the Playwright suite asserts exactly this by
submitting a ZIP the client would accept. The UAE has no postal system, so its
postcode field is not marked required.

**Phone normalisation** to E.164, with country prefix handling.

**Delivery estimator.** A public REST endpoint returning a date window from the
destination country's transit profile plus the product's workshop lead time.
Shown on the product page before the visitor commits to a cart.

**Export notice at checkout** for destinations outside India, stating that goods
ship as a zero-rated export and that local duties may apply.

**Checkout field customisation** through `woocommerce_checkout_fields` — company
name promoted for trade buyers, field order adjusted, placeholders rewritten.

**Demo payment gateways.** A fresh WooCommerce install has every gateway
disabled, so a seeded store looked complete and then failed at the last step of
checkout with "Invalid payment method". `DemoSeeder::seed_payment_gateways()`
enables the two offline gateways — Cash on delivery, titled "Pay on invoice
(demo)", and Bank transfer, titled "Bank transfer (demo)" — and gives both a
description saying that no payment is taken and no payment or bank details are
stored. A gateway somebody has already enabled is left alone.

The "(demo)" labelling is the point: nothing here processes a payment, and
nobody looking at the store should have to wonder whether it does. A real
deployment configures a real gateway and turns these off — see
[deployment.md](deployment.md).

---

## Wishlist

Works signed in and signed out, through one interface:

| | Storage | Cap |
|---|---|---|
| Logged in | `bhc_wishlist` table | 60 items |
| Guest | HMAC-signed cookie | 40 items |

`Wishlist\WishlistService` picks the strategy; nothing above it knows which one
it got, and the integration suite asserts both behave identically through the
interface. A guest's cookie merges into their rows on login. Toggles go through
`bhc/v1` with a nonce and a rate limit, and degrade to a form post without
JavaScript.

There are two ways in. `Customer\AccountEndpoints` adds a **Wishlist** tab to My
Account through `add_rewrite_endpoint` (at `EP_PAGES` only — claiming `EP_ROOT`
as well would take `/wishlist/` at the site root and 404 the standalone page),
so it inherits WooCommerce's account navigation and access control. The
`[bhc_wishlist]` shortcode renders the same grid on an ordinary page.

Because that page renders per-visitor content, it is kept out of the index and
out of the sitemap. There is no setting recording which page it is, so
`WishlistRenderer::page_id()` locates it by searching for the shortcode — the one
thing that stays true however the page is named or moved.

---

## REST API

Eight routes under `bhc/v1`, every one with a real permission callback — the
integration suite fails the build if any route lacks one. Full table in
[security.md](security.md#rest-api).

The catalogue and recommendation endpoints exist so a headless front end, a
mobile app or a marketing site could read this store's merchandising without
reimplementing any of it. `CatalogController` builds its filters through the same
`FilterRequest::from_array()` the storefront uses, so the REST catalogue and the
shop page cannot drift apart. Responses carry cache headers.

---

## Admin

* **Dashboard** — catalogue health, low stock, index freshness, recent activity.
* **Health check** — schema version against the expected version, object cache
  status, Action Scheduler availability, merchandising index population,
  WordPress version, WooCommerce version, plugin version and environment type,
  and PHP version. It reports the transient fallback honestly rather than
  claiming a cache that is not there, and when a persistent cache is present it
  names Redis specifically when Redis is what is serving — "Active
  (WP_Object_Cache)" is true of every drop-in and tells an operator nothing
  about whether the thing they installed is the thing serving them. Nothing
  sensitive is included: no credentials, no connection strings, no absolute
  paths, no customer data, so the screen is safe to screenshot into a support
  ticket.
* **Low stock, including variations.** `ProductRepository::low_stock_ids()`
  queries both `product` and `product_variation` against
  `woocommerce_notify_low_stock_amount`. It used to query `product` only, so a
  store with 22 variable products reported "nothing is running low" while three
  sizes were down to three units — a variable product holds no stock of its own.
  A variation has no edit screen of its own and `get_edit_post_link()` returns
  nothing for one, so variation rows link to the parent, where the variation's
  stock field actually lives.
* **Settings** — one screen, one option row, 21 typed settings. Among them,
  "Delete data on uninstall", off by default.
* All three gated on `manage_bhc_commerce` (or `manage_woocommerce`).

## WP-CLI

```
wp bhc products sync [--job=<job>] [--batch=<n>] [--attributes] [--async]
wp bhc cache warm | flush [--group=<group>] | status
wp bhc health-check [--format=<format>] [--strict]
wp bhc demo seed [--products=<n>] [--orders=<n>] [--skip-images] [--skip-content] [--skip-index]
wp bhc demo reset [--yes] [--orphans]
wp bhc demo status
```

`health-check` prints the checks as a table (or JSON, which also carries the full
report), then says what it found. A failing check exits non-zero. A warning
exits zero but prints a warning naming the count — it used to print "All checks
passed" under a table with an Attention row in it, which is the kind of summary
that gets believed instead of the table. `--strict` turns a warning into a
non-zero exit too, which is what makes the command usable as a deployment gate.

`wp bhc demo status` reports products and variations as separate rows, and counts
objects that still exist rather than ids ever created.

## Background jobs

Four Action Scheduler jobs, all extending `AbstractBatchJob` (batching, retry
with exponential backoff, structured logging) and all idempotent. Schedules and
rationale in [performance.md](performance.md#background-work).

## Compatibility

* **HPOS** — declared compatible and actually compatible: all order meta goes
  through the CRUD, and the metabox is registered for both order screens.
* **Cart/Checkout blocks** — compatibility declared. The demo store uses the
  classic shortcode cart and checkout, because the plugin's export notice and
  field customisations hook into the classic templates; the trade-off is
  documented in [deployment.md](deployment.md).
* **Templates** — theme overrides resolve through the standard WooCommerce
  contract (`yourtheme/bhc-commerce-core/<path>`). The theme also overrides
  WooCommerce's `single-product/tabs/tabs.php` to supply the full tab pattern —
  `aria-selected`, roving tabindex, hidden panels, Home/End keys, deep links.
* **Multisite** — untested. Nothing in the design prevents it, but nothing has
  been verified.
