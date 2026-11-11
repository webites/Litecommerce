<?php

declare(strict_types=1);

namespace Tests\Feature\Retention;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Audit\Contracts\AuditRetentionRegistry;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Orders\Contracts\GuestOrderAccess;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/** Retention decisions of 2026-11-06: carts, audit entries and visitor identifiers. */
final class DataRetentionTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        Carbon::setTestNow('2026-11-07 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function cart(?int $customerId, int $idleDays, string $status = 'active'): int
    {
        $id = (int) DB::table('carts')->insertGetId([
            'customer_account_id' => $customerId, 'guest_token' => $customerId === null ? fake()->uuid() : null, 'currency_code' => 'PLN',
            'status' => $status, 'last_activity_at' => now()->subDays($idleDays)->subMinute(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('checkout_drafts')->insert([
            'id' => fake()->uuid(), 'cart_id' => $id, 'cart_version_seen' => 0, 'buyer_type' => 'person', 'email' => 'x@example.com',
            'currency_code' => 'PLN', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function customer(): int
    {
        return (int) DB::table('customer_accounts')->insertGetId(['email' => fake()->unique()->safeEmail(), 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_guest_cart_is_removed_after_30_idle_days_and_a_customer_cart_after_90(): void
    {
        $customer = $this->customer();
        $staleGuest = $this->cart(null, 30);
        $freshGuest = $this->cart(null, 29);
        $staleCustomer = $this->cart($customer, 90);
        $customerPastGuestLimit = $this->cart($customer, 60);
        $checkedOutGuest = $this->cart(null, 45, 'checked_out');

        $this->artisan('cart:prune-inactive')->assertSuccessful();

        $remaining = DB::table('carts')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$freshGuest, $customerPastGuestLimit], $remaining);
        $this->assertSame(2, DB::table('checkout_drafts')->count(), 'drafts leave with their carts');
        $this->assertNotContains($staleGuest, $remaining);
        $this->assertNotContains($staleCustomer, $remaining);
        $this->assertNotContains($checkedOutGuest, $remaining);
    }

    public function test_cart_items_and_coupons_go_with_the_cart_and_pruning_is_batched(): void
    {
        $cart = $this->cart(null, 40);
        DB::table('cart_coupons')->insert(['cart_id' => $cart, 'code' => 'ABC', 'attached_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        foreach (range(1, 3) as $ignored) {
            $this->cart(null, 40);
        }

        $this->artisan('cart:prune-inactive', ['--batch' => 2, '--max-batches' => 1])->assertSuccessful();
        $this->assertSame(2, DB::table('carts')->count(), 'one batch of two');

        $this->artisan('cart:prune-inactive')->assertSuccessful();
        $this->assertSame(0, DB::table('carts')->count());
        $this->assertSame(0, DB::table('cart_coupons')->count());
        $this->assertSame(0, DB::table('checkout_drafts')->count());
    }

    private function audit(string $action, int $ageMonths): void
    {
        app(AuditLogger::class)->record(new AuditEntryData('staff', '1', $action, 'order', 'o-1'));
        DB::table('audit_entries')->orderByDesc('id')->limit(1)->update(['created_at' => now()->subMonths($ageMonths)->subMinute()]);
    }

    public function test_audit_entries_older_than_24_months_go_unless_their_kind_is_kept_forever(): void
    {
        $this->audit('catalog.product.updated', 25);
        $this->audit('catalog.product.updated', 23);
        $this->audit('orders.fulfillment.handed_over', 60);
        $this->audit('pricing.tax_rate.updated', 60);
        $this->audit('returns.refund.paid_out', 60);
        $this->audit('identity.staff.updated', 60);
        $this->audit('catalog.variant.price_updated', 60);
        $this->audit('cms.page.saved', 60);

        $this->artisan('audit:prune')->assertSuccessful();

        $left = DB::table('audit_entries')->orderBy('id')->pluck('action')->all();
        $this->assertSame([
            'catalog.product.updated', 'orders.fulfillment.handed_over', 'pricing.tax_rate.updated',
            'returns.refund.paid_out', 'identity.staff.updated', 'catalog.variant.price_updated',
        ], $left);
    }

    public function test_a_module_can_keep_its_own_audit_kind_forever(): void
    {
        $this->audit('plugin.invoices.issued', 40);
        $this->artisan('audit:prune')->assertSuccessful();
        $this->assertSame(0, DB::table('audit_entries')->count());

        app(AuditRetentionRegistry::class)->keepIndefinitely('plugin.invoices.');
        $this->audit('plugin.invoices.issued', 40);
        $this->artisan('audit:prune')->assertSuccessful();
        $this->assertSame(1, DB::table('audit_entries')->count());
    }

    public function test_visitor_identifiers_in_audit_metadata_are_cleared_after_90_days_but_the_entry_stays(): void
    {
        $record = static fn (int $ageDays): int => (int) DB::table('audit_entries')->insertGetId([
            'actor_type' => 'staff', 'actor_id' => '1', 'action' => 'catalog.product.updated', 'subject_type' => 'product', 'subject_id' => '1',
            'metadata' => json_encode(['ip_address' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0', 'note' => 'keep']),
            'created_at' => now()->subDays($ageDays)->subMinute(),
        ]);
        $old = $record(91);
        $recent = $record(89);

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertSame(['note' => 'keep'], json_decode((string) DB::table('audit_entries')->where('id', $old)->value('metadata'), true));
        $this->assertSame('203.0.113.7', json_decode((string) DB::table('audit_entries')->where('id', $recent)->value('metadata'), true)['ip_address']);
    }

    public function test_download_attempts_lose_the_visitor_identifiers_after_90_days_but_stay(): void
    {
        Schema::disableForeignKeyConstraints();
        $grant = DB::table('download_grants')->insertGetId([
            'access_token' => 'tok', 'variant_file_set_id' => 1, 'digital_asset_version_id' => 1, 'reference_type' => 'order_line', 'reference_id' => '1',
            'granted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $attempt = static fn (int $ageDays) => DB::table('download_attempts')->insertGetId([
            'download_grant_id' => $grant, 'ip_address' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0', 'outcome' => 'served',
            'occurred_at' => now()->subDays($ageDays)->subMinute(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $old = $attempt(91);
        $recent = $attempt(89);

        $this->artisan('digital:scrub-download-attempts')->assertSuccessful();

        $this->assertDatabaseHas('download_attempts', ['id' => $old, 'ip_address' => null, 'user_agent' => null, 'outcome' => 'served']);
        $this->assertDatabaseHas('download_attempts', ['id' => $recent, 'ip_address' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0']);
    }

    public function test_a_guest_order_token_works_for_a_year_then_stops_and_is_pruned(): void
    {
        $orderId = $this->guestOrder();
        $access = app(GuestOrderAccess::class);
        $token = $access->issue($orderId);

        $this->assertNotNull($access->resolve($token));
        $this->assertDatabaseHas('guest_order_access_tokens', ['order_id' => $orderId, 'expires_at' => now()->addDays(365)->toDateTimeString()]);

        Carbon::setTestNow(now()->addDays(366));
        $this->assertNull($access->resolve($token), 'expired link no longer shows the order');

        $this->artisan('orders:prune-guest-tokens')->assertSuccessful();
        $this->assertSame(0, DB::table('guest_order_access_tokens')->count());
        $this->assertSame(1, DB::table('orders')->count(), 'the order itself stays');
    }

    public function test_every_new_email_link_gets_a_fresh_year(): void
    {
        $orderId = $this->guestOrder();
        $access = app(GuestOrderAccess::class);
        $first = $access->issue($orderId);

        Carbon::setTestNow(now()->addDays(300));
        $second = $access->issue($orderId);
        Carbon::setTestNow(now()->addDays(100));

        $this->assertNull($access->resolve($first));
        $this->assertNotNull($access->resolve($second));
    }

    private function guestOrder(): string
    {
        $id = (string) Str::uuid();
        DB::table('orders')->insert([
            'id' => $id, 'number' => crc32($id), 'public_number' => (string) crc32($id), 'source_type' => 'test', 'source_id' => 'g-1', 'status' => 'processing', 'payment_status' => 'paid',
            'payment_method_id' => 'core.bank_transfer', 'payment_kind' => 'bank_transfer', 'email' => 'guest@example.com', 'currency_code' => 'PLN', 'mode' => 'live', 'locale' => 'pl',
            'total_net_amount' => '1', 'total_vat_amount' => '0', 'total_gross_amount' => '1', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('order_party_snapshots')->insert([
            'order_id' => $id, 'buyer_type' => 'person', 'email' => 'guest@example.com', 'billing_address' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_the_retention_tasks_are_registered_with_the_scheduler(): void
    {
        $keys = array_map(static fn ($task): string => $task->key, app(ScheduledTaskRegistry::class)->all());

        foreach (['core.cart.prune_inactive', 'core.audit.prune', 'core.digital.scrub_download_attempts', 'core.orders.prune_guest_tokens'] as $key) {
            $this->assertContains($key, $keys);
        }
    }
}
