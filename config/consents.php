<?php

return [
    'withdrawn_evidence_retention_years' => (int) env('CONSENTS_WITHDRAWN_RETENTION_YEARS', 6),
    'prune_batch' => (int) env('CONSENTS_PRUNE_BATCH', 200),
    'prune_max_batches' => (int) env('CONSENTS_PRUNE_MAX_BATCHES', 10),
];
