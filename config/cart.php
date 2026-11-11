<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Guest cart cookie
    |--------------------------------------------------------------------------
    |
    | A guest cart is identified by a random token in an encrypted,
    | httpOnly cookie (EncryptCookies already covers every cookie in the
    | 'web' middleware group) — never a raw id in the request, to keep a
    | guest from guessing another guest's cart.
    |
    */

    'guest_cookie_name' => env('CART_GUEST_COOKIE_NAME', 'lc_cart_token'),

    'guest_cookie_ttl_days' => (int) env('CART_GUEST_COOKIE_TTL_DAYS', 30),

    // Rejection codes that signal a broken checkout state rather than a customer mistake; they are written to the log.
    'monitored_rejections' => ['delivery_method_required', 'pickup_point_required'],

    /*
    |--------------------------------------------------------------------------
    | Abandonment
    |--------------------------------------------------------------------------
    |
    | docs/knowledge-base/05-orders.md: "Po domyślnie 24 h bez aktywności
    | koszyk porzucony (konfigurowalne); powrót aktywuje." Computed lazily
    | on read (no cron dependency), mirroring how Warehouse reconciles
    | expired reservations opportunistically rather than relying on one.
    |
    */

    'abandon_after_hours' => (int) env('CART_ABANDON_AFTER_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Carts idle longer than this are removed together with their checkout
    | drafts by the cart:prune-inactive task. A guest cart matches the
    | lifetime of its cookie; a signed-in customer's cart lives longer.
    |
    */

    'guest_retention_days' => (int) env('CART_GUEST_RETENTION_DAYS', 30),

    'customer_retention_days' => (int) env('CART_CUSTOMER_RETENTION_DAYS', 90),

    'prune_batch' => (int) env('CART_PRUNE_BATCH', 200),

    'prune_max_batches' => (int) env('CART_PRUNE_MAX_BATCHES', 10),

];
