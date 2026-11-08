<?php

return [
    // Disk name from config/filesystems.php. Swapping storage providers
    // (e.g. a future S3 plugin) means pointing this at a different disk,
    // not changing any module code — see docs/knowledge-base/09-presentation.md#media-i-storage.
    'disk' => env('MEDIA_DISK', 'public'),

    'max_size_kb' => (int) env('MEDIA_MAX_SIZE_KB', 8192),

    // Disk for PrivateFileLibrary (digital product files) — never the
    // public disk, never symlinked/mirrored into the web root. Local's
    // default root is storage/app/private (config/filesystems.php).
    'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),

    'private_max_size_kb' => (int) env('MEDIA_PRIVATE_MAX_SIZE_KB', 512000),

    // How many private files the scheduled integrity check re-hashes per run.
    'verify_batch' => (int) env('MEDIA_VERIFY_BATCH', 10),

    // Hand the byte transfer to the web server: none | x-sendfile | x-accel-redirect | litespeed.
    'download_offload' => env('MEDIA_DOWNLOAD_OFFLOAD', 'none'),

    // Internal nginx location mapped to the private disk root (x-accel-redirect only).
    'download_offload_prefix' => env('MEDIA_DOWNLOAD_OFFLOAD_PREFIX', '/protected-private'),
];
