<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

final readonly class AdministratorData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $hashedPassword,
    ) {}
}
