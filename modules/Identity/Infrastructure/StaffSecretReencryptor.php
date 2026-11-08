<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Infrastructure;

use App\Support\Secrets\ColumnReencryptor;
use App\Support\Secrets\SecretReencryptor;

final class StaffSecretReencryptor implements SecretReencryptor
{
    public function reencrypt(int $batchSize): array
    {
        return ColumnReencryptor::table('staff_accounts', 'id', ['two_factor_secret', 'two_factor_recovery_codes'], $batchSize);
    }
}
