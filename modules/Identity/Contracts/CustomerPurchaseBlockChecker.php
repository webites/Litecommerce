<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

interface CustomerPurchaseBlockChecker
{
    /** Includes the merchant-facing reason for an authenticated customer. */
    public function forCustomerAccount(int $customerAccountId): PurchaseBlockStatus;

    /** Never exposes whether an account exists or why it was blocked. */
    public function isGuestEmailBlocked(string $email): bool;
}
