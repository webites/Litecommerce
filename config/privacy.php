<?php

return [
    'order_data_retention_years' => (int) env('PRIVACY_ORDER_DATA_RETENTION_YEARS', 5),
    'anonymize_batch' => (int) env('PRIVACY_ANONYMIZE_BATCH', 100),
    'anonymize_max_batches' => (int) env('PRIVACY_ANONYMIZE_MAX_BATCHES', 10),
];
