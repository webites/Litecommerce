<?php

return [
    'default_ttl' => (int) env('OBJECT_CACHE_DEFAULT_TTL', 3600),
    'max_ttl' => (int) env('OBJECT_CACHE_MAX_TTL', 86400),
    'generation_ttl' => (int) env('OBJECT_CACHE_GENERATION_TTL', 604800),
];
