# Security

The posture, in one line: **nothing trusts the request.** Every input is
validated against a schema, every output is escaped at the point of output, every
privileged action checks a capability and a nonce, and every SQL statement is
prepared.

Where a decision is centralised, it is centralised so that an audit is a single
file rather than a search across 151.

---

## Authorisation

`Security\Capabilities` is the only place capability names appear:

```php
Capabilities::MANAGE_COMMERCE = 'manage_bhc_commerce';
Capabilities::EDIT_PRODUCTS   = 'edit_products';
Capabilities::EDIT_ORDERS     = 'edit_shop_orders';
Capabilities::VIEW_REPORTS    = 'view_woocommerce_reports';
```

Capabilities, never role names. A shop with a custom "Merchandiser" role grants
`manage_bhc_commerce` and everything works; nothing in the codebase asks
`current_user_can( 'administrator' )`.

Object-level checks use the meta capability, so WordPress's own mapping decides:
`Capabilities::can_edit_product( $id )` is `current_user_can( 'edit_post', $id )`,
which correctly refuses an author editing somebody else's product.

`Customer\Roles` registers one additional role (`bhc_wholesale_customer`) with one
capability (`bhc_view_wholesale_pricing`). Wholesale pricing is gated on the
capability, not the role, so an administrator can grant it to an existing
customer without changing their role.

## CSRF

Every state-changing surface verifies a nonce **before** it does anything else:

| Surface | Protection |
|---|---|
| Admin forms and metaboxes | `wp_nonce_field()` + `wp_verify_nonce()` |
| Settings screen | Dedicated action, verified before the capability check even runs |
| REST writes | `X-WP-Nonce`, verified explicitly in `RestGuard` |
| AJAX | Same REST nonce; there are no `admin-ajax` write endpoints |

The REST case is the one that is easy to get wrong. WordPress's cookie
authentication only applies when `X-WP-Nonce` validates — but a route that also
serves logged-out visitors will happily accept an unauthenticated request with no
nonce at all. Every write route in `bhc/v1` therefore checks the nonce itself,
regardless of whether a user is logged in.

## Input

`Security\Sanitizer` is the only sanitisation layer. Each method casts, bounds
and filters, and returns a **typed** value:

| Method | Returns |
|---|---|
| `id()` / `id_list( $max )` | `int` / bounded `int[]` |
| `text( $max_length )` | `string`, `sanitize_text_field` then truncated |
| `key()` | `string`, `sanitize_key` |
| `slug_list( $max )` | bounded `string[]` of slugs |
| `amount()` | `float`, clamped to a sane range |
| `rich_text()` | `string` through `wp_kses_post` |
| `country()` | ISO 3166-1 alpha-2, or `''` |
| `postcode()` | normalised, or `''` |

Bounding matters as much as filtering: `id_list()` caps its length, so a request
that posts ten thousand product ids cannot turn into a ten-thousand-row `IN`
clause.

Three rules hold everywhere:

1. **Unslash before sanitise.** `wp_unslash()` then `Sanitizer::*`, always in
   that order — WordPress adds slashes to superglobals, and sanitising first
   bakes them in.
2. **Validate against a schema, not a blocklist.** REST routes declare `args`
   with `type`, `enum`, `minimum`/`maximum` and `sanitize_callback`; WordPress
   rejects anything that does not match before a controller runs.
3. **Never trust a hidden field.** Prices, totals and tier eligibility are
   recalculated server-side in `woocommerce_before_calculate_totals`. A posted
   price is ignored.

## Output

Escaped at the point of output, in the encoding of the context:
`esc_html()`, `esc_attr()`, `esc_url()`, `esc_textarea()`, `wp_kses_post()` for
editor content, `wp_json_encode()` for anything that reaches JavaScript.

Templates receive **data**, never markup. A renderer that builds a fragment
escapes inside the builder and the call site is annotated, which is the only
pattern in the codebase where an `EscapeOutput` exclusion applies.

## SQL

Everything goes through `$wpdb->prepare()`. `Database\AbstractRepository` owns
table naming so a table name is never interpolated from anything a request
touched.

Two places interpolate an identifier, because SQL does not allow a placeholder
for one:

* `Schema::drop()` — table names from `Schema::definitions()`.
* `SearchService` — `$wpdb->posts` and `$wpdb->wc_product_meta_lookup`, both
  from `$wpdb`, with the search term bound as `%s`.

Both carry a `phpcs:ignore` naming the sniff and stating why. Nothing else in the
codebase interpolates into SQL.

`$wpdb->esc_like()` is used before every `LIKE`, so a search for `100%` does not
become a wildcard.

## REST API

Nine route registrations under `bhc/v1`, and `Security\RestGuard` holds every
permission callback. Centralising them means no route can ship with
`permission_callback => '__return_true'` by accident — and the integration suite
asserts it, by walking `rest_get_server()->get_routes()` and failing if any
`bhc/v1` route lacks a callback.

| Route | Methods | Access |
|---|---|---|
| `/catalog` | GET | Public, read-limited |
| `/catalog/facets` | GET | Public, read-limited |
| `/products/(?P<product_id>\d+)/recommendations` | GET | Public, read-limited |
| `/delivery-estimate` | GET | Public, read-limited |
| `/wishlist` | GET, DELETE | Owner or valid guest token; nonce on DELETE |
| `/wishlist/toggle` | POST | Nonce required |
| `/wishlist/(?P<product_id>\d+)` | DELETE | Nonce required |
| `/health` | GET | `manage_bhc_commerce` |

### Rate limiting

`Security\RateLimiter` is a fixed-window counter over the cache: 120 reads and 40
writes per minute per identity (user id, or a hashed IP for guests). Fixed window
is deliberate — one cache read plus one write, no storage of its own, and when
the cache is cold the failure mode is *allow*, which is the right default for a
storefront. A sliding window would be more precise and would fail closed on a
cache outage, which is worse.

## Cookies

Guest wishlists and "recently viewed" have to survive without a database write
per anonymous visitor, but a cookie is attacker-controlled input.
`Security\SignedCookie` therefore:

* JSON-encodes the payload and signs it with `hash_hmac` over `wp_salt()`,
* verifies with `hash_equals()` (constant time),
* treats a bad signature as *no cookie* rather than as an error,
* caps the payload at 2 KB, and the id list at 40 entries,
* sets `HttpOnly` always and `Secure` whenever the site is on HTTPS,
* stores **only product ids** — never anything personal.

## Response headers

`Security\Headers` adds four:

```
X-Content-Type-Options: nosniff
Referrer-Policy:        strict-origin-when-cross-origin
X-Frame-Options:        SAMEORIGIN
Permissions-Policy:     geolocation=(), microphone=(), camera=(), interest-cohort=()
```

**No Content-Security-Policy is emitted by default**, and that is a deliberate
choice rather than an omission. Payment gateways inject their own scripts and
iframes; a wrong CSP breaks checkout silently, and a store loses orders before
anybody notices. A site that has audited its gateways adds one through
`bhc_security_headers`. See [deployment.md](deployment.md) for how to do that
safely.

## Secrets

There are none in the repository. No API keys, no tokens, no credentials, no
`.env`. Signing keys come from `wp_salt()`, which is per-install. The demo
seeder invents customer names and addresses; nothing is copied from anywhere.

## Logging

`Logging\Logger` writes structured entries through WooCommerce's logger when it
is available. Before anything is written the payload passes through a redaction
pass, which replaces the value of any key containing one of 23 substrings —
`password`, `pass`, `pwd`, `token`, `access_token`, `refresh_token`, `secret`,
`api_key`, `apikey`, `authorization`, `auth`, `nonce`, `card`, `card_number`,
`cvv`, `cvc`, `iban`, `session`, `session_token`, `cookie`, `ssn`, `tax_id`.
Matching is on a substring of the key, so `stripe_secret_key` and
`customer_session` are caught without having to be enumerated. There is a unit
test for it, because a logger that leaks is worse than no logger.

## What is deliberately *not* claimed

* **No legal or tax compliance claim.** The plugin stores HSN codes, GST rates
  and export/domestic classification, and shows them on orders and invoicing
  surfaces. That is data modelling for an export business. It is not a
  compliance system, it has not been reviewed by anybody qualified to say it is,
  and it must not be relied on as one.
* **No PCI scope.** Payments are WooCommerce's, through whichever gateway a
  deployment configures. No card data touches this code.
* **No security audit.** The practices here are the ones a careful build applies;
  they are not a substitute for a penetration test before a real store takes real
  money.

## Verifying

```bash
npm run lint:php          # WPCS security sniffs across plugin, theme and harness
wp eval-file bin/integration-tests.php
```

The integration suite asserts, against the running store:

* every `bhc/v1` route declares a permission callback,
* the health endpoint rejects an anonymous caller (401/403),
* a wishlist write with no nonce is rejected,
* a public route stays public and still validates its input,
* invalid input is rejected before the controller runs.

The Playwright purchase flow additionally submits a deliberately invalid ZIP code
and asserts that the **server** rejects it, not just the client.
