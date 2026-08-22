# Testing

Three layers, each answering a question the others cannot.

| Layer | Count | Runtime | Question it answers |
|---|---:|---:|---|
| **Unit** (PHPUnit) | 71 tests, 133 assertions | ~0.02 s | Is the logic correct in isolation? |
| **Integration** (`wp eval-file`) | 59 assertions | ~2 s | Does it behave correctly *inside a real WooCommerce store*? |
| **End-to-end** (Playwright) | 4 suites, 50 checks | ~90 s | Can a person actually buy something, and can everyone? |

Plus **WPCS** across the plugin, theme and harness, at zero violations.

---

## Unit tests

`wp-content/plugins/bhc-commerce-core/tests/Unit/`, run with
`npm run test:unit` (or `composer test` from the plugin directory).

They run in **~20 milliseconds with no WordPress loaded**. `tests/bootstrap.php`
stubs the handful of WordPress functions the units under test touch
(`sanitize_text_field`, `absint`, `wp_kses_post`, `wp_json_encode`, …). That
constraint is the point: anything that cannot be tested without WordPress is
either genuinely integration code, or badly factored.

| Suite | Covers |
|---|---|
| `ContainerTest` | Bindings, singletons, aliases, autowiring, circular-dependency detection |
| `CacheManagerTest` | Key building, group versioning, `remember()` misses, TTL expiry, the `MISS` sentinel vs a cached `false` |
| `DiscountCalculatorTest` | Tier selection at, between and above breakpoints; wholesale gating; rounding |
| `PostcodeValidatorTest` | US/UK/CA/AU/DE formats, normalisation, rejection of a valid-elsewhere code |
| `PhoneValidatorTest` | E.164 normalisation, extensions, country prefixes |
| `SanitizerTest` | Every sanitiser method, including bounds and hostile input |
| `LoggerRedactionTest` | Nested arrays, substring key matching, non-string values |

### Where tests and implementation disagreed

Six assertions failed on first run. In five cases the **test** was wrong, and
correcting it was the right fix — writing a test that asserts what you assumed
rather than what is specified is how a suite becomes a liability:

* `absint( -3 )` is `3`, not `0`.
* The postcode sanitiser keeps letters, because UK and Canadian postcodes have
  them.
* The `/bhc/v1` namespace index route is registered by WordPress, not by the
  plugin, so asserting it has the plugin's permission callback was wrong.
* A "sanity guard" assertion asserted nothing.

In the sixth, the implementation was wrong: `ArrayStore` computed expiry as
`time() + $ttl` unconditionally, so a negative TTL produced a value in the past
that was nevertheless treated as live. That one was a real bug, found by a test
written to be awkward on purpose.

---

## Integration assertions

`bin/integration-tests.php`, run with:

```bash
wp eval-file bin/integration-tests.php
```

It executes **inside the running store**, against the real database, the real
WooCommerce, the real container. That is deliberate: `WP_UnitTestCase` gives an
empty database and a mocked-out WooCommerce, and most of what can go wrong in a
WooCommerce build goes wrong in the interaction with real data.

Groups:

| Group | Asserts |
|---|---|
| Environment | Plugin booted, schema installed, container resolves, WooCommerce active |
| Catalogue read model | Bounded queries, ordering, id-list caching, hydration |
| **Query efficiency** | Batch priming removes most per-card queries; a primed card costs under three |
| Pricing | Tier breakpoints, wholesale gating, cart recalculation |
| Badges | Resolution order, manual overrides, automatic rules |
| Recommendations | Strategy fallback order, dedupe, exclusion of the seed product |
| Search and filters | Facets, price range, filter combinations, `per_page` bounds |
| Wishlist storage | User table and guest cookie behave identically through the interface |
| Caching | Group flush orphans keys, `remember()` handles a cached `false` |
| **REST API** | Every route registered, every route has a permission callback, anonymous 401/403, nonce rejection, input validation |
| Checkout services | Postcode validation per country, delivery window, workshop lead time |
| Merchandising index | Stats rows exist, bestseller ranking populated |
| **SEO output** | JSON-LD emitted, `Product` node present, `BreadcrumbList` present, exactly one canonical, OG price tags |

The query-efficiency assertions are written as **relationships, not fixed
numbers** — "primed must cost materially less than unprimed, and under three
queries per card" — so they stay meaningful as the catalogue grows instead of
becoming a number nobody dares change.

`declare(strict_types=1)` is deliberately absent from this file: `wp eval-file`
evaluates it inside an existing scope where the declaration is illegal.

---

## End-to-end

Playwright, against a real browser. `npm run test:e2e` and `npm run test:admin`.

### `purchase-flow.mjs` — 13 checks

Shop renders cards → AJAX filter updates the grid → the URL updates
(`history.pushState`) → product page has an add-to-cart form → wishlist toggle
flips state → header count increments → delivery estimator returns a window →
product reaches the cart → **an invalid ZIP is rejected server-side** → the order
completes → the confirmation shows the order number → the export notice appears
for a US destination → **no uncaught JavaScript errors** anywhere in the flow.

The invalid-ZIP check matters: it submits a postcode the client would accept and
asserts the *server* refuses it, which is the difference between validation and
decoration.

### `admin-screens.mjs`

Logs in and loads the dashboard, health screen, settings and Action Scheduler,
asserting a 200 and **no PHP notice, warning, deprecation or fatal** in the
output of any of them, then opens the product editor and confirms the plugin's
data panel is present. JS errors are collected across all of it.

### `web-vitals.mjs` — 6 checks

Measures **CLS and LCP** with a `PerformanceObserver` on the home, shop and
product pages at 1440 px and 390 px, scrolling each document first so a shift
caused by a late-loading image still counts. Fails the run if CLS exceeds 0.1,
if LCP exceeds 2.5 s, or if the page logs a JavaScript error.

Current result: **CLS 0.0000 on all six**, LCP 240–304 ms.

It caught a real defect the moment it was written: the product page was firing
two requests to `secure.gravatar.com` for review avatars, sending the visitor's
IP and a hash of each commenter's e-mail to a third party and blocking on a
round trip for what would render as a generic silhouette. Avatars are now
inline SVG monograms generated locally — no request, no third party.

Override the target with `BHC_BASE_URL`.

### `accessibility.mjs` — 24 checks

Runs **axe-core** over twelve pages — home, shop, product, category, cart,
my-account, wishlist, blog, about, FAQ, search, 404 — at 1440 px and 390 px,
against `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa` and `best-practice`. It fails
on any violation at serious or critical impact and prints everything else.

Current result: **no violations at any impact level, on all 24 renders.**

It did not start there. The first run reported **686 serious colour-contrast
violations** from a single root cause: the muted text token `#857a6c` measured
3.49–4.14:1 against the three paper tones, failing AA for body text everywhere
it appeared. Three real defects came out of that run:

* the muted token, now `#706557` (4.72–5.60:1), with a separate inverse token
  for the same role on the dark banner;
* disabled buttons, which used `opacity: 0.5` — the obvious way to say
  "disabled" and the wrong one, since it drags label and background toward each
  other and put the disabled add-to-cart at 3.15:1. An explicit colour pair
  reads the same and measures 4.81:1;
* heading order on the blog and search listings, where `h3` card titles followed
  the page `h1` with nothing between them.

Automated checks cover roughly a third of WCAG. This is the mechanical third —
labels, contrast, landmarks, heading structure, ARIA misuse. It does not replace
a keyboard and screen-reader pass, and this build has not had one.

---

## Coding standards

```bash
npm run lint:php        # phpcs
npm run lint:php:fix    # phpcbf
npm run lint            # stylelint + eslint + phpcs
```

`phpcs.xml.dist` runs `WordPress` plus `PHPCompatibility` (`testVersion 8.2-`)
over the plugin, theme and test harness. **Zero violations.**

Every exclusion in the ruleset states why it exists. The substantive ones:

* File- and class-naming rules are off — the plugin is namespaced and PSR-4, so
  `class-my-thing.php` does not apply.
* `UnusedFunctionParameter` is off — WordPress hook callbacks have a fixed arity;
  dropping a trailing parameter changes the signature WordPress calls.
* `SlowDBQuery` is off — `tax_query`/`meta_query` is how a WooCommerce catalogue
  is queried at all, and these call sites are bounded, lookup-table-joined and
  cached.
* `DocComment.MissingShort` is off — it fires on inline `/** @var Type $x */`
  type assertions, which are for static analysis, not prose.
* Commenting sniffs are off **for `tests/` and `bin/` only** — a PHPUnit method
  name is the documentation.

Where a specific line needs an exception, it carries a `phpcs:ignore` naming the
sniff and the reason, rather than a blanket disable. There are nine of those, all
in two admin save handlers where the sanitiser is a static call WPCS cannot
follow.

---

## What is not covered, and why

* **Visual regression.** No baseline screenshots. The CLS measurement and the
  admin-notice checks catch the failures that matter most here; pixel diffing a
  demo store would mostly generate churn.
* **Manual accessibility.** axe-core covers what a machine can check. Keyboard
  traps, focus order, screen-reader announcements and the actual usability of
  the filter drawer have not been tested by a person.
* **Load testing.** Query counts are measured; concurrency is not.
* **Cross-browser.** Playwright runs Chromium only.
* **Payment gateways.** Checkout is exercised with cash-on-delivery. Testing a
  real gateway needs real credentials, which do not belong in a repository.

## Running everything

```bash
npm run lint
npm run test:unit
wp eval-file bin/integration-tests.php
npm run test:e2e
npm run test:admin
npm run test:vitals
npm run test:a11y
```

The e2e suites need the store running (`php -S localhost:8088 -t ~/wp-demo
~/wp-demo/router.php`) and create a real order each time, which is expected — the
seeder is idempotent and `wp bhc demo reset --yes` clears everything.
