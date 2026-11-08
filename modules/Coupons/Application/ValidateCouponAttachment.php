<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Litecommerce\Modules\Coupons\Contracts\CouponAttachmentStatus;
use Litecommerce\Modules\Coupons\Contracts\CouponAttachmentValidator;
use Litecommerce\Modules\Coupons\Infrastructure\Models\Coupon;
use Litecommerce\Modules\Coupons\Infrastructure\Models\CouponSettings;
use Litecommerce\Modules\Pricing\Contracts\ExchangeRateCatalog;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;

/**
 * The one-time PIN-checking gate — see Contracts\CouponAttachmentValidator.
 * Every check runs under the coupon row's lock so concurrent attach
 * attempts (from the same shared code) count PIN attempts correctly
 * instead of racing past the limit.
 */
final class ValidateCouponAttachment implements CouponAttachmentValidator
{
    public function __construct(
        private readonly CurrencyRepository $currencies,
        private readonly ExchangeRateCatalog $exchangeRates,
    ) {}

    public function attach(
        string $code,
        ?string $pin,
        ?int $customerAccountId,
        string $currencyCode,
        array $alreadyAttachedCodes,
    ): CouponAttachmentStatus {
        if (! CouponSettings::current()->enabled) {
            return CouponAttachmentStatus::ModuleDisabled;
        }

        return DB::transaction(function () use ($code, $pin, $customerAccountId, $currencyCode, $alreadyAttachedCodes): CouponAttachmentStatus {
            $coupon = Coupon::query()->where('code', $code)->lockForUpdate()->first();

            if ($coupon === null) {
                return CouponAttachmentStatus::InvalidCode;
            }

            if ($coupon->status === 'blocked') {
                return CouponAttachmentStatus::Blocked;
            }

            if ($coupon->isExpired()) {
                return CouponAttachmentStatus::Expired;
            }

            if ($coupon->customer_account_id !== null && $coupon->customer_account_id !== $customerAccountId) {
                return $customerAccountId === null ? CouponAttachmentStatus::AccountRequired : CouponAttachmentStatus::AccountMismatch;
            }

            $base = $this->currencies->base();

            if ($base !== null && $currencyCode !== $base->code && $this->exchangeRates->find($currencyCode) === null) {
                return CouponAttachmentStatus::CurrencyUnavailable;
            }

            if (! $coupon->combinable_with_other_coupons && $alreadyAttachedCodes !== []) {
                return CouponAttachmentStatus::NotCombinable;
            }

            if ($alreadyAttachedCodes !== []) {
                $blocksAdditions = Coupon::query()
                    ->whereIn('code', $alreadyAttachedCodes)
                    ->where('combinable_with_other_coupons', false)
                    ->exists();

                if ($blocksAdditions) {
                    return CouponAttachmentStatus::NotCombinable;
                }
            }

            if ($coupon->pin_hash !== null) {
                if ($coupon->isPinLocked()) {
                    return CouponAttachmentStatus::PinLocked;
                }

                if ($pin === null || $pin === '') {
                    return CouponAttachmentStatus::PinRequired;
                }

                if (! Hash::check($pin, $coupon->pin_hash)) {
                    $attempts = $coupon->failed_pin_attempts + 1;
                    $locked = $attempts >= (int) config('coupons.max_pin_attempts', 5);
                    $coupon->update([
                        'failed_pin_attempts' => $attempts,
                        'pin_locked_at' => $locked ? now() : null,
                    ]);

                    return $locked ? CouponAttachmentStatus::PinLocked : CouponAttachmentStatus::PinIncorrect;
                }

                $coupon->update(['failed_pin_attempts' => 0, 'pin_locked_at' => null]);
            }

            return CouponAttachmentStatus::Applied;
        });
    }
}
