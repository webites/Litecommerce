<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * HTTP-level coverage for the read-only /panel/cart preview and its
 * ViewOrders permission gate.
 */
final class CartPanelTest extends TestCase
{
    use CarriesTestCookies;
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
        DB::table('warehouses')->insert(['name' => 'Magazyn główny', 'created_at' => now(), 'updated_at' => now()]);
        $this->withCredentials();
    }

    private function createVariant(): int
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Kubek testowy', 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), [
            'regular_amount' => '10.00',
            'regular_mode' => 'gross',
        ])->assertSessionHasNoErrors();

        $this->app['auth']->forgetGuards();

        return $variantId;
    }

    public function test_the_overview_lists_carts_with_a_derived_abandoned_status(): void
    {
        $variantId = $this->createVariant();
        $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        DB::table('carts')->update(['last_activity_at' => now()->subHours(48)]);

        $administrator = StaffAccount::factory()->create();

        $this->actingAs($administrator)->get(route('panel.cart.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Panel/Cart/Index')
                ->has('carts.data', 1)
                ->where('carts.data.0.owner', 'Gość')
                ->where('carts.data.0.items_count', 1)
                ->where('carts.data.0.status', 'abandoned'));
    }

    public function test_staff_can_open_a_cart_and_inspect_its_lines(): void
    {
        $variantId = $this->createVariant();
        $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '2'])->assertOk();
        $cartId = (int) DB::table('carts')->value('id');

        // The price is set at add time (Cart\Application\AddCartItem now
        // rejects adding a priceless variant) and removed afterward, to
        // still exercise the panel showing an existing line that lost its
        // price later — the same real scenario as P2-012's cart-level fix.
        DB::table('variant_prices')->where('variant_id', $variantId)->delete();

        $administrator = StaffAccount::factory()->create();

        $this->actingAs($administrator)->get(route('panel.cart.show', $cartId))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Panel/Cart/Show')
                ->where('cart.id', $cartId)
                ->where('cart.owner', 'Gość')
                ->where('cart.lines.0.variant_id', $variantId)
                ->where('cart.lines.0.name', 'Kubek testowy')
                ->where('cart.lines.0.quantity', '2.000000')
                ->where('cart.lines.0.available', false)
                ->where('cart.lines.0.unavailable_reason', 'no_price')
                ->has('cart.checkout_drafts', 0));
    }

    public function test_unknown_cart_redirects_to_the_listing_with_an_error(): void
    {
        $administrator = StaffAccount::factory()->create();

        $this->actingAs($administrator)->get(route('panel.cart.show', 999999))
            ->assertRedirect(route('panel.cart.index'))
            ->assertSessionHas('error');
    }

    public function test_a_content_editor_without_view_orders_is_forbidden(): void
    {
        $editor = StaffAccount::factory()->withRole(SystemStaffRole::ContentEditor)->create();

        $this->actingAs($editor)->get(route('panel.cart.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('panel.cart.show', 1))->assertForbidden();
    }
}
