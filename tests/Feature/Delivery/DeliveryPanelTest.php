<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Delivery\Contracts\DeliveryKind;
use Litecommerce\Modules\Delivery\Contracts\DeliveryMethodCatalog;
use Litecommerce\Modules\Delivery\Contracts\DeliveryQuoteContext;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethod;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodDescriptor;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodQuote;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodRegistry;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * HTTP-level coverage for /panel/delivery: creating both built-in kinds,
 * region rules, pickup points, and the ManageDelivery permission gate.
 */
final class DeliveryPanelTest extends TestCase
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

    public function test_administrator_can_create_a_flat_rate_method_with_region_rules(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.delivery.store'), [
            'kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true,
            'supports_cash_on_delivery' => true, 'supports_pay_on_pickup' => false,
        ])->assertSessionHasNoErrors();

        $methodId = (int) DB::table('delivery_methods')->value('id');
        $this->assertDatabaseHas('delivery_methods', ['id' => $methodId, 'kind' => 'flat_rate', 'supports_cash_on_delivery' => true]);

        $this->actingAs($staff)->put(route('panel.delivery.update', $methodId), [
            ...self::FLAT_RATE,
            'region_rules' => [
                ['rule_type' => 'country_rate', 'country_code' => 'PL', 'continent_code' => null, 'price_amount' => '14.99', 'currency_overrides' => []],
                ['rule_type' => 'exclude', 'country_code' => 'RU', 'continent_code' => null, 'price_amount' => null, 'currency_overrides' => []],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('delivery_region_rules', ['delivery_method_id' => $methodId, 'rule_type' => 'country_rate', 'country_code' => 'PL']);
        $this->assertDatabaseHas('delivery_region_rules', ['delivery_method_id' => $methodId, 'rule_type' => 'exclude', 'country_code' => 'RU']);
    }

    public function test_a_courier_method_is_created_together_with_its_region_rates(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.delivery.store'), [
            ...self::FLAT_RATE,
            'region_rules' => [['rule_type' => 'country_rate', 'country_code' => 'PL', 'continent_code' => null, 'price_amount' => '12', 'currency_overrides' => []]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('delivery_region_rules')->count());

        $this->actingAs($staff)->post(route('panel.delivery.store'), [
            ...self::FLAT_RATE, 'name' => 'Bez ceny',
            'region_rules' => [['rule_type' => 'country_rate', 'country_code' => 'PL', 'continent_code' => null, 'price_amount' => null, 'currency_overrides' => []]],
        ])->assertSessionHasErrors('region_rules');
        $this->assertDatabaseMissing('delivery_methods', ['name' => 'Bez ceny']);
    }

    public function test_a_region_rule_without_its_country_or_continent_is_rejected(): void
    {
        $staff = StaffAccount::factory()->create();

        foreach ([
            ['rule_type' => 'country_rate', 'country_code' => null, 'continent_code' => null, 'price_amount' => '5', 'currency_overrides' => []],
            ['rule_type' => 'exclude', 'country_code' => null, 'continent_code' => null, 'price_amount' => null, 'currency_overrides' => []],
            ['rule_type' => 'continent_rate', 'country_code' => null, 'continent_code' => null, 'price_amount' => '5', 'currency_overrides' => []],
        ] as $rule) {
            $this->actingAs($staff)->post(route('panel.delivery.store'), [...self::FLAT_RATE, 'region_rules' => [$rule]])->assertSessionHasErrors();
        }

        $this->assertDatabaseCount('delivery_methods', 0);
    }

    public function test_a_rate_rule_without_a_price_is_rejected(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.delivery.store'), ['kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true, 'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => false]);
        $methodId = (int) DB::table('delivery_methods')->value('id');

        $response = $this->actingAs($staff)->put(route('panel.delivery.update', $methodId), [
            ...self::FLAT_RATE,
            'region_rules' => [['rule_type' => 'country_rate', 'country_code' => 'PL', 'continent_code' => null, 'price_amount' => null, 'currency_overrides' => []]],
        ]);

        $response->assertSessionHasErrors('region_rules');
        $this->assertDatabaseMissing('delivery_region_rules', ['delivery_method_id' => $methodId]);
    }

    public function test_pay_on_pickup_is_forced_off_for_a_flat_rate_method(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.delivery.store'), [
            'kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true,
            'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('delivery_methods', ['kind' => 'flat_rate', 'supports_pay_on_pickup' => false]);
    }

    private const FLAT_RATE = ['kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true, 'supports_cash_on_delivery' => true, 'supports_pay_on_pickup' => false];

    private const FIRST_POINT = [
        'name' => 'Sklep centralny', 'country_code' => 'PL', 'postal_code' => '00-001',
        'city' => 'Warszawa', 'address_line_1' => 'Testowa 1', 'active' => true,
        'instructions' => ['pl' => 'Pon.–pt. 10–18'],
    ];

    public function test_administrator_creates_a_pickup_method_together_with_its_first_point(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.delivery.store'), [
            'kind' => 'pickup', 'name' => 'Odbiór osobisty', 'active' => true, 'price_amount' => '0',
            'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => true,
            'pickup_point' => self::FIRST_POINT,
        ])->assertSessionHasNoErrors();

        $methodId = (int) DB::table('delivery_methods')->value('id');
        $this->assertDatabaseHas('delivery_pickup_points', ['delivery_method_id' => $methodId, 'name' => 'Sklep centralny']);
        $this->assertSame(['pl' => 'Pon.–pt. 10–18'], json_decode((string) DB::table('delivery_pickup_points')->value('instructions'), true));
    }

    public function test_a_pickup_method_cannot_be_created_without_a_first_point(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.delivery.store'), [
            'kind' => 'pickup', 'name' => 'Odbiór osobisty', 'active' => true,
            'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => false,
        ])->assertSessionHasErrors(['pickup_point.name', 'pickup_point.city']);

        $this->assertDatabaseCount('delivery_methods', 0);
    }

    public function test_a_pickup_method_cannot_be_enabled_while_it_has_no_active_point(): void
    {
        $staff = StaffAccount::factory()->create();
        $methodId = DB::table('delivery_methods')->insertGetId(['kind' => 'pickup', 'name' => 'Odbiór', 'active' => false, 'created_at' => now(), 'updated_at' => now()]);
        $payload = ['kind' => 'pickup', 'name' => 'Odbiór', 'active' => true, 'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => false];

        $this->actingAs($staff)->put(route('panel.delivery.update', $methodId), $payload)->assertSessionHasErrors('active');
        $this->assertDatabaseHas('delivery_methods', ['id' => $methodId, 'active' => false]);

        $this->actingAs($staff)->put(route('panel.delivery.update', $methodId), [...$payload, 'pickup_points' => [self::FIRST_POINT]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('delivery_methods', ['id' => $methodId, 'active' => true]);
        $this->assertDatabaseHas('delivery_pickup_points', ['delivery_method_id' => $methodId, 'name' => 'Sklep centralny']);
    }

    public function test_one_save_updates_the_method_overrides_regions_and_points_together(): void
    {
        $staff = StaffAccount::factory()->create();
        DB::table('shop_currencies')->insert(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_base' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($staff)->post(route('panel.delivery.store'), [
            'kind' => 'pickup', 'name' => 'Odbiór', 'active' => true, 'price_amount' => '0',
            'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => false, 'pickup_point' => self::FIRST_POINT,
        ])->assertSessionHasNoErrors();
        $methodId = (int) DB::table('delivery_methods')->value('id');
        $keptId = (int) DB::table('delivery_pickup_points')->value('id');

        $this->actingAs($staff)->put(route('panel.delivery.update', $methodId), [
            'kind' => 'pickup', 'name' => 'Odbiór w sklepie', 'active' => true, 'price_amount' => '5',
            'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => true, 'pickup_deadline_days' => 7,
            'currency_overrides' => [['currency_code' => 'EUR', 'price_amount' => '1.5', 'free_threshold_amount' => null]],
            'pickup_points' => [
                [...self::FIRST_POINT, 'id' => $keptId, 'name' => 'Sklep po zmianie'],
                [...self::FIRST_POINT, 'name' => 'Drugi punkt', 'city' => 'Kraków'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('delivery_methods', ['id' => $methodId, 'name' => 'Odbiór w sklepie', 'pickup_deadline_days' => 7, 'supports_pay_on_pickup' => true]);
        $this->assertDatabaseHas('delivery_method_currency_overrides', ['delivery_method_id' => $methodId, 'currency_code' => 'EUR']);
        $this->assertDatabaseHas('delivery_pickup_points', ['id' => $keptId, 'name' => 'Sklep po zmianie']);
        $this->assertDatabaseHas('delivery_pickup_points', ['delivery_method_id' => $methodId, 'name' => 'Drugi punkt', 'city' => 'Kraków']);
        $this->assertSame(2, DB::table('delivery_pickup_points')->where('delivery_method_id', $methodId)->count());

        $this->actingAs($staff)->put(route('panel.delivery.update', $methodId), [
            'kind' => 'pickup', 'name' => 'Odbiór w sklepie', 'active' => true, 'price_amount' => '5',
            'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => true,
            'pickup_points' => [[...self::FIRST_POINT, 'id' => $keptId]],
        ])->assertSessionHasNoErrors();
        $this->assertSame([$keptId], DB::table('delivery_pickup_points')->where('delivery_method_id', $methodId)->pluck('id')->map(fn ($id): int => (int) $id)->all());
    }

    public function test_an_invalid_section_saves_nothing_and_an_active_pickup_method_needs_an_active_point(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.delivery.store'), [
            'kind' => 'pickup', 'name' => 'Odbiór', 'active' => true, 'price_amount' => '0',
            'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => false, 'pickup_point' => self::FIRST_POINT,
        ])->assertSessionHasNoErrors();
        $methodId = (int) DB::table('delivery_methods')->value('id');
        $base = ['kind' => 'pickup', 'name' => 'Zmieniona', 'active' => true, 'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => false];

        $this->actingAs($staff)->put(route('panel.delivery.update', $methodId), [...$base, 'pickup_points' => [[...self::FIRST_POINT, 'city' => '']]])->assertSessionHasErrors('pickup_points.0.city');
        $this->actingAs($staff)->put(route('panel.delivery.update', $methodId), [...$base, 'pickup_points' => [[...self::FIRST_POINT, 'active' => false]]])->assertSessionHasErrors('active');
        $this->actingAs($staff)->put(route('panel.delivery.update', $methodId), [...$base, 'pickup_points' => []])->assertSessionHasErrors('active');
        $this->assertDatabaseHas('delivery_methods', ['id' => $methodId, 'name' => 'Odbiór']);
        $this->assertSame(1, DB::table('delivery_pickup_points')->where('delivery_method_id', $methodId)->count());
    }

    public function test_a_pickup_point_of_another_method_cannot_be_edited_through_this_one(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.delivery.store'), ['kind' => 'pickup', 'name' => 'A', 'active' => true, 'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => false, 'pickup_point' => self::FIRST_POINT]);
        $this->actingAs($staff)->post(route('panel.delivery.store'), ['kind' => 'pickup', 'name' => 'B', 'active' => true, 'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => false, 'pickup_point' => [...self::FIRST_POINT, 'name' => 'Punkt B']]);
        $methodA = (int) DB::table('delivery_methods')->where('name', 'A')->value('id');
        $foreignPoint = (int) DB::table('delivery_pickup_points')->where('name', 'Punkt B')->value('id');

        $this->actingAs($staff)->put(route('panel.delivery.update', $methodA), [
            'kind' => 'pickup', 'name' => 'A', 'active' => true, 'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => false,
            'pickup_points' => [[...self::FIRST_POINT, 'id' => $foreignPoint, 'name' => 'Przejęty']],
        ])->assertSessionHasErrors('pickup_points.0.id');
        $this->assertDatabaseHas('delivery_pickup_points', ['id' => $foreignPoint, 'name' => 'Punkt B']);
    }

    public function test_the_pickup_deadline_is_kept_only_for_pickup_methods_and_validated(): void
    {
        $staff = StaffAccount::factory()->create();
        $base = ['active' => true, 'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => false, 'price_amount' => '0'];

        $this->actingAs($staff)->post(route('panel.delivery.store'), [...$base, 'kind' => 'pickup', 'name' => 'Odbiór', 'pickup_deadline_days' => 5, 'pickup_point' => self::FIRST_POINT])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.delivery.store'), [...$base, 'kind' => 'flat_rate', 'name' => 'Kurier', 'pickup_deadline_days' => 5])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.delivery.store'), [...$base, 'kind' => 'pickup', 'name' => 'Zły', 'pickup_deadline_days' => 0, 'pickup_point' => self::FIRST_POINT])->assertSessionHasErrors('pickup_deadline_days');

        $this->assertDatabaseHas('delivery_methods', ['name' => 'Odbiór', 'pickup_deadline_days' => 5]);
        $this->assertDatabaseHas('delivery_methods', ['name' => 'Kurier', 'pickup_deadline_days' => null]);
        $this->assertDatabaseMissing('delivery_methods', ['name' => 'Zły']);

        $pickupId = (int) DB::table('delivery_methods')->where('name', 'Odbiór')->value('id');
        $catalog = app(DeliveryMethodCatalog::class);
        $this->assertSame(5, $catalog->pickupDeadlineDays('core.'.$pickupId));
        $this->assertNull($catalog->pickupDeadlineDays('core.999999'));
    }

    public function test_a_plugin_pickup_method_keeps_its_deadline(): void
    {
        $this->registerPluginMethod(kind: DeliveryKind::Pickup);
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->put(route('panel.delivery.plugin-methods.update', 'hello-plugin.flat'), [
            'active' => true, 'tax_rate_id' => null, 'allow_cash_on_delivery' => false, 'allow_pay_on_pickup' => false, 'pickup_deadline_days' => 7,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shipping_method_settings', ['method_id' => 'hello-plugin.flat', 'pickup_deadline_days' => 7]);
        $this->assertSame(7, app(DeliveryMethodCatalog::class)->pickupDeadlineDays('hello-plugin.flat'));
    }

    public function test_a_non_pickup_plugin_method_drops_the_deadline(): void
    {
        $this->registerPluginMethod(kind: DeliveryKind::FlatRate);
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->put(route('panel.delivery.plugin-methods.update', 'hello-plugin.flat'), [
            'active' => true, 'tax_rate_id' => null, 'allow_cash_on_delivery' => false, 'allow_pay_on_pickup' => false, 'pickup_deadline_days' => 7,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shipping_method_settings', ['method_id' => 'hello-plugin.flat', 'pickup_deadline_days' => null]);
    }

    public function test_staff_without_manage_delivery_permission_is_forbidden(): void
    {
        $staff = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();

        $this->actingAs($staff)->get(route('panel.settings.tabs.show', 'delivery'))->assertNotFound();
    }

    private function registerPluginMethod(bool $codCapable = false, DeliveryKind $kind = DeliveryKind::FlatRate): void
    {
        app(ShippingMethodRegistry::class)->register(new class($codCapable, $kind) implements ShippingMethod
        {
            public function __construct(private readonly bool $codCapable, private readonly DeliveryKind $kind) {}

            public function descriptor(): ShippingMethodDescriptor
            {
                return new ShippingMethodDescriptor('hello-plugin.flat', 'hello-plugin', 'Hello Plugin: flat rate', $this->codCapable, false, $this->kind);
            }

            public function available(DeliveryQuoteContext $context): bool
            {
                return true;
            }

            public function quote(DeliveryQuoteContext $context, string $locale): ?ShippingMethodQuote
            {
                return new ShippingMethodQuote('9.99');
            }
        });
    }

    public function test_a_plugin_registered_shipping_method_is_listed_inactive_with_its_capabilities(): void
    {
        $this->registerPluginMethod(codCapable: true);
        DB::table('delivery_methods')->insert(['kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs(StaffAccount::factory()->create())->getJson(route('panel.settings.tabs.show', 'delivery'))
            ->assertOk()
            ->assertJsonCount(1, 'props.plugin_methods')
            ->assertJsonPath('props.plugin_methods.0.id', 'hello-plugin.flat')
            ->assertJsonPath('props.plugin_methods.0.provider_id', 'hello-plugin')
            ->assertJsonPath('props.plugin_methods.0.name', 'Hello Plugin: flat rate')
            ->assertJsonPath('props.plugin_methods.0.active', false)
            ->assertJsonPath('props.plugin_methods.0.can_cash_on_delivery', true)
            ->assertJsonPath('props.plugin_methods.0.can_pay_on_pickup', false);
    }

    public function test_the_merchant_enables_a_plugin_method_and_the_flags_are_clamped_to_its_capabilities(): void
    {
        $this->registerPluginMethod(codCapable: false);
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->put(route('panel.delivery.plugin-methods.update', 'hello-plugin.flat'), [
            'active' => true, 'tax_rate_id' => null, 'allow_cash_on_delivery' => true, 'allow_pay_on_pickup' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shipping_method_settings', [
            'method_id' => 'hello-plugin.flat', 'active' => true, 'allow_cash_on_delivery' => false, 'allow_pay_on_pickup' => false,
        ]);
    }

    public function test_plugin_method_settings_reject_unknown_and_configured_methods(): void
    {
        $this->registerPluginMethod();
        $staff = StaffAccount::factory()->create();
        $coreId = (int) DB::table('delivery_methods')->insertGetId(['kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $payload = ['active' => true, 'tax_rate_id' => null, 'allow_cash_on_delivery' => false, 'allow_pay_on_pickup' => false];

        $this->actingAs($staff)->put(route('panel.delivery.plugin-methods.update', 'other-plugin.missing'), $payload)->assertNotFound();
        $this->actingAs($staff)->put(route('panel.delivery.plugin-methods.update', 'core.'.$coreId), $payload)->assertNotFound();
        $this->assertDatabaseCount('shipping_method_settings', 0);
    }

    public function test_plugin_method_settings_need_the_manage_delivery_permission(): void
    {
        $this->registerPluginMethod();
        $staff = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();

        $this->actingAs($staff)->put(route('panel.delivery.plugin-methods.update', 'hello-plugin.flat'), [
            'active' => true, 'tax_rate_id' => null, 'allow_cash_on_delivery' => false, 'allow_pay_on_pickup' => false,
        ])->assertForbidden();
        $this->assertDatabaseCount('shipping_method_settings', 0);
    }
}
