<?php

return [
    'setup_title' => 'Enable a payment method',
    'setup_description' => 'Enter a bank account for bank transfers or install a payment gateway. Pay on pickup is enabled in the delivery settings.',
    'settings' => [
        'on_delivery_title' => 'Pay on pickup and cash on delivery',
        'on_delivery_description' => 'These methods need no account or gateway. Enable them on the delivery method: “Customer can pay on pickup” (local pickup) or “Accepts cash on delivery (COD)” (courier). Customers see them at checkout only with such a delivery.',
        'on_delivery_action' => 'Open delivery settings',
        'title' => 'Payments',
        'description' => 'Bank transfer details and the customer-facing instructions shown for the bank transfer payment method.',
        'no_active_account_hides_transfer' => 'An inactive account hides bank transfer as a payment method.',
        'beneficiary_name' => 'Beneficiary name',
        'account_number' => 'Account number',
        'instructions_for_language' => 'Customer instructions — :language',
        'transfer_active' => 'Transfer active',
        'save_account' => 'Save account',
    ],
];
