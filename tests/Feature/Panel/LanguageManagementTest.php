<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Store\Application\SetDefaultLanguage;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * There was no panel CRUD for languages before P1-014's "canonical/noindex
 * ogólne" step — the only prior way to get a second store language was
 * SetDefaultLanguage (wizard, first language only) or a direct DB insert.
 * See docs/knowledge-base/09-presentation.md's P1-014 SEO section.
 */
final class LanguageManagementTest extends TestCase
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

    public function test_guest_cannot_manage_languages(): void
    {
        $this->get(route('panel.settings.tabs.show', 'languages'))->assertRedirect(route('login'));
    }

    public function test_adding_a_language_creates_it_enabled_and_never_default(): void
    {
        $this->actingAs($this->staff)->post(route('panel.languages.store'), [
            'code' => 'en', 'name' => 'English', 'is_enabled' => true, 'noindex' => false,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('languages', [
            'code' => 'en', 'name' => 'English', 'is_default' => false, 'is_enabled' => true, 'noindex' => false,
        ]);
    }

    public function test_language_code_must_be_unique(): void
    {
        $this->actingAs($this->staff)->post(route('panel.languages.store'), [
            'code' => 'pl', 'name' => 'Duplikat', 'is_enabled' => true, 'noindex' => false,
        ])->assertSessionHasErrors('code');
    }

    public function test_updating_a_language_saves_name_enabled_and_noindex(): void
    {
        DB::table('languages')->insert(['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_enabled' => true, 'noindex' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->staff)->put(route('panel.languages.update', 'en'), [
            'name' => 'Angielski', 'is_enabled' => true, 'is_default' => false, 'noindex' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('languages', ['code' => 'en', 'name' => 'Angielski', 'noindex' => true]);
    }

    public function test_promoting_a_language_to_default_demotes_the_previous_one_and_forces_it_enabled(): void
    {
        DB::table('languages')->insert(['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_enabled' => false, 'noindex' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->staff)->put(route('panel.languages.update', 'en'), [
            'name' => 'English', 'is_enabled' => false, 'is_default' => true, 'noindex' => false,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('languages', ['code' => 'en', 'is_default' => true, 'is_enabled' => true]);
        $this->assertDatabaseHas('languages', ['code' => 'pl', 'is_default' => false]);
    }

    public function test_unsetting_the_current_default_without_promoting_another_is_a_no_op(): void
    {
        $this->actingAs($this->staff)->put(route('panel.languages.update', 'pl'), [
            'name' => 'Polski', 'is_enabled' => true, 'is_default' => false, 'noindex' => false,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('languages', ['code' => 'pl', 'is_default' => true]);
    }

    public function test_disabling_the_default_language_is_a_no_op_it_always_stays_enabled(): void
    {
        DB::table('languages')->insert(['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_enabled' => true, 'noindex' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->staff)->put(route('panel.languages.update', 'pl'), [
            'name' => 'Polski', 'is_enabled' => false, 'is_default' => false, 'noindex' => false,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('languages', ['code' => 'pl', 'is_default' => true, 'is_enabled' => true]);
    }

    /**
     * Only reachable when a non-default language is somehow the last one
     * enabled — the default is always forced enabled by this same class, so
     * this can only happen from data that predates that rule (a direct DB
     * write, a future migration). Defense in depth, not a normal UI path —
     * the panel form itself already disables this toggle for the default row.
     */
    public function test_disabling_the_last_enabled_language_is_rejected_even_when_it_is_not_the_default(): void
    {
        DB::table('languages')->insert(['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_enabled' => true, 'noindex' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('languages')->where('code', 'pl')->update(['is_enabled' => false]);

        $this->actingAs($this->staff)->put(route('panel.languages.update', 'en'), [
            'name' => 'English', 'is_enabled' => false, 'is_default' => false, 'noindex' => false,
        ])->assertSessionHasErrors('is_enabled');

        $this->assertDatabaseHas('languages', ['code' => 'en', 'is_enabled' => true]);
    }

    public function test_disabling_a_non_default_language_is_allowed_when_another_stays_enabled(): void
    {
        DB::table('languages')->insert(['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_enabled' => true, 'noindex' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->staff)->put(route('panel.languages.update', 'en'), [
            'name' => 'English', 'is_enabled' => false, 'is_default' => false, 'noindex' => false,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('languages', ['code' => 'en', 'is_enabled' => false]);
    }
}
