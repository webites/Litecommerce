<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PIN attempt limiting
    |--------------------------------------------------------------------------
    |
    | "Opcjonalny PIN: hash i ograniczenie prób" (04-pricing-coupons.md).
    | After this many consecutive incorrect attempts the code locks until a
    | staff member resets it from the panel — there is no automatic
    | unlock-after-time in MVP, since a coupon PIN protects a shared code's
    | balance rather than an individual account.
    |
    */

    'max_pin_attempts' => 5,

    'reconcile_batch' => (int) env('COUPONS_RECONCILE_BATCH', 200),

];
