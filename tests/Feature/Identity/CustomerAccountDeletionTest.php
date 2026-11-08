<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessage;
use Litecommerce\Modules\Identity\Contracts\Events\CustomerAccountDeleted;
use Litecommerce\Modules\Identity\Infrastructure\Models\CustomerAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class CustomerAccountDeletionTest extends TestCase
{
    use UsesIsolatedStorage;

    private const PASSWORD = 'CorrectPassword123!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function customer(string $email = 'buyer@example.com'): CustomerAccount
    {
        $customer = CustomerAccount::query()->create(['email' => $email, 'password' => self::PASSWORD]);
        DB::table('buyer_profiles')->insert(['customer_account_id' => $customer->getKey(), 'type' => 'person', 'first_name' => 'Jan', 'last_name' => 'Kowalski', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('address_book_entries')->insert([
            'customer_account_id' => $customer->getKey(), 'recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001',
            'city' => 'Warszawa', 'address_line_1' => 'Testowa 1', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $customer;
    }

    private function requestDeletion(CustomerAccount $customer, string $password = self::PASSWORD): TestResponse
    {
        return $this->actingAs($customer, 'customer')->postJson(route('customer.account.deletion.request'), ['password' => $password]);
    }

    /** @var list<string> */
    private array $seenTokens = [];

    private function issuedToken(): string
    {
        foreach (EmailMessage::query()->where('definition_key', 'core.customer_account_deletion_confirm')->get() as $message) {
            $token = basename((string) parse_url((string) $message->variables['confirm_url'], PHP_URL_PATH));
            if (! in_array($token, $this->seenTokens, true)) {
                $this->seenTokens[] = $token;

                return $token;
            }
        }

        $this->fail('No new confirmation link was issued.');
    }

    public function test_request_requires_a_signed_in_customer(): void
    {
        $this->postJson(route('customer.account.deletion.request'), ['password' => self::PASSWORD])->assertUnauthorized();
        $this->assertDatabaseCount('customer_account_deletion_requests', 0);
    }

    public function test_wrong_password_is_refused_and_throttled_without_sending_mail(): void
    {
        $customer = $this->customer();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->requestDeletion($customer, 'wrong')->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->requestDeletion($customer)->assertUnprocessable();

        $this->assertDatabaseCount('customer_account_deletion_requests', 0);
        $this->assertDatabaseCount('email_messages', 0);
    }

    public function test_refusals_are_worded_in_the_storefront_language_sent_with_the_request(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'customer')->postJson(route('customer.account.deletion.request'), ['password' => 'wrong', 'locale' => 'pl'])
            ->assertUnprocessable()->assertJsonPath('errors.password.0', 'Hasło jest nieprawidłowe.');
        $this->postJson(route('customer.account.deletion.confirm'), ['token' => 'nope', 'locale' => 'pl'])
            ->assertUnprocessable()->assertJsonPath('errors.token.0', trans('identity::account_deletion.invalid_link', [], 'pl'));
        $this->postJson(route('customer.account.deletion.request'), ['password' => 'wrong', 'locale' => 'xx'])
            ->assertUnprocessable()->assertJsonPath('errors.password.0', trans('identity::account_deletion.invalid_password', [], 'pl'));
    }

    public function test_correct_password_sends_one_link_and_stores_only_its_hash(): void
    {
        $customer = $this->customer();

        $this->requestDeletion($customer)->assertOk();

        $token = $this->issuedToken();
        $this->assertSame(64, strlen($token));
        $stored = DB::table('customer_account_deletion_requests')->where('customer_account_id', $customer->getKey())->first();
        $this->assertSame(hash('sha256', $token), $stored->token_hash);
        $this->assertDatabaseHas('audit_entries', ['action' => 'identity.customer_account.deletion_requested', 'subject_id' => (string) $customer->getKey()]);
        $this->assertNotNull(CustomerAccount::query()->find($customer->getKey()));
    }

    public function test_a_new_request_replaces_the_previous_link(): void
    {
        $customer = $this->customer();
        $this->requestDeletion($customer)->assertOk();
        $first = $this->issuedToken();
        $this->requestDeletion($customer)->assertOk();
        $second = $this->issuedToken();

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('customer_account_deletion_requests', 1);
        $this->postJson(route('customer.account.deletion.confirm'), ['token' => $first])->assertUnprocessable();
        $this->assertNotNull(CustomerAccount::query()->find($customer->getKey()));
    }

    public function test_an_active_purchase_block_prevents_self_service_deletion(): void
    {
        $customer = $this->customer();
        DB::table('account_purchase_blocks')->insert(['customer_account_id' => $customer->getKey(), 'reason' => 'Fraud', 'blocked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->requestDeletion($customer)->assertUnprocessable();

        $this->assertDatabaseCount('customer_account_deletion_requests', 0);
    }

    public function test_a_block_added_after_the_link_was_issued_prevents_the_deletion(): void
    {
        $customer = $this->customer();
        $this->requestDeletion($customer)->assertOk();
        $token = $this->issuedToken();
        DB::table('account_purchase_blocks')->insert(['customer_account_id' => $customer->getKey(), 'reason' => 'Fraud', 'blocked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->postJson(route('customer.account.deletion.confirm'), ['token' => $token])->assertUnprocessable();

        $this->assertNotNull(CustomerAccount::query()->find($customer->getKey()));
    }

    public function test_confirming_removes_the_account_its_data_and_carts_but_keeps_orders(): void
    {
        $customer = $this->customer();
        $other = $this->customer('other@example.com');
        $cartId = (int) DB::table('carts')->insertGetId(['customer_account_id' => $customer->getKey(), 'currency_code' => 'PLN', 'status' => 'active', 'last_activity_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('checkout_drafts')->insert(['id' => (string) Str::uuid(), 'cart_id' => $cartId, 'cart_version_seen' => 0, 'buyer_type' => 'person', 'email' => 'buyer@example.com', 'currency_code' => 'PLN', 'created_at' => now(), 'updated_at' => now()]);
        $otherCart = (int) DB::table('carts')->insertGetId(['customer_account_id' => $other->getKey(), 'currency_code' => 'PLN', 'status' => 'active', 'last_activity_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $orderId = (string) Str::uuid();
        DB::table('orders')->insert([
            'id' => $orderId, 'customer_account_id' => $customer->getKey(), 'source_type' => 'test', 'source_id' => $orderId, 'status' => 'processing', 'payment_status' => 'paid',
            'payment_method_id' => 'core.bank_transfer', 'payment_kind' => 'bank_transfer', 'email' => 'buyer@example.com', 'currency_code' => 'PLN', 'mode' => 'live', 'locale' => 'pl',
            'total_net_amount' => '100', 'total_vat_amount' => '23', 'total_gross_amount' => '123', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->requestDeletion($customer)->assertOk();
        Event::fake([CustomerAccountDeleted::class]);

        $this->postJson(route('customer.account.deletion.confirm'), ['token' => $this->issuedToken()])->assertOk();

        $this->assertNull(CustomerAccount::query()->find($customer->getKey()));
        $this->assertDatabaseMissing('buyer_profiles', ['customer_account_id' => $customer->getKey()]);
        $this->assertDatabaseMissing('address_book_entries', ['customer_account_id' => $customer->getKey()]);
        $this->assertDatabaseMissing('carts', ['id' => $cartId]);
        $this->assertDatabaseCount('checkout_drafts', 0);
        $this->assertDatabaseHas('carts', ['id' => $otherCart]);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'customer_account_id' => null, 'email' => 'buyer@example.com']);
        $this->assertDatabaseHas('audit_entries', ['action' => 'identity.customer_account.deleted', 'subject_id' => (string) $customer->getKey()]);
        $this->assertNotNull(CustomerAccount::query()->find($other->getKey()));
        $this->assertSame(1, EmailMessage::query()->where('definition_key', 'core.customer_account_deleted')->count());
        Event::assertDispatched(CustomerAccountDeleted::class, fn (CustomerAccountDeleted $event): bool => $event->customerAccountId === $customer->getKey());
    }

    public function test_the_audit_trail_does_not_keep_the_email_address(): void
    {
        $customer = $this->customer();
        $this->requestDeletion($customer)->assertOk();
        $this->postJson(route('customer.account.deletion.confirm'), ['token' => $this->issuedToken()])->assertOk();

        foreach (DB::table('audit_entries')->where('action', 'like', 'identity.customer_account.%')->get() as $entry) {
            $this->assertStringNotContainsString('buyer@example.com', json_encode($entry));
        }
    }

    public function test_the_link_is_single_use_and_expires(): void
    {
        $customer = $this->customer();
        $this->requestDeletion($customer)->assertOk();
        $token = $this->issuedToken();
        $this->postJson(route('customer.account.deletion.confirm'), ['token' => $token])->assertOk();
        $this->postJson(route('customer.account.deletion.confirm'), ['token' => $token])->assertUnprocessable();

        $second = $this->customer('second@example.com');
        $this->requestDeletion($second)->assertOk();
        $expired = $this->issuedToken();
        Carbon::setTestNow(now()->addMinutes((int) config('identity.account_deletion_link_minutes') + 1));
        $this->postJson(route('customer.account.deletion.confirm'), ['token' => $expired])->assertUnprocessable();
        $this->assertNotNull(CustomerAccount::query()->find($second->getKey()));
    }

    public function test_an_unknown_token_changes_nothing(): void
    {
        $customer = $this->customer();

        $this->postJson(route('customer.account.deletion.confirm'), ['token' => Str::random(64)])->assertUnprocessable();

        $this->assertNotNull(CustomerAccount::query()->find($customer->getKey()));
    }

    public function test_confirming_signs_out_the_session_of_the_deleted_account_only(): void
    {
        $customer = $this->customer();
        $this->requestDeletion($customer)->assertOk();
        $token = $this->issuedToken();

        $this->actingAs($customer, 'customer')->postJson(route('customer.account.deletion.confirm'), ['token' => $token])->assertOk();

        $this->assertGuest('customer');

        $second = $this->customer('second@example.com');
        $third = $this->customer('third@example.com');
        $this->requestDeletion($second)->assertOk();
        $secondToken = $this->issuedToken();
        $this->actingAs($third, 'customer')->postJson(route('customer.account.deletion.confirm'), ['token' => $secondToken])->assertOk();
        $this->assertSame($third->getKey(), Auth::guard('customer')->id());
    }
}
