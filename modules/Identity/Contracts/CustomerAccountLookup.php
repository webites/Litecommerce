<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

interface CustomerAccountLookup
{
    public function emailFor(int $customerAccountId): ?string;

    /**
     * Bulk read for listings — never resolve a listing's emails with one
     * emailFor() call per row.
     *
     * @param  list<int>  $customerAccountIds
     * @return array<int, string> keyed by customer account id; unknown
     *                            ids are omitted
     */
    public function emailsFor(array $customerAccountIds): array;
}
