# SEO

Everything below is implemented in the plugin, under `src/SEO/`. No SEO plugin is
required, and none is assumed — if Yoast or Rank Math is installed, the two would
duplicate each other, so this is a build-your-own rather than a
bolt-a-plugin-on approach. That is the right call here because the structured
data a materials exporter needs (HSN, origin, unit of sale, lead time) is
specific enough that a generic plugin would need custom code anyway.

---

## Structured data

`SEO\Schema\SchemaGraph` composes `SchemaPieceInterface` implementations into a
**single `@graph`**, printed once per page. One `<script type="application/ld+json">`
per document, never several competing blocks — search engines resolve one graph
far more reliably than five islands, and cross-references between nodes (`@id`)
only work inside one.

| Piece | Emitted on | Notable |
|---|---|---|
| `OrganizationSchema` | every page | Name, logo, contact point, `manufacturer` |
| `WebSiteSchema` | every page | `SearchAction` pointing at the site search |
| `BreadcrumbListSchema` | every page with a trail | Mirrors the visible breadcrumb exactly |
| `ProductSchema` | product pages | Offer, availability, price, currency, SKU, brand, `aggregateRating` |
| `ArticleSchema` | journal posts | Headline, dates, author, image |

The graph is filterable through `bhc_schema_graph`, and the whole feature is
switchable from the settings screen.

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

* `<title>` through `wp_get_document_title` (never a hard-coded `<title>` tag)
* `<meta name="description">` — hand-written for pages, derived from the short
  description for products, from the excerpt for posts
* `<link rel="canonical">` — **exactly one per page**, asserted by the
  integration suite
* Open Graph: `og:type`, `og:title`, `og:description`, `og:url`, `og:image`,
  `og:site_name`, plus `product:price:amount` and `product:price:currency` on
  products
* Twitter: `summary_large_image`, title, description, image

### Canonical host

A staging deployment served from `staging.example.com` will happily emit
canonicals pointing at itself, which is how staging sites end up in an index.
`canonical_host` in the settings normalises every absolute URL in metadata to
`https://www.bonehorncrafts.com`. It is filterable
(`bhc_force_canonical_host`) so a genuinely multi-domain deployment can opt out.

### Paginated and filtered views

* Page 2+ of an archive canonicalises **to itself**, not to page 1 — that has
  been the guidance since `rel=prev/next` stopped being an indexing signal.
  `rel="prev"` / `rel="next"` are still emitted alongside it: they cost two tags,
  they are still read by other crawlers, and they do not contradict the
  self-canonical.
* A filtered catalogue view (`?material=…`, `?min_price=…`) canonicalises to the
  unfiltered archive and is `noindex, follow`.

---

## Robots policy

`SEO\RobotsPolicy` sets `noindex, follow` on:

* cart, checkout, my-account and every WooCommerce endpoint URL,
* internal search results,
* 404s,
* filtered catalogue views.

`follow` rather than `nofollow` throughout: these pages should not be indexed,
but the links out of them are legitimate paths into the catalogue.

Everything else — home, shop, category and tag archives, product pages, content
pages, journal posts — is indexable.

## Sitemaps

`SEO\SitemapIntegration` works with WordPress core's sitemap provider rather than
replacing it — three filters, no bespoke XML:

* `wp_sitemaps_posts_query_args` excludes the cart, checkout, my-account and
  wishlist pages. A sitemap that lists the cart is a crawl-budget leak.
* `wp_sitemaps_taxonomies` drops every `pa_*` attribute taxonomy and
  `product_visibility`. Attribute archives are thin, near-duplicate pages.
* `wp_sitemaps_post_types` makes sure `product` is included.

Products, categories, tags, pages and journal posts stay in. The product count
feeding the index is cached, so building the sitemap does not run an unbounded
query.

`/sitemap.xml` and `/robots.txt` both respond 200 on the demo store.

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

## Verifying

```bash
wp eval-file bin/integration-tests.php     # SEO group
curl -s http://localhost:8088/product/<slug>/ | grep -c 'rel="canonical"'   # 1
curl -s http://localhost:8088/sitemap.xml
curl -s http://localhost:8088/robots.txt
```

The integration suite asserts, against a rendered product page: JSON-LD is
emitted, the graph contains a `Product` node, it contains a `BreadcrumbList`,
exactly one canonical is present, and the Open Graph price tags are there.
