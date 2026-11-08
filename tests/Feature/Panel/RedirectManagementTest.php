<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Redirects\Infrastructure\Models\Redirect;
use Litecommerce\Modules\Store\Application\SetDefaultLanguage;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class RedirectManagementTest extends TestCase
{
    use UsesIsolatedStorage;

    private StaffAccount $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();

        app(SetDefaultLanguage::class)->handle('pl', 'Polski');
        $this->staff = StaffAccount::factory()->create();
    }

    public function test_guest_cannot_manage_redirects(): void
    {
        $this->get(route('panel.settings.tabs.show', 'redirects'))->assertRedirect(route('login'));
    }

    public function test_creating_a_redirect_persists_it(): void
    {
        $this->actingAs($this->staff)->post(route('panel.redirects.store'), [
            'locale' => 'pl', 'from_path' => '/stary-adres', 'to_path' => '/nowy-adres',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('redirects', ['locale' => 'pl', 'from_path' => '/stary-adres', 'to_path' => '/nowy-adres']);
    }

    public function test_from_path_must_start_with_a_slash(): void
    {
        $this->actingAs($this->staff)->post(route('panel.redirects.store'), [
            'locale' => 'pl', 'from_path' => 'bez-slasha', 'to_path' => '/nowy-adres',
        ])->assertSessionHasErrors('from_path');
    }

    public function test_locale_must_be_an_enabled_language(): void
    {
        $this->actingAs($this->staff)->post(route('panel.redirects.store'), [
            'locale' => 'de', 'from_path' => '/stary-adres', 'to_path' => '/nowy-adres',
        ])->assertSessionHasErrors('locale');
    }

    public function test_self_redirect_is_rejected(): void
    {
        $this->actingAs($this->staff)->post(route('panel.redirects.store'), [
            'locale' => 'pl', 'from_path' => '/adres', 'to_path' => '/adres',
        ])->assertSessionHasErrors('to_path');
    }

    public function test_duplicate_from_path_for_the_same_locale_is_rejected(): void
    {
        Redirect::query()->create(['locale' => 'pl', 'from_path' => '/a', 'to_path' => '/b']);

        $this->actingAs($this->staff)->post(route('panel.redirects.store'), [
            'locale' => 'pl', 'from_path' => '/a', 'to_path' => '/c',
        ])->assertSessionHasErrors('from_path');
    }

    public function test_pointing_a_new_redirect_at_an_existing_redirects_source_is_rejected_as_a_chain(): void
    {
        Redirect::query()->create(['locale' => 'pl', 'from_path' => '/b', 'to_path' => '/c']);

        $this->actingAs($this->staff)->post(route('panel.redirects.store'), [
            'locale' => 'pl', 'from_path' => '/a', 'to_path' => '/b',
        ])->assertSessionHasErrors('to_path');
    }

    public function test_sourcing_a_new_redirect_from_an_existing_redirects_target_is_rejected_as_a_chain(): void
    {
        Redirect::query()->create(['locale' => 'pl', 'from_path' => '/a', 'to_path' => '/b']);

        $this->actingAs($this->staff)->post(route('panel.redirects.store'), [
            'locale' => 'pl', 'from_path' => '/b', 'to_path' => '/c',
        ])->assertSessionHasErrors('from_path');
    }

    public function test_updating_a_redirect_may_keep_its_own_from_path_unchanged(): void
    {
        $redirect = Redirect::query()->create(['locale' => 'pl', 'from_path' => '/a', 'to_path' => '/b']);

        $this->actingAs($this->staff)->put(route('panel.redirects.update', $redirect->id), [
            'locale' => 'pl', 'from_path' => '/a', 'to_path' => '/c',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('redirects', ['id' => $redirect->id, 'from_path' => '/a', 'to_path' => '/c']);
    }

    public function test_deleting_a_redirect_removes_it(): void
    {
        $redirect = Redirect::query()->create(['locale' => 'pl', 'from_path' => '/a', 'to_path' => '/b']);

        $this->actingAs($this->staff)->delete(route('panel.redirects.destroy', $redirect->id))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('redirects', ['id' => $redirect->id]);
    }
}
