# Testing

Three layers, each answering a question the others cannot.

| Layer | Count | Runtime | Question it answers |
|---|---:|---:|---|
| **Unit** (PHPUnit) | 73 tests, 136 assertions | ~0.02 s | Is the logic correct in isolation? |
| **Integration** (`wp eval-file`) | 62 assertions | ~2 s | Does it behave correctly *inside a real WooCommerce store*? |
| **End-to-end** (Playwright) | 4 suites, 50 checks | minutes | Can a person actually buy something, and can everyone? |

Plus **standards**: `npm run lint` runs stylelint, eslint and phpcs, in that
order, all three clean.

---

## Unit tests

`wp-content/plugins/bhc-commerce-core/tests/Unit/`, run with
`npm run test:unit` (or `composer test` from the plugin directory).

They run in **16 milliseconds with no WordPress loaded**. `tests/bootstrap.php`
stubs the handful of WordPress functions the units under test touch
(`sanitize_text_field`, `absint`, `wp_kses_post`, `wp_json_encode`, …). That
constraint is the point: anything that cannot be tested without WordPress is
either genuinely integration code, or badly factored.

| Suite | Covers |
|---|---|
| `ContainerTest` | Bindings, singletons, aliases, autowiring, circular-dependency detection |
| `CacheManagerTest` | Key building, group versioning, `remember()` misses, TTL expiry, **a 0 TTL meaning no expiry**, the `MISS` sentinel vs a cached `false` |
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

A second store bug surfaced later, from the same corner of the TTL contract.
`CacheManager` writes each group's version entry with a TTL of `0`, meaning *no
expiry*, so the version outlives the entries it invalidates. `TransientStore`
substituted its own default instead, so on the fallback path that runs on any
host without an object cache — most of them — a group flush quietly undid itself
about an hour later and every orphaned entry came back. Fixed, along with
`set_transient`'s habit of leaving a stale timeout row behind. Two tests now
lock the contract: `test_a_zero_ttl_never_expires` (`0` is permanent, `-1` is
already dead) and `test_flushing_a_group_survives_the_version_entry_ttl`, which
builds a fresh `CacheManager` the way a later request would and asserts it reads
the bumped version rather than falling back to `1` and finding the old entry
still there.

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
| **SEO output** | JSON-LD emitted, `Product` node present, `BreadcrumbList` present, exactly one canonical, OG price tags, **exactly one JSON-LD block on the page**, `@id` uses the canonical host, wishlist page carries `noindex` |

The query-efficiency assertions are written as **relationships, not fixed
numbers** — "primed must cost materially less than unprimed, and under three
queries per card" — so they stay meaningful as the catalogue grows instead of
becoming a number nobody dares change.

`declare(strict_types=1)` is deliberately absent from this file: `wp eval-file`
evaluates it inside an existing scope where the declaration is illegal.

---

## End-to-end

Playwright against a real browser, four suites:

```bash
npm run test:e2e      # purchase-flow.mjs
npm run test:admin    # admin-screens.mjs
npm run test:vitals   # web-vitals.mjs
npm run test:a11y     # accessibility.mjs
```

All four share `tests/e2e/browser.mjs`, which resolves the Chromium binary
(this container ships one at a fixed path and blocks Playwright's own download;
CI installs it normally) and reads the target from `BHC_BASE_URL`, defaulting to
`http://localhost:8088`. They also assume the seeded demo catalogue — the vitals
and accessibility suites navigate to specific product and category slugs.

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
printing the HTTP status and whether the body contains a PHP fatal, warning or
notice, then opens the product editor and reports whether the plugin's data
panel is present. JS errors are collected across all of it, and screenshots are
written to `/tmp/shots/`.

Unlike the other three, this suite **reports rather than gates**: it prints its
findings and always exits `0`, so a regression here shows in the output but does
not fail a run. Read it, do not just check its exit code.

### `web-vitals.mjs` — 6 checks

Measures **CLS and LCP** with a `PerformanceObserver` on the home, shop and
product pages at 1440 px and 390 px, scrolling each document first so a shift
caused by a late-loading image still counts. Fails the run if CLS exceeds 0.1,
if LCP exceeds 2.5 s, or if the page logs a JavaScript error.

Current result: **CLS 0.0000 on all six**, stable across consecutive runs, with
LCP typically 180–360 ms. The LCP budget is deliberately loose against PHP's
built-in server, which serialises subresources: the point is catching a
regression, not benchmarking the host. See [performance.md](performance.md) for what those
numbers rest on.

It caught a real defect the moment it was written: the product page was firing
two requests to `secure.gravatar.com` for review avatars, sending the visitor's
IP and a hash of each commenter's e-mail to a third party and blocking on a
round trip for what would render as a generic silhouette. Avatars are now
inline SVG monograms generated locally — no request, no third party.

### `accessibility.mjs` — 24 renders

Runs **axe-core** over twelve pages — home, shop, product, category, cart,
my-account, wishlist, blog, about, FAQ, search, 404 — at 1440 px and 390 px,
against `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa` and `best-practice`. It fails
on any violation at serious or critical impact and prints everything else,
grouped by rule.

Current result: **no violations at any impact level, on all 24 renders.**

It did not start there. The first run reported **686 serious colour-contrast
violations** from a single root cause: the muted text token `#857a6c` measured
3.49–4.14:1 against the three paper tones, failing AA for body text everywhere
it appeared. The defects that came out of that run:

* the muted token, now `#706557` (4.72–5.60:1), with a separate inverse token
  for the same role on the dark banner;
* low-stock messaging, which borrowed the decorative brass `#a9832f` at
  2.92–3.46:1 — the copy a shopper most needs to read. It has its own token now,
  `#7d5f14`, same hue at 4.95–5.87:1;
* field borders, which used the decorative rule colour at 1.73:1. WCAG 1.4.11
  holds a UI component boundary to 3:1, and the border is the only thing marking
  where an input begins; `#9e8f73` now does that job;
* disabled buttons, which used `opacity: 0.5` — the obvious way to say
  "disabled" and the wrong one, since it drags label and background toward each
  other and put the disabled add-to-cart at 3.15:1. An explicit colour pair
  reads the same and measures 4.81:1;
* heading order on the blog and search listings, where `h3` card titles followed
  the page `h1` with nothing between them.

Two ARIA defects were repaired in the same pass. The product tabs carried
`role="tab"` with no `aria-selected`, so the theme now overrides WooCommerce's
`tabs.php` with the whole pattern — `aria-selected`, roving `tabindex`, hidden
panels, Home/End keys, deep links. And the sticky add-to-cart bar was
`aria-hidden` while its button stayed tabbable, which puts a focus stop in a
place a screen reader has been told does not exist; it carries `inert` now, set
and removed alongside the visibility toggle.

Automated checks cover roughly a third of WCAG. This is the mechanical third —
labels, contrast, landmarks, heading structure, ARIA misuse. It does not replace
a keyboard and screen-reader pass, and this build has not had one.

---

## Coding standards

```bash
npm run lint            # stylelint, then eslint, then phpcs
npm run lint:css        # stylelint over the theme SCSS
npm run lint:js         # eslint over theme + plugin JS, tests/, tools/
npm run lint:php        # phpcs
npm run lint:php:fix    # phpcbf
```

The three run chained with `&&`, so `npm run lint` stops at the first one that
fails and you may need more than one pass to see everything.

**eslint and stylelint are new, and only now real.** Both were declared in
`package.json` for a long time with no config and no install, which meant
`npm run lint` failed outright rather than reporting anything — a lint script
that has never run is worse than no lint script, because it reads like coverage.
They are installed with configs now, and both are clean:

* `eslint.config.mjs` — flat config over `wp-content/**/assets/js/**/*.js`,
  `tests/**/*.mjs` and `tools/**/*.mjs`. Correctness rules (`no-undef`,
  `no-unused-vars`, `eqeqeq`, `prefer-const`) plus `@stylistic` rules matching
  the tab-indented, spaced-parens house style the PHP already uses, so the two
  halves of the codebase do not read as different projects. The globals list is
  written out by hand — the browser surface the storefront actually touches, the
  two `wp_localize_script` objects, `axe`, and `process` for the harnesses. That
  costs a line whenever a module reaches for something new, and buys a real
  `no-undef`: a typo'd global is an error rather than an assumed browser API.
* `.stylelintrc.json` — `stylelint-config-standard-scss` with the naming rules
  (`selector-class-pattern`, `custom-property-pattern`, the SCSS `$variable` and
  `@mixin` patterns) switched off, because the theme's conventions predate the
  config and renaming everything to satisfy a linter is churn, not quality. A
  handful of stylistic rules are off for the same reason. What is left is the
  part worth keeping: invalid values, duplicate properties, malformed selectors.

`phpcs.xml.dist` runs `WordPress` plus `PHPCompatibility` (`testVersion 8.2-`)
over the plugin, theme and `bin/` — 216 PHP files, **zero violations**.

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
sniff and the reason, rather than a blanket disable. There are 94 of those and
no `phpcs:disable` anywhere; most are custom-table queries stating why the table
name is safe to interpolate. The largest single cluster is nine, in two admin
save handlers where the sanitiser is a static call WPCS cannot follow.

---

## What is not covered, and why

* **Visual regression.** No baseline screenshots. The CLS measurement and the
  admin-screen output catch the failures that matter most here; pixel diffing a
  demo store would mostly generate churn.
* **Admin regressions, automatically.** `admin-screens.mjs` prints its findings
  and exits `0`. Somebody has to read the output.
* **Manual accessibility.** axe-core covers what a machine can check. Keyboard
  traps, focus order, screen-reader announcements and the actual usability of
  the filter drawer have not been tested by a person.
* **Load testing.** Query counts are measured; concurrency is not.
* **Cross-browser.** Playwright runs Chromium only.
* **Payment gateways.** Checkout is exercised with cash-on-delivery, which the
  seeder enables along with bank transfer, both labelled "(demo)". Testing a
  real gateway needs real credentials, which do not belong in a repository.
* **The SQLite path, end to end.** The browser suites have been run against the
  MySQL + Redis build. SQLite remains supported for a no-database-server setup
  (see [hosting.md](hosting.md)), but it needs `tools/dev-mu-plugins/bhc-sqlite-dev.php` to
  get past WooCommerce's stock-reservation SQL, and it is a different code path.

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

The four browser suites need the store running — start it with a worker pool:

```bash
PHP_CLI_SERVER_WORKERS=6 php -S localhost:8088 -t ~/wp-demo ~/wp-demo/router.php
```

PHP's built-in server runs a single process by default, so a browser suite that
opens several pages at once queues behind itself and times out in ways that look
exactly like real failures — a suite reporting a 30-second navigation timeout is
usually the server, not the site. `PHP_CLI_SERVER_WORKERS` gives it a small pool
and the symptom goes away. Set it before you spend an afternoon debugging a
phantom regression.

`purchase-flow.mjs` places a real order every run, which is expected: the seeder
is idempotent and `wp bhc demo reset --yes` clears everything.
