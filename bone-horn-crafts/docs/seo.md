# SEO

Everything below is implemented in the plugin, under `src/SEO/`. No SEO plugin is
required, and none is assumed — this is a build-your-own rather than a
bolt-a-plugin-on approach. That is the right call here because the structured
data a materials exporter needs (HSN, origin, unit of sale, lead time) is
specific enough that a generic plugin would need custom code anyway.

If a dedicated SEO plugin *is* active, `MetaTagService::should_output()` detects
Yoast, Rank Math, SEOPress and All in One SEO and stands down, leaving the head
to them; `bhc_output_seo_meta` overrides the decision either way. Note the limit
of that courtesy: only the meta service stands down. `SchemaGraph` keeps printing
its graph regardless, so on a site with Yoast you would want to turn structured
data off in the settings as well, or you get two graphs.

---

## Structured data

`SEO\Schema\SchemaGraph` composes `SchemaPieceInterface` implementations into a
**single `@graph`**, printed once per page. One `<script type="application/ld+json">`
per document, never several competing blocks — search engines resolve one graph
far more reliably than five islands, and cross-references between nodes (`@id`)
only work inside one.

That means opting WooCommerce out. WooCommerce prints its own Product, Review and
BreadcrumbList JSON-LD in the footer, describing the same page under different
`@id` values and built from the raw site URL rather than the canonical host, so
the two disagree about which node is which. `SchemaGraph` empties WooCommerce's
type list through `woocommerce_structured_data_type_for_page` — its own supported
opt-out — keeping only `order`, which drives markup inside transactional e-mails
that this graph does not cover. The opt-out is conditional on the setting: switch
structured data off and the filter returns the list untouched, so the job goes
straight back to WooCommerce rather than leaving the page with none.

The cost of taking this over is that WooCommerce's own future additions to its
markup — new node types, new properties as spec guidance changes — no longer
reach these pages. What the graph emits is what is written here.

| Piece | Emitted on | Notable |
|---|---|---|
| `OrganizationSchema` | every page | Name, logo, contact point, `manufacturer` |
| `WebSiteSchema` | every page | `SearchAction` pointing at the site search |
| `BreadcrumbListSchema` | every page with a trail of more than one item, except the front page | Mirrors the visible breadcrumb exactly |
| `ProductSchema` | product pages | Offer, availability, price, currency, SKU, brand, `aggregateRating` |
| `ArticleSchema` | journal posts | Headline, dates, author, image |

The piece list is filterable through `bhc_schema_pieces` and the assembled graph
through `bhc_schema_graph`; the whole feature is switchable from the settings
screen. Nothing is printed on a 404.

The integration suite fetches a rendered product page and asserts that there is
**exactly one** `application/ld+json` block on it, that the graph contains a
`Product` node and a `BreadcrumbList`, and that `@id` uses the canonical host.

### `aggregateRating`

Emitted **only** when the product actually has reviews in the database, and every
review in this store is part of the clearly-labelled demo dataset. Nothing
fabricates a rating, and nothing claims a review that does not exist. A product
with no reviews emits no `aggregateRating` node at all rather than a zero.

### `manufacturer`

`ProductSchema` sets `manufacturer` to the manufacturing entity from the settings
(AS International) because that is genuinely accurate for goods made in India.
`brand` is Bone Horn Crafts. Everywhere else — site name, titles, meta,
Open Graph, e-mails, invoices, admin menus, alt text — is Bone Horn Crafts.

---

## Meta and canonicals

`SEO\MetaTagService` emits, per page type:

* `<title>` through `document_title_parts` (never a hard-coded `<title>` tag)
* `<meta name="description">` — see below
* `<link rel="canonical">` — **exactly one per page**. Core's `rel_canonical`
  action is removed on registration, because this one is context aware (paged
  archives, filtered views) and two canonicals is worse than either. The
  integration suite asserts the count is 1.
* Open Graph: `og:site_name`, `og:type`, `og:title`, `og:locale`, `og:url`,
  `og:description`, `og:image`, `og:image:alt`
* On products: `product:price:amount`, `product:price:currency`,
  `product:availability`, `product:brand` and `product:retailer_item_id` (the
  SKU, when there is one)
* Twitter: card type, `twitter:site` when a handle is configured, title,
  description, image

### Descriptions

| Page | Source |
|---|---|
| Front page | Hand-written, translatable |
| Product | Short description, falling back to the full description |
| Other singular | Excerpt, falling back to the content |
| Category / tag / attribute archive | Term description |
| Shop | Hand-written, translatable |
| Blog archive (posts page) | The posts page's own excerpt, falling back to a hand-written line |

The blog archive branch exists because the posts page is neither `is_singular()`
nor a term archive, so it fell through every other branch and shipped with no
description at all.

Everything is stripped of shortcodes and tags and truncated to 155 characters on
a word boundary.

### Paginated views

Page 2 of an archive used to repeat page one's description verbatim, which is a
duplicate-content signal on every paginated view in the store. Paged requests now
truncate to 130 characters and append `" — page N."`, so the description stays
distinct without being written twice:

```php
$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

if ( $paged > 1 && '' !== $description ) {
    $description = trim( Str::truncate( $description, 130, '' ) ) . sprintf(
        /* translators: %d: page number. */
        __( ' — page %d.', 'bhc-commerce-core' ),
        $paged
    );
}
```

It is a mechanical suffix, not editorial copy. It distinguishes the pages; it
does not make page 4 of an archive interesting.

### Share images

`og:image` comes from the featured image (`large`) on singular pages. Everywhere
else — home, shop, every category and tag archive, the blog — there is no
featured image to borrow, and those pages previously emitted no image at all and
degraded to a text-only Twitter card. `BrandProfile::social_image()` now supplies
a fallback: the `social_image_id` setting, or the site icon at 512px if that is
unset. The demo seeder fills the setting once, with the first product thumbnail
in the catalogue, and leaves it alone afterwards so a store that has chosen its
own share image keeps it.

The card type follows the image rather than being asserted: `summary_large_image`
when there is an image, `summary` when there is not, so a store with no share
image and no site icon advertises a card it can actually fill.

### Canonical host

A staging deployment served from `staging.example.com` will happily emit
canonicals pointing at itself, which is how staging sites end up in an index.
`canonical_host` in the settings normalises every absolute URL in metadata — the
canonical, `og:url`, `rel=prev/next`, image URLs, schema `@id` values.

The rewrite is applied when `wp_get_environment_type()` is anything other than
`production`, on the reasoning that production already runs on the canonical
host. `bhc_force_canonical_host` overrides that in both directions, for a
genuinely multi-domain deployment or for a production install served from behind
a different hostname. `setup-demo.sh` sets `WP_ENVIRONMENT_TYPE=development`, so
the demo store does rewrite.

The setting is validated by meaning, not as free text: `Options::valid_host()`
requires a URL with an http/https scheme and a host containing a dot, and invalid
input keeps the previous value rather than blanking it. A canonical host stored
as "not a url" would otherwise break the canonical tag on every page.

### Paginated and filtered views

* Page 2+ of an archive canonicalises **to itself**, not to page 1 — that has
  been the guidance since `rel=prev/next` stopped being an indexing signal.
  `rel="prev"` / `rel="next"` are still emitted alongside it on archives, the
  blog and the shop: they cost two tags, they are still read by other crawlers,
  and they do not contradict the self-canonical.
* Those two links are run through the same canonicalisation as everything else.
  They used to come straight out of `get_pagenum_link()` on the request host
  while the canonical pointed at the configured host, which tells a crawler two
  different things about the same document.
* A filtered catalogue view (`?material=…`, `?min_price=…`) canonicalises to the
  unfiltered archive and is `noindex, follow`.

---

## Robots policy

`SEO\RobotsPolicy` sets `noindex, follow, noarchive` on:

* cart, checkout, my-account and every WooCommerce endpoint URL,
* the wishlist page, whose content is whatever this visitor saved,
* internal search results,
* 404s.

Filtered catalogue views get `noindex, follow` without `noarchive` — the
underlying products are archivable, it is the facet permutation that should not
be indexed.

`follow` rather than `nofollow` throughout: these pages should not be indexed,
but the links out of them are legitimate paths into the catalogue.

Everything else — home, shop, category and tag archives, product pages, content
pages, journal posts — is indexable, and gets `max-image-preview: large` and
`max-snippet: -1`.

The wishlist page is found by looking for the page that contains the
`[bhc_wishlist]` shortcode (`WishlistRenderer::page_id()`), because a store can
put the shortcode anywhere and nothing records where. The lookup is a `get_posts`
search memoised in a static for the request. The integration suite fetches
`/wishlist/` and asserts the response carries `noindex`.

## Sitemaps

`SEO\SitemapIntegration` works with WordPress core's sitemap provider rather than
replacing it — three filters, no bespoke XML:

* `wp_sitemaps_posts_query_args` excludes the cart, checkout and my-account
  pages, plus the wishlist page, located by the same shortcode lookup. A sitemap
  that lists the cart is a crawl-budget leak; one that lists a page rendering the
  visitor's own saved list is worse.
* `wp_sitemaps_taxonomies` drops every `pa_*` attribute taxonomy and
  `product_visibility`. Attribute archives are thin, near-duplicate pages.
* `wp_sitemaps_post_types` makes sure `product` is included.

Products, categories, tags, pages and journal posts stay in. Paging, caching and
the index document are core's, unmodified.

## Breadcrumbs

`SEO\BreadcrumbService` builds one trail and it is used twice: rendered visibly by
`bhc_breadcrumbs()`, and serialised into `BreadcrumbListSchema`. Building it once
is the point — a visible breadcrumb that disagrees with the structured data is a
common and entirely self-inflicted error.

The trail handles the shop page explicitly (WordPress otherwise labels it
"Archives: Shop"), taxonomy archives with their ancestors, single products with
their primary category, journal posts, and nested pages. It is filterable through
`bhc_breadcrumb_trail`.

## Content and URLs

* Permalinks: `/%postname%/`, products at `/product/…`, categories at
  `/product-category/…`.
* One `<h1>` per page, and heading levels never skip.
* Every image has meaningful alt text; card images get the product name, and
  gallery images get a numbered, translatable variant.
* Internal linking is real: category cards, recommendation rails, the journal and
  the footer all link into the catalogue.
* Product copy is written per product — 60 distinct short descriptions, intros
  and bullet lists in the demo dataset, not one template with the name
  substituted.

`robots.txt` adds the store's own disallow rules through the `robots_txt` filter.
It appends a `Sitemap:` line only when core has not already written the same one:

```php
$sitemap = esc_url_raw( home_url( '/wp-sitemap.xml' ) );

if ( ! str_contains( $output, 'Sitemap: ' . $sitemap ) ) {
    $rules[] = '';
    $rules[] = 'Sitemap: ' . $sitemap;
}
```

The file used to carry two identical `Sitemap:` lines. That is not harmful to a
crawler, but a robots.txt that repeats itself is the sort of thing that gets
copied into the next project and grows. The check is a literal string match on
core's exact line, so if core ever changes how it formats that line the duplicate
comes back — the failure mode is a repeated line, not a missing one.

## Verifying

```bash
wp eval-file bin/integration-tests.php     # SEO output group
curl -s http://localhost:8088/product/<slug>/ | grep -c 'rel="canonical"'      # 1
curl -s http://localhost:8088/product/<slug>/ | grep -c 'application/ld+json'  # 1
curl -s http://localhost:8088/ | grep 'og:image'
curl -s http://localhost:8088/wp-sitemap.xml
curl -s http://localhost:8088/robots.txt | grep -c '^Sitemap:'                 # 1
```

The integration suite asserts, against a rendered product page: JSON-LD is
emitted, the graph contains a `Product` node, it contains a `BreadcrumbList`,
exactly one canonical is present, the Open Graph price tags are there, exactly
one JSON-LD block is on the page, and `@id` uses the canonical host. It also
fetches the wishlist page and asserts it is `noindex`.
