<?php

return [
    // GitHub repository (owner/name) whose stable tags are offered as core updates.
    'repository' => env('UPDATES_REPOSITORY', 'webites/Litecommerce'),

    // Base64 Ed25519 public keys accepted for release signatures: the built-in release key plus optional extras from the environment.
    'trusted_public_keys' => array_values(array_unique(array_merge(
        ['+UfucFhfzuy3IU7BW18DdkEhQB9mFi+YAkZzAyYGZqs='],
        array_filter(array_map('trim', explode(',', (string) env('UPDATES_TRUSTED_PUBLIC_KEYS', '')))),
    ))),

    // Application paths an update replaces; the check warns when any of them cannot be written.
    'writable_paths' => ['app', 'bootstrap', 'config', 'database', 'lang', 'modules', 'public', 'resources', 'routes', 'vendor', 'storage/app'],

    // Paths an update never creates, replaces or deletes.
    'protected' => ['.env', 'storage', 'bootstrap/cache'],

    'step_budget_seconds' => (int) env('UPDATES_STEP_BUDGET_SECONDS', 20),

    'max_archive_bytes' => 250 * 1024 * 1024,
    'max_uncompressed_bytes' => 800 * 1024 * 1024,
    'max_compression_ratio' => 200,

    'minimum_free_bytes' => 300 * 1024 * 1024,

    'package' => [
        'exclude' => [
            '.git', '.github', '.idea', '.vscode', 'node_modules', 'tests', 'docker',
            '.env', 'storage/installed.lock', 'storage/maintenance.json',
            'public/hot', 'public/storage', 'phpunit.xml', 'phpstan.neon', 'CHANGELOG.md',
        ],
    ],
];
