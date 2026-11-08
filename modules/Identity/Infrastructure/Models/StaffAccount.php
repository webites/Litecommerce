<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Infrastructure\Models;

use Database\Factories\StaffAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Litecommerce\Modules\Email\Contracts\EmailEnvelope;
use Litecommerce\Modules\Email\Contracts\TransactionalEmailQueue;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;

#[Fillable(['name', 'email', 'password', 'role', 'permission_overrides', 'is_active', 'deactivated_at'])]
#[Hidden([
    'password',
    'remember_token',
    'two_factor_secret',
    'two_factor_recovery_codes',
])]
final class StaffAccount extends Authenticatable
{
    /** @use HasFactory<StaffAccountFactory> */
    use HasFactory, Notifiable;

    protected $table = 'staff_accounts';

    public function setEmailAttribute(string $email): void
    {
        $this->attributes['email'] = Str::lower(trim($email));
    }

    public function sendPasswordResetNotification($token): void
    {
        app(TransactionalEmailQueue::class)->enqueue(new EmailEnvelope(
            'core.staff_password_reset',
            $this->email,
            $this->name,
            (string) config('app.locale', 'pl'),
            [
                'store_name' => app(StoreSettingsReader::class)->current()->storeName,
                'reset_url' => route('password.reset', ['token' => $token, 'email' => $this->email]),
            ],
            'staff-password-reset:'.hash('sha256', $this->email.':'.$token),
            'staff_account',
            (string) $this->getKey(),
        ));
    }

    protected static function newFactory(): StaffAccountFactory
    {
        return StaffAccountFactory::new();
    }

    public function setPermissionOverridesAttribute(array $overrides): void
    {
        foreach ($overrides as $key => $value) {
            if (! is_string($key) || ! is_bool($value)) {
                throw new InvalidArgumentException('Nadpisania uprawnień muszą być mapą kluczy na wartości boolean.');
            }
        }

        $this->attributes['permission_overrides'] = json_encode($overrides, JSON_THROW_ON_ERROR);
    }

    protected static function booted(): void
    {
        self::updating(function (self $account): void {
            if ($account->isDirty(['role', 'permission_overrides'])) {
                $account->permission_revision = ((int) $account->getOriginal('permission_revision')) + 1;
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => SystemStaffRole::class,
            'permission_overrides' => 'array',
            'permission_revision' => 'integer',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_used_timestep' => 'integer',
        ];
    }
}
