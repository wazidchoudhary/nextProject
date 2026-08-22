# Demo data

Everything in this store is invented for this repository. No product, customer,
review, order, price, address or photograph is taken from a real business.

```bash
wp bhc demo seed                       # build it
wp bhc demo status                     # what exists now
wp bhc demo reset --yes                # remove it
```

Current dataset:

| | |
|---|---:|
| Products | 60 |
| Variations | 22 (8 variable products) |
| Product images | 180 |
| Categories / tags | 9 / 10 |
| Customers | 8 |
| Reviews | 148 |
| Orders | 26 |
| Pages | 18 |
| Journal articles | 6 |
| Menus | 2 |
| Shipping zones | 4 + rest of world |

---

## The catalogue

`Demo\ProductCatalog` holds 60 rows across eight product families — bone scales,
horn scales, wood and acrylic blocks, guitar parts, pen blanks, drinking horns,
combs/beads/cutlery, and workshop essentials.

Each row carries the full commercial record a materials exporter would actually
keep: SKU, price, optional sale price, stock, material, finish, application,
colour, size, product type, shape, unit of sale, lead time, HSN code, GST rate,
lot reference, quantity price tiers, badges, and its own short description,
introduction and specification bullets.

The copy is written per product. Sixty distinct descriptions, not one template
with the name substituted — which is the single most reliable tell that a demo
store is a demo store, and it is also what makes the SEO work meaningful.

Prices, lead times, tier breakpoints and stock levels are plausible for the
trade but are **invented**. They are not benchmarked against anyone's real price
list.

## Product imagery

`Demo\ImageFactory` renders every product image procedurally with GD. Nothing is
downloaded, nothing is scraped, and no licensed or third-party photograph is
involved.

Each image is built from:

* a palette per material (bone, buffalo horn, rosewood, acrylic, brass…),
* a low-resolution texture — layered sine waves for grain and banding, plus
  noise so it does not read as CGI, and mineral speckle for bone and horn,
* upscaled onto a studio backdrop with a stacked-ellipse contact shadow,
* clipped through a silhouette mask (`rounded`, `round`, `horn` with a tapered
  body and elliptical mouth, `comb` with a spine and teeth),
* a sheen applied during the masked copy, and a resampled alpha-mask vignette.

Rendering is **deterministic**: `mt_srand( crc32( $sku . '|' . $view ) )` seeds
each image, so the same SKU always produces the same file and re-seeding does not
churn 180 images. (`wp_rand()` is a CSPRNG and cannot be seeded, which is why
this is one of the few places `mt_rand()` is correct.)

Every generated attachment is marked `_bhc_demo`, which is what lets
`wp bhc demo reset --orphans` find images whose product has since been deleted.

Skip it with `--skip-images` if `gd` is unavailable or you want a fast seed.

## Customers, reviews and orders

* **Customers** — eight fictional trade buyers with invented names, addresses
  and company names across the USA, UK, Germany and Australia.
* **Reviews** — 148, drawn from `Demo\ReviewLibrary`, distributed so ratings are
  realistic (mostly 4–5 with a scattering of 3s) rather than uniformly perfect.
  They are what makes `aggregateRating` legitimate: the structured data reports
  reviews that genuinely exist in the database and are part of this labelled
  demo dataset. Nothing invents an external rating or cites a review platform.
* **Orders** — 26 across the WooCommerce statuses, each carrying the export
  metadata the plugin adds (HSN summary, lot references, declared value, export
  type, destination zone, and per-line HSN/GST/lot/origin). They also feed the
  merchandising index, so bestseller ranks and "bought together" reflect real
  order data rather than random numbers.

Ratings are written correctly, which is fiddly: WooCommerce derives a product's
average from stored rating **counts**, so the seeder writes the counts first,
then the average, then the review count, all through the CRUD object.

## Content

`Demo\ContentLibrary` provides the homepage copy, 14 pages (about, contact, FAQ,
shipping, returns, privacy, terms, size guide, care guide, order tracking,
new arrivals, bestsellers, wishlist, journal index) and six journal articles
written as a workshop would write them — degreasing bone, reading a horn before
you cut it, fitting a nut and saddle, stabilised versus untreated burl, what
happens to a parcel at customs.

`configure_store()` also clears the placeholder content a fresh WordPress and
WooCommerce install ships with — *Hello world!*, *Sample Page*, the
*A WordPress Commenter* comment and the draft refund policy. Each is matched on
its default slug **and** skipped once edited, so a page somebody repurposed
survives. Leaving them in place is the clearest possible signal that a site is an
untouched install.

## Idempotency

Re-running the seeder is safe. Everything is matched before it is created:

| Object | Matched on |
|---|---|
| Products | SKU, with a `postmeta` fallback when the WooCommerce lookup misses |
| Variations | The stored size attribute slug |
| Terms | Slug |
| Pages and articles | Slug |
| Customers | E-mail |
| Reviews | Author, product and content hash |
| Orders | Recorded id in the demo state |
| Shipping zones | Zone name |

A `bhc_demo_seeding` transient acts as a concurrency lock, so two seeds started
at once cannot both create the catalogue. Transactional e-mail is short-circuited
through `pre_wp_mail` for the duration — a seed that creates 26 orders should not
send 26 e-mails.

### The bug this section exists because of

Variations were originally matched with `$variation->get_attribute( 'pa_size' )`,
which returns the term's **display name** (`5 x 1.5 x 0.30 in`), never the slug
the seeder writes (`5x1-5x0-3`). The lookup missed every time and each reseed
appended a fresh set: after eight runs a product that should have had three
variations had 24. It now keys on `get_attributes()`, which holds the stored
slug, and deletes any duplicate or withdrawn size — so an affected store heals
itself on the next seed.

## Reset

```bash
wp bhc demo reset --yes            # remove everything the seeder created
wp bhc demo reset --yes --orphans  # also sweep _bhc_demo objects it lost track of
```

`Demo\DemoState` records the id of every object the seeder creates, and the reset
removes **only** ids on that list that also carry the `_bhc_demo` marker. A
product a real merchandiser added by hand is never touched, even if it sits in a
demo category and looks exactly like demo data. Nothing is matched by title,
slug or heuristics.

The reset also truncates the derived tables (`bhc_product_stats`,
`bhc_product_affinity`, `bhc_wishlist`), because leaving bestseller ranks
pointing at deleted products makes rails silently render short.

`wp bhc demo status` counts objects that **still exist**, not ids that were ever
created — an append-only state record would otherwise report a store that is not
there.

## Options

```bash
wp bhc demo seed --products=12 --orders=6 --skip-images   # fast partial seed
wp bhc demo seed --skip-content                           # catalogue only
wp bhc demo seed --skip-index                             # skip the index rebuild
```

## What is deliberately absent

* No third-party company names, logos, trademarks or branding.
* No copied product photography — every image is generated here.
* No real customer data, addresses or e-mail addresses.
* No claimed testimonials, external review scores or press quotes.
* No claimed certifications, compliance statements or trade credentials.

**AS International** appears in exactly two places, both of which are accurate
rather than promotional: the footer credit ("A brand by AS International") and
the `manufacturer` field in Organization and Product structured data. Every other
surface — site name, titles, meta, Open Graph, e-mails, invoices, admin menus,
alt text — is Bone Horn Crafts.
