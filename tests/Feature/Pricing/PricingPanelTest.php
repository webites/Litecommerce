<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * HTTP-level coverage for the pricing settings tab: adding a currency
 * (Store), setting its exchange rate and managing tax rates (Pricing),
 * plus the ManagePricing permission gate.
 */
final class PricingPanelTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('shop_currencies')->insert([
            'code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_administrator_can_add_a_currency_and_set_its_rate(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.pricing.currencies.store'), [
            'code' => 'eur', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shop_currencies', ['code' => 'EUR', 'is_base' => false]);

        $this->actingAs($staff)->put(route('panel.pricing.currencies.rate.update', 'EUR'), [
            'base_units_per_unit' => '4.3210',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('exchange_rates', ['currency_code' => 'EUR']);

        // The stored value is a fixed 6-decimal string ("4.321000") —
        // the panel must display the trimmed "4.321" a merchant entered,
        // not the raw column value.
        $this->actingAs($staff)->getJson(route('panel.settings.tabs.show', 'pricing'))
            ->assertOk()
            ->assertJsonPath('component', 'pricing')
            ->assertJsonPath('props.currencies.1.rate', '4.321');
    }

    public function test_setting_a_rate_for_the_base_currency_is_rejected(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->put(route('panel.pricing.currencies.rate.update', 'PLN'), [
            'base_units_per_unit' => '1',
        ])->assertStatus(422);
    }

    public function test_it_creates_and_updates_a_tax_rate(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.pricing.tax-rates.store'), [
            'name' => 'Podstawowa', 'kind' => 'percentage', 'percentage' => '23',
        ])->assertSessionHasNoErrors();

        $taxRateId = (int) DB::table('tax_rates')->value('id');

        $this->actingAs($staff)->put(route('panel.pricing.tax-rates.update', $taxRateId), [
            'name' => 'Podstawowa (23%)', 'kind' => 'percentage', 'percentage' => '23',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tax_rates', ['id' => $taxRateId, 'name' => 'Podstawowa (23%)']);
    }

    public function test_an_exempt_tax_rate_does_not_require_a_percentage(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.pricing.tax-rates.store'), [
            'name' => 'Zwolniona', 'kind' => 'exempt',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tax_rates', ['name' => 'Zwolniona', 'kind' => 'exempt', 'percentage' => null]);
    }

    public function test_staff_without_manage_pricing_permission_is_forbidden(): void
    {
        $staff = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();

        $this->actingAs($staff)->getJson(route('panel.settings.tabs.show', 'pricing'))->assertNotFound();
    }
}
