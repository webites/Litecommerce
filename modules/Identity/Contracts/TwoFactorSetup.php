<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

final readonly class TwoFactorSetup
{
    /** @param list<string> $recoveryCodes */
    public function __construct(
        public string $secret,
        public string $otpAuthUri,
        public array $recoveryCodes,
    ) {}
}
