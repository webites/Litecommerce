<?php

return [
    'private_disk' => env('EMAIL_PRIVATE_DISK', 'local'),
    'attachment_directory' => 'email-attachments',
    'orphan_grace_hours' => (int) env('EMAIL_ORPHAN_GRACE_HOURS', 24),
    'history_retention_days' => (int) env('EMAIL_HISTORY_RETENTION_DAYS', 365),
    'history_prune_batch' => (int) env('EMAIL_HISTORY_PRUNE_BATCH', 100),
    'history_prune_max_batches' => (int) env('EMAIL_HISTORY_PRUNE_MAX_BATCHES', 10),
    'lease_seconds' => (int) env('EMAIL_LEASE_SECONDS', 120),
    'max_attempts' => (int) env('EMAIL_MAX_ATTEMPTS', 6),
    'backoff_seconds' => [60, 300, 1800, 7200, 43200],
    'cron_batch' => (int) env('EMAIL_CRON_BATCH', 25),
    'cron_time_limit' => (int) env('EMAIL_CRON_TIME_LIMIT', 50),
    'traffic_batch' => (int) env('EMAIL_TRAFFIC_BATCH', 1),
    'traffic_time_limit' => (int) env('EMAIL_TRAFFIC_TIME_LIMIT', 2),
    'smtp_timeout' => (int) env('EMAIL_SMTP_TIMEOUT', 15),
];
