<?php

return [
    'retry_after_seconds' => (int) env('MAINTENANCE_RETRY_AFTER_SECONDS', 300),
    'message_max_length' => 500,
];
