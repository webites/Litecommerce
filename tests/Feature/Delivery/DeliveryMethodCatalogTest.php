<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Delivery\Contracts\DeliveryMethodCatalog;
use Litecommerce\Modules\Delivery\Contracts\DeliveryQuoteContext;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryMethod;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryPickupPoint;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryRegionRule;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Pricing\Infrastructure\Models\ExchangeRate;
use Litecommerce\Modules\Pricing\Infrastructure\Models\TaxRate;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Exercises the "Dostawa" rules in docs/knowledge-base/06-fulfillment.md
 * (region precedence, free threshold, currency overrides, VAT split)
 * through the public DeliveryMethodCatalog contract — the same object
 * Cart/Checkout resolve.
 */
final class DeliveryMethodCatalogTest extends TestCase
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

    private function catalog(): DeliveryMethodCatalog
    {
        return app(DeliveryMethodCatalog::class);
    }

    public function test_pickup_defaults_to_a_zero_price_when_none_is_set(): void
    {
        $method = DeliveryMethod::query()->create(['kind' => 'pickup', 'name' => 'Odbiór osobisty', 'active' => true]);
        DeliveryPickupPoint::query()->create([
            'delivery_method_id' => $method->id, 'name' => 'Sklep', 'country_code' => 'PL',
            'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1', 'active' => true,
        ]);

        $context = new DeliveryQuoteContext('PLN', 'PL', '0');
        $quote = $this->catalog()->quote($method->methodKey(), $context, 'pl');

        $this->assertNotNull($quote);
        $this->assertSame('0.00', $quote->grossAmount);
        $this->assertCount(1, $quote->pickupPoints);
    }

    public function test_pickup_with_no_active_points_is_hidden(): void
    {
        $method = DeliveryMethod::query()->create(['kind' => 'pickup', 'name' => 'Odbiór osobisty', 'active' => true]);

        $context = new DeliveryQuoteContext('PLN', 'PL', '0');

        $this->assertNull($this->catalog()->quote($method->methodKey(), $context, 'pl'));
        $this->assertSame([], $this->catalog()->quoteAvailable($context, 'pl'));
    }

    public function test_country_exclusion_takes_precedence_over_a_continent_rate(): void
    {
        $method = DeliveryMethod::query()->create(['kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true]);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'continent_rate', 'continent_code' => 'EU', 'price_amount' => '20.00']);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'exclude', 'country_code' => 'PL']);

        $context = new DeliveryQuoteContext('PLN', 'PL', '0');

        $this->assertNull($this->catalog()->quote($method->methodKey(), $context, 'pl'));
    }

    public function test_country_rate_takes_precedence_over_continent_rate(): void
    {
        $method = DeliveryMethod::query()->create(['kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true]);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'continent_rate', 'continent_code' => 'EU', 'price_amount' => '20.00']);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '14.99']);

        $context = new DeliveryQuoteContext('PLN', 'PL', '0');
        $quote = $this->catalog()->quote($method->methodKey(), $context, 'pl');

        $this->assertSame('14.99', $quote->grossAmount);
    }

    public function test_no_matching_region_is_unavailable(): void
    {
        $method = DeliveryMethod::query()->create(['kind' => 'flat_rate', 'name' => 'Kurier krajowy', 'active' => true]);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '14.99']);

        $context = new DeliveryQuoteContext('PLN', 'DE', '0');

        $this->assertNull($this->catalog()->quote($method->methodKey(), $context, 'pl'));
    }

    public function test_flat_rate_without_an_address_is_unavailable(): void
    {
        $method = DeliveryMethod::query()->create(['kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true]);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '14.99']);

        $context = new DeliveryQuoteContext('PLN', null, '0');

        $this->assertNull($this->catalog()->quote($method->methodKey(), $context, 'pl'));
    }

    public function test_free_threshold_zeroes_the_price_without_changing_availability(): void
    {
        $method = DeliveryMethod::query()->create([
            'kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true, 'free_threshold_amount' => '200.00',
        ]);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '14.99']);

        $below = $this->catalog()->quote($method->methodKey(), new DeliveryQuoteContext('PLN', 'PL', '199.99'), 'pl');
        $this->assertSame('14.99', $below->grossAmount);
        $this->assertFalse($below->freeThresholdApplied);

        $above = $this->catalog()->quote($method->methodKey(), new DeliveryQuoteContext('PLN', 'PL', '200.00'), 'pl');
        $this->assertSame('0.00', $above->grossAmount);
        $this->assertTrue($above->freeThresholdApplied);
    }

    public function test_region_price_applies_vat_and_a_currency_override(): void
    {
        $taxRate = TaxRate::query()->create(['name' => 'Podstawowa', 'kind' => 'percentage', 'percentage' => '23.00']);
        $method = DeliveryMethod::query()->create(['kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true, 'tax_rate_id' => $taxRate->id]);
        $rule = DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '10.00']);

        $plnQuote = $this->catalog()->quote($method->methodKey(), new DeliveryQuoteContext('PLN', 'PL', '0'), 'pl');
        // Default entry mode is gross (P0/installer default) — 10.00 gross at 23% VAT.
        $this->assertSame('10.00', $plnQuote->grossAmount);
        $this->assertSame('8.13', $plnQuote->netAmount);
        $this->assertSame('1.87', $plnQuote->vatAmount);

        DB::table('shop_currencies')->insert([
            'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_base' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        ExchangeRate::query()->create(['currency_code' => 'EUR', 'base_units_per_unit' => '4.000000']);
        $rule->currencyOverrides()->create(['currency_code' => 'EUR', 'price_amount' => '1.99']);

        $eurQuote = $this->catalog()->quote($method->methodKey(), new DeliveryQuoteContext('EUR', 'PL', '0'), 'pl');
        $this->assertSame('1.99', $eurQuote->grossAmount);

        $anotherCountryEurQuote = $this->catalog()->quote($method->methodKey(), new DeliveryQuoteContext('EUR', 'PL', '0'), 'pl');
        $this->assertSame('1.99', $anotherCountryEurQuote->grossAmount);
    }

    public function test_capability_flags_are_reported_independently_of_price(): void
    {
        $codMethod = DeliveryMethod::query()->create(['kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true, 'supports_cash_on_delivery' => true]);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $codMethod->id, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '10.00']);

        $pickupMethod = DeliveryMethod::query()->create(['kind' => 'pickup', 'name' => 'Odbiór', 'active' => true, 'supports_pay_on_pickup' => true]);
        DeliveryPickupPoint::query()->create([
            'delivery_method_id' => $pickupMethod->id, 'name' => 'Sklep', 'country_code' => 'PL',
            'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1', 'active' => true,
        ]);

        $this->assertTrue($this->catalog()->supportsCashOnDelivery($codMethod->methodKey()));
        $this->assertFalse($this->catalog()->supportsPayOnPickup($codMethod->methodKey()));
        $this->assertTrue($this->catalog()->supportsPayOnPickup($pickupMethod->methodKey()));
        $this->assertFalse($this->catalog()->supportsCashOnDelivery($pickupMethod->methodKey()));
    }

    public function test_inactive_methods_are_never_returned(): void
    {
        $method = DeliveryMethod::query()->create(['kind' => 'flat_rate', 'name' => 'Kurier', 'active' => false]);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '10.00']);

        $context = new DeliveryQuoteContext('PLN', 'PL', '0');

        $this->assertNull($this->catalog()->quote($method->methodKey(), $context, 'pl'));
        $this->assertSame([], $this->catalog()->quoteAvailable($context, 'pl'));
    }

    public function test_sellable_countries_default_to_every_known_code_with_no_delivery_methods_configured_yet(): void
    {
        $sellable = $this->catalog()->sellableCountryCodes();

        $this->assertNotEmpty($sellable);
        $this->assertContains('PL', $sellable);
        $this->assertContains('US', $sellable);
        $this->assertSame(array_keys(config('delivery.continents')), $sellable);
    }

    public function test_sellable_countries_are_the_union_across_active_methods_country_rates(): void
    {
        $poland = DeliveryMethod::query()->create(['kind' => 'flat_rate', 'name' => 'Kurier krajowy', 'active' => true]);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $poland->id, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '10.00']);

        $germany = DeliveryMethod::query()->create(['kind' => 'flat_rate', 'name' => 'Kurier DE', 'active' => true]);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $germany->id, 'rule_type' => 'country_rate', 'country_code' => 'DE', 'price_amount' => '20.00']);

        $sellable = $this->catalog()->sellableCountryCodes();

        $this->assertContains('PL', $sellable);
        $this->assertContains('DE', $sellable);
        $this->assertNotContains('FR', $sellable);
    }

    public function test_a_continent_rate_makes_every_country_on_that_continent_sellable(): void
    {
        $method = DeliveryMethod::query()->create(['kind' => 'flat_rate', 'name' => 'Kurier UE', 'active' => true]);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'continent_rate', 'continent_code' => 'EU', 'price_amount' => '20.00']);

        $sellable = $this->catalog()->sellableCountryCodes();

        $this->assertContains('PL', $sellable); // EU
        $this->assertContains('DE', $sellable); // EU
        $this->assertNotContains('US', $sellable); // NA
    }

    public function test_an_excluded_country_is_not_sellable_even_with_a_country_rate_for_it(): void
    {
        $method = DeliveryMethod::query()->create(['kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true]);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '10.00']);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'exclude', 'country_code' => 'PL']);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'country_rate', 'country_code' => 'DE', 'price_amount' => '20.00']);

        $sellable = $this->catalog()->sellableCountryCodes();

        $this->assertNotContains('PL', $sellable);
        $this->assertContains('DE', $sellable);
    }

    public function test_pickup_methods_never_widen_sellable_countries(): void
    {
        $pickup = DeliveryMethod::query()->create(['kind' => 'pickup', 'name' => 'Odbiór osobisty', 'active' => true]);
        DeliveryPickupPoint::query()->create([
            'delivery_method_id' => $pickup->id, 'name' => 'Sklep', 'country_code' => 'PL',
            'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1', 'active' => true,
        ]);
        $courier = DeliveryMethod::query()->create(['kind' => 'flat_rate', 'name' => 'Kurier krajowy', 'active' => true]);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $courier->id, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '10.00']);

        $sellable = $this->catalog()->sellableCountryCodes();

        $this->assertContains('PL', $sellable);
        $this->assertNotContains('DE', $sellable);
    }

    public function test_inactive_methods_do_not_contribute_to_sellable_countries(): void
    {
        $method = DeliveryMethod::query()->create(['kind' => 'flat_rate', 'name' => 'Kurier', 'active' => false]);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '10.00']);

        $sellable = $this->catalog()->sellableCountryCodes();

        $this->assertSame(array_keys(config('delivery.continents')), $sellable);
    }
}
