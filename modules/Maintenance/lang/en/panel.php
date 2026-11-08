<?php

return [
    'title' => 'Maintenance mode',
    'description' => 'Shows customers a maintenance page and blocks purchases. The panel, staff sign-in and payment webhooks keep working; stored webhooks are processed once maintenance is turned off.',
    'active_manual' => 'Maintenance mode is on. Customers see the maintenance page.',
    'active_update' => 'The store is being updated. Customers see the maintenance page.',
    'message_label' => 'Message for customers (optional)',
    'message_help' => 'When empty, customers see the default message.',
    'enable' => 'Turn on maintenance mode',
    'enable_confirm' => 'Turn on maintenance mode? Customers will not be able to browse the store or place orders.',
    'disable' => 'Turn off maintenance mode',
    'banner_manual' => 'Maintenance mode is on — customers see the maintenance page and purchases are blocked.',
    'banner_update' => 'The store is being updated — customers see the maintenance page and purchases are blocked.',
    'enabled' => 'Maintenance mode is on. Customers see the maintenance page and purchases are blocked.',
    'disabled' => 'Maintenance mode is off.',
    'already_active' => 'Maintenance mode is already on.',
    'not_active' => 'Maintenance mode is not on.',
];
