<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Infrastructure;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Litecommerce\Modules\Identity\Contracts\StaffCredentialVerifier;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;

final class EloquentStaffCredentialVerifier implements StaffCredentialVerifier
{
    public function verify(string $email, string $password): ?Authenticatable
    {
        $account = StaffAccount::query()
            ->where('email', Str::lower(trim($email)))
            ->where('is_active', true)
            ->first();

        if ($account === null || ! Hash::check($password, $account->password)) {
            return null;
        }

        if (Hash::needsRehash($account->password)) {
            $account->forceFill(['password' => Hash::make($password)])->save();
        }

        return $account;
    }
}
