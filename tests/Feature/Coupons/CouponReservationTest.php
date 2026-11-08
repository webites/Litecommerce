<?php

declare(strict_types=1);

namespace Tests\Feature\Coupons;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Coupons\Contracts\CouponInsufficientBalanceException;
use Litecommerce\Modules\Coupons\Contracts\CouponReservationManager;
use Litecommerce\Modules\Coupons\Contracts\CouponReservationState;
use Litecommerce\Modules\Coupons\Contracts\InvalidCouponReservationStateException;
use Litecommerce\Modules\Coupons\Contracts\UnknownCouponException;
use Litecommerce\Modules\Coupons\Infrastructure\Models\Coupon;
use Litecommerce\Modules\Coupons\Infrastructure\Models\CouponLedgerEntry;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Scheduler\Application\RunDueScheduledTasks;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * The reservation state machine from Coupons\Contracts\CouponReservationManager
 * — see that interface for why confirm() is the consumption moment and
 * release() covers both the never-consumed (Pending) and already-consumed
 * (Confirmed) cases symmetrically.
 */
final class CouponReservationTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
    }

    private function createCoupon(string $balance = '100.00', array $overrides = []): Coupon
    {
        $coupon = Coupon::query()->create([
            'code' => 'TEST'.random_int(1000, 9999), 'status' => 'active', 'balance' => $balance,
            'reserved_amount' => '0', 'combinable_with_promotional' => true,
            'combinable_with_other_coupons' => true, 'applies_to_delivery' => false,
            ...$overrides,
        ]);
        CouponLedgerEntry::query()->create([
            'coupon_id' => $coupon->getKey(), 'type' => 'issue', 'amount' => $balance, 'occurred_at' => now(),
        ]);

        return $coupon;
    }

    public function test_reserving_increases_reserved_and_reduces_available(): void
    {
        $coupon = $this->createCoupon('100.00');

        $reservation = app(CouponReservationManager::class)->reserve($coupon->code, '30.00', 'order', 'order-1', null);

        $this->assertSame(CouponReservationState::Pending, $reservation->state);
        $coupon->refresh();
        $this->assertSame('30.000000', (string) $coupon->reserved_amount);
        $this->assertSame('70.000000', $coupon->availableBalance());
    }

    public function test_reserving_more_than_available_balance_throws(): void
    {
        $coupon = $this->createCoupon('10.00');

        $this->expectException(CouponInsufficientBalanceException::class);
        app(CouponReservationManager::class)->reserve($coupon->code, '10.01', 'order', 'order-1', null);
    }

    public function test_reserving_an_unknown_code_throws(): void
    {
        $this->expectException(UnknownCouponException::class);
        app(CouponReservationManager::class)->reserve('DOES-NOT-EXIST', '1.00', 'order', 'order-1', null);
    }

    public function test_confirm_writes_a_usage_ledger_entry_and_permanently_reduces_balance(): void
    {
        $coupon = $this->createCoupon('100.00');
        $manager = app(CouponReservationManager::class);
        $reservation = $manager->reserve($coupon->code, '30.00', 'order', 'order-1', null);

        $manager->confirm($reservation->id);

        $coupon->refresh();
        $this->assertSame('70.000000', (string) $coupon->balance);
        $this->assertSame('0.000000', (string) $coupon->reserved_amount);
        $this->assertSame('70.000000', $coupon->availableBalance());
        $this->assertDatabaseHas('coupon_ledger_entries', ['coupon_id' => $coupon->getKey(), 'type' => 'usage', 'amount' => '-30.000000']);
    }

    public function test_confirm_is_idempotent(): void
    {
        $coupon = $this->createCoupon('100.00');
        $manager = app(CouponReservationManager::class);
        $reservation = $manager->reserve($coupon->code, '30.00', 'order', 'order-1', null);

        $manager->confirm($reservation->id);
        $manager->confirm($reservation->id);

        $coupon->refresh();
        $this->assertSame('70.000000', (string) $coupon->balance);
        $this->assertSame(1, CouponLedgerEntry::query()->where('type', 'usage')->count());
    }

    public function test_release_of_a_pending_reservation_has_no_ledger_effect(): void
    {
        $coupon = $this->createCoupon('100.00');
        $manager = app(CouponReservationManager::class);
        $reservation = $manager->reserve($coupon->code, '30.00', 'order', 'order-1', null);

        $manager->release($reservation->id);

        $coupon->refresh();
        $this->assertSame('100.000000', (string) $coupon->balance);
        $this->assertSame('0.000000', (string) $coupon->reserved_amount);
        $this->assertSame('100.000000', $coupon->availableBalance());
        $this->assertSame(0, CouponLedgerEntry::query()->whereIn('type', ['usage', 'restore'])->count());
    }

    /**
     * COD/pay-on-pickup/free orders reserve+confirm atomically at
     * placement — cancelling one must give the balance back with a
     * compensating ledger entry, not silently drop it.
     */
    public function test_release_of_an_already_confirmed_reservation_restores_the_balance(): void
    {
        $coupon = $this->createCoupon('100.00');
        $manager = app(CouponReservationManager::class);
        $reservation = $manager->reserve($coupon->code, '30.00', 'order', 'order-1', null);
        $manager->confirm($reservation->id);

        $manager->release($reservation->id);

        $coupon->refresh();
        $this->assertSame('100.000000', (string) $coupon->balance);
        $this->assertDatabaseHas('coupon_ledger_entries', ['coupon_id' => $coupon->getKey(), 'type' => 'restore', 'amount' => '30.000000']);
    }

    public function test_release_is_idempotent_after_already_released(): void
    {
        $coupon = $this->createCoupon('100.00');
        $manager = app(CouponReservationManager::class);
        $reservation = $manager->reserve($coupon->code, '30.00', 'order', 'order-1', null);

        $manager->release($reservation->id);
        $manager->release($reservation->id);

        $coupon->refresh();
        $this->assertSame('100.000000', (string) $coupon->balance);
    }

    public function test_confirming_a_released_reservation_throws_with_the_current_state(): void
    {
        $coupon = $this->createCoupon('100.00');
        $manager = app(CouponReservationManager::class);
        $reservation = $manager->reserve($coupon->code, '30.00', 'order', 'order-1', null);
        $manager->release($reservation->id);

        try {
            $manager->confirm($reservation->id);
            $this->fail('Expected InvalidCouponReservationStateException.');
        } catch (InvalidCouponReservationStateException $exception) {
            $this->assertSame(CouponReservationState::Released, $exception->currentState);
        }
    }

    public function test_restore_credits_balance_independently_of_any_reservation(): void
    {
        $coupon = $this->createCoupon('50.00');

        app(CouponReservationManager::class)->restore($coupon->code, '12.34', 'return_line', 'rl-1', 'Zwrot zaakceptowany.');

        $coupon->refresh();
        $this->assertSame('62.340000', (string) $coupon->balance);
        $this->assertDatabaseHas('coupon_ledger_entries', [
            'coupon_id' => $coupon->getKey(), 'type' => 'restore', 'amount' => '12.340000',
            'reference_type' => 'return_line', 'reference_id' => 'rl-1',
        ]);
    }

    /**
     * "Zwrot nie przedłuża terminu: przywrócone saldo wygasłego kodu jest
     * niedostępne" — restore() still credits the ledger even for an
     * expired code; only *new* reservations are blocked elsewhere.
     */
    public function test_restore_applies_even_to_an_expired_code(): void
    {
        $coupon = $this->createCoupon('50.00', ['expires_at' => now()->subDay()]);

        app(CouponReservationManager::class)->restore($coupon->code, '10.00', 'return_line', 'rl-1', 'Zwrot.');

        $coupon->refresh();
        $this->assertSame('60.000000', (string) $coupon->balance);
    }

    public function test_reconcile_releases_only_expired_pending_reservations(): void
    {
        // Two separate codes — reserving $valid on its own coupon must not
        // opportunistically reconcile $expired's coupon, so the explicit
        // reconcile below is what actually catches it (see also
        // test_reserve_opportunistically_reconciles_expired_reservations_for_the_same_code).
        $expiredCoupon = $this->createCoupon('100.00');
        $validCoupon = $this->createCoupon('100.00');
        $manager = app(CouponReservationManager::class);
        $expired = $manager->reserve($expiredCoupon->code, '10.00', 'order', 'order-1', now()->subMinute()->toDateTimeImmutable());
        $valid = $manager->reserve($validCoupon->code, '20.00', 'order', 'order-2', now()->addHour()->toDateTimeImmutable());

        $released = $manager->reconcileExpiredReservations();

        $this->assertSame(1, $released);
        $this->assertSame(CouponReservationState::Expired->value, DB::table('coupon_reservations')->find($expired->id)->state);
        $this->assertSame(CouponReservationState::Pending->value, DB::table('coupon_reservations')->find($valid->id)->state);
        $expiredCoupon->refresh();
        $validCoupon->refresh();
        $this->assertSame('0.000000', (string) $expiredCoupon->reserved_amount);
        $this->assertSame('20.000000', (string) $validCoupon->reserved_amount);
    }

    public function test_global_sweep_honours_its_limit_and_the_scheduler_task_drains_the_rest(): void
    {
        $manager = app(CouponReservationManager::class);
        foreach ([1, 2] as $n) {
            $coupon = $this->createCoupon('100.00');
            $manager->reserve($coupon->code, '10.00', 'order', "order-{$n}", now()->subMinute()->toDateTimeImmutable());
        }

        $this->assertSame(1, $manager->reconcileExpiredReservations(null, 1));
        $this->assertSame(1, DB::table('coupon_reservations')->where('state', CouponReservationState::Pending->value)->count());

        app(RunDueScheduledTasks::class)->run(20, 10);

        $this->assertSame(0, DB::table('coupon_reservations')->where('state', CouponReservationState::Pending->value)->count());
        $this->assertSame(0.0, (float) DB::table('coupons')->sum('reserved_amount'));
        $this->assertDatabaseHas('scheduled_tasks', ['key' => 'core.coupons.reconcile_expired_reservations', 'run_count' => 1, 'consecutive_failures' => 0]);
    }

    /** reserve() opportunistically reconciles this code's own expired holds first, so a missing cron never blocks new use. */
    public function test_reserve_opportunistically_reconciles_expired_reservations_for_the_same_code(): void
    {
        $coupon = $this->createCoupon('10.00');
        $manager = app(CouponReservationManager::class);
        $manager->reserve($coupon->code, '10.00', 'order', 'order-1', now()->subMinute()->toDateTimeImmutable());

        // Without reconciliation this would fail: reserved_amount already
        // holds all 10.00 from the (by now expired) first reservation.
        $second = $manager->reserve($coupon->code, '10.00', 'order', 'order-2', null);

        $this->assertSame(CouponReservationState::Pending, $second->state);
    }
}
