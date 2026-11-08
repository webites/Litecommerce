<?php

return [
    'retention_months' => (int) env('AUDIT_RETENTION_MONTHS', 24),
    'identifier_retention_days' => (int) env('AUDIT_IDENTIFIER_RETENTION_DAYS', 90),
    'prune_batch' => (int) env('AUDIT_PRUNE_BATCH', 200),
    'prune_max_batches' => (int) env('AUDIT_PRUNE_MAX_BATCHES', 10),
];
