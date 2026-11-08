<?php

return [
    'source_unreachable' => 'Could not reach GitHub. Try again later.',
    'signature_invalid' => 'The release signature is invalid, so the release was rejected.',
    'manifest_invalid' => 'The release description is damaged, so the release was rejected.',
    'unexpected_error' => 'An unexpected error occurred.',
    'incompatible' => 'This server does not meet the release requirements.',
    'maintenance_already_active' => 'Maintenance mode is already on. Turn it off and try again.',
    'backup_failed' => 'The database backup could not be created.',
    'backup_unwritable' => 'The backup directory is not writable.',
    'archive_too_large' => 'The release archive is larger than the allowed limit.',
    'archive_unreadable' => 'The downloaded archive is corrupted.',
    'checksum_invalid' => 'The release checksum has an invalid format.',
    'checksum_mismatch' => 'The downloaded archive does not match the published checksum.',
    'files_missing' => 'The archive is missing files listed in the release manifest.',
    'staging_unwritable' => 'The archive cannot be unpacked into the working directory.',
    'unexpected_entry' => 'The archive contains files outside the release manifest.',
    'swap_failed' => 'Files could not be replaced. They were restored to the previous version.',
    'restore_failed' => 'The previous files could not be restored.',
    'migration_failed' => 'The database migration failed. The store stays in maintenance mode — restore the backup.',
    'post_update_failed' => 'The cache could not be cleared after the update.',
    'integrity_failed' => 'Files on the server differ from the release. The store stays in maintenance mode.',
];
