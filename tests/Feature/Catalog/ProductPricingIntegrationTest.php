<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Pricing\Infrastructure\Models\TaxRate;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Catalog's product edit page embeds Pricing data (price, VAT assignment)
 * exactly like it already embeds Media data — this covers that wiring
 * end to end through the real HTTP routes, not just the PriceQuoter unit.
 */
final class ProductPricingIntegrationTest extends TestCase
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

    private function createProductAndVariant(StaffAccount $staff): array
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Kubek testowy', 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'draft', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        $productId = (int) DB::table('products')->value('id');
        $variantId = (int) DB::table('product_variants')->value('id');

        return [$productId, $variantId];
    }

    public function test_a_fresh_variant_has_no_price_yet_and_a_zero_price_is_a_distinct_valid_state(): void
    {
        $staff = StaffAccount::factory()->create();
        [$productId, $variantId] = $this->createProductAndVariant($staff);

        $this->actingAs($staff)->get(route('panel.catalog.products.edit', $productId))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Panel/Catalog/Edit')
                ->where('variants.0.price.has_price', false)
                ->where('variants.0.price.regular_amount', ''));

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), [
            'regular_amount' => '0.00',
            'regular_mode' => 'gross',
        ])->assertSessionHasNoErrors();

        $this->actingAs($staff)->get(route('panel.catalog.products.edit', $productId))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Panel/Catalog/Edit')
                ->where('variants.0.price.has_price', true)
                ->where('variants.0.price.regular_amount', '0'));
    }

    public function test_it_sets_and_displays_the_variant_base_price(): void
    {
        $staff = StaffAccount::factory()->create();
        [$productId, $variantId] = $this->createProductAndVariant($staff);

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), [
            'regular_amount' => '19.99',
            'regular_mode' => 'gross',
            'promotional_amount' => '14.99',
            'promotional_mode' => 'gross',
        ])->assertSessionHasNoErrors();

        $this->actingAs($staff)->get(route('panel.catalog.products.edit', $productId))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Panel/Catalog/Edit')
                ->where('variants.0.price.regular_amount', '19.99')
                ->where('variants.0.price.promotional_amount', '14.99')
                ->where('variants.0.price.base_quote.promotional_active', true)
                ->where('variants.0.price.base_quote.gross', '14.99'));
    }

    public function test_it_rejects_a_price_with_too_many_decimal_places_for_the_currency(): void
    {
        $staff = StaffAccount::factory()->create();
        [$productId, $variantId] = $this->createProductAndVariant($staff);

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), [
            'regular_amount' => '19.999',
            'regular_mode' => 'gross',
        ])->assertSessionHasErrors('regular_amount');
    }

    public function test_it_sets_a_currency_override_and_assigns_tax(): void
    {
        $staff = StaffAccount::factory()->create();
        [$productId, $variantId] = $this->createProductAndVariant($staff);
        DB::table('shop_currencies')->insert([
            'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_base' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $taxRate = TaxRate::query()->create(['name' => 'Podstawowa', 'kind' => 'percentage', 'percentage' => '23']);

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), [
            'regular_amount' => '20.00',
            'regular_mode' => 'gross',
        ])->assertSessionHasNoErrors();

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.override.update', [$productId, $variantId, 'EUR']), [
            'regular_amount' => '4.99',
            'regular_mode' => 'gross',
        ])->assertSessionHasNoErrors();

        $this->actingAs($staff)->put(route('panel.catalog.products.tax.update', $productId), [
            'tax_rate_id' => $taxRate->getKey(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_tax_assignments', ['product_id' => $productId, 'tax_rate_id' => $taxRate->getKey()]);
        $this->assertDatabaseHas('variant_price_overrides', ['variant_id' => $variantId, 'currency_code' => 'EUR', 'regular_amount' => 4.99]);

        $this->actingAs($staff)->get(route('panel.catalog.products.edit', $productId))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('product.tax_rate_id', $taxRate->getKey())
                ->where('variants.0.price.overrides.EUR.regular_amount', '4.99'));
    }

    public function test_it_rejects_an_override_for_the_base_currency(): void
    {
        $staff = StaffAccount::factory()->create();
        [$productId, $variantId] = $this->createProductAndVariant($staff);

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.override.update', [$productId, $variantId, 'PLN']), [
            'regular_amount' => '4.99',
            'regular_mode' => 'gross',
        ])->assertStatus(404);
    }

    /**
     * A content editor has ManageCatalog (can edit the product form) but
     * not ManagePricing — must not be able to change prices or tax via
     * these catalog-nested routes just because they can reach the page.
     */
    public function test_a_content_editor_cannot_change_price_or_tax(): void
    {
        $administrator = StaffAccount::factory()->create();
        [$productId, $variantId] = $this->createProductAndVariant($administrator);
        $taxRate = TaxRate::query()->create(['name' => 'Podstawowa', 'kind' => 'percentage', 'percentage' => '23']);

        $editor = StaffAccount::factory()->withRole(SystemStaffRole::ContentEditor)->create();

        $this->actingAs($editor)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), [
            'regular_amount' => '20.00',
            'regular_mode' => 'gross',
        ])->assertForbidden();

        $this->actingAs($editor)->put(route('panel.catalog.products.tax.update', $productId), [
            'tax_rate_id' => $taxRate->getKey(),
        ])->assertForbidden();

        $this->assertDatabaseMissing('variant_prices', ['variant_id' => $variantId]);
        $this->assertDatabaseMissing('product_tax_assignments', ['product_id' => $productId]);
    }
}
