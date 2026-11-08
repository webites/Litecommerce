<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Hash;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_valid_credentials_authenticate_and_redirect_to_dashboard(): void
    {
        StaffAccount::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'Password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_invalid_credentials_show_generic_error(): void
    {
        StaffAccount::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = StaffAccount::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Password123'),
        ]);

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_authenticated_user_visiting_login_is_redirected_to_dashboard(): void
    {
        $user = StaffAccount::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Password123'),
        ]);

        $this->actingAs($user)->get('/login')->assertRedirect(route('dashboard'));
    }
}
