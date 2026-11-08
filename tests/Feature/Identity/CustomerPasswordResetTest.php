<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessage;
use Litecommerce\Modules\Identity\Infrastructure\Models\CustomerAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class CustomerPasswordResetTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_response_does_not_enumerate_accounts_and_valid_token_resets_password(): void
    {
        $customer = CustomerAccount::query()->create(['email' => 'buyer@example.com', 'password' => 'OldPassword123!']);

        $existing = $this->postJson(route('customer.password.email'), ['email' => $customer->email])->assertOk();
        $missing = $this->postJson(route('customer.password.email'), ['email' => 'missing@example.com'])->assertOk();
        $this->assertSame($existing->json('message'), $missing->json('message'));
        $this->assertDatabaseCount('email_messages', 1);

        $message = EmailMessage::query()->where('definition_key', 'core.customer_password_reset')->firstOrFail();
        $path = (string) parse_url((string) $message->variables['reset_url'], PHP_URL_PATH);
        $token = basename($path);

        $this->postJson(route('customer.password.reset'), [
            'email' => $customer->email,
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', (string) $customer->fresh()->password));
        $this->assertDatabaseMissing('customer_password_reset_tokens', ['email' => $customer->email]);
    }
}
