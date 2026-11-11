<?php

declare(strict_types=1);

namespace Tests\Feature\Retention;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Orders\Contracts\Events\OrderPersonalDataAnonymized;
use Litecommerce\Modules\Orders\Contracts\GuestOrderAccess;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/** Decision of 2026-11-07: personal data of an order is removed 5 years after the end of its year. */
final class PersonalDataAnonymizationTest extends TestCase
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

    private function order(string $createdAt): string
    {
        $id = (string) Str::uuid();
        DB::table('orders')->insert([
            'id' => $id, 'number' => crc32($id), 'public_number' => (string) crc32($id), 'source_type' => 'test', 'source_id' => $id, 'status' => 'processing', 'payment_status' => 'paid',
            'payment_method_id' => 'core.bank_transfer', 'payment_kind' => 'bank_transfer', 'email' => 'jan@example.com', 'currency_code' => 'PLN',
            'mode' => 'live', 'locale' => 'pl', 'total_net_amount' => '100', 'total_vat_amount' => '23', 'total_gross_amount' => '123',
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
        DB::table('order_party_snapshots')->insert([
            'order_id' => $id, 'buyer_type' => 'company', 'email' => 'jan@example.com', 'company_name' => 'Firma Sp. z o.o.', 'tax_identifier' => '5260001246',
            'registration_number' => '123', 'phone' => '+48123456789',
            'billing_address' => json_encode(['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1']),
            'shipping_address' => json_encode(['recipient_name' => 'Anna Nowak', 'country_code' => 'DE', 'city' => 'Berlin', 'address_line_1' => 'Weg 2']),
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
        DB::table('order_notes')->insert(['order_id' => $id, 'body' => 'Klient prosił o dzwonienie na 600100200', 'author_type' => 'staff', 'author_id' => '1', 'created_at' => $createdAt, 'updated_at' => $createdAt]);
        DB::table('order_status_history')->insert([
            ['order_id' => $id, 'old_status' => null, 'new_status' => 'processing', 'actor_type' => 'staff', 'actor_id' => '1', 'reason' => 'Rozmowa z Janem', 'occurred_at' => $createdAt, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['order_id' => $id, 'old_status' => null, 'new_status' => 'pending_payment', 'actor_type' => 'system', 'actor_id' => null, 'reason' => 'Proszę dzwonić na 600100200', 'occurred_at' => $createdAt, 'created_at' => $createdAt, 'updated_at' => $createdAt],
            ['order_id' => $id, 'old_status' => 'pending_payment', 'new_status' => 'processing', 'actor_type' => 'system', 'actor_id' => null, 'reason' => 'Potwierdzono pełną wpłatę.', 'occurred_at' => $createdAt, 'created_at' => $createdAt, 'updated_at' => $createdAt],
        ]);

        return $id;
    }

    public function test_an_order_is_anonymized_only_after_the_end_of_its_year_plus_five_years(): void
    {
        $lastYearToAnonymize = $this->order('2020-12-31 23:00:00');
        $firstYearToKeep = $this->order('2021-01-01 00:30:00');

        $this->artisan('orders:anonymize-expired')->assertSuccessful();

        $this->assertNotNull(DB::table('orders')->where('id', $lastYearToAnonymize)->value('anonymized_at'));
        $this->assertNull(DB::table('orders')->where('id', $firstYearToKeep)->value('anonymized_at'));
        $this->assertSame('jan@example.com', DB::table('orders')->where('id', $firstYearToKeep)->value('email'));
    }

    public function test_personal_data_goes_but_the_accounting_record_stays(): void
    {
        $id = $this->order('2020-06-01 10:00:00');
        $token = $this->guestToken($id);

        $this->artisan('orders:anonymize-expired')->assertSuccessful();

        $order = DB::table('orders')->where('id', $id)->first();
        $this->assertStringEndsWith('@anonymized.invalid', $order->email);
        $this->assertEquals(123, $order->total_gross_amount);
        $this->assertSame('paid', $order->payment_status);

        $party = DB::table('order_party_snapshots')->where('order_id', $id)->first();
        $this->assertSame($order->email, $party->email);
        $this->assertNull($party->company_name);
        $this->assertNull($party->tax_identifier);
        $this->assertNull($party->phone);
        $this->assertSame(['country_code' => 'PL'], json_decode($party->billing_address, true));
        $this->assertSame(['country_code' => 'DE'], json_decode($party->shipping_address, true));

        $this->assertSame('[usunięto]', DB::table('order_notes')->where('order_id', $id)->value('body'));
        $this->assertNull(DB::table('order_status_history')->where('order_id', $id)->where('actor_type', 'staff')->value('reason'));
        $this->assertNull(DB::table('order_status_history')->where('order_id', $id)->whereNull('old_status')->where('actor_type', 'system')->value('reason'));
        $this->assertSame('Potwierdzono pełną wpłatę.', DB::table('order_status_history')->where('order_id', $id)->whereNotNull('old_status')->value('reason'));
        $this->assertSame(0, DB::table('guest_order_access_tokens')->where('order_id', $id)->count());
        $this->assertNull(app(GuestOrderAccess::class)->resolve($token));
        $this->assertDatabaseHas('order_outbox_events', ['order_id' => $id, 'event_type' => 'OrderPersonalDataAnonymized']);
    }

    public function test_it_is_idempotent_and_announces_each_order_once(): void
    {
        $id = $this->order('2019-03-01 10:00:00');
        $seen = [];
        Event::listen(OrderPersonalDataAnonymized::class, static function (OrderPersonalDataAnonymized $event) use (&$seen): void {
            $seen[] = $event->orderId;
        });

        $this->artisan('orders:anonymize-expired')->assertSuccessful();
        $this->artisan('orders:anonymize-expired')->assertSuccessful();

        $this->assertSame([$id], $seen);
        $this->assertSame(1, DB::table('order_outbox_events')->where('event_type', 'OrderPersonalDataAnonymized')->count());
    }

    public function test_the_retention_years_are_configurable(): void
    {
        $id = $this->order('2023-06-01 10:00:00');

        $this->artisan('orders:anonymize-expired')->assertSuccessful();
        $this->assertNull(DB::table('orders')->where('id', $id)->value('anonymized_at'));

        $this->artisan('orders:anonymize-expired', ['--years' => 2])->assertSuccessful();
        $this->assertNotNull(DB::table('orders')->where('id', $id)->value('anonymized_at'));
    }

    public function test_return_requests_and_download_grants_follow_the_same_rule(): void
    {
        $orderId = $this->order('2020-06-01 10:00:00');
        $request = static fn (string $at): string => tap((string) Str::uuid(), static fn (string $id) => DB::table('return_requests')->insert([
            'id' => $id, 'order_id' => $orderId, 'source' => 'staff', 'email' => 'jan@example.com', 'comment' => 'Adres: ul. Testowa 1', 'status' => 'open',
            'created_at' => $at, 'updated_at' => $at,
        ]));
        $oldRequest = $request('2020-07-01 10:00:00');
        $newRequest = $request('2021-07-01 10:00:00');

        Schema::disableForeignKeyConstraints();
        $grant = static fn (string $token, string $at): int => DB::table('download_grants')->insertGetId([
            'access_token' => $token, 'variant_file_set_id' => 1, 'digital_asset_version_id' => 1, 'reference_type' => 'order_line', 'reference_id' => $token,
            'email' => 'jan@example.com', 'granted_at' => $at, 'created_at' => $at, 'updated_at' => $at,
        ]);
        $oldGrant = $grant('old', '2020-07-01 10:00:00');
        $newGrant = $grant('new', '2021-07-01 10:00:00');

        $this->artisan('returns:anonymize-expired')->assertSuccessful();
        $this->artisan('digital:anonymize-expired-grants')->assertSuccessful();

        $old = DB::table('return_requests')->where('id', $oldRequest)->first();
        $this->assertStringEndsWith('@anonymized.invalid', $old->email);
        $this->assertNull($old->comment);
        $this->assertNotNull($old->anonymized_at);
        $this->assertSame('jan@example.com', DB::table('return_requests')->where('id', $newRequest)->value('email'));
        $this->assertNull(DB::table('download_grants')->where('id', $oldGrant)->value('email'));
        $this->assertSame('jan@example.com', DB::table('download_grants')->where('id', $newGrant)->value('email'));
    }

    public function test_the_anonymization_tasks_are_registered_with_the_scheduler(): void
    {
        $keys = array_map(static fn ($task): string => $task->key, app(ScheduledTaskRegistry::class)->all());

        foreach (['core.orders.anonymize_expired', 'core.returns.anonymize_expired', 'core.digital.anonymize_expired_grants'] as $key) {
            $this->assertContains($key, $keys);
        }
    }

    private function guestToken(string $orderId): string
    {
        return app(GuestOrderAccess::class)->issue($orderId);
    }
}
