<?php

return [
    'attempt_identifier_retention_days' => (int) env('DIGITAL_ATTEMPT_IDENTIFIER_RETENTION_DAYS', 90),
    'scrub_batch' => (int) env('DIGITAL_SCRUB_BATCH', 500),
    'scrub_max_batches' => (int) env('DIGITAL_SCRUB_MAX_BATCHES', 10),
];
