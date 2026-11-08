<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

final class AccountDeletionRefusedException extends \DomainException
{
    public const INVALID_PASSWORD = 'invalid_password';

    public const PURCHASE_BLOCKED = 'purchase_blocked';

    public const INVALID_TOKEN = 'invalid_token';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
