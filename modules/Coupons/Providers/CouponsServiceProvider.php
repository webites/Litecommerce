<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Providers;

use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Coupons\Application\ManageCouponReservations;
use Litecommerce\Modules\Coupons\Application\QuoteCouponAllocation;
use Litecommerce\Modules\Coupons\Application\ValidateCouponAttachment;
use Litecommerce\Modules\Coupons\Contracts\CouponAttachmentValidator;
use Litecommerce\Modules\Coupons\Contracts\CouponPricer;
use Litecommerce\Modules\Coupons\Contracts\CouponReservationManager;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;

final class CouponsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CouponPricer::class, QuoteCouponAllocation::class);
        $this->app->bind(CouponAttachmentValidator::class, ValidateCouponAttachment::class);
        $this->app->bind(CouponReservationManager::class, ManageCouponReservations::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'coupons');

        $this->app->make(ScheduledTaskRegistry::class)->register(
            'core.coupons.reconcile_expired_reservations',
            60,
            fn () => $this->app->make(CouponReservationManager::class)->reconcileExpiredReservations(
                null,
                (int) config('coupons.reconcile_batch', 200),
            ),
        );
    }
}
