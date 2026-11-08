<?php

namespace Tests\Feature\Auth;

use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessage;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        $user = StaffAccount::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        $this->assertDatabaseHas('email_messages', [
            'definition_key' => 'core.staff_password_reset',
            'source_type' => 'staff_account',
            'source_id' => (string) $user->id,
            'status' => 'queued',
        ]);
    }

    public function test_reset_request_does_not_reveal_whether_staff_account_exists(): void
    {
        $existing = StaffAccount::factory()->create();

        $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => $existing->email]);
        $knownStatus = $known->getSession()->get('status');
        $missing = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'missing@example.com']);

        $known->assertRedirect('/forgot-password');
        $missing->assertRedirect('/forgot-password');
        $this->assertSame($knownStatus, $missing->getSession()->get('status'));
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $user = StaffAccount::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);
        $response = $this->get('/reset-password/'.$this->resetToken());
        $response->assertStatus(200);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = StaffAccount::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);
        $response = $this->post('/reset-password', [
            'token' => $this->resetToken(),
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect(route('login'));
    }

    private function resetToken(): string
    {
        $message = EmailMessage::query()->where('definition_key', 'core.staff_password_reset')->latest()->firstOrFail();
        $url = (string) ($message->variables['reset_url'] ?? '');
        $path = (string) parse_url($url, PHP_URL_PATH);

        return basename($path);
    }
}
