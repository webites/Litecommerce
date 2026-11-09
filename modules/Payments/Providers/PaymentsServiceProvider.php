<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Orders\Contracts\PickupPaymentRecorder;
use Litecommerce\Modules\Payments\Application\ConfirmManualPayment;
use Litecommerce\Modules\Payments\Application\EloquentPaymentAttemptCatalog;
use Litecommerce\Modules\Payments\Application\ReconcileExpiredPayments;
use Litecommerce\Modules\Payments\Application\RecordPickupPayment;
use Litecommerce\Modules\Payments\Application\StartPaymentAttempt;
use Litecommerce\Modules\Payments\Console\ReplayDeferredPaymentWebhooksCommand;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptCatalog;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStarter;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Litecommerce\Modules\Payments\Contracts\PaymentExpiryReconciler;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodRegistry;
use Litecommerce\Modules\Payments\Contracts\PaymentObligationChecker;
use Litecommerce\Modules\Payments\Infrastructure\CoreBankTransferMethod;
use Litecommerce\Modules\Payments\Infrastructure\CoreCashOnDeliveryMethod;
use Litecommerce\Modules\Payments\Infrastructure\CoreCouponFullCoverageMethod;
use Litecommerce\Modules\Payments\Infrastructure\CorePayOnPickupMethod;
use Litecommerce\Modules\Payments\Infrastructure\EloquentPaymentObligationChecker;
use Litecommerce\Modules\Payments\Infrastructure\InMemoryPaymentMethodRegistry;
use Litecommerce\Modules\Payments\Infrastructure\Models\BankTransferAccount;
use Litecommerce\Modules\Payments\Presentation\Controllers\PaymentSettingsController;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Litecommerce\Modules\Store\Contracts\SettingsTabRegistry;
use Litecommerce\Modules\Store\Contracts\SetupChecklist;

final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/payments.php', 'payments');

        $this->app->singleton(PaymentMethodRegistry::class, InMemoryPaymentMethodRegistry::class);
        $this->app->bind(PaymentAttemptStarter::class, StartPaymentAttempt::class);
        $this->app->bind(PaymentObligationChecker::class, EloquentPaymentObligationChecker::class);
        $this->app->bind(PaymentAttemptCatalog::class, EloquentPaymentAttemptCatalog::class);
        $this->app->bind(PaymentConfirmation::class, ConfirmManualPayment::class);
        $this->app->bind(PickupPaymentRecorder::class, RecordPickupPayment::class);
        $this->app->bind(PaymentExpiryReconciler::class, ReconcileExpiredPayments::class);
    }

    public function boot(
        PaymentMethodRegistry $registry,
        CoreBankTransferMethod $bankTransfer,
        CoreCashOnDeliveryMethod $cashOnDelivery,
        CorePayOnPickupMethod $payOnPickup,
        CoreCouponFullCoverageMethod $couponFullCoverage,
    ): void {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'payments');

        $this->app->make(ScheduledTaskRegistry::class)->register(
            'core.payments.reconcile_expired',
            60,
            fn () => $this->app->make(PaymentExpiryReconciler::class)->reconcileDue(
                (int) config('orders.payment_reconcile_batch', 50),
            ),
        );

        $this->commands([ReplayDeferredPaymentWebhooksCommand::class]);
        $this->app->make(ScheduledTaskRegistry::class)->register('core.payments.replay_deferred_webhooks', 60, static function (): void {
            Artisan::call('payments:replay-deferred-webhooks');
        });

        $registry->register($bankTransfer);
        $registry->register($cashOnDelivery);
        $registry->register($payOnPickup);
        $registry->register($couponFullCoverage);

        $this->app->make(SettingsTabRegistry::class)->register(
            'payments',
            'store::tabs.payments',
            300,
            [$this->app->make(PaymentSettingsController::class), 'settingsTab'],
            fn (): bool => auth()->id() !== null && $this->app->make(StaffPermissionChecker::class)->allows((int) auth()->id(), CorePermission::ManageOrders->value),
        );

        $this->app->make(SetupChecklist::class)->register(
            'payments',
            300,
            'payments::panel.setup_title',
            'payments::panel.setup_description',
            '/panel/settings#payments',
            static fn (): bool => BankTransferAccount::query()->where('enabled', true)->exists()
                || array_filter($registry->all(), static fn ($descriptor): bool => ! str_starts_with($descriptor->id, 'core.')) !== [],
            fn (): bool => auth()->id() !== null && $this->app->make(StaffPermissionChecker::class)->allows((int) auth()->id(), CorePermission::ManageOrders->value),
        );
    }
}
