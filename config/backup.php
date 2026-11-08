<?php

return [
    'directory' => env('BACKUP_DIRECTORY', storage_path('app/backups')),
    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH'),
    'insert_batch_bytes' => (int) env('BACKUP_INSERT_BATCH_BYTES', 1048576),
    'min_free_space_mb' => (int) env('BACKUP_MIN_FREE_SPACE_MB', 10),
];
