# Custom WooCommerce features

Everything below is built on WooCommerce's own extension points — hooks, CRUD
data stores, template overrides, the REST framework, Action Scheduler. **No
WooCommerce core file is modified, and no core class is monkey-patched.** An
upgrade is a normal upgrade.

---

## Catalogue and merchandising

**Faceted filtering with AJAX and real URLs.**
`Search\FilterRequest` parses material, finish, application, colour, product
type, price range, stock and sort into a validated value object.
`Search\FacetRepository` counts each facet against the current result set, so a
filter that would return nothing is shown as unavailable rather than as a dead
end. The panel updates by `fetch` and `history.pushState`, so a filtered view is
a shareable URL and the back button works. With JavaScript off it is a normal
`GET` form.

**Lookup-table sorting.** Price, popularity and rating sorts join
`wc_product_meta_lookup` instead of `postmeta`. The join is attached by
query-scoped filters guarded by a query var, and detached in a `finally`.

**Merchandising badges.** `Product\Badges\*` — a registry of `Badge` value
objects, a resolver that combines automatic rules (new arrival, low stock, on
sale, bestseller) with manually assigned ones from the product editor, and a
renderer. Both the registry and the resolution are filterable.

**Recommendations with strategy fallback.** Five strategies behind
`RecommendationStrategyInterface` — bought together (from the precomputed
affinity index), same category, shared attribute, shared tag, price band.
`RecommendationService` asks them in priority order, dedupes, excludes the seed
product and anything out of stock, and stops once it has enough. A brand-new
store with no order history still shows sensible rails, because the later
strategies do not need orders.

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

**Country-aware postcode validation.** `Checkout\PostcodeValidator` with
`CountryProfile` definitions for the store's markets (US, UK, CA, AU, DE and
others). Validated server-side in `woocommerce_after_checkout_validation`, so a
client-side bypass changes nothing — the Playwright suite asserts exactly this by
submitting a ZIP the client would accept.

**Phone normalisation** to E.164, with country prefix handling.

**Delivery estimator.** A public REST endpoint returning a date window from the
destination country's transit profile plus the product's workshop lead time.
Shown on the product page before the visitor commits to a cart.

**Export notice at checkout** for destinations outside India, stating that goods
ship as a zero-rated export and that local duties may apply.

**Checkout field customisation** through `woocommerce_checkout_fields` — company
name promoted for trade buyers, field order adjusted, placeholders rewritten.

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

The wishlist page is a **My Account endpoint** (`add_rewrite_endpoint`), so it
inherits WooCommerce's account navigation and access control rather than being a
bolted-on page.

---

## REST API

Eight routes under `bhc/v1`, every one with a real permission callback — the
integration suite fails the build if any route lacks one. Full table in
[security.md](security.md#rest-api).

The catalogue and recommendation endpoints exist so a headless front end, a
mobile app or a marketing site could read this store's merchandising without
reimplementing any of it. Responses carry cache headers.

---

## Admin

* **Dashboard** — catalogue health, low stock, index freshness, recent activity.
* **Health check** — schema version, object cache status, Action Scheduler
  availability, index population, PHP version. Reports the transient fallback
  honestly rather than claiming a cache that is not there.
* **Settings** — one screen, one option row, 20 typed settings.
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

`--strict` makes `health-check` exit non-zero on a warning, which is what makes
it usable as a deployment gate.

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
  contract (`yourtheme/bhc-commerce-core/<path>`).
* **Multisite** — untested. Nothing in the design prevents it, but nothing has
  been verified.
