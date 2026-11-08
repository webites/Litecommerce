<?php

declare(strict_types=1);

namespace Tests\Feature\Installer;

use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class InstallationGateMiddlewareTest extends TestCase
{
    use UsesIsolatedStorage;

    public function test_visitor_is_redirected_to_install_when_not_installed(): void
    {
        $this->useIsolatedStorage();

        $this->get('/login')->assertRedirect('/install');
    }

    public function test_install_routes_are_reachable_when_not_installed(): void
    {
        $this->useIsolatedStorage();

        $this->get('/install')->assertOk();
    }

    public function test_database_step_form_loads_over_file_session_before_any_migration_ran(): void
    {
        $this->useIsolatedStorage();

        $this->get('/install/database')->assertOk();
    }

    public function test_health_check_reachable_regardless_of_installation_state(): void
    {
        $this->useIsolatedStorage();

        $this->get('/up')->assertOk();
    }

    public function test_install_routes_redirect_to_login_when_already_installed(): void
    {
        $this->markAsInstalled();

        $this->get('/install')->assertRedirect(route('login'));
    }

    public function test_non_install_routes_reachable_when_installed(): void
    {
        $this->markAsInstalled();

        $this->get('/login')->assertOk();
    }

    private function markAsInstalled(): void
    {
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
    }
}
