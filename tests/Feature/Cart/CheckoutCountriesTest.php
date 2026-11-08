<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryMethod;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryRegionRule;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * GET /checkout/countries — the data source behind the checkout country
 * picker (theme::partials.country-select / react-storefront's
 * CountrySelect.jsx). See docs/knowledge-base/09-presentation.md for the
 * "kraj w checkoucie" feature writeup.
 */
final class CheckoutCountriesTest extends TestCase
{
    use CarriesTestCookies;
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->withCredentials();
    }

    public function test_it_returns_the_full_translated_country_list_when_delivery_is_not_configured_yet(): void
    {
        $response = $this->getJson('/checkout/countries');

        $response->assertOk();
        $countries = $response->json('countries');

        $this->assertNotEmpty($countries);
        $poland = collect($countries)->firstWhere('code', 'PL');
        $this->assertNotNull($poland);
        $this->assertSame('Polska', $poland['name']);
        $this->assertSame('🇵🇱', $poland['flag']);
    }

    public function test_it_only_lists_countries_a_currently_active_delivery_method_can_actually_quote(): void
    {
        $method = DeliveryMethod::query()->create(['kind' => 'flat_rate', 'name' => 'Kurier krajowy', 'active' => true]);
        DeliveryRegionRule::query()->create(['delivery_method_id' => $method->id, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '10.00']);

        $response = $this->getJson('/checkout/countries');

        $codes = collect($response->json('countries'))->pluck('code');

        $this->assertTrue($codes->contains('PL'));
        $this->assertFalse($codes->contains('DE'));
    }
}
