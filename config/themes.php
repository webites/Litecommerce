<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Package limits
    |--------------------------------------------------------------------------
    |
    | Same rationale as config/extensions.php — basic sanity limits only,
    | not a security boundary (see docs/knowledge-base/09-presentation.md).
    | A theme package tends to carry more (built CSS/JS/images) than a
    | typical plugin, hence the higher default size.
    |
    */

    'max_package_size_mb' => env('THEMES_MAX_PACKAGE_SIZE_MB', 100),

    'max_file_count' => env('THEMES_MAX_FILE_COUNT', 5000),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Extracted theme code lives on this private disk (Blade views, never
    | served directly) — only the theme's own assets/ subfolder is copied
    | out to a public disk on activation, see Infrastructure\ThemeAssetPublisher.
    |
    */

    'disk' => env('THEMES_DISK', 'local'),

    'path' => 'themes',

    /*
    |--------------------------------------------------------------------------
    | Published assets
    |--------------------------------------------------------------------------
    |
    | Where an activated theme's assets/ folder is published under the
    | public disk — served at /themes/<id>/... — same symlink-with-copy-
    | fallback approach as Media\Contracts\PublicStorageLinker (P1-003).
    |
    */

    'public_disk' => env('THEMES_PUBLIC_DISK', 'public'),

    'public_path' => 'themes',

];
