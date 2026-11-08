<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Litecommerce\Modules\Coupons\Domain\Decimal;
use Litecommerce\Modules\Coupons\Infrastructure\Models\Coupon;
use Litecommerce\Modules\Coupons\Infrastructure\Models\CouponLedgerEntry;
use Litecommerce\Modules\Coupons\Infrastructure\Models\CouponSettings;

/**
 * Panel-only CRUD for coupons — a concrete class consumed exclusively by
 * Presentation\Controllers\Panel\CouponController, the same convention as
 * Delivery\Application\ManageDeliveryMethods (no public Contracts interface
 * needed since nothing outside this module calls it).
 */
final class ManageCoupons
{
    /** @param  array<string, mixed>  $data */
    public function create(array $data, string $actorId): Coupon
    {
        return DB::transaction(function () use ($data, $actorId): Coupon {
            $code = $data['code'] !== '' && $data['code'] !== null ? strtoupper((string) $data['code']) : $this->generateUniqueCode();

            $coupon = Coupon::query()->create([
                'code' => $code, 'status' => 'active',
                'customer_account_id' => $data['customer_account_id'] ?? null,
                'pin_hash' => empty($data['pin']) ? null : Hash::make((string) $data['pin']),
                'expires_at' => $data['expires_at'] ?? null,
                'combinable_with_promotional' => (bool) $data['combinable_with_promotional'],
                'combinable_with_other_coupons' => (bool) $data['combinable_with_other_coupons'],
                'applies_to_delivery' => (bool) $data['applies_to_delivery'],
                'note' => $data['note'] ?? null,
                'balance' => '0', 'reserved_amount' => '0',
            ]);

            $initialBalance = (string) ($data['initial_balance'] ?? '0');
            if (Decimal::isPositive($initialBalance)) {
                $this->topUp($coupon, $initialBalance, $actorId, trans('coupons::panel.ledger_reasons.initial_balance'));
            }

            return $coupon->fresh();
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(Coupon $coupon, array $data): void
    {
        $attributes = [
            'status' => $data['status'],
            'customer_account_id' => $data['customer_account_id'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'combinable_with_promotional' => (bool) $data['combinable_with_promotional'],
            'combinable_with_other_coupons' => (bool) $data['combinable_with_other_coupons'],
            'applies_to_delivery' => (bool) $data['applies_to_delivery'],
            'note' => $data['note'] ?? null,
        ];

        if (array_key_exists('pin', $data)) {
            $attributes['pin_hash'] = empty($data['pin']) ? null : Hash::make((string) $data['pin']);
        }

        $coupon->update($attributes);
    }

    public function topUp(Coupon $coupon, string $amount, string $actorId, ?string $reason = null): void
    {
        if (! Decimal::isPositive($amount)) {
            throw new \InvalidArgumentException('Top-up amount must be positive.');
        }

        DB::transaction(function () use ($coupon, $amount, $actorId, $reason): void {
            $locked = Coupon::query()->lockForUpdate()->findOrFail($coupon->getKey());
            $locked->update(['balance' => Decimal::add((string) $locked->balance, $amount)]);

            CouponLedgerEntry::query()->create([
                'coupon_id' => $locked->getKey(), 'type' => 'issue', 'amount' => $amount,
                'reason' => $reason, 'actor_id' => $actorId, 'occurred_at' => now(),
            ]);
        });
    }

    public function resetPinAttempts(Coupon $coupon): void
    {
        $coupon->update(['failed_pin_attempts' => 0, 'pin_locked_at' => null]);
    }

    public function updateSettings(bool $enabled): void
    {
        CouponSettings::current()->update(['enabled' => $enabled]);
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(10));
        } while (Coupon::query()->where('code', $code)->exists());

        return $code;
    }
}
