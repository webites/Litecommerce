<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Litecommerce\Modules\Audit\Infrastructure\Models\AuditEntry;
use Litecommerce\Modules\Identity\Contracts\StaffTwoFactorManager;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class StaffTwoFactorAuthenticationTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
    }

    public function test_setup_encrypts_secret_hashes_recovery_codes_and_requires_confirmation(): void
    {
        $staff = StaffAccount::factory()->create();
        $manager = app(StaffTwoFactorManager::class);

        $setup = $manager->beginSetup($staff->id);
        $staff->refresh();

        $this->assertTrue($manager->isPending($staff->id));
        $this->assertFalse($manager->isEnabled($staff->id));
        $this->assertNotSame($setup->secret, DB::table('staff_accounts')->where('id', $staff->id)->value('two_factor_secret'));
        $this->assertNotContains($setup->recoveryCodes[0], $staff->two_factor_recovery_codes);

        $code = app(Google2FA::class)->getCurrentOtp($setup->secret);
        $this->assertTrue($manager->confirmSetup($staff->id, $code));
        $this->assertTrue($manager->isEnabled($staff->id));
        $this->assertFalse($manager->verifyChallenge($staff->id, $code));
        $this->assertTrue(AuditEntry::query()->where('action', 'identity.staff.two_factor_enabled')->exists());
    }

    public function test_enabled_two_factor_interrupts_password_login_and_consumes_recovery_code_once(): void
    {
        Event::fake([Login::class]);

        $staff = StaffAccount::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'Password123',
        ]);
        $manager = app(StaffTwoFactorManager::class);
        $setup = $manager->beginSetup($staff->id);
        $manager->confirmSetup($staff->id, app(Google2FA::class)->getCurrentOtp($setup->secret));

        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'Password123',
        ])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        Event::assertNotDispatched(Login::class);

        $this->post('/two-factor-challenge', [
            'code' => $setup->recoveryCodes[0],
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($staff);
        Event::assertDispatched(Login::class);

        $this->post('/logout');
        $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'Password123',
        ]);
        $this->post('/two-factor-challenge', [
            'code' => $setup->recoveryCodes[0],
        ])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_two_factor_settings_require_recent_password_confirmation(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)
            ->post('/profile/two-factor')
            ->assertRedirect(route('password.confirm'));
    }
}
