# Changelog

All notable changes to Litecommerce are listed here. The project follows
[Semantic Versioning](https://semver.org/). While the version is below 1.0.0,
minor versions may contain breaking changes.

## 0.1.1 - 2026-11-08

- Panel footer with a thank-you note and the installed version.
- Panel messages: a banner on every panel page and a message list on the dashboard (an available update is announced until it is installed), plus a registry for modules and plugins to publish their own messages.
- New database table `panel_message_reads` (added by the update migration).

## 0.1.0 - 2026-11-08

First public release.

- Web installer and setup wizard for ordinary shared hosting.
- Panel and storefront in Polish and English.
- Catalog with variants, product types, categories, attributes and media.
- Pricing, VAT, coupons, price adjustments, cart and checkout.
- Orders with price snapshots, offline payment methods in core, returns and
  refunds.
- Stock, reservations, delivery methods and digital products.
- CMS pages, menus, blog, redirects and search.
- Staff roles with per-permission overrides, audit log.
- Themes and plugins installed from ZIP packages.
- Background tasks without a permanent worker (traffic mode or cron).
- Maintenance mode, database and file backup and verification.
- Signed releases and core updates from the panel.
