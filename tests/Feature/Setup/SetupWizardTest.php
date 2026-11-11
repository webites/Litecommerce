<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Litecommerce\Modules\Email\Contracts\EmailSettings;
use Litecommerce\Modules\Email\Contracts\EmailTransportException;
use Litecommerce\Modules\Email\Contracts\TestEmailSender;
use Litecommerce\Modules\Email\Contracts\TransportFailureKind;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Store\Application\SetBaseCurrency;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;
use Litecommerce\Modules\Store\Infrastructure\Models\Language;
use Litecommerce\Modules\Store\Infrastructure\Models\ShopCurrency;
use Litecommerce\Modules\Warehouse\Contracts\WarehouseProvisioner;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class SetupWizardTest extends TestCase
{
    use UsesIsolatedStorage;

    private StaffAccount $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();

        $this->admin = StaffAccount::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Password123'),
        ]);
    }

    private function page(string $component): \Closure
    {
        return fn (AssertableInertia $page) => $page->component($component);
    }

    private function completeRequiredSteps(): void
    {
        $this->actingAs($this->admin)->post('/panel/wizard', ['code' => 'en'])->assertRedirect(route('panel.wizard.show'));
        $this->actingAs($this->admin)->post('/panel/wizard', ['code' => 'usd', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2])->assertRedirect(route('panel.wizard.show'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/panel/wizard')->assertRedirect(route('login'));
        $this->post('/panel/wizard/skip')->assertRedirect(route('login'));
    }

    public function test_the_wizard_starts_with_the_language_and_offers_the_core_languages(): void
    {
        $this->actingAs($this->admin)->get('/panel/wizard')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Panel/Wizard/Language')
            ->where('languages', [['code' => 'en', 'name' => 'English'], ['code' => 'pl', 'name' => 'Polski']])
            ->where('skippable', false));
    }

    public function test_the_default_language_is_one_of_the_core_languages_with_its_own_name(): void
    {
        $this->actingAs($this->admin)->post('/panel/wizard', ['code' => 'xx'])->assertSessionHasErrors('code');
        $this->assertSame(0, Language::query()->count());

        $this->actingAs($this->admin)->post('/panel/wizard', ['code' => 'pl'])->assertRedirect(route('panel.wizard.show'));

        $language = Language::query()->where('is_default', true)->sole();
        $this->assertSame(['pl', 'Polski'], [$language->code, $language->name]);
        $this->actingAs($this->admin)->get('/panel/wizard')->assertInertia($this->page('Panel/Wizard/Currency'));
    }

    public function test_submitting_currency_creates_exactly_one_base_currency_and_advances(): void
    {
        $this->actingAs($this->admin)->post('/panel/wizard', ['code' => 'en']);

        $this->actingAs($this->admin)->post('/panel/wizard', ['code' => 'pln', 'name' => 'Polish Zloty', 'symbol' => 'zł', 'decimal_places' => 2])
            ->assertRedirect(route('panel.wizard.show'));

        $this->assertSame(1, ShopCurrency::query()->where('is_base', true)->count());
        $this->assertSame('PLN', ShopCurrency::query()->where('is_base', true)->first()->code);
        $this->actingAs($this->admin)->get('/panel/wizard')->assertInertia(fn (AssertableInertia $page) => $page->component('Panel/Wizard/Profile')->where('skippable', true));
    }

    public function test_invalid_currency_input_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/panel/wizard', ['code' => 'en']);

        $this->actingAs($this->admin)->post('/panel/wizard', ['code' => '12', 'name' => '', 'symbol' => '', 'decimal_places' => 9])
            ->assertSessionHasErrors(['code', 'name', 'symbol', 'decimal_places']);
        $this->assertSame(0, ShopCurrency::query()->count());
    }

    public function test_setting_a_new_base_currency_unsets_the_previous_one(): void
    {
        $setBaseCurrency = app(SetBaseCurrency::class);

        $setBaseCurrency->handle('PLN', 'Polski złoty', 'zł', 2);
        $setBaseCurrency->handle('EUR', 'Euro', '€', 2);

        $this->assertSame(1, ShopCurrency::query()->where('is_base', true)->count());
        $this->assertSame('EUR', ShopCurrency::query()->where('is_base', true)->first()->code);
    }

    public function test_the_required_steps_cannot_be_skipped(): void
    {
        $this->actingAs($this->admin)->post('/panel/wizard/skip')->assertStatus(422);

        $this->actingAs($this->admin)->post('/panel/wizard', ['code' => 'en']);
        $this->actingAs($this->admin)->post('/panel/wizard/skip')->assertStatus(422);
    }

    public function test_the_store_name_is_saved_in_the_profile_step(): void
    {
        $this->completeRequiredSteps();

        $this->actingAs($this->admin)->post('/panel/wizard', ['store_name' => ''])->assertSessionHasErrors('store_name');
        $this->actingAs($this->admin)->post('/panel/wizard', ['store_name' => 'Acme Store'])->assertRedirect(route('panel.wizard.show'));

        $this->assertSame('Acme Store', app(StoreSettingsReader::class)->current()->storeName);
        $this->actingAs($this->admin)->get('/panel/wizard')->assertInertia($this->page('Panel/Wizard/Pricing'));
    }

    public function test_pricing_sets_the_entry_mode_and_an_optional_tax_rate_once(): void
    {
        $this->completeRequiredSteps();
        $this->actingAs($this->admin)->post('/panel/wizard/skip');

        $this->actingAs($this->admin)->post('/panel/wizard', ['price_entry_mode' => 'net', 'tax_rate_name' => 'Sales tax'])->assertSessionHasErrors('tax_rate_percentage');
        $this->actingAs($this->admin)->post('/panel/wizard', ['price_entry_mode' => 'net', 'tax_rate_name' => 'Sales tax', 'tax_rate_percentage' => '8'])
            ->assertRedirect(route('panel.wizard.show'));

        $this->assertSame('net', DB::table('pricing_settings')->value('price_entry_mode'));
        $this->assertSame(1, DB::table('tax_rates')->where('name', 'Sales tax')->where('kind', 'percentage')->count());
        $this->assertEquals(8, DB::table('tax_rates')->where('name', 'Sales tax')->value('percentage'));
    }

    public function test_pricing_without_a_tax_rate_only_changes_the_mode(): void
    {
        $this->completeRequiredSteps();
        $this->actingAs($this->admin)->post('/panel/wizard/skip');

        $this->actingAs($this->admin)->post('/panel/wizard', ['price_entry_mode' => 'gross'])->assertRedirect(route('panel.wizard.show'));

        $this->assertSame('gross', DB::table('pricing_settings')->value('price_entry_mode'));
        $this->assertSame(0, DB::table('tax_rates')->count());
    }

    public function test_the_delivery_step_adds_a_local_pickup_method_with_its_point_and_the_only_tax_rate(): void
    {
        $this->completeRequiredSteps();
        $this->actingAs($this->admin)->post('/panel/wizard/skip');
        $this->actingAs($this->admin)->post('/panel/wizard', ['price_entry_mode' => 'gross', 'tax_rate_name' => 'VAT 23%', 'tax_rate_percentage' => '23']);

        $this->actingAs($this->admin)->get('/panel/wizard')->assertInertia(fn (AssertableInertia $page) => $page->component('Panel/Wizard/Delivery')->where('baseCurrency', 'USD')->has('countries'));
        $this->actingAs($this->admin)->post('/panel/wizard', ['kind' => 'pickup', 'method_name' => 'Odbiór osobisty', 'country_code' => 'pl'])
            ->assertSessionHasErrors(['point_name', 'postal_code', 'city', 'address_line_1']);
        $this->actingAs($this->admin)->post('/panel/wizard', [
            'kind' => 'pickup', 'method_name' => 'Odbiór osobisty', 'country_code' => 'pl', 'point_name' => 'Sklep główny',
            'postal_code' => '50-001', 'city' => 'Wrocław', 'address_line_1' => 'Rynek 1',
        ])->assertRedirect(route('panel.wizard.show'));

        $method = DB::table('delivery_methods')->first();
        $this->assertSame('pickup', $method->kind);
        $this->assertSame(1, (int) $method->active);
        $this->assertSame((int) DB::table('tax_rates')->value('id'), (int) $method->tax_rate_id);
        $this->assertDatabaseHas('delivery_pickup_points', ['delivery_method_id' => $method->id, 'name' => 'Sklep główny', 'country_code' => 'PL', 'city' => 'Wrocław', 'active' => 1]);
        $this->assertDatabaseHas('audit_entries', ['action' => 'setup.step_saved', 'subject_id' => 'delivery']);
    }

    public function test_the_delivery_step_adds_a_courier_with_one_country_rate(): void
    {
        $this->completeRequiredSteps();
        $this->actingAs($this->admin)->post('/panel/wizard/skip');
        $this->actingAs($this->admin)->post('/panel/wizard/skip');

        $this->actingAs($this->admin)->post('/panel/wizard', ['kind' => 'courier', 'method_name' => 'Kurier', 'country_code' => 'PL'])->assertSessionHasErrors('price_amount');
        $this->actingAs($this->admin)->post('/panel/wizard', ['kind' => 'courier', 'method_name' => 'Kurier', 'country_code' => 'PL', 'price_amount' => '15'])
            ->assertRedirect(route('panel.wizard.show'));

        $method = DB::table('delivery_methods')->first();
        $this->assertSame('flat_rate', $method->kind);
        $this->assertNull($method->tax_rate_id);
        $this->assertDatabaseHas('delivery_region_rules', ['delivery_method_id' => $method->id, 'rule_type' => 'country_rate', 'country_code' => 'PL']);
        $this->assertEquals(15, DB::table('delivery_region_rules')->value('price_amount'));
    }

    public function test_a_form_for_an_earlier_step_does_not_act_on_the_current_one(): void
    {
        app(WarehouseProvisioner::class)->ensureDefaultWarehouseExists();
        $this->advanceToEmailStep();

        $this->actingAs($this->admin)->post('/panel/wizard?step=delivery')->assertRedirect(route('panel.wizard.show'));
        $this->actingAs($this->admin)->post('/panel/wizard/skip?step=delivery')->assertRedirect(route('panel.wizard.show'));

        $this->assertSame(0, DB::table('demo_data_batches')->count());
        $this->actingAs($this->admin)->get('/panel/wizard')->assertInertia($this->page('Panel/Wizard/Email'));
    }

    public function test_the_delivery_step_can_be_skipped_without_creating_a_method(): void
    {
        $this->completeRequiredSteps();
        $this->actingAs($this->admin)->post('/panel/wizard/skip');
        $this->actingAs($this->admin)->post('/panel/wizard/skip');

        $this->actingAs($this->admin)->post('/panel/wizard/skip')->assertRedirect(route('panel.wizard.show'));

        $this->assertSame(0, DB::table('delivery_methods')->count());
        $this->assertDatabaseHas('audit_entries', ['action' => 'setup.step_skipped', 'subject_id' => 'delivery']);
    }

    public function test_email_settings_are_saved_through_the_email_module(): void
    {
        $this->advanceToEmailStep();

        $this->actingAs($this->admin)->get('/panel/wizard')->assertInertia(fn (AssertableInertia $page) => $page->component('Panel/Wizard/Email')->where('adminEmail', 'admin@example.com'));
        $this->actingAs($this->admin)->post('/panel/wizard', ['from_name' => 'Shop', 'from_email' => 'nope'])->assertSessionHasErrors(['from_email', 'smtp_host', 'smtp_port']);

        $this->actingAs($this->admin)->post('/panel/wizard', [
            'from_name' => 'Acme Store', 'from_email' => 'Shop@Example.com', 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587,
            'smtp_encryption' => 'tls', 'smtp_username' => 'mailer', 'smtp_password' => 's3cret',
        ])->assertRedirect(route('panel.wizard.show'));

        $settings = app(EmailSettings::class)->current();
        $this->assertSame(['Acme Store', 'shop@example.com', 'smtp.example.com', 587, 'smtp'], [$settings->fromName, $settings->fromEmail, $settings->smtpHost, $settings->smtpPort, $settings->transportKey]);
        $this->assertTrue($settings->smtpPasswordConfigured);
        $this->assertSame('s3cret', app(EmailSettings::class)->smtpPassword());
    }

    /** @return array<string, mixed> */
    private function smtpForm(): array
    {
        return ['from_name' => 'Acme Store', 'from_email' => 'shop@example.com', 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_encryption' => 'tls'];
    }

    private function advanceToEmailStep(): void
    {
        $this->completeRequiredSteps();
        foreach (['profile', 'pricing', 'delivery'] as $step) {
            $this->actingAs($this->admin)->post('/panel/wizard/skip');
        }
    }

    public function test_a_test_message_goes_to_the_administrator_and_the_step_stays_open(): void
    {
        $this->advanceToEmailStep();
        $this->mock(TestEmailSender::class)->shouldReceive('send')->once()->with('admin@example.com', 'en');

        $this->actingAs($this->admin)->post('/panel/wizard/email/test', $this->smtpForm())->assertRedirect()->assertSessionHas('status');

        $this->assertSame('smtp.example.com', app(EmailSettings::class)->current()->smtpHost);
        $this->actingAs($this->admin)->get('/panel/wizard')->assertInertia(fn (AssertableInertia $page) => $page->component('Panel/Wizard/Email'));
    }

    public function test_a_failing_test_message_reports_the_reason_and_does_not_finish_the_step(): void
    {
        $this->advanceToEmailStep();
        $this->mock(TestEmailSender::class)->shouldReceive('send')->once()
            ->andThrow(new EmailTransportException(TransportFailureKind::Temporary, 'SMTP transport failed.', 'smtp_transport_error', new \RuntimeException('Connection refused')));

        $this->actingAs($this->admin)->post('/panel/wizard/email/test', $this->smtpForm())->assertSessionHas('error', 'The test message could not be sent: Connection refused');

        $this->actingAs($this->admin)->get('/panel/wizard')->assertInertia(fn (AssertableInertia $page) => $page->component('Panel/Wizard/Email'));
    }

    public function test_a_test_message_is_only_available_on_the_email_step_and_needs_valid_settings(): void
    {
        $this->actingAs($this->admin)->post('/panel/wizard/email/test', $this->smtpForm())->assertUnprocessable();

        $this->advanceToEmailStep();
        $this->actingAs($this->admin)->post('/panel/wizard/email/test', ['from_email' => 'nope'])->assertSessionHasErrors(['from_email', 'smtp_host']);
    }

    public function test_skipping_everything_optional_finishes_the_wizard(): void
    {
        $this->completeRequiredSteps();
        foreach (['profile', 'pricing', 'delivery', 'email', 'demo'] as $step) {
            $this->actingAs($this->admin)->post('/panel/wizard/skip')->assertRedirect(route('panel.wizard.show'));
        }

        $this->actingAs($this->admin)->get('/panel/wizard')->assertInertia($this->page('Panel/Wizard/Done'));
        $this->actingAs($this->admin)->post('/panel/wizard/skip')->assertStatus(422);
        $this->assertDatabaseHas('audit_entries', ['action' => 'setup.step_skipped', 'subject_id' => 'demo']);
    }

    public function test_demo_data_can_be_added_from_the_last_step(): void
    {
        app(WarehouseProvisioner::class)->ensureDefaultWarehouseExists();
        $this->completeRequiredSteps();
        foreach (['profile', 'pricing', 'delivery', 'email'] as $step) {
            $this->actingAs($this->admin)->post('/panel/wizard/skip');
        }

        $this->actingAs($this->admin)->get('/panel/wizard')->assertInertia($this->page('Panel/Wizard/Demo'));
        $this->actingAs($this->admin)->post('/panel/wizard')->assertRedirect(route('panel.wizard.show'))->assertSessionMissing('error');

        $this->assertDatabaseHas('audit_entries', ['action' => 'setup.step_saved', 'subject_id' => 'demo']);
        $this->assertGreaterThan(0, DB::table('demo_data_batches')->count());
        $this->actingAs($this->admin)->get('/panel/wizard')->assertInertia($this->page('Panel/Wizard/Done'));
    }

    public function test_the_panel_waits_for_the_required_steps_only(): void
    {
        $this->actingAs($this->admin)->get('/panel')->assertRedirect(route('panel.wizard.show'));

        $this->completeRequiredSteps();

        $this->actingAs($this->admin)->get('/panel')->assertOk();
    }

    public function test_the_wizard_texts_exist_in_both_core_languages_with_matching_keys(): void
    {
        $english = require base_path('modules/Setup/lang/en/panel.php');
        $polish = require base_path('modules/Setup/lang/pl/panel.php');

        $this->assertSame($this->flatten($english), $this->flatten($polish));
        $this->assertSame('e.g. USD', $english['currency']['code_example']);
        $this->assertSame('np. PLN', $polish['currency']['code_example']);
    }

    /** @return list<string> */
    private function flatten(array $messages, string $prefix = ''): array
    {
        $keys = [];
        foreach ($messages as $key => $value) {
            is_array($value) ? array_push($keys, ...$this->flatten($value, "{$prefix}{$key}.")) : $keys[] = "{$prefix}{$key}";
        }

        return $keys;
    }
}
