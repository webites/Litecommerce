<?php

return [
    'deferred_webhook_max_body_bytes' => (int) env('PAYMENTS_DEFERRED_WEBHOOK_MAX_BODY_BYTES', 65536),
    'deferred_webhook_max_pending' => (int) env('PAYMENTS_DEFERRED_WEBHOOK_MAX_PENDING', 5000),
    'deferred_webhook_max_attempts' => (int) env('PAYMENTS_DEFERRED_WEBHOOK_MAX_ATTEMPTS', 5),
    'deferred_webhook_batch' => (int) env('PAYMENTS_DEFERRED_WEBHOOK_BATCH', 50),
    'deferred_webhook_claim_timeout_minutes' => (int) env('PAYMENTS_DEFERRED_WEBHOOK_CLAIM_TIMEOUT_MINUTES', 15),
    'deferred_webhook_retention_days' => (int) env('PAYMENTS_DEFERRED_WEBHOOK_RETENTION_DAYS', 30),
];
