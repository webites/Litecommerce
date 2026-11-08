<?php

return [
    'default_payment_method' => env('ORDERS_DEFAULT_PAYMENT_METHOD', 'core.bank_transfer'),
    'online_reservation_minutes' => (int) env('ORDERS_ONLINE_RESERVATION_MINUTES', 60),
    'payment_link_reservation_hours' => (int) env('ORDERS_PAYMENT_LINK_RESERVATION_HOURS', 4),
    'bank_transfer_deadline_days' => (int) env('ORDERS_BANK_TRANSFER_DEADLINE_DAYS', 5),
    'uncertain_payment_extension_minutes' => (int) env('ORDERS_UNCERTAIN_PAYMENT_EXTENSION_MINUTES', 15),
    'payment_reconcile_batch' => (int) env('ORDERS_PAYMENT_RECONCILE_BATCH', 50),
    'guest_token_ttl_days' => (int) env('ORDERS_GUEST_TOKEN_TTL_DAYS', 365),
    'guest_token_prune_batch' => (int) env('ORDERS_GUEST_TOKEN_PRUNE_BATCH', 500),
    'guest_token_prune_max_batches' => (int) env('ORDERS_GUEST_TOKEN_PRUNE_MAX_BATCHES', 10),
    'pickup_reminder_days' => (int) env('ORDERS_PICKUP_REMINDER_DAYS', 2),
    'pickup_expiry_batch' => (int) env('ORDERS_PICKUP_EXPIRY_BATCH', 50),
];
