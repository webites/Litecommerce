<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Litecommerce\Modules\Email\Contracts\EmailEnvelope;
use Litecommerce\Modules\Email\Contracts\TransactionalEmailQueue;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;

#[Fillable(['email', 'password'])]
#[Hidden(['password', 'remember_token'])]
final class CustomerAccount extends Authenticatable
{
    use Notifiable;

    public function setEmailAttribute(string $email): void
    {
        $this->attributes['email'] = Str::lower(trim($email));
    }

    public function sendPasswordResetNotification($token): void
    {
        $defaultLanguage = app(LanguageRepository::class)->default();
        $locale = $defaultLanguage === null ? (string) config('app.fallback_locale', 'en') : $defaultLanguage->code;
        app(TransactionalEmailQueue::class)->enqueue(new EmailEnvelope(
            'core.customer_password_reset',
            $this->email,
            null,
            $locale,
            [
                'store_name' => app(StoreSettingsReader::class)->current()->storeName,
                'reset_url' => route('storefront.account.password.reset', ['locale' => $locale, 'token' => $token, 'email' => $this->email]),
            ],
            'customer-password-reset:'.hash('sha256', $this->email.':'.$token),
            'customer_account',
            (string) $this->getKey(),
        ));
    }

    /** @return HasMany<BuyerProfile, $this> */
    public function buyerProfiles(): HasMany
    {
        return $this->hasMany(BuyerProfile::class);
    }

    /** @return HasMany<AddressBookEntry, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(AddressBookEntry::class);
    }

    /** @return HasMany<AccountPurchaseBlock, $this> */
    public function purchaseBlocks(): HasMany
    {
        return $this->hasMany(AccountPurchaseBlock::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
