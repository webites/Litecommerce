<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Delivery\Contracts\DeliveryMethodCatalog;
use Litecommerce\Modules\Delivery\Contracts\DeliveryQuoteContext;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethod;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodDescriptor;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodQuote;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodRegistry;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use RuntimeException;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * A plugin shipping method is purchasable only after the merchant enables
 * it, flows through the same pricing/tax pipeline as configured methods, is
 * snapshotted on the order by its method key, and can never turn a failure
 * into a successful checkout (P2-016a).
 */
final class CheckoutPluginShippingTest extends TestCase
{
    use CarriesTestCookies;
    use UsesIsolatedStorage;

    public const METHOD = 'test-plugin.courier';

    private array $plAddress = [
        'recipient_name' => 'Jan Kowalski', 'country_code' => 'PL',
        'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('bank_transfer_accounts')->insert(['currency_code' => 'PLN', 'beneficiary_name' => 'Litecommerce Test', 'account_number' => 'PL001234', 'instructions' => json_encode(['pl' => 'x']), 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('warehouses')->insert(['name' => 'Magazyn główny', 'created_at' => now(), 'updated_at' => now()]);
        $this->withCredentials();
    }

    private function registerMethod(string $amount = '10.00', bool $codCapable = false, bool $throws = false): void
    {
        app(ShippingMethodRegistry::class)->register(new class($amount, $codCapable, $throws) implements ShippingMethod
        {
            public function __construct(private readonly string $amount, private readonly bool $codCapable, private readonly bool $throws) {}

            public function descriptor(): ShippingMethodDescriptor
            {
                return new ShippingMethodDescriptor(CheckoutPluginShippingTest::METHOD, 'test-plugin', 'Kurier z pluginu', $this->codCapable);
            }

            public function available(DeliveryQuoteContext $context): bool
            {
                return true;
            }

            public function quote(DeliveryQuoteContext $context, string $locale): ?ShippingMethodQuote
            {
                return $this->throws ? throw new RuntimeException('carrier api down') : new ShippingMethodQuote($this->amount);
            }
        });
    }

    private function enableMethod(?int $taxRateId = null, bool $allowCod = false): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->put(route('panel.delivery.plugin-methods.update', self::METHOD), [
            'active' => true, 'tax_rate_id' => $taxRateId, 'allow_cash_on_delivery' => $allowCod, 'allow_pay_on_pickup' => false,
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();
    }

    private function createPhysicalVariant(): int
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
        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), ['regular_amount' => '10.00', 'regular_mode' => 'gross'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $variantId), ['quantity' => '5'])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        return $variantId;
    }

    private function draftWithMethod(int $variantId, ?string $methodId): string
    {
        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'guest@example.com',
            'billing_address' => $this->plAddress, 'ships_to_different_address' => false,
            'delivery_method_id' => $methodId,
        ])->assertOk();

        return $draftId;
    }

    public function test_a_plugin_method_is_not_offered_until_the_merchant_enables_it(): void
    {
        $this->registerMethod();
        $variantId = $this->createPhysicalVariant();
        $draftId = $this->draftWithMethod($variantId, self::METHOD);

        $price = $this->getJson("/checkout/{$draftId}")->assertOk()->json('price');
        $this->assertSame([], $price['delivery']['options']);
        $this->assertNull($price['delivery']['selected']);
        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $price['hash']])
            ->assertStatus(422)->assertJsonPath('code', 'delivery_method_unavailable');
    }

    public function test_an_enabled_plugin_method_is_priced_with_the_assigned_vat_and_snapshotted_on_the_order(): void
    {
        $this->registerMethod('12.30');
        $taxRateId = (int) DB::table('tax_rates')->insertGetId(['name' => 'Podstawowa', 'kind' => 'percentage', 'percentage' => '23.00', 'created_at' => now(), 'updated_at' => now()]);
        $this->enableMethod($taxRateId);
        $variantId = $this->createPhysicalVariant();
        $draftId = $this->draftWithMethod($variantId, self::METHOD);

        $price = $this->getJson("/checkout/{$draftId}")->assertOk()->json('price');
        $this->assertSame(self::METHOD, $price['delivery']['options'][0]['method_id']);
        $this->assertSame('12.30', $price['delivery']['selected']['gross_amount']);
        $this->assertSame('10.00', $price['delivery']['selected']['net_amount']);
        $this->assertSame('2.30', $price['delivery']['selected']['vat_amount']);
        $this->assertSame('22.30', $price['grand_total_gross_amount']);

        $orderId = $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $price['hash']])->assertOk()->json('order.id');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'total_gross_amount' => '22.30']);
        $this->assertDatabaseHas('order_deliveries', ['order_id' => $orderId, 'delivery_method_id' => self::METHOD, 'name' => 'Kurier z pluginu', 'gross_amount' => '12.30']);
    }

    public function test_a_failing_plugin_method_disappears_and_never_yields_a_successful_checkout(): void
    {
        $this->registerMethod(throws: true);
        $this->enableMethod();
        $variantId = $this->createPhysicalVariant();
        $draftId = $this->draftWithMethod($variantId, self::METHOD);

        $price = $this->getJson("/checkout/{$draftId}")->assertOk()->json('price');
        $this->assertSame([], $price['delivery']['options']);
        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $price['hash']])
            ->assertStatus(422)->assertJsonPath('code', 'delivery_method_unavailable');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_plugin_method_returning_a_malformed_amount_is_unavailable(): void
    {
        $this->registerMethod('-5.00');
        $this->enableMethod();

        $options = app(DeliveryMethodCatalog::class)->quoteAvailable(new DeliveryQuoteContext('PLN', 'PL', '10.00'), 'pl');

        $this->assertSame([], $options);
    }

    public function test_cash_on_delivery_needs_both_the_plugin_capability_and_the_merchant_link(): void
    {
        $this->registerMethod(codCapable: true);
        $this->enableMethod(allowCod: false);
        $variantId = $this->createPhysicalVariant();
        $draftId = $this->draftWithMethod($variantId, self::METHOD);
        $hash = $this->getJson("/checkout/{$draftId}")->json('price.hash');

        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.cash_on_delivery'])
            ->assertStatus(422)->assertJsonPath('code', 'payment_method_unavailable');

        DB::table('shipping_method_settings')->where('method_id', self::METHOD)->update(['allow_cash_on_delivery' => true]);
        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.cash_on_delivery'])->assertOk();
    }

    public function test_an_unrestricted_plugin_method_makes_every_country_sellable(): void
    {
        $this->registerMethod();
        $this->enableMethod();

        $codes = app(DeliveryMethodCatalog::class)->sellableCountryCodes();

        $this->assertSame(array_keys(config('delivery.continents')), $codes);
    }
}
