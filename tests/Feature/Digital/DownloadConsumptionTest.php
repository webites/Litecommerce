<?php

declare(strict_types=1);

namespace Tests\Feature\Digital;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessManager;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Media\Application\VerifyPrivateFiles;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * The public, token-secured GET /downloads/{token} endpoint —
 * "Gość korzysta z bezpiecznego dostępu bez konta. Linki nie są
 * publicznymi adresami plików" — and the resumed-download counting rule
 * documented on Digital\Application\ConsumeDownload.
 */
final class DownloadConsumptionTest extends TestCase
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

    /** @return string access token of the created grant */
    private function grantedToken(?int $maxDownloads = null): string
    {
        $staff = StaffAccount::factory()->create();
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
            'file' => UploadedFile::fake()->createWithContent('ebook.pdf', str_repeat('zawartość pliku testowego. ', 20)),
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

        return (string) DB::table('download_grants')->where('order_id', $orderId)->value('access_token');
    }

    public function test_a_fresh_request_serves_the_file_and_consumes_one_unit(): void
    {
        $token = $this->grantedToken(maxDownloads: 2);

        $response = $this->get("/downloads/{$token}");

        $response->assertOk();
        $this->assertStringContainsString('ebook.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertGreaterThan(0, (int) $response->headers->get('Content-Length'));
        $this->assertDatabaseHas('download_grants', ['access_token' => $token, 'downloads_used' => 1]);
        $this->assertDatabaseHas('download_attempts', ['outcome' => 'served', 'counted' => true]);
    }

    public function test_download_is_blocked_once_the_limit_is_reached(): void
    {
        $token = $this->grantedToken(maxDownloads: 1);

        $this->get("/downloads/{$token}")->assertOk();
        $this->get("/downloads/{$token}")->assertForbidden();

        $this->assertDatabaseHas('download_grants', ['access_token' => $token, 'downloads_used' => 1]);
        $this->assertDatabaseHas('download_attempts', ['outcome' => 'limit_exceeded', 'counted' => false]);
    }

    public function test_a_range_request_resuming_the_same_download_does_not_consume_another_unit(): void
    {
        $token = $this->grantedToken(maxDownloads: 1);

        $this->get("/downloads/{$token}")->assertOk();
        $resumed = $this->get("/downloads/{$token}", ['Range' => 'bytes=10-']);

        $resumed->assertStatus(206);
        $this->assertDatabaseHas('download_grants', ['access_token' => $token, 'downloads_used' => 1]);
        $this->assertDatabaseHas('download_attempts', ['outcome' => 'served', 'counted' => false, 'range_start' => 10]);
    }

    public function test_an_expired_grant_is_rejected(): void
    {
        $token = $this->grantedToken();
        DB::table('download_grants')->where('access_token', $token)->update(['expires_at' => now()->subDay()]);

        $this->get("/downloads/{$token}")->assertStatus(410);
    }

    public function test_a_revoked_grant_is_rejected(): void
    {
        $token = $this->grantedToken();
        $grantId = (int) DB::table('download_grants')->where('access_token', $token)->value('id');
        app(DigitalAccessManager::class)->revoke($grantId, 'Zwrot zatwierdzony.');

        $this->get("/downloads/{$token}")->assertForbidden();
        $this->assertDatabaseHas('download_attempts', ['outcome' => 'revoked']);
    }

    public function test_an_unknown_token_returns_not_found(): void
    {
        $this->get('/downloads/doesnotexist1234567890')->assertNotFound();
        $this->assertSame(0, DB::table('download_attempts')->count());
    }

    public function test_a_tampered_file_is_never_served_and_does_not_consume_the_limit(): void
    {
        $token = $this->grantedToken(maxDownloads: 2);
        $file = DB::table('private_files')->first();
        Storage::disk($file->disk)->put($file->path, 'tampered');

        app(VerifyPrivateFiles::class)->run(10);

        $this->get("/downloads/{$token}")->assertStatus(503);
        $this->assertDatabaseHas('download_grants', ['access_token' => $token, 'downloads_used' => 0]);
        $this->assertDatabaseHas('download_attempts', ['outcome' => 'unavailable', 'counted' => false]);
        $this->assertDatabaseHas('audit_entries', ['action' => 'media.private_file.integrity_failed']);
    }

    public function test_offload_hands_the_transfer_to_the_web_server_after_the_limit_is_counted(): void
    {
        $token = $this->grantedToken(maxDownloads: 2);
        config(['media.download_offload' => 'x-accel-redirect', 'media.download_offload_prefix' => '/protected-private']);

        $response = $this->get("/downloads/{$token}");

        $response->assertOk();
        $this->assertStringStartsWith('/protected-private/digital/', (string) $response->headers->get('X-Accel-Redirect'));
        $this->assertSame('', $response->getContent());
        $this->assertDatabaseHas('download_grants', ['access_token' => $token, 'downloads_used' => 1]);
    }
}
