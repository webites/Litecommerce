<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Infrastructure;

use Illuminate\Support\Str;
use Litecommerce\Modules\Identity\Contracts\CustomerPurchaseBlockChecker;
use Litecommerce\Modules\Identity\Contracts\PurchaseBlockStatus;
use Litecommerce\Modules\Identity\Infrastructure\Models\AccountPurchaseBlock;

final class EloquentCustomerPurchaseBlockChecker implements CustomerPurchaseBlockChecker
{
    public function forCustomerAccount(int $customerAccountId): PurchaseBlockStatus
    {
        $block = AccountPurchaseBlock::query()
            ->where('customer_account_id', $customerAccountId)
            ->whereNull('lifted_at')
            ->latest('blocked_at')
            ->first();

        return $block === null
            ? PurchaseBlockStatus::allowed()
            : PurchaseBlockStatus::blocked($block->reason);
    }

    public function isGuestEmailBlocked(string $email): bool
    {
        return AccountPurchaseBlock::query()
            ->whereNull('lifted_at')
            ->whereHas('customerAccount', fn ($query) => $query->where('email', Str::lower(trim($email))))
            ->exists();
    }
}
