# Changelog

All notable changes to Litecommerce are listed here. The project follows
[Semantic Versioning](https://semver.org/). While the version is below 1.0.0,
minor versions may contain breaking changes.

## 0.1.5 - 2026-11-11

- Orders have a short, readable number (starting at 1001) instead of a long technical identifier. It is shown to customers (confirmation page, account, guest link, e-mails, bank transfer title) and to staff (orders list, order page, returns). A prefix such as "ORD-" can be set in Settings → Orders; a new prefix applies only to orders placed afterwards. The orders list can be searched by number or e-mail. The update migration numbers existing orders from 1001 in the order they were placed; custom e-mail templates should use `{{order_number}}` instead of `{{order_id}}`.
- The setup wizard has a new optional "Delivery" step: add local pickup with its first pickup point or a courier with a rate for one country. Delivery VAT follows the store's only tax rate when there is exactly one.
- Delivery settings: a method is saved with one Save button (rates, regions and pickup points together); creating a method is a short wizard with a preview of how it looks at checkout; a courier method is created together with its rates.
- Dates and times are shown in the store time zone (new setting in Settings → General), in the reader's language. Quantities, country names, addresses, phone numbers (international notation) and percentages are formatted the same way in the storefront, e-mails and panel. The order page in the panel shows the buyer's phone number.
- Security: updated Symfony components and league/commonmark to versions that fix published advisories.
- Narrow screens: the orders list shows cards; the setup wizard progress bar fits 480 px.
- New dependency: giggsey/libphonenumber-for-php-lite (phone number formatting).

## 0.1.4 - 2026-11-09

- Fixed checkout with local pickup: the pickup method is now ordered without the "Select a pickup point" error. A method with a single pickup point uses it automatically; with several points the customer still picks one.
- Checkout shows the address and instructions of each pickup point.
- Delivery settings: a pickup method is created together with its first pickup point in one window; fields explain themselves and show examples; pickup instructions (opening hours, how to collect) can be entered per language; a pickup method without an active point is marked as hidden from customers and cannot be enabled until a point exists; courier-only options (free shipping threshold, cash on delivery) are no longer shown for pickup.
- The dashboard has a "Start selling" checklist (first product, delivery method, payment method) that disappears when everything is set up.
- The Payments tab explains how to enable pay on pickup and cash on delivery.
- Product form: sections are grouped by importance (only the required ones are open), categories are a collapsible tree with a primary category, and a sticky save bar is always visible.
- Descriptive attributes can be managed in the panel (new "Attributes" screen with groups), are shown on the product page in a specification tab and can be used as storefront filters.
- New database table `attribute_groups` and attribute columns (added by the update migrations).

## 0.1.3 - 2026-11-08

- Fixed the panel jumping far down the page when a checkbox or radio button was clicked (for example in the product form): the whole page no longer scrolls.

## 0.1.2 - 2026-11-08

- The installer sets `APP_NAME` to Litecommerce when the environment file has none.
- Browser tab titles use the application name from the server instead of a build-time value (they ended with "Laravel" in release packages).
- Panel page headers: the title and its description now fit the top bar instead of being cut by its border; titles are one size on every screen.

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
