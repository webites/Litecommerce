<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\PlacesStorefrontOrders;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class StoredHtmlXssTest extends TestCase
{
    use PlacesStorefrontOrders;
    use UsesIsolatedStorage;

    private const PAYLOAD = '<p>ok</p><script>window.pwned=1</script><img src=x onerror="window.pwned=2"><a href="javascript:window.pwned=3">x</a>';

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->setUpShop();
        $this->installReferenceTheme();
    }

    private function assertClean(string $html): void
    {
        $this->assertStringContainsString('<p>ok</p>', $html, 'the safe part of the content must still render');
        $this->assertStringNotContainsString('<script>window.pwned', $html);
        $this->assertStringNotContainsString('onerror=', $html);
        $this->assertStringNotContainsString('javascript:window.pwned', $html);
    }

    public function test_a_product_description_cannot_inject_script_into_the_storefront(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Xss produkt', 'short_description' => '', 'description_html' => self::PAYLOAD,
            'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1', 'sku' => '', 'gtin' => '',
            'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');
        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), ['regular_amount' => '10.00', 'regular_mode' => 'net'])->assertSessionHasNoErrors();
        $slug = (string) DB::table('product_translations')->where('product_id', $productId)->value('slug');
        $this->app['auth']->forgetGuards();

        $this->assertClean($this->get('/pl/products/'.$slug)->assertOk()->getContent());
    }

    public function test_a_cms_rich_text_block_cannot_inject_script_into_the_storefront(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'strona-xss', 'title' => 'Strona xss', 'seo_title' => '', 'seo_description' => '', 'status' => 'draft',
        ])->assertSessionHasNoErrors();
        $pageId = (int) DB::table('cms_pages')->latest('id')->value('id');
        $this->actingAs($staff)->put(route('panel.cms.pages.update', $pageId), [
            'root' => ['props' => ['title' => 'Strona xss', 'slug' => 'strona-xss', 'seo_title' => '', 'seo_description' => '', 'status' => 'published']],
            'content' => [['id' => 'b1', 'type' => 'rich-text', 'props' => ['html' => self::PAYLOAD]]],
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $this->assertClean($this->get('/pl/strona-xss')->assertOk()->getContent());
    }

    public function test_the_filter_option_is_audited_and_turning_it_off_stores_html_as_typed(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->put(route('panel.settings.html-filter.update'), ['filter_html' => false])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_entries', ['action' => 'store.html_filter.updated']);

        $this->actingAs($staff)->post(route('panel.catalog.products.references.brands.store'), ['locale' => 'pl', 'name' => 'Marka raw', 'description_html' => self::PAYLOAD])->assertSessionHasNoErrors();
        $this->assertStringContainsString('<script>', (string) DB::table('brand_translations')->where('name', 'Marka raw')->value('description_html'));
    }

    public function test_cleaning_stored_content_rewrites_earlier_unfiltered_html(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->put(route('panel.settings.html-filter.update'), ['filter_html' => false])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.catalog.products.references.brands.store'), ['locale' => 'pl', 'name' => 'Marka stara', 'description_html' => self::PAYLOAD])->assertSessionHasNoErrors();
        $this->actingAs($staff)->put(route('panel.settings.html-filter.update'), ['filter_html' => true])->assertSessionHasNoErrors();

        $this->actingAs($staff)->post(route('panel.settings.html-filter.sanitize'))->assertSessionHasNoErrors();

        $stored = (string) DB::table('brand_translations')->where('name', 'Marka stara')->value('description_html');
        $this->assertStringContainsString('<p>ok</p>', $stored);
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('onerror', $stored);
    }

    public function test_the_html_filter_settings_require_the_store_settings_permission(): void
    {
        $this->actingAs(StaffAccount::factory()->create(['permission_overrides' => ['core.store.settings.manage' => false]]));
        $this->put(route('panel.settings.html-filter.update'), ['filter_html' => false])->assertForbidden();
        $this->post(route('panel.settings.html-filter.sanitize'))->assertForbidden();
    }
}
