<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\PlacesStorefrontOrders;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class StorefrontScenariosTest extends TestCase
{
    use PlacesStorefrontOrders;
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->setUpShop();
    }

    private function createDigitalVariant(StaffAccount $staff, string $price): int
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Ebook '.uniqid(), 'short_description' => '', 'description_html' => '',
            'type' => 'digital', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');
        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), ['regular_amount' => $price, 'regular_mode' => 'gross'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.catalog.products.variants.digital-files.store', [$productId, $variantId]), [
            'label' => 'Plik', 'file' => UploadedFile::fake()->createWithContent('a.pdf', 'x'),
        ])->assertSessionHasNoErrors();

        return $variantId;
    }

    public function test_a_mixed_cart_offers_no_cash_on_delivery_or_pay_on_pickup_and_pays_by_transfer(): void
    {
        $staff = StaffAccount::factory()->create();
        [, $physicalId] = $this->createPhysicalVariant($staff, '10.00');
        $digitalId = $this->createDigitalVariant($staff, '20.00');
        $this->app['auth']->forgetGuards();
        $this->startNewShopper();

        $this->carryCookiesFrom($this->postJson('/cart/items', ['variant_id' => $physicalId, 'quantity' => '1'])->assertOk());
        $this->carryCookiesFrom($this->postJson('/cart/items', ['variant_id' => $digitalId, 'quantity' => '1'])->assertOk());
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com',
            'billing_address' => ['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$this->pickupMethodId, 'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();

        $offered = collect($this->getJson("/checkout/{$draftId}/payment-methods")->assertOk()->json('methods'))->pluck('id')->all();

        $this->assertContains('core.bank_transfer', $offered);
        $this->assertNotContains('core.cash_on_delivery', $offered);
        $this->assertNotContains('core.pay_on_pickup', $offered);

        $hash = (string) $this->getJson("/checkout/{$draftId}")->json('price.hash');
        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.cash_on_delivery'])
            ->assertStatus(422)->assertJsonPath('code', 'payment_method_unavailable');
        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])->assertOk();
        $this->assertSame(2, DB::table('order_lines')->count());
    }

    public function test_a_digital_only_cart_needs_no_delivery_and_a_physical_cart_cannot_confirm_without_it(): void
    {
        $staff = StaffAccount::factory()->create();
        [, $physicalId] = $this->createPhysicalVariant($staff, '10.00');
        $digitalId = $this->createDigitalVariant($staff, '20.00');
        $this->app['auth']->forgetGuards();

        $this->startNewShopper();
        $this->carryCookiesFrom($this->postJson('/cart/items', ['variant_id' => $physicalId, 'quantity' => '1'])->assertOk());
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com',
            'billing_address' => ['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            'ships_to_different_address' => false,
        ])->assertOk();
        $hash = (string) $this->getJson("/checkout/{$draftId}")->json('price.hash');
        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])->assertStatus(422);
        $this->assertSame(0, DB::table('orders')->count());

        $this->startNewShopper();
        $this->carryCookiesFrom($this->postJson('/cart/items', ['variant_id' => $digitalId, 'quantity' => '1'])->assertOk());
        $digitalDraft = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$digitalDraft}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com',
            'billing_address' => ['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            'ships_to_different_address' => false,
        ])->assertOk();
        $digitalHash = (string) $this->getJson("/checkout/{$digitalDraft}")->json('price.hash');
        $this->postJson("/checkout/{$digitalDraft}/confirm", ['price_hash' => $digitalHash, 'payment_method_id' => 'core.bank_transfer'])->assertOk();
        $this->assertSame(1, DB::table('orders')->count());
    }
}
