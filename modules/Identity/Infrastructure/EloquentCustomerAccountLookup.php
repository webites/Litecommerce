<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Infrastructure;

use Litecommerce\Modules\Identity\Contracts\CustomerAccountLookup;
use Litecommerce\Modules\Identity\Infrastructure\Models\CustomerAccount;

final class EloquentCustomerAccountLookup implements CustomerAccountLookup
{
    public function emailFor(int $customerAccountId): ?string
    {
        return CustomerAccount::query()->whereKey($customerAccountId)->value('email');
    }

    public function emailsFor(array $customerAccountIds): array
    {
        if ($customerAccountIds === []) {
            return [];
        }

        return CustomerAccount::query()
            ->whereIn('id', $customerAccountIds)
            ->pluck('email', 'id')
            ->map(static fn ($email): string => (string) $email)
            ->all();
    }
}
