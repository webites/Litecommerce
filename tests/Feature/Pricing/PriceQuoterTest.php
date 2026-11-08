<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Pricing\Contracts\PriceMode;
use Litecommerce\Modules\Pricing\Contracts\PriceQuoter;
use Litecommerce\Modules\Pricing\Contracts\ProductTaxAssignmentManager;
use Litecommerce\Modules\Pricing\Contracts\TaxQualificationKind;
use Litecommerce\Modules\Pricing\Contracts\VariantPriceManager;
use Litecommerce\Modules\Pricing\Infrastructure\Models\ExchangeRate;
use Litecommerce\Modules\Pricing\Infrastructure\Models\TaxRate;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Exercises the Money/VAT policy in
 * docs/knowledge-base/04-pricing-coupons.md end to end through the public
 * PriceQuoter contract — the same object Cart/Checkout will resolve once
 * they exist.
 */
final class PriceQuoterTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert([
            'code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('shop_currencies')->insert([
            'code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createVariant(): int
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Kubek testowy', 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'draft', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        return (int) DB::table('product_variants')->value('id');
    }

    public function test_it_returns_null_when_no_base_price_is_set(): void
    {
        $variantId = $this->createVariant();

        $this->assertNull(app(PriceQuoter::class)->quote($variantId, 'PLN', '1'));
    }

    public function test_net_price_without_tax_assignment_defaults_to_zero_percent(): void
    {
        $variantId = $this->createVariant();
        app(VariantPriceManager::class)->setBasePrice($variantId, '10.00', PriceMode::Net, null, null);

        $quote = app(PriceQuoter::class)->quote($variantId, 'PLN', '3');

        $this->assertNotNull($quote);
        $this->assertSame('30.00', $quote->lineNetAmount);
        $this->assertSame('0.00', $quote->lineVatAmount);
        $this->assertSame('30.00', $quote->lineGrossAmount);
        $this->assertSame(TaxQualificationKind::Percentage, $quote->taxQualificationKind);
        $this->assertFalse($quote->promotionalActive);
    }

    public function test_net_price_acceptance_example_with_23_percent_vat(): void
    {
        $variantId = $this->createVariant();
        app(VariantPriceManager::class)->setBasePrice($variantId, '10.00', PriceMode::Net, null, null);
        $this->assignTax($variantId, '23.00');

        $quote = app(PriceQuoter::class)->quote($variantId, 'PLN', '3');

        $this->assertSame('30.00', $quote->lineNetAmount);
        $this->assertSame('6.90', $quote->lineVatAmount);
        $this->assertSame('36.90', $quote->lineGrossAmount);
    }

    public function test_gross_price_acceptance_example_with_23_percent_vat(): void
    {
        $variantId = $this->createVariant();
        app(VariantPriceManager::class)->setBasePrice($variantId, '12.30', PriceMode::Gross, null, null);
        $this->assignTax($variantId, '23.00');

        $quote = app(PriceQuoter::class)->quote($variantId, 'PLN', '2');

        $this->assertSame('24.60', $quote->lineGrossAmount);
        $this->assertSame('4.60', $quote->lineVatAmount);
        $this->assertSame('20.00', $quote->lineNetAmount);
    }

    public function test_promotional_price_is_applied_when_lower_than_regular(): void
    {
        $variantId = $this->createVariant();
        app(VariantPriceManager::class)->setBasePrice($variantId, '20.00', PriceMode::Gross, '15.00', PriceMode::Gross);

        $quote = app(PriceQuoter::class)->quote($variantId, 'PLN', '1');

        $this->assertTrue($quote->promotionalActive);
        $this->assertNull($quote->promotionalDropReason);
        $this->assertSame('15.00', $quote->unitEffectiveAmount);
        $this->assertSame('15.00', $quote->lineGrossAmount);
    }

    public function test_promotional_price_is_dropped_when_not_lower_than_regular(): void
    {
        $variantId = $this->createVariant();
        // Both gross, promotional equal to regular — invalid, must be silently dropped.
        app(VariantPriceManager::class)->setBasePrice($variantId, '20.00', PriceMode::Gross, '20.00', PriceMode::Gross);

        $quote = app(PriceQuoter::class)->quote($variantId, 'PLN', '1');

        $this->assertFalse($quote->promotionalActive);
        $this->assertSame('not_lower_than_regular', $quote->promotionalDropReason);
        $this->assertSame('20.00', $quote->unitEffectiveAmount);
        $this->assertSame('20.00', $quote->lineGrossAmount);
    }

    public function test_the_rate_change_conflict_example_drops_the_promotion_without_blocking_the_write(): void
    {
        // The documented example: regular price is converted (no override),
        // promotional price is a fixed EUR override. A rate change that makes
        // the converted regular price drop below the fixed promo must not be
        // blocked — the promo is simply not applied for that currency.
        $variantId = $this->createVariant();
        app(VariantPriceManager::class)->setBasePrice($variantId, '20.00', PriceMode::Gross, null, null);
        DB::table('shop_currencies')->insert([
            'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_base' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(VariantPriceManager::class)->setCurrencyOverride($variantId, 'EUR', null, null, '4.80', PriceMode::Gross);
        ExchangeRate::query()->create(['currency_code' => 'EUR', 'base_units_per_unit' => '4.000000']);

        $before = app(PriceQuoter::class)->quote($variantId, 'EUR', '1');
        $this->assertTrue($before->promotionalActive);
        $this->assertSame('5.00', $before->regularUnitAmount);

        // Rate changes from 4.00 to 4.50 — write must not be blocked.
        ExchangeRate::query()->where('currency_code', 'EUR')->update(['base_units_per_unit' => '4.500000']);

        $after = app(PriceQuoter::class)->quote($variantId, 'EUR', '1');
        $this->assertNotNull($after);
        $this->assertFalse($after->promotionalActive);
        $this->assertSame('not_lower_than_regular', $after->promotionalDropReason);
        $this->assertSame('4.44', $after->regularUnitAmount);
        $this->assertSame('4.44', $after->unitEffectiveAmount);
    }

    public function test_currency_conversion_without_override_rounds_to_currency_precision(): void
    {
        $variantId = $this->createVariant();
        app(VariantPriceManager::class)->setBasePrice($variantId, '10.00', PriceMode::Gross, null, null);
        DB::table('shop_currencies')->insert([
            'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_base' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        ExchangeRate::query()->create(['currency_code' => 'EUR', 'base_units_per_unit' => '4.320000']);

        $quote = app(PriceQuoter::class)->quote($variantId, 'EUR', '1');

        // 10.00 / 4.32 = 2.31481... -> half up to 2dp = 2.31
        $this->assertSame('2.31', $quote->regularUnitAmount);
        $this->assertSame('converted', $quote->unitSource);
    }

    public function test_it_returns_null_for_an_unconfigured_currency_without_override(): void
    {
        $variantId = $this->createVariant();
        app(VariantPriceManager::class)->setBasePrice($variantId, '10.00', PriceMode::Gross, null, null);
        DB::table('shop_currencies')->insert([
            'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_base' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertNull(app(PriceQuoter::class)->quote($variantId, 'EUR', '1'));
    }

    public function test_currency_override_takes_precedence_over_conversion(): void
    {
        $variantId = $this->createVariant();
        app(VariantPriceManager::class)->setBasePrice($variantId, '10.00', PriceMode::Gross, null, null);
        DB::table('shop_currencies')->insert([
            'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_base' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        ExchangeRate::query()->create(['currency_code' => 'EUR', 'base_units_per_unit' => '4.320000']);
        app(VariantPriceManager::class)->setCurrencyOverride($variantId, 'EUR', '1.99', PriceMode::Gross, null, null);

        $quote = app(PriceQuoter::class)->quote($variantId, 'EUR', '1');

        $this->assertSame('1.99', $quote->regularUnitAmount);
        $this->assertSame('override', $quote->unitSource);
    }

    private function assignTax(int $variantId, string $percentage): void
    {
        $variant = DB::table('product_variants')->find($variantId);
        $taxRate = TaxRate::query()->create(['name' => 'Podstawowa', 'kind' => 'percentage', 'percentage' => $percentage]);
        app(ProductTaxAssignmentManager::class)->assign((int) $variant->product_id, (int) $taxRate->getKey());
    }
}
