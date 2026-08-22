# Database design

The rule applied throughout: **use WordPress and WooCommerce tables until they
demonstrably do not fit.** Three tables did not fit, and each one is justified
below. Everything else — products, variations, orders, customers, reviews,
attributes, categories — lives where WooCommerce puts it, which is what keeps
imports, exports, HPOS, the REST API and every other plugin working.

All three tables are created by `dbDelta()` in `Database\Schema`, installed by
`Database\Installer` on activation and on version change, and accessed through
repositories extending `Database\AbstractRepository`, which owns table naming
and prepared statements.

---

## `{prefix}bhc_wishlist`

```sql
id          bigint unsigned  auto_increment
user_id     bigint unsigned  default 0
list_token  char(64)         default ''
product_id  bigint unsigned
date_added  datetime

PRIMARY KEY (id)
UNIQUE KEY  user_product   (user_id, product_id)
KEY         token_product  (list_token, product_id)
KEY         product_id     (product_id)
KEY         date_added     (date_added)
```

**Why not user meta.** A wishlist is a many-to-many relation between users and
products, and the questions asked of it are relational: *is this product on this
user's list* (on every product card), *what is on this list* (the wishlist page),
*how many lists is this product on* (merchandising). Serialised in user meta,
the first question needs a full unserialise per card and the third is impossible
without scanning every user.

**What the keys buy.** The unique key on `(user_id, product_id)` makes "add to
wishlist" idempotent at the storage layer rather than in application code — a
double-tapped button cannot create a duplicate row. `token_product` serves the
same lookups for guests, whose identity is a signed cookie token rather than a
user id. `date_added` supports the weekly prune job without a filesort.

**Guests never appear here.** A guest wishlist lives entirely in an HMAC-signed
cookie (`Security\SignedCookie`), capped at 40 ids. Nothing is written to the
database until the visitor logs in, at which point the cookie is merged into
their rows. Storing a row per anonymous visitor would fill this table with
records nobody will ever read again.

---

## `{prefix}bhc_product_affinity`

```sql
product_id  bigint unsigned
related_id  bigint unsigned
strategy    varchar(32)      default 'bought_together'
score       decimal(8,5)
updated_at  datetime

PRIMARY KEY (product_id, strategy, related_id)
KEY         product_score (product_id, score)
KEY         related_id    (related_id)
KEY         updated_at    (updated_at)
```

**Why a table.** "Customers who bought this also bought…" is a self-join across
every order item in the store. Computing it per page view is out of the question;
caching the computation does not help the first visitor after each order, and
invalidating it correctly is worse than precomputing it.

So it is precomputed. `Jobs\MerchandisingIndexJob` walks the last 180 days of
completed orders in batches through Action Scheduler and writes co-occurrence
scores here. A recommendation lookup at request time is one indexed read.

**The composite primary key** is the natural key — a product may relate to the
same product under two different strategies with different scores, but never
twice under one. `(product_id, score)` covers the only query the read path makes:
*top N related ids for this product under this strategy, by score*.

**`related_id`** exists for cleanup: when a product is deleted, its rows must go
from both sides. `updated_at` lets the indexer expire rows whose source orders
have aged out.

---

## `{prefix}bhc_product_stats`

```sql
product_id       bigint unsigned
views_30d        int unsigned
units_30d        int unsigned
revenue_30d      decimal(12,2)
bestseller_rank  int unsigned
trending_score   decimal(10,5)
updated_at       datetime

PRIMARY KEY (product_id)
KEY bestseller_rank (bestseller_rank)
KEY trending_score  (trending_score)
KEY units_30d       (units_30d)
```

**Why not post meta.** These are counters written far more often than they are
read, and read in *ranked* order. Post meta gives neither: every write touches
the shared `postmeta` table and busts the product's meta cache, and ordering by a
`meta_value` means a join plus a filesort over a `longtext` column.

A dedicated table gives typed columns, an index per ranking, and — importantly —
writes that do not invalidate the product meta cache the whole catalogue depends
on. Bumping a view counter should not make the product card re-query.

**How it is filled.** Views are buffered in the object cache and flushed every 15
minutes (`Jobs\ViewBufferFlushJob`), so a traffic spike costs cache writes, not
database writes. Units and revenue come from the same nightly pass over
`wc_order_product_lookup` that builds the affinity index.

**Orphans.** Both derived tables prune rows whose products no longer exist before
each ranking pass. Without that, a deleted product keeps its bestseller rank and
the rail silently renders fewer cards than it asked for.

---

## WooCommerce lookup tables

The plugin reads two WooCommerce-owned tables directly and writes to neither:

* **`wc_product_meta_lookup`** — `ProductQuery` joins it for price, stock status,
  rating and total sales ordering. These are the four sorts a shop page offers,
  and doing them through `postmeta` is the single biggest cause of slow
  WooCommerce catalogues.
* **`wc_order_product_lookup`** — the merchandising indexer reads order line
  items from here rather than from order item meta, which turns the nightly pass
  from a per-order loop into a couple of aggregate queries.

## Where plugin data lives when it is *not* in a custom table

| Data | Storage | Why |
|---|---|---|
| Product HSN code, GST rate, lot reference, lead time, unit of sale, care notes, badges, tier prices | `postmeta`, keys owned by `Product\ProductMeta` | Per-product attributes read with the product; the meta cache is primed in the same batch |
| Order export type, declared value, HSN summary, lot references | Order meta via the CRUD, keys owned by `Order\OrderMeta` | HPOS-safe: written through `$order->update_meta_data()`, so it lands in whichever table WooCommerce is using |
| Wholesale eligibility | User meta + a role capability | It is an account property, and roles are how WordPress expresses it |
| Recently viewed | Signed cookie | Ephemeral, per-visitor, worthless after the session |
| Settings | One autoloaded option, `Support\Options` | One row, one cache entry |
| Cached id lists, facet counts, schema fragments | Object cache or transients | Derived; see [performance.md](performance.md) |

## Migrations

`Database\Installer` compares a stored schema version against the code's and runs
`dbDelta()` when they differ, then fires `bhc_schema_installed`. `dbDelta()` is
additive, so a column added in a later version reaches existing installs without
a bespoke migration path.

Tables are dropped only by `uninstall.php`, and only when **Delete data on
uninstall** has been switched on in the settings screen first. Deactivation never
deletes anything, so a deactivate-reactivate cycle, a failed update or a host
migration cannot lose a customer's wishlists.

`wp bhc health-check` reports the installed schema version against the expected
one.
