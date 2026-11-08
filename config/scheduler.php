<?php

return [
    'mode' => env('SCHEDULER_MODE', 'traffic'),
    'lease_seconds' => (int) env('SCHEDULER_LEASE_SECONDS', 120),
    'failure_retry_seconds' => (int) env('SCHEDULER_FAILURE_RETRY_SECONDS', 60),
    'system_limit' => (int) env('SCHEDULER_SYSTEM_LIMIT', 20),
    'system_time_limit' => (int) env('SCHEDULER_SYSTEM_TIME_LIMIT', 50),
    'traffic_interval_seconds' => (int) env('SCHEDULER_TRAFFIC_INTERVAL_SECONDS', 30),
    'traffic_limit' => (int) env('SCHEDULER_TRAFFIC_LIMIT', 2),
    'traffic_time_limit' => (int) env('SCHEDULER_TRAFFIC_TIME_LIMIT', 3),
];
