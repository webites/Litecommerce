<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\PlacesStorefrontOrders;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Guards against N+1 regressions on the hot read paths: the query count must not grow with the number of products or cart lines.
 */
final class QueryCountTest extends TestCase
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
        $this->installReferenceTheme();
    }

    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_the_product_listing_does_not_issue_more_queries_for_more_products(): void
    {
        $staff = StaffAccount::factory()->create();
        for ($i = 0; $i < 2; $i++) {
            $this->createPhysicalVariant($staff, '10.00');
        }
        $this->get('/pl/products')->assertOk();
        $few = $this->countQueries(fn () => $this->get('/pl/products')->assertOk());

        for ($i = 0; $i < 12; $i++) {
            $this->createPhysicalVariant($staff, '10.00');
        }
        $this->get('/pl/products')->assertOk();
        $many = $this->countQueries(fn () => $this->get('/pl/products')->assertOk());

        $this->assertLessThanOrEqual($few + 2, $many, "listing queries grew from {$few} to {$many}");
        $this->assertLessThanOrEqual(60, $many);
    }

    public function test_the_checkout_price_does_not_issue_more_queries_for_more_cart_lines(): void
    {
        $staff = StaffAccount::factory()->create();
        $variantIds = [];
        for ($i = 0; $i < 7; $i++) {
            $variantIds[] = $this->createPhysicalVariant($staff, '10.00')[1];
        }
        $this->startNewShopper();
        $this->carryCookiesFrom($this->postJson('/cart/items', ['variant_id' => $variantIds[0], 'quantity' => '1'])->assertOk());
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com',
            'billing_address' => ['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$this->pickupMethodId, 'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();
        $this->getJson("/checkout/{$draftId}")->assertOk();
        $one = $this->countQueries(fn () => $this->getJson("/checkout/{$draftId}")->assertOk());

        foreach (array_slice($variantIds, 1) as $variantId) {
            $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        }
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com',
            'billing_address' => ['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$this->pickupMethodId, 'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();
        $this->getJson("/checkout/{$draftId}")->assertOk();
        $seven = $this->countQueries(fn () => $this->getJson("/checkout/{$draftId}")->assertOk());

        $this->assertSame($one, $seven, "checkout price must cost a constant number of queries, got {$one} for 1 line and {$seven} for 7");
    }

    public function test_the_cart_view_does_not_issue_more_queries_for_more_cart_lines(): void
    {
        $staff = StaffAccount::factory()->create();
        $variantIds = [];
        for ($i = 0; $i < 7; $i++) {
            $variantIds[] = $this->createPhysicalVariant($staff, '10.00')[1];
        }
        $this->startNewShopper();
        $this->carryCookiesFrom($this->postJson('/cart/items', ['variant_id' => $variantIds[0], 'quantity' => '1'])->assertOk());
        $this->getJson('/cart')->assertOk();
        $one = $this->countQueries(fn () => $this->getJson('/cart')->assertOk());

        foreach (array_slice($variantIds, 1) as $variantId) {
            $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        }
        $this->getJson('/cart')->assertOk();
        $seven = $this->countQueries(fn () => $this->getJson('/cart')->assertOk());

        $this->assertSame($one, $seven, "cart view must cost a constant number of queries, got {$one} for 1 line and {$seven} for 7");
    }
}
