<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

interface CustomerAccountDeletion
{
    /**
     * Verifies the password and e-mails a single-use confirmation link; a
     * new request replaces the previous link.
     *
     * @throws AccountDeletionRefusedException
     */
    public function request(int $customerAccountId, string $password): void;

    /**
     * Deletes the account the link was issued for, with its profiles and
     * addresses. Returns the deleted account id.
     *
     * @throws AccountDeletionRefusedException
     */
    public function confirm(string $token): int;
}
