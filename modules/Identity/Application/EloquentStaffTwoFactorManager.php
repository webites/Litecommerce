<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Application;

use DomainException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Identity\Contracts\StaffTwoFactorManager;
use Litecommerce\Modules\Identity\Contracts\TwoFactorSetup;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use PragmaRX\Google2FA\Google2FA;

final readonly class EloquentStaffTwoFactorManager implements StaffTwoFactorManager
{
    public function __construct(
        private Google2FA $totp,
        private AuditLogger $auditLogger,
    ) {}

    public function beginSetup(int $staffAccountId): TwoFactorSetup
    {
        $account = $this->account($staffAccountId);

        if ($account->two_factor_confirmed_at !== null) {
            throw new DomainException('Najpierw wyłącz aktywne uwierzytelnianie dwuskładnikowe.');
        }

        $secret = $this->totp->generateSecretKey(32);
        $recoveryCodes = $this->newRecoveryCodes();

        $account->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(
                static fn (string $code): string => Hash::make($code),
                $recoveryCodes,
            ),
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_timestep' => null,
        ])->save();

        return new TwoFactorSetup(
            secret: $secret,
            otpAuthUri: $this->totp->getQRCodeUrl(config('app.name', 'Litecommerce'), $account->email, $secret),
            recoveryCodes: $recoveryCodes,
        );
    }

    public function confirmSetup(int $staffAccountId, string $code): bool
    {
        $account = $this->account($staffAccountId);
        $timestep = $this->validTotpTimestep($account, $code);

        if ($timestep === null) {
            return false;
        }

        $account->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_timestep' => $timestep,
        ])->save();

        $this->audit($account, 'identity.staff.two_factor_enabled');

        return true;
    }

    public function disable(int $staffAccountId): void
    {
        $account = $this->account($staffAccountId);
        $account->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_timestep' => null,
        ])->save();

        $this->audit($account, 'identity.staff.two_factor_disabled');
    }

    public function isEnabled(int $staffAccountId): bool
    {
        $account = $this->account($staffAccountId);

        return $account->two_factor_secret !== null
            && $account->two_factor_confirmed_at !== null;
    }

    public function isPending(int $staffAccountId): bool
    {
        $account = $this->account($staffAccountId);

        return $account->two_factor_secret !== null
            && $account->two_factor_confirmed_at === null;
    }

    public function verifyChallenge(int $staffAccountId, string $code): bool
    {
        $account = $this->account($staffAccountId);

        if (! $this->isEnabledAccount($account)) {
            return false;
        }

        if (preg_match('/^\d{6}$/', $code) === 1) {
            $timestep = $this->validTotpTimestep($account, $code);

            if ($timestep === null) {
                return false;
            }

            $account->forceFill(['two_factor_last_used_timestep' => $timestep])->save();

            return true;
        }

        return $this->consumeRecoveryCode($account, $code);
    }

    private function account(int $staffAccountId): StaffAccount
    {
        return StaffAccount::query()->findOrFail($staffAccountId);
    }

    private function isEnabledAccount(StaffAccount $account): bool
    {
        return $account->two_factor_secret !== null
            && $account->two_factor_confirmed_at !== null;
    }

    private function validTotpTimestep(StaffAccount $account, string $code): ?int
    {
        if ($account->two_factor_secret === null || preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }

        if ($account->two_factor_last_used_timestep === null) {
            return $this->totp->verifyKey($account->two_factor_secret, $code, 1)
                ? $this->totp->getTimestamp()
                : null;
        }

        $verified = $this->totp->verifyKeyNewer(
            $account->two_factor_secret,
            $code,
            $account->two_factor_last_used_timestep,
            1,
        );

        return is_int($verified) ? $verified : null;
    }

    private function consumeRecoveryCode(StaffAccount $account, string $code): bool
    {
        /** @var list<string> $hashes */
        $hashes = $account->two_factor_recovery_codes ?? [];

        foreach ($hashes as $index => $hash) {
            if (! Hash::check($code, $hash)) {
                continue;
            }

            unset($hashes[$index]);
            $account->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();
            $this->audit($account, 'identity.staff.two_factor_recovery_code_used');

            return true;
        }

        return false;
    }

    /** @return list<string> */
    private function newRecoveryCodes(): array
    {
        return array_map(
            static fn (): string => Str::lower(Str::random(10).'-'.Str::random(10)),
            range(1, 8),
        );
    }

    private function audit(StaffAccount $account, string $action): void
    {
        $this->auditLogger->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $account->getKey(),
            action: $action,
            subjectType: 'staff_account',
            subjectId: (string) $account->getKey(),
        ));
    }
}
