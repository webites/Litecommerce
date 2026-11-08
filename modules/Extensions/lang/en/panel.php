<?php

return [
    'index' => [
        'title' => 'Extensions',
        'description' => 'Installed from a local ZIP package — has core-level access, like trusted application code.',
        'upload_card_title' => 'Upload package',
        'upload_card_description' => 'A ZIP file with manifest.json at the root. After upload the extension is disabled — install it from its details page.',
        'upload_button' => 'Upload ZIP',
        'untrusted_ack' => 'I understand a plugin is PHP code with full access to the store and its authenticity is not verified. I only upload packages from authors I trust.',
        'installed_card_title' => 'Installed extensions',
        'empty_title' => 'No extensions',
        'empty_description' => 'Upload your first ZIP package above.',
        'version_prefix' => 'version',
        'details' => 'Details',
    ],
    'status' => [
        'winding_down' => 'Winding down',
        'disabled' => 'Disabled',
        'enabled' => 'Enabled',
        'not_installed' => 'Not installed',
    ],
    'show' => [
        'identifier' => 'Identifier: :id',
        'state_title' => 'State',
        'status_label' => 'Status',
        'secret_saved' => 'Saved — leave empty to keep',
        'version_label' => 'Version',
        'sha256_label' => 'Package SHA-256',
        'dependencies_label' => 'Dependencies',
        'install' => 'Install',
        'enable' => 'Enable',
        'disable' => 'Disable',
        'uninstall' => 'Uninstall',
        'uninstall_confirm' => 'Uninstall ":name"? The plugin author decides the fate of its data.',
        'settings_title' => 'Settings',
        'save' => 'Save',
    ],
];
