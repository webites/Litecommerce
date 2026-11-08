<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Coupons\Contracts\CouponInsufficientBalanceException;
use Litecommerce\Modules\Coupons\Contracts\CouponReservationData;
use Litecommerce\Modules\Coupons\Contracts\CouponReservationManager;
use Litecommerce\Modules\Coupons\Contracts\CouponReservationState;
use Litecommerce\Modules\Coupons\Contracts\InvalidCouponReservationStateException;
use Litecommerce\Modules\Coupons\Contracts\UnknownCouponException;
use Litecommerce\Modules\Coupons\Domain\Decimal;
use Litecommerce\Modules\Coupons\Infrastructure\Models\Coupon;
use Litecommerce\Modules\Coupons\Infrastructure\Models\CouponLedgerEntry;
use Litecommerce\Modules\Coupons\Infrastructure\Models\CouponReservation;

/**
 * Implements the reservation state machine described on
 * Contracts\CouponReservationManager — see that interface for the
 * confirm()-is-consumption / release()-is-symmetric design rationale.
 */
final class ManageCouponReservations implements CouponReservationManager
{
    public function reserve(
        string $code,
        string $amount,
        string $referenceType,
        string $referenceId,
        ?\DateTimeImmutable $expiresAt,
    ): CouponReservationData {
        if (! Decimal::isPositive($amount)) {
            throw new \InvalidArgumentException('Reservation amount must be positive.');
        }

        return DB::transaction(function () use ($code, $amount, $referenceType, $referenceId, $expiresAt): CouponReservationData {
            $coupon = Coupon::query()->where('code', $code)->lockForUpdate()->first();

            if ($coupon === null) {
                throw new UnknownCouponException($code);
            }

            $this->releaseExpiredForLockedCoupon($coupon);
            $coupon->refresh();

            $available = $coupon->availableBalance();

            if (Decimal::compare($amount, $available) > 0) {
                throw new CouponInsufficientBalanceException($code, $amount, $available);
            }

            $coupon->update(['reserved_amount' => Decimal::add((string) $coupon->reserved_amount, $amount)]);

            $reservation = CouponReservation::query()->create([
                'coupon_id' => $coupon->getKey(),
                'amount' => $amount,
                'state' => CouponReservationState::Pending->value,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'expires_at' => $expiresAt,
            ]);

            return new CouponReservationData(
                id: (int) $reservation->getKey(),
                couponCode: $code,
                amount: $amount,
                state: CouponReservationState::Pending,
                referenceType: $referenceType,
                referenceId: $referenceId,
                expiresAt: $expiresAt,
            );
        });
    }

    public function confirm(int $reservationId): void
    {
        DB::transaction(function () use ($reservationId): void {
            $reservation = CouponReservation::query()->lockForUpdate()->findOrFail($reservationId);

            if ($reservation->state === CouponReservationState::Confirmed->value) {
                return;
            }

            if ($reservation->state !== CouponReservationState::Pending->value) {
                throw new InvalidCouponReservationStateException($reservationId, CouponReservationState::from($reservation->state), 'confirm');
            }

            $coupon = Coupon::query()->lockForUpdate()->findOrFail($reservation->coupon_id);
            $coupon->update([
                'reserved_amount' => $this->clampToZero(Decimal::sub((string) $coupon->reserved_amount, (string) $reservation->amount)),
                'balance' => Decimal::sub((string) $coupon->balance, (string) $reservation->amount),
            ]);

            CouponLedgerEntry::query()->create([
                'coupon_id' => $coupon->getKey(), 'type' => 'usage', 'amount' => '-'.$reservation->amount,
                'reference_type' => $reservation->reference_type, 'reference_id' => $reservation->reference_id,
                'reason' => null, 'occurred_at' => now(),
            ]);

            $reservation->update(['state' => CouponReservationState::Confirmed->value]);
        });
    }

    public function release(int $reservationId): void
    {
        DB::transaction(function () use ($reservationId): void {
            $reservation = CouponReservation::query()->lockForUpdate()->findOrFail($reservationId);

            if (in_array($reservation->state, [CouponReservationState::Released->value, CouponReservationState::Expired->value], true)) {
                return;
            }

            $coupon = Coupon::query()->lockForUpdate()->findOrFail($reservation->coupon_id);

            if ($reservation->state === CouponReservationState::Confirmed->value) {
                // The 'usage' ledger entry already reduced balance — give it back.
                $coupon->update(['balance' => Decimal::add((string) $coupon->balance, (string) $reservation->amount)]);
                CouponLedgerEntry::query()->create([
                    'coupon_id' => $coupon->getKey(), 'type' => 'restore', 'amount' => $reservation->amount,
                    'reference_type' => $reservation->reference_type, 'reference_id' => $reservation->reference_id,
                    'reason' => trans('coupons::panel.ledger_reasons.order_cancelled'), 'occurred_at' => now(),
                ]);
            } else {
                // Pending — nothing was ever consumed, just release the hold.
                $coupon->update(['reserved_amount' => $this->clampToZero(Decimal::sub((string) $coupon->reserved_amount, (string) $reservation->amount))]);
            }

            $reservation->update(['state' => CouponReservationState::Released->value]);
        });
    }

    public function restore(string $code, string $amount, string $referenceType, string $referenceId, string $reason): void
    {
        if (! Decimal::isPositive($amount)) {
            return;
        }

        DB::transaction(function () use ($code, $amount, $referenceType, $referenceId, $reason): void {
            $coupon = Coupon::query()->where('code', $code)->lockForUpdate()->first();

            if ($coupon === null) {
                throw new UnknownCouponException($code);
            }

            $coupon->update(['balance' => Decimal::add((string) $coupon->balance, $amount)]);

            CouponLedgerEntry::query()->create([
                'coupon_id' => $coupon->getKey(), 'type' => 'restore', 'amount' => $amount,
                'reference_type' => $referenceType, 'reference_id' => $referenceId,
                'reason' => $reason, 'occurred_at' => now(),
            ]);
        });
    }

    public function reconcileExpiredReservations(?string $code = null, ?int $limit = null): int
    {
        $couponIds = $code === null
            ? CouponReservation::query()
                ->where('state', CouponReservationState::Pending->value)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->distinct()
                ->when($limit !== null, static fn ($query) => $query->limit(max(1, $limit)))
                ->pluck('coupon_id')
            : Coupon::query()->where('code', $code)->pluck('id');

        $released = 0;

        foreach ($couponIds as $couponId) {
            $released += DB::transaction(function () use ($couponId): int {
                $coupon = Coupon::query()->lockForUpdate()->find($couponId);

                return $coupon === null ? 0 : $this->releaseExpiredForLockedCoupon($coupon);
            });
        }

        return $released;
    }

    /**
     * Releases this coupon's expired pending reservations. Caller must
     * already hold the coupon's row lock within an open transaction.
     */
    private function releaseExpiredForLockedCoupon(Coupon $coupon): int
    {
        $expired = CouponReservation::query()
            ->where('coupon_id', $coupon->getKey())
            ->where('state', CouponReservationState::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->lockForUpdate()
            ->get();

        if ($expired->isEmpty()) {
            return 0;
        }

        $totalReleased = '0';

        foreach ($expired as $reservation) {
            $totalReleased = Decimal::add($totalReleased, (string) $reservation->amount);
            $reservation->update(['state' => CouponReservationState::Expired->value]);
        }

        $coupon->update(['reserved_amount' => $this->clampToZero(Decimal::sub((string) $coupon->reserved_amount, $totalReleased))]);

        return $expired->count();
    }

    private function clampToZero(string $value): string
    {
        return Decimal::compare($value, '0') < 0 ? '0' : $value;
    }
}
