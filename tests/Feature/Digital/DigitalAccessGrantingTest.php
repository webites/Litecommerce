<?php

declare(strict_types=1);

namespace Tests\Feature\Digital;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessGrantor;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentManager;
use Litecommerce\Modules\Orders\Contracts\OrderPlacementLineData;
use Litecommerce\Modules\Orders\Contracts\OrderPlacer;
use Litecommerce\Modules\Orders\Contracts\PlaceOrderData;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Grant creation — "Po potwierdzeniu zapłaty ... dostęp niezależny od
 * wysyłki części fizycznej" and the snapshot rule
 * (docs/knowledge-base/06-fulfillment.md#dostęp-cyfrowy), wired into
 * Orders\Application\{PlaceOrder,ManageOrderPayment}.
 */
final class DigitalAccessGrantingTest extends TestCase
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

    /** @return array{0:int,1:int,2:int} product id, variant id, file id */
    private function createDigitalVariantWithFile(StaffAccount $staff, ?int $maxDownloads = 2, ?int $validityDays = 30): array
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
            'label' => 'Plik główny',
            'max_downloads' => $maxDownloads === null ? '' : (string) $maxDownloads,
            'validity_days' => $validityDays === null ? '' : (string) $validityDays,
            'file' => UploadedFile::fake()->createWithContent('ebook.pdf', 'zawartość'),
        ])->assertSessionHasNoErrors();
        $fileId = (int) DB::table('variant_file_sets')->value('id');

        return [$productId, $variantId, $fileId];
    }

    /** @return array{0:string,1:int} order id, variant id */
    private function placeDigitalOrder(int $variantId): array
    {
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
        $response = $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])->assertOk();

        return [(string) $response->json('order.id'), $variantId];
    }

    public function test_confirming_payment_grants_access_snapshotted_from_the_file_at_purchase_time(): void
    {
        $staff = StaffAccount::factory()->create();
        [, $variantId, $fileId] = $this->createDigitalVariantWithFile($staff, maxDownloads: 2, validityDays: 30);
        [$orderId] = $this->placeDigitalOrder($variantId);

        $this->assertSame(0, DB::table('download_grants')->count(), 'unpaid order must not grant access yet');

        app(PaymentConfirmation::class)->confirmManual($orderId, 'BANK-1', new \DateTimeImmutable('2026-01-10T10:00:00+00:00'), (string) $staff->getKey());

        $this->assertSame(1, DB::table('download_grants')->count());
        $this->assertDatabaseHas('download_grants', [
            'order_id' => $orderId, 'variant_file_set_id' => $fileId, 'max_downloads' => 2, 'downloads_used' => 0,
            'granted_at' => '2026-01-10 10:00:00', 'expires_at' => '2026-02-09 10:00:00',
        ]);

        // Editing the file's settings afterwards must not reach back into
        // rights already sold.
        $this->actingAs($staff)->put(route('panel.catalog.products.variants.digital-files.update', [DB::table('products')->value('id'), $variantId, $fileId]), [
            'label' => 'Plik główny', 'max_downloads' => '99', 'validity_days' => '1', 'active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('download_grants', ['order_id' => $orderId, 'max_downloads' => 2]);
    }

    public function test_late_payment_does_not_grant_digital_access(): void
    {
        $staff = StaffAccount::factory()->create();
        [, $variantId] = $this->createDigitalVariantWithFile($staff);
        [$orderId] = $this->placeDigitalOrder($variantId);

        app(OrderPaymentManager::class)->cancelUnpaid($orderId, 'Rezygnacja klienta');
        app(PaymentConfirmation::class)->confirmManual($orderId, 'LATE-1', new \DateTimeImmutable, (string) $staff->getKey());

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'late_payment']);
        $this->assertSame(0, DB::table('download_grants')->count());
    }

    public function test_a_free_order_grants_access_immediately_at_placement(): void
    {
        $staff = StaffAccount::factory()->create();
        [, $variantId, $fileId] = $this->createDigitalVariantWithFile($staff);

        $data = new PlaceOrderData(
            sourceType: 'test', sourceId: 'free-1', customerAccountId: null, buyerType: 'person',
            email: 'free@example.com', companyName: null, taxIdentifier: null, registrationNumber: null,
            phone: null, customerNotes: null, billingAddress: ['recipient_name' => 'Jan', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            shippingAddress: null, locale: 'pl', currencyCode: 'PLN', paymentMethodId: 'core.free', paymentKind: 'free',
            totalNetAmount: '0', totalVatAmount: '0', totalGrossAmount: '0',
            lines: [new OrderPlacementLineData($variantId, 'digital', 'SKU-FREE', 'Ebook testowy', '1', '0', '0', '0', '0')],
        );

        $placed = app(OrderPlacer::class)->place($data);

        $this->assertSame('paid', $placed->paymentStatus);
        $this->assertDatabaseHas('download_grants', ['order_id' => $placed->id, 'variant_file_set_id' => $fileId]);
    }

    public function test_granting_is_idempotent_for_the_same_order_line_and_file(): void
    {
        $staff = StaffAccount::factory()->create();
        [, $variantId] = $this->createDigitalVariantWithFile($staff);
        $grantor = app(DigitalAccessGrantor::class);

        $first = $grantor->grantForOrderLine($variantId, 'order_line', '999', null, null, 'a@example.com', new \DateTimeImmutable);
        $second = $grantor->grantForOrderLine($variantId, 'order_line', '999', null, null, 'a@example.com', new \DateTimeImmutable);

        $this->assertCount(1, $first);
        $this->assertSame($first[0]->id, $second[0]->id);
        $this->assertSame(1, DB::table('download_grants')->count());
    }

    /** @return array{0:string,1:StaffAccount} a digital order whose payment arrived after cancellation */
    private function lateDigitalOrder(): array
    {
        $staff = StaffAccount::factory()->create();
        [, $variantId] = $this->createDigitalVariantWithFile($staff);
        [$orderId] = $this->placeDigitalOrder($variantId);
        app(OrderPaymentManager::class)->cancelUnpaid($orderId, 'Rezygnacja klienta');
        app(PaymentConfirmation::class)->confirmManual($orderId, 'LATE-1', new \DateTimeImmutable, (string) $staff->getKey());
        $this->app['auth']->forgetGuards();

        return [$orderId, $staff];
    }

    public function test_staff_grants_access_after_a_late_payment_with_audit_and_email(): void
    {
        [$orderId, $staff] = $this->lateDigitalOrder();

        $this->actingAs($staff)->post(route('panel.orders.digital-access.grant', $orderId), ['reason' => 'Klient zapłacił, plik nie wymaga zapasu.'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('download_grants', ['order_id' => $orderId, 'downloads_used' => 0]);
        $this->assertDatabaseHas('audit_entries', ['action' => 'orders.digital_access.granted_after_late_payment', 'subject_id' => $orderId]);
        $this->assertDatabaseHas('order_outbox_events', ['order_id' => $orderId, 'event_type' => 'DigitalAccessGrantedManually']);
        $this->assertSame(1, DB::table('email_messages')->where('definition_key', 'core.digital_access_granted')->count());
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'cancelled', 'payment_status' => 'late_payment']);
    }

    public function test_repeating_the_manual_grant_creates_nothing_new_and_is_not_audited_twice(): void
    {
        [$orderId, $staff] = $this->lateDigitalOrder();
        $payload = ['reason' => 'Decyzja pracownika.'];

        $this->actingAs($staff)->post(route('panel.orders.digital-access.grant', $orderId), $payload)->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.orders.digital-access.grant', $orderId), $payload)->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('download_grants')->count());
        $this->assertSame(1, DB::table('audit_entries')->where('action', 'orders.digital_access.granted_after_late_payment')->count());
        $this->assertSame(1, DB::table('email_messages')->where('definition_key', 'core.digital_access_granted')->count());
    }

    public function test_the_manual_grant_needs_a_reason(): void
    {
        [$orderId, $staff] = $this->lateDigitalOrder();

        $this->actingAs($staff)->post(route('panel.orders.digital-access.grant', $orderId), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($staff)->post(route('panel.orders.digital-access.grant', $orderId), ['reason' => '   '])->assertSessionHasErrors('reason');
        $this->assertSame(0, DB::table('download_grants')->count());
    }

    public function test_the_manual_grant_is_refused_unless_the_payment_is_late(): void
    {
        $staff = StaffAccount::factory()->create();
        [, $variantId] = $this->createDigitalVariantWithFile($staff);
        [$unpaid] = $this->placeDigitalOrder($variantId);
        $this->app['auth']->forgetGuards();

        $this->actingAs($staff)->post(route('panel.orders.digital-access.grant', $unpaid), ['reason' => 'Test'])->assertSessionHasErrors('digital_grant');

        app(PaymentConfirmation::class)->confirmManual($unpaid, 'BANK-1', new \DateTimeImmutable, (string) $staff->getKey());
        $granted = DB::table('download_grants')->count();
        $this->actingAs($staff)->post(route('panel.orders.digital-access.grant', $unpaid), ['reason' => 'Test'])->assertSessionHasErrors('digital_grant');
        $this->assertSame($granted, DB::table('download_grants')->count());
    }

    public function test_the_manual_grant_is_refused_when_the_file_is_no_longer_purchasable(): void
    {
        [$orderId, $staff] = $this->lateDigitalOrder();
        DB::table('variant_file_sets')->update(['active' => false]);

        $this->actingAs($staff)->post(route('panel.orders.digital-access.grant', $orderId), ['reason' => 'Test'])->assertSessionHasErrors('digital_grant');
        $this->assertSame(0, DB::table('download_grants')->count());
        $this->assertDatabaseMissing('order_outbox_events', ['event_type' => 'DigitalAccessGrantedManually']);
    }

    public function test_the_manual_grant_needs_the_manage_orders_permission_and_the_page_flags_eligibility(): void
    {
        [$orderId, $staff] = $this->lateDigitalOrder();
        $viewer = StaffAccount::factory()->create(['permission_overrides' => [CorePermission::ManageOrders->value => false]]);

        $this->actingAs($viewer)->post(route('panel.orders.digital-access.grant', $orderId), ['reason' => 'Test'])->assertForbidden();
        $this->assertSame(0, DB::table('download_grants')->count());

        $this->actingAs($staff)->get(route('panel.orders.show', $orderId))->assertOk()
            ->assertInertia(fn ($page) => $page->where('order.can_grant_digital_access', true));
    }
}
