<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

interface StaffTwoFactorManager
{
    public function beginSetup(int $staffAccountId): TwoFactorSetup;

    public function confirmSetup(int $staffAccountId, string $code): bool;

    public function disable(int $staffAccountId): void;

    public function isEnabled(int $staffAccountId): bool;

    public function isPending(int $staffAccountId): bool;

    public function verifyChallenge(int $staffAccountId, string $code): bool;
}
