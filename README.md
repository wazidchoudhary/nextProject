# Bone Horn Crafts

The Bone Horn Crafts store: a WordPress + WooCommerce build with a custom theme
and two custom plugins. Everything lives in [`bone-horn-crafts/`](bone-horn-crafts/) —
see [its README](bone-horn-crafts/README.md) for setup, deployment and the
command reference.

```
bone-horn-crafts/
  wp-content/
    themes/bhc-theme/                  Storefront theme
    plugins/bhc-commerce-core/         Merchandising, search, wishlist, jobs, CLI
    plugins/bhc-newsletter/            Double opt-in mailing list
  tests/                               PHPUnit and Playwright suites
  bin/                                 Setup and deployment scripts
  docs/                                Runbooks
```

A Next.js prototype previously occupied the repository root. It has been
removed; its history remains in the git log.
