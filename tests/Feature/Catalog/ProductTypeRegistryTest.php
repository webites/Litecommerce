<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Contracts\ProductTypeDescriptor;
use Litecommerce\Modules\Catalog\Contracts\ProductTypeRegistry;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\PlacesStorefrontOrders;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class ProductTypeRegistryTest extends TestCase
{
    use PlacesStorefrontOrders;
    use UsesIsolatedStorage;

    private StaffAccount $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->setUpShop();
        $this->staff = StaffAccount::factory()->create();
    }

    private function registerServiceType(): void
    {
        app(ProductTypeRegistry::class)->register(new ProductTypeDescriptor(
            'service', 'catalog::product_types.digital',
            tracksStock: false, requiresDelivery: false, grantsDigitalAccess: false, soldAsSingleUnit: false,
        ));
    }

    private function createServiceVariant(): int
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Usługa montażu', 'short_description' => '', 'description_html' => '',
            'type' => 'service', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1', 'sku' => '', 'gtin' => '',
            'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');
        $this->actingAs($this->staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), ['regular_amount' => '50.00', 'regular_mode' => 'gross'])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        return $variantId;
    }

    public function test_core_types_are_registered_with_their_capabilities(): void
    {
        $registry = app(ProductTypeRegistry::class);

        $this->assertTrue($registry->get('physical')->tracksStock);
        $this->assertTrue($registry->get('physical')->requiresDelivery);
        $this->assertTrue($registry->get('digital')->grantsDigitalAccess);
        $this->assertTrue($registry->get('digital')->soldAsSingleUnit);
        $this->assertFalse($registry->get('digital')->tracksStock);
    }

    public function test_an_unregistered_type_is_inert_and_duplicates_are_rejected(): void
    {
        $registry = app(ProductTypeRegistry::class);

        $ghost = $registry->get('ghost');
        $this->assertFalse($ghost->tracksStock || $ghost->requiresDelivery || $ghost->grantsDigitalAccess || $ghost->soldAsSingleUnit);
        $this->assertFalse($registry->has('ghost'));

        $this->expectException(\InvalidArgumentException::class);
        $registry->register(new ProductTypeDescriptor('physical', 'x', true, true, false, false));
    }

    public function test_the_panel_only_accepts_registered_types(): void
    {
        $payload = [
            'locale' => 'pl', 'name' => 'Coś', 'short_description' => '', 'description_html' => '', 'type' => 'service',
            'status' => 'draft', 'featured' => false, 'unit_code' => 'piece', 'minimum_quantity' => '1', 'maximum_quantity' => '',
            'quantity_step' => '1', 'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ];

        $this->actingAs($this->staff)->post(route('panel.catalog.products.store'), $payload)->assertSessionHasErrors('type');

        $this->registerServiceType();
        $this->actingAs($this->staff)->post(route('panel.catalog.products.store'), $payload)->assertSessionHasNoErrors();
    }

    public function test_a_type_without_stock_or_delivery_can_be_ordered_without_either(): void
    {
        $this->registerServiceType();
        $variantId = $this->createServiceVariant();

        $this->actingAs($this->staff)->post(route('panel.warehouse.variants.receive', $variantId), ['quantity' => '1'])->assertNotFound();
        $this->app['auth']->forgetGuards();

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com',
            'billing_address' => ['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            'ships_to_different_address' => false,
        ])->assertOk();
        $hash = (string) $this->getJson("/checkout/{$draftId}")->assertOk()->json('price.hash');

        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])->assertOk();

        $this->assertSame(0, DB::table('stock_reservations')->count());
    }

    public function test_a_product_of_an_unregistered_type_cannot_be_bought(): void
    {
        $this->registerServiceType();
        $variantId = $this->createServiceVariant();
        DB::table('products')->update(['type' => 'ghost']);

        $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'variant_unavailable');
    }
}
