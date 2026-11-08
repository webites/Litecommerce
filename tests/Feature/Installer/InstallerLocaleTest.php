<?php

declare(strict_types=1);

namespace Tests\Feature\Installer;

use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class InstallerLocaleTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
    }

    public function test_the_installer_follows_the_browsers_preferred_supported_language(): void
    {
        $this->withHeader('Accept-Language', 'pl-PL,pl;q=0.9,en;q=0.5')->get('/install')
            ->assertOk()->assertSee('Wymagania serwera')->assertSee('lang="pl"', false);

        $this->withHeader('Accept-Language', 'de-DE,de;q=0.9,en;q=0.5')->get('/install')
            ->assertOk()->assertSee('Server requirements');
    }

    public function test_the_visitor_can_switch_the_language_and_the_choice_beats_the_browser(): void
    {
        $response = $this->withHeader('Accept-Language', 'en')->from('/install')->post('/install/locale', ['locale' => 'pl']);

        $response->assertRedirect('/install')->assertCookie('ui_locale', 'pl', false);
        $this->withUnencryptedCookie('ui_locale', 'pl')->withHeader('Accept-Language', 'en')->get('/install/database')
            ->assertSee('Połączenie z bazą danych')->assertSee('Nazwa bazy');
    }

    public function test_an_unsupported_language_is_rejected(): void
    {
        $this->from('/install')->post('/install/locale', ['locale' => 'xx'])->assertSessionHasErrors('locale');
    }

    public function test_the_choice_survives_the_switch_of_the_session_store_through_the_cookie(): void
    {
        $this->withUnencryptedCookie('ui_locale', 'pl')->get('/install/database')
            ->assertOk()->assertSee('Połączenie z bazą danych');
    }

    public function test_validation_messages_use_translated_field_names(): void
    {
        $this->withUnencryptedCookie('ui_locale', 'pl')->from('/install/database')->post('/install/database', [])
            ->assertSessionHasErrors(['host', 'database']);
        $this->assertStringContainsString('nazwa bazy', session('errors')->first('database'));
    }
}
