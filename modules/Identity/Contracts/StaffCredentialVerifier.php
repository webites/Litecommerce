<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface StaffCredentialVerifier
{
    public function verify(string $email, string $password): ?Authenticatable;
}
