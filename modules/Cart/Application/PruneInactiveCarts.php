<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cart\Infrastructure\Models\Cart;
use Litecommerce\Modules\Cart\Infrastructure\Models\CheckoutDraft;

/**
 * Removes carts with their items, coupons and checkout drafts once they were
 * idle longer than the retention period: shorter for a guest, longer for a
 * signed-in customer. Orders keep their own snapshots.
 */
final class PruneInactiveCarts
{
    public function pruneBatch(\DateTimeImmutable $now, int $guestDays, int $customerDays, int $batchSize): int
    {
        return DB::transaction(function () use ($now, $guestDays, $customerDays, $batchSize): int {
            $ids = $this->expired(Cart::query(), $now, $guestDays, $customerDays)
                ->orderBy('id')->limit($batchSize)->lockForUpdate()->pluck('id')->all();

            if ($ids === []) {
                return 0;
            }

            CheckoutDraft::query()->whereIn('cart_id', $ids)->delete();

            return Cart::query()->whereKey($ids)->delete();
        });
    }

    /**
     * @param  Builder<Cart>  $query
     * @return Builder<Cart>
     */
    private function expired(Builder $query, \DateTimeImmutable $now, int $guestDays, int $customerDays): Builder
    {
        return $query->where(static function (Builder $cart) use ($now, $guestDays, $customerDays): void {
            $cart->where(static function (Builder $guest) use ($now, $guestDays): void {
                $guest->whereNull('customer_account_id')->where('last_activity_at', '<', $now->modify("-{$guestDays} days"));
            })->orWhere(static function (Builder $customer) use ($now, $customerDays): void {
                $customer->whereNotNull('customer_account_id')->where('last_activity_at', '<', $now->modify("-{$customerDays} days"));
            });
        });
    }
}
