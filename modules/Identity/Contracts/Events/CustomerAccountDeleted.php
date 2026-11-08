<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts\Events;

/** The customer erased their account; extensions keeping data keyed by the account id should erase it too. */
final readonly class CustomerAccountDeleted
{
    public function __construct(public int $customerAccountId, public \DateTimeImmutable $deletedAt) {}
}
