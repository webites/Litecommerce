<?php

declare(strict_types=1);

namespace Tests\Feature\Digital;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * "Uprawniony pracownik może wydłużyć dostęp/zwiększyć limit, z audytem"
 * (docs/knowledge-base/06-fulfillment.md#dostęp-cyfrowy), exposed on the
 * order detail panel next to the other order-fulfilment actions.
 */
final class OrderDigitalAccessPanelTest extends TestCase
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
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('bank_transfer_accounts')->insert(['currency_code' => 'PLN', 'beneficiary_name' => 'Litecommerce Test', 'account_number' => 'PL001234', 'instructions' => json_encode(['pl' => 'Tytułem numer zamówienia.']), 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->withCredentials();
    }

    private function placePaidDigitalOrder(StaffAccount $staff): string
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Ebook testowy', 'short_description' => '', 'description_html' => '',
            'type' => 'digital', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');
        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), ['regular_amount' => '19.99', 'regular_mode' => 'gross'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.catalog.products.variants.digital-files.store', [$productId, $variantId]), [
            'label' => 'Plik główny', 'max_downloads' => '2', 'validity_days' => '30',
            'file' => UploadedFile::fake()->createWithContent('ebook.pdf', 'zawartość'),
        ])->assertSessionHasNoErrors();

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
        $orderId = (string) $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])->assertOk()->json('order.id');

        app(PaymentConfirmation::class)->confirmManual($orderId, 'BANK-1', new \DateTimeImmutable, (string) $staff->getKey());

        return $orderId;
    }

    public function test_order_detail_page_shows_the_grant_and_staff_can_extend_limit_and_revoke_it(): void
    {
        $staff = StaffAccount::factory()->create();
        $orderId = $this->placePaidDigitalOrder($staff);
        $grantId = (int) DB::table('download_grants')->where('order_id', $orderId)->value('id');

        $this->actingAs($staff)->get(route('panel.orders.show', $orderId))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('order.digital_access.0.file_label', 'Plik główny')
                ->where('order.digital_access.0.max_downloads', 2)
                ->where('order.digital_access.0.usable', true));

        $this->actingAs($staff)->put(route('panel.orders.digital-access.limit', [$orderId, $grantId]), ['max_downloads' => '10'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('download_grants', ['id' => $grantId, 'max_downloads' => 10]);

        $this->actingAs($staff)->put(route('panel.orders.digital-access.extend', [$orderId, $grantId]), ['until' => '2030-01-01T00:00'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('download_grants', ['id' => $grantId, 'expires_at' => '2030-01-01 00:00:00']);

        $this->actingAs($staff)->post(route('panel.orders.digital-access.revoke', [$orderId, $grantId]), ['reason' => 'Zwrot zatwierdzony.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('download_grants', ['id' => $grantId, 'revoked_reason' => 'Zwrot zatwierdzony.']);
        $this->assertNotNull(DB::table('download_grants')->where('id', $grantId)->value('revoked_at'));

        $this->assertDatabaseHas('audit_entries', ['action' => 'orders.digital_access.revoked', 'subject_type' => 'order', 'subject_id' => $orderId]);
    }

    public function test_a_role_without_manage_orders_cannot_act_on_digital_access(): void
    {
        $staff = StaffAccount::factory()->create();
        $orderId = $this->placePaidDigitalOrder($staff);
        $grantId = (int) DB::table('download_grants')->where('order_id', $orderId)->value('id');
        $editor = StaffAccount::factory()->withRole(SystemStaffRole::ContentEditor)->create();

        $this->actingAs($editor)->post(route('panel.orders.digital-access.revoke', [$orderId, $grantId]), ['reason' => 'x'])->assertForbidden();
    }
}
