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
| Variations | 22 (across 8 variable products) |
| Attachments | 186 — 180 product images, 6 journal images |
| Categories / tags | 9 / 10, plus one journal category (20 terms tracked) |
| Customers | 8 |
| Reviews | 148 |
| Orders | 24 |
| Pages | 18 (15 authored, plus the WooCommerce store pages the seeder rewrites) |
| Journal articles | 6 |
| Menus | 2 |
| Shipping zones | 4 + rest of world |
| Payment gateways | 2, both offline and both labelled "(demo)" |

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

Which rows become variable products is decided deterministically —
`should_be_variable()` takes knife-scale rows where `crc32( sku ) % 3 === 0`, so
a reseed never flips a product between simple and variable. That works out to 8
variable products carrying 22 size variations between them.

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

Three views are rendered per product on a 1200px canvas: view 0 becomes the
featured image, views 1 and 2 the gallery. Sixty products, 180 files.

Rendering is **deterministic**: `mt_srand( crc32( $sku . '|' . $view ) )` seeds
each image, so the same SKU always produces the same file and re-seeding does not
churn 180 images. (`wp_rand()` is a CSPRNG and cannot be seeded, which is why
this is one of the few places `mt_rand()` is correct.)

Every generated attachment is marked `_bhc_demo`, which is what lets
`wp bhc demo reset --orphans` find images whose product has since been deleted.

Skip it with `--skip-images` if `gd` is unavailable or you want a fast seed.

### WebP derivatives

The uploaded file itself is a JPEG — `imagejpeg()` at quality 82. What gets
served is not: the theme maps `image/jpeg` to `image/webp` through
`image_editor_output_format` (`bhc_webp_subsizes()` in the theme's
`inc/performance.php`), so every sub-size WordPress cuts is written as WebP.
Only the derivatives change format; the original upload keeps its own, and PNG
is left alone.

Measured on the 600×600 `bhc-card` size a shop card actually requests: 16,599
bytes as JPEG, 6,278 bytes as WebP — 62% smaller. A twelve-card shop page
downloads roughly 83KB of imagery in total.

The costs are worth naming. Storage roughly doubles for the affected sizes,
because the JPEG original stays on disk beside a full set of WebP sub-sizes.
There is no `<picture>` fallback, so the assumption that every targeted browser
reads WebP is load-bearing. And if the server's image editor cannot write WebP,
WordPress silently carries on emitting JPEGs — there is nothing to
feature-detect, and nothing warns you. See (performance.md).

## Customers, reviews and orders

* **Customers** — eight fictional trade buyers with invented names, addresses
  and company names across the USA, UK, Germany and Australia.
* **Reviews** — 148, drawn from `Demo\ReviewLibrary`, distributed so ratings are
  realistic (mostly 4–5 with a scattering of 3s) rather than uniformly perfect.
  Each product gets `1 + ( crc32( sku ) % 4 )` of them. They are what makes
  `aggregateRating` legitimate: the structured data reports reviews that
  genuinely exist in the database and are part of this labelled demo dataset.
  Nothing invents an external rating or cites a review platform.
* **Orders** — 24, cycling through `completed`, `processing` and `on-hold`, each
  carrying the export metadata the plugin adds (HSN summary, lot references,
  declared value, export type, destination zone, and per-line HSN/GST/lot/
  origin). Their payment method title is `Demo payment (test)`. They also feed
  the merchandising index, so bestseller ranks and "bought together" reflect
  real order data rather than random numbers.

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

## Payment gateways

A fresh WooCommerce install has **every** gateway disabled. The store looked
finished and then failed at the last step of checkout with *Invalid payment
method*, which is a bad way to find out. `seed_payment_gateways()` enables the
two offline gateways:

| Gateway | Title on the checkout form |
|---|---|
| `cod` | Pay on invoice (demo) |
| `bacs` | Bank transfer (demo) |

Both descriptions say plainly that no payment is taken and no payment or bank
details are stored. The "(demo)" suffix is deliberate: nothing in this build
processes a payment, and nobody looking at the store should have to guess
whether it does.

A gateway that is already enabled is left exactly as it is — the seeder only
writes settings for gateways it finds switched off, so a store somebody has
configured for real is never overwritten. A real deployment configures a real
gateway and turns these two off; see (deployment.md).

## The social share image

Only singular pages have a featured image to borrow, so the home page, the shop
and every archive were emitting Open Graph and Twitter tags with no image at
all — the highest-value share targets on the site producing the worst-looking
links. `seed_social_image()` walks the tracked products, takes the first
featured image it finds, and stores its id as the plugin's `social_image_id`
option. `BrandProfile::social_image()` reads that for every non-singular
request, falling back to the site icon when it is unset.

It is written once and then left alone: if `social_image_id` is already set the
function returns immediately, so a store that has chosen its own share image
keeps it. That has one consequence worth knowing — a `reset` deletes the
attachment but does not clear the option, so a reset-then-reseed store is left
pointing at a deleted id and silently falls back to the site icon. Clear the
option by hand if you want the seeder to pick a fresh image.

## Idempotency

Re-running the seeder is safe. Everything is matched before it is created:

| Object | Matched on |
|---|---|
| Products | SKU, with a `postmeta` fallback when the WooCommerce lookup misses |
| Variations | The stored size attribute slug |
| Terms | Slug |
| Pages and articles | Slug |
| Customers | E-mail |
| Reviews | Product plus reviewer e-mail — one review per reviewer per product |
| Orders | Count already recorded in the demo state; only the shortfall is created |
| Shipping zones | Zone name |

A `bhc_demo_seeding` transient acts as a concurrency lock, so two seeds started
at once cannot both create the catalogue. Transactional e-mail is short-circuited
through `pre_wp_mail` for the duration — a seed that creates 24 orders should not
send 24 e-mails.

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

The reset also truncates the two derived tables (`bhc_product_stats`,
`bhc_product_affinity`), because leaving bestseller ranks pointing at deleted
products makes rails silently render short, and it deletes the
`bhc_wishlist_page_id` option. The `bhc_wishlist` table itself is left alone:
wishlist rows belong to real users, not to the demo dataset.

## Status

`wp bhc demo status` counts objects that **still exist**, not ids that were ever
created. The state record is append-only, so a product deleted by hand or a
variation the seeder withdrew stayed in it and got counted regardless — the
command reported **252 products** for this 60-product catalogue, which is
exactly the kind of number nobody checks.

`DemoState::summary()` now resolves each tracked id before counting it, and
splits the products bucket by post type, so products and variations are reported
on separate rows (60 and 22, not one meaningless 82). The same summary is what
the reset prints before it asks for confirmation, so the confirmation prompt
describes the store that is actually there.

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
* No real payment processing — the only two gateways enabled are offline ones.

**AS International** appears in exactly two places, both of which are accurate
rather than promotional: the footer credit ("A brand by AS International") and
the `manufacturer` field in Organization and Product structured data. Every other
surface — site name, titles, meta, Open Graph, e-mails, invoices, admin menus,
alt text — is Bone Horn Crafts.
