<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessage;
use Litecommerce\Modules\Identity\Infrastructure\Models\CustomerAccount;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Store\Contracts\StoreSettingKeys;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;
use Litecommerce\Modules\Storefront\Contracts\ProductTabContext;
use Litecommerce\Modules\Storefront\Contracts\ProductTabRegistry;
use Litecommerce\Modules\Themes\Contracts\ThemeInstaller;
use Litecommerce\Modules\Themes\Providers\ThemesServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;
use ZipArchive;

/**
 * End-to-end coverage of P1-013's public storefront pages, rendered by the
 * bundled reference theme (resources/themes/default-storefront) against
 * the real JSON API (routes/storefront.php) — see
 * docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013.
 */
final class StorefrontPagesTest extends TestCase
{
    use CarriesTestCookies;
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->withCredentials();

        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('bank_transfer_accounts')->insert([
            'currency_code' => 'PLN', 'beneficiary_name' => 'Sklep Testowy', 'account_number' => 'PL00 1111 2222 3333',
            'instructions' => json_encode(['pl' => 'Podaj numer zamowienia w tytule.']), 'enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $zipPath = $this->zipDirectory(base_path('resources/themes/default-storefront'));
        app(ThemeInstaller::class)->installAndActivate($zipPath);
        @unlink($zipPath);
        $this->rebootThemes();
    }

    private function rebootThemes(): void
    {
        (new ThemesServiceProvider($this->app))->boot();
    }

    private function zipDirectory(string $sourceDir): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'lc-theme-').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $relative = substr($file->getPathname(), strlen($sourceDir) + 1);
            $zip->addFile($file->getPathname(), $relative);
        }
        $zip->close();

        return $zipPath;
    }

    /** @return array{productId:int, variantId:int, slug:string} */
    private function createDigitalProduct(string $name, ?string $priceAmount, bool $published = true): array
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => $name, 'short_description' => 'Opis krotki', 'description_html' => '<p>Opis</p>',
            'type' => 'digital', 'status' => $published ? 'published' : 'draft', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->where('product_id', $productId)->value('id');
        $slug = (string) DB::table('product_translations')->where('product_id', $productId)->value('slug');

        if ($priceAmount !== null) {
            $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), [
                'regular_amount' => $priceAmount, 'regular_mode' => 'gross',
            ])->assertSessionHasNoErrors();
        }

        $this->app['auth']->forgetGuards();

        return ['productId' => $productId, 'variantId' => $variantId, 'slug' => $slug];
    }

    public function test_root_redirects_to_default_locale_and_unknown_locale_is_404(): void
    {
        $this->get('/')->assertRedirect('/pl');
        $this->get('/xx')->assertNotFound();
    }

    public function test_catalog_listing_shows_only_published_products_with_price(): void
    {
        $published = $this->createDigitalProduct('Ebook widoczny', '25.00');
        $this->createDigitalProduct('Ebook szkic', '10.00', published: false);

        $response = $this->get('/pl')->assertOk();
        $response->assertSee('Ebook widoczny');
        $response->assertSee('25,00 PLN');
        $response->assertDontSee('25.000000 PLN');
        $response->assertDontSee('Ebook szkic');
    }

    public function test_catalog_listing_cards_render_a_quick_add_to_cart_form_for_the_default_variant(): void
    {
        $product = $this->createDigitalProduct('Ebook szybki koszyk', '15.00');

        $response = $this->get('/pl')->assertOk();
        $response->assertSee('Dodaj do koszyka');
        $response->assertSee('name="variant_id" value="'.$product['variantId'].'"', false);
    }

    public function test_product_page_shows_a_variant_with_no_price_as_unavailable_and_disables_add_to_cart(): void
    {
        $product = $this->createDigitalProduct('Ebook bez ceny', null);

        $response = $this->get('/pl/products/'.$product['slug'])->assertOk();
        $response->assertSee('Cena niedostępna');
        $response->assertSee('Niedostępny');
        $response->assertSee('name="variant_id" value="'.$product['variantId'].'"', false);
        $response->assertSee('<button type="button" data-storefront-step="-1" disabled', false);
        $response->assertSee('<button type="submit" class="btn btn-primary" disabled>', false);
    }

    public function test_products_listing_moved_to_its_own_explicit_path(): void
    {
        $this->createDigitalProduct('Ebook pod nowym adresem', '25.00');

        $this->get('/pl/products')->assertOk()->assertSee('Ebook pod nowym adresem');
    }

    public function test_catalog_uses_configured_page_size_and_shared_pagination_block(): void
    {
        $first = $this->createDigitalProduct('Pierwszy ebook', '25.00');
        $second = $this->createDigitalProduct('Drugi ebook', '35.00');
        app(StoreSettingsStore::class)->set(StoreSettingKeys::CatalogProductsPerPage, 1);

        $firstPage = $this->get('/pl/products')->assertOk();
        $firstPage->assertSee('aria-label="Paginacja"', false);
        $firstPage->assertSee('class="catalog-filter-controls"', false);
        $firstPage->assertSee('class="catalog-filter-actions"', false);
        $firstPage->assertSee('Drugi ebook');
        $firstPage->assertDontSee('Pierwszy ebook');

        $secondPage = $this->get('/pl/products?page=2')->assertOk();
        $secondPage->assertSee('Pierwszy ebook');
        $secondPage->assertDontSee('Drugi ebook');
        $this->assertNotSame($first['variantId'], $second['variantId']);
    }

    public function test_homepage_renders_the_published_cms_page_flagged_as_homepage(): void
    {
        $staff = StaffAccount::factory()->create();
        $pageId = $this->createCmsPage($staff, 'strona-glowna', 'Strona glowna', published: true);
        $this->actingAs($staff)->put(route('panel.cms.pages.update', $pageId), [
            'root' => ['props' => ['title' => 'Strona glowna', 'slug' => 'strona-glowna', 'seo_title' => '', 'seo_description' => '', 'status' => 'published']],
            'content' => [
                ['id' => 'a', 'type' => 'hero', 'props' => ['title' => 'Witaj na stronie glownej']],
            ],
        ])->assertSessionHasNoErrors();
        $this->actingAs($staff)->put(route('panel.cms.pages.homepage.update', $pageId))->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $this->get('/pl')->assertOk()->assertSee('Witaj na stronie glownej');
    }

    public function test_cms_page_renders_by_slug_and_unpublished_or_unknown_slug_is_404(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->createCmsPage($staff, 'o-nas', 'O nas', published: true);
        $this->createCmsPage($staff, 'szkic', 'Szkic', published: false);
        $this->app['auth']->forgetGuards();

        $this->get('/pl/o-nas')->assertOk()->assertSee('O nas');
        $this->get('/pl/szkic')->assertNotFound();
        $this->get('/pl/nie-istnieje')->assertNotFound();
    }

    private function createCmsPage(StaffAccount $staff, string $slug, string $title, bool $published): int
    {
        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => $slug, 'title' => $title,
            'seo_title' => '', 'seo_description' => '', 'status' => $published ? 'published' : 'draft',
        ])->assertSessionHasNoErrors();

        return (int) DB::table('cms_pages')->latest('id')->value('id');
    }

    public function test_product_page_shows_upsell_and_cross_sell_but_hides_unpublished_targets(): void
    {
        $source = $this->createDigitalProduct('Ebook glowny', '19.99');
        $upsell = $this->createDigitalProduct('Ebook drozszy', '39.99');
        $crossSell = $this->createDigitalProduct('Ebook dodatkowy', '9.99');
        $hidden = $this->createDigitalProduct('Ebook szkic', '5.00', published: false);

        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->put(route('panel.catalog.products.relations.update', $source['productId']), [
            'relations' => [
                ['type' => 'upsell', 'target_product_id' => $upsell['productId']],
                ['type' => 'cross_sell', 'target_product_id' => $crossSell['productId']],
                ['type' => 'cross_sell', 'target_product_id' => $hidden['productId']],
            ],
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $response = $this->get('/pl/products/'.$source['slug'])->assertOk();
        $response->assertSee('Może spodoba się także');
        $response->assertSee('Ebook drozszy');
        $response->assertSee('39,99 PLN');
        $response->assertSee('Często kupowane razem');
        $response->assertSee('Ebook dodatkowy');
        $response->assertDontSee('Ebook szkic');
    }

    public function test_product_page_variant_picker_resolves_exact_variant_with_price_and_promo(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Koszulka', 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1',
            'sku' => 'SHIRT-BASE', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $slug = (string) DB::table('product_translations')->where('product_id', $productId)->value('slug');

        [$colorId, $colorValues] = $this->createAttributeWithValues($staff, 'kolor', 'Kolor', ['czerwony' => 'Czerwony', 'niebieski' => 'Niebieski'], 'color');
        [$sizeId, $sizeValues] = $this->createAttributeWithValues($staff, 'rozmiar', 'Rozmiar', ['s' => 'S', 'm' => 'M']);

        $this->actingAs($staff)->post(route('panel.catalog.products.variants.generate', $productId), [
            'options' => [
                ['attribute_definition_id' => $colorId, 'attribute_value_ids' => $colorValues],
                ['attribute_definition_id' => $sizeId, 'attribute_value_ids' => $sizeValues],
            ],
        ])->assertSessionHasNoErrors();

        $variantByCombo = [];
        foreach (DB::table('product_variants')->where('product_id', $productId)->pluck('id') as $variantId) {
            $values = DB::table('variant_option_values as vov')
                ->join('product_options as po', 'po.id', '=', 'vov.product_option_id')
                ->join('attribute_definitions as ad', 'ad.id', '=', 'po.attribute_definition_id')
                ->join('attribute_values as av', 'av.id', '=', 'vov.attribute_value_id')
                ->where('vov.product_variant_id', $variantId)
                ->pluck('av.value_key', 'ad.key');
            $variantByCombo[$values->get('kolor').'-'.$values->get('rozmiar')] = $variantId;
        }

        $plainVariantId = $variantByCombo['czerwony-s'];
        $promoVariantId = $variantByCombo['niebieski-m'];

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $plainVariantId]), [
            'regular_amount' => '80.00', 'regular_mode' => 'gross',
        ])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $plainVariantId), ['quantity' => '5'])->assertSessionHasNoErrors();

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $promoVariantId]), [
            'regular_amount' => '150.00', 'regular_mode' => 'gross', 'promotional_amount' => '99.00', 'promotional_mode' => 'gross',
        ])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $promoVariantId), ['quantity' => '5'])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $response = $this->get('/pl/products/'.$slug)->assertOk();
        $response->assertSee('Kolor');
        $response->assertSee('Czerwony');
        $response->assertSee('Rozmiar');
        // Each variant block carries its OWN full axis combination as its show-if
        // condition — this is what lets the picker resolve to exactly one block.
        $response->assertSee('data-storefront-show-if="kolor:czerwony,rozmiar:s"', false);
        $response->assertSee('80,00 PLN');
        $response->assertSee('data-storefront-show-if="kolor:niebieski,rozmiar:m"', false);
        $response->assertSee('150,00 PLN');
        $response->assertSee('99,00 PLN');
    }

    /**
     * The product page composes its content from config('storefront.regions.catalog.show')
     * (@themeRegions in catalog/show.blade.php), not a hardcoded @include
     * chain — a store's own config, not the theme, decides the region set
     * and order. This is a CORE capability (Storefront\Contracts\ThemeRegionComposer),
     * not something specific to the bundled theme.
     */
    public function test_product_page_region_order_and_set_come_from_store_config_not_the_theme(): void
    {
        // "Opis krotki" (short_description, see createDigitalProduct()) is
        // the marker here — the reactive SKU line moved from this region
        // into "product-purchase" itself in the 2026-09-18 redesign (see
        // partials/product-purchase.blade.php), so it can no longer stand
        // in for "is the product-details region present" the way it used to.
        $product = $this->createDigitalProduct('Ebook regiony', '10.00');

        config(['storefront.regions' => ['catalog.show' => ['product-meta', 'product-purchase']]]);
        $response = $this->get('/pl/products/'.$product['slug'])->assertOk();
        $response->assertDontSee('Opis krotki', false);
        $response->assertSee('Dodaj do koszyka');

        config(['storefront.regions' => ['catalog.show' => ['product-purchase', 'product-details']]]);
        $html = $this->get('/pl/products/'.$product['slug'])->assertOk()->getContent();
        $this->assertLessThan(strpos((string) $html, 'Opis krotki'), strpos((string) $html, 'Dodaj do koszyka'));
    }

    /**
     * A merchant can add a new option axis (here: size) to a product that
     * already had variants generated from an earlier, narrower axis set
     * (here: color only) — GenerateVariants never retroactively assigns
     * the new axis to those older variants (see its own doc comment: it
     * only inserts missing combinations, never prunes/backfills existing
     * ones). Those older variants must never appear to "match" a full,
     * real selection just because they happen to share the color value —
     * see EloquentProductCatalog::optionAxesAndAssignments() and
     * partials/variant-picker.blade.php's "__missing__" sentinel.
     */
    public function test_variant_missing_a_later_added_option_axis_never_double_matches_a_complete_combination(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Bluza', 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1',
            'sku' => 'HOODIE-BASE', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $slug = (string) DB::table('product_translations')->where('product_id', $productId)->value('slug');

        [$colorId, $colorValues] = $this->createAttributeWithValues($staff, 'kolor', 'Kolor', ['czerwony' => 'Czerwony']);
        $this->actingAs($staff)->post(route('panel.catalog.products.variants.generate', $productId), [
            'options' => [['attribute_definition_id' => $colorId, 'attribute_value_ids' => $colorValues]],
        ])->assertSessionHasNoErrors();

        // Added later: a size axis, generated on top of the color-only variant above.
        [$sizeId, $sizeValues] = $this->createAttributeWithValues($staff, 'rozmiar', 'Rozmiar', ['s' => 'S']);
        $this->actingAs($staff)->post(route('panel.catalog.products.variants.generate', $productId), [
            'options' => [
                ['attribute_definition_id' => $colorId, 'attribute_value_ids' => $colorValues],
                ['attribute_definition_id' => $sizeId, 'attribute_value_ids' => $sizeValues],
            ],
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        // 2 variants: the original color-only one (now stale) and the new complete kolor+rozmiar combination.
        $this->assertDatabaseCount('product_variants', 2);

        $response = $this->get('/pl/products/'.$slug)->assertOk();
        $response->assertSee('data-storefront-show-if="kolor:czerwony,rozmiar:__missing__"', false);
        $response->assertSee('data-storefront-show-if="kolor:czerwony,rozmiar:s"', false);
    }

    /**
     * @param  array<string, string>  $values  value_key => translated name
     * @return array{0: int, 1: list<int>} [definitionId, list<valueId>]
     */
    private function createAttributeWithValues(StaffAccount $staff, string $key, string $name, array $values, string $type = 'single_select'): array
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.references.attributes.store'), [
            'locale' => 'pl', 'key' => $key, 'name' => $name, 'type' => $type, 'is_filterable' => true,
        ])->assertSessionHasNoErrors();
        $definitionId = (int) DB::table('attribute_definitions')->where('key', $key)->value('id');
        $ids = [];

        foreach ($values as $valueKey => $valueName) {
            $this->actingAs($staff)->post(route('panel.catalog.products.references.attribute-values.store', $definitionId), [
                'locale' => 'pl', 'value_key' => $valueKey, 'name' => $valueName,
                'color_hex' => $type === 'color' ? '#123456' : null,
            ])->assertSessionHasNoErrors();
            $ids[] = (int) DB::table('attribute_values')->where('attribute_definition_id', $definitionId)->where('value_key', $valueKey)->value('id');
        }

        return [$definitionId, $ids];
    }

    /**
     * Same product/attribute/variant shape as
     * test_product_page_variant_picker_resolves_exact_variant_with_price_and_promo,
     * factored out for the variant-URL tests below, which only care about
     * which slugs the four kolor x rozmiar combinations resolve to.
     *
     * @return array{productId:int, slug:string, variants:array<string,int>} variants keyed "colorValueKey-sizeValueKey"
     */
    private function createProductWithColorSizeVariants(StaffAccount $staff): array
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Koszulka', 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1',
            'sku' => 'SHIRT-BASE', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $slug = (string) DB::table('product_translations')->where('product_id', $productId)->value('slug');

        [$colorId, $colorValues] = $this->createAttributeWithValues($staff, 'kolor', 'Kolor', ['czerwony' => 'Czerwony', 'niebieski' => 'Niebieski'], 'color');
        [$sizeId, $sizeValues] = $this->createAttributeWithValues($staff, 'rozmiar', 'Rozmiar', ['s' => 'S', 'm' => 'M']);

        $this->actingAs($staff)->post(route('panel.catalog.products.variants.generate', $productId), [
            'options' => [
                ['attribute_definition_id' => $colorId, 'attribute_value_ids' => $colorValues],
                ['attribute_definition_id' => $sizeId, 'attribute_value_ids' => $sizeValues],
            ],
        ])->assertSessionHasNoErrors();

        $variantByCombo = [];
        foreach (DB::table('product_variants')->where('product_id', $productId)->pluck('id') as $variantId) {
            $values = DB::table('variant_option_values as vov')
                ->join('product_options as po', 'po.id', '=', 'vov.product_option_id')
                ->join('attribute_definitions as ad', 'ad.id', '=', 'po.attribute_definition_id')
                ->join('attribute_values as av', 'av.id', '=', 'vov.attribute_value_id')
                ->where('vov.product_variant_id', $variantId)
                ->pluck('av.value_key', 'ad.key');
            $variantByCombo[$values->get('kolor').'-'.$values->get('rozmiar')] = $variantId;
        }

        $this->app['auth']->forgetGuards();

        return ['productId' => $productId, 'slug' => $slug, 'variants' => $variantByCombo];
    }

    public function test_product_page_deep_link_url_preselects_the_matching_variant(): void
    {
        $staff = StaffAccount::factory()->create();
        $data = $this->createProductWithColorSizeVariants($staff);

        $html = (string) $this->get('/pl/products/'.$data['slug'].'/kolor/czerwony/rozmiar/s')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="kolor"[^>]*value="czerwony"[^>]*checked[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="rozmiar"[^>]*value="s"[^>]*checked[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="kolor"[^>]*value="niebieski"[^>]*checked[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="rozmiar"[^>]*value="m"[^>]*checked[^>]*>/', $html);
    }

    public function test_product_page_deep_link_with_segments_out_of_canonical_order_redirects_301_to_canonical_order(): void
    {
        $staff = StaffAccount::factory()->create();
        $data = $this->createProductWithColorSizeVariants($staff);

        $response = $this->get('/pl/products/'.$data['slug'].'/rozmiar/s/kolor/czerwony');

        $response->assertStatus(301);
        $response->assertRedirect('/pl/products/'.$data['slug'].'/kolor/czerwony/rozmiar/s');
    }

    public function test_product_page_deep_link_with_incomplete_combination_is_404(): void
    {
        $staff = StaffAccount::factory()->create();
        $data = $this->createProductWithColorSizeVariants($staff);

        $this->get('/pl/products/'.$data['slug'].'/kolor/czerwony')->assertNotFound();
    }

    public function test_product_page_deep_link_with_unknown_axis_slug_is_404(): void
    {
        $staff = StaffAccount::factory()->create();
        $data = $this->createProductWithColorSizeVariants($staff);

        $this->get('/pl/products/'.$data['slug'].'/nieznana-os/czerwony/rozmiar/s')->assertNotFound();
    }

    public function test_product_page_deep_link_with_unknown_value_slug_is_404(): void
    {
        $staff = StaffAccount::factory()->create();
        $data = $this->createProductWithColorSizeVariants($staff);

        $this->get('/pl/products/'.$data['slug'].'/kolor/zielony/rozmiar/s')->assertNotFound();
    }

    public function test_product_page_deep_link_for_a_disabled_variant_is_404(): void
    {
        $staff = StaffAccount::factory()->create();
        $data = $this->createProductWithColorSizeVariants($staff);
        DB::table('product_variants')->where('id', $data['variants']['czerwony-s'])->update(['enabled' => false]);

        $this->get('/pl/products/'.$data['slug'].'/kolor/czerwony/rozmiar/s')->assertNotFound();
    }

    public function test_product_with_no_option_axes_and_extra_url_segments_is_404(): void
    {
        $product = $this->createDigitalProduct('Ebook bez wariantow', '19.99');

        $this->get('/pl/products/'.$product['slug'].'/cokolwiek/tutaj')->assertNotFound();
    }

    public function test_product_page_canonical_link_always_points_to_the_base_product_url_even_on_a_variant_deep_link(): void
    {
        $staff = StaffAccount::factory()->create();
        $data = $this->createProductWithColorSizeVariants($staff);
        $canonicalTag = '<link rel="canonical" href="'.url('/pl/products/'.$data['slug']).'">';

        $this->get('/pl/products/'.$data['slug'])->assertOk()->assertSee($canonicalTag, false);
        $this->get('/pl/products/'.$data['slug'].'/kolor/czerwony/rozmiar/s')->assertOk()->assertSee($canonicalTag, false);
    }

    public function test_product_page_canonical_points_to_the_variant_url_itself_when_mode_is_indexed_separately(): void
    {
        $staff = StaffAccount::factory()->create();
        $data = $this->createProductWithColorSizeVariants($staff);
        DB::table('products')->where('id', $data['productId'])->update(['variant_seo_mode' => 'indexed_separately']);
        $baseUrl = url('/pl/products/'.$data['slug']);
        $variantUrl = $baseUrl.'/kolor/czerwony/rozmiar/s';

        $this->get('/pl/products/'.$data['slug'])->assertOk()->assertSee('<link rel="canonical" href="'.$baseUrl.'">', false);
        $this->get('/pl/products/'.$data['slug'].'/kolor/czerwony/rozmiar/s')->assertOk()
            ->assertSee('<link rel="canonical" href="'.$variantUrl.'">', false);
    }

    public function test_product_page_variant_deep_link_gets_noindex_meta_when_mode_is_noindex_but_base_url_does_not(): void
    {
        $staff = StaffAccount::factory()->create();
        $data = $this->createProductWithColorSizeVariants($staff);
        DB::table('products')->where('id', $data['productId'])->update(['variant_seo_mode' => 'noindex']);

        $this->get('/pl/products/'.$data['slug'])->assertOk()->assertDontSee('name="robots"', false);
        $this->get('/pl/products/'.$data['slug'].'/kolor/czerwony/rozmiar/s')->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_product_page_shows_details_and_unknown_slug_is_404(): void
    {
        $product = $this->createDigitalProduct('Ebook szczegoly', '19.99');

        $this->get('/pl/products/'.$product['slug'])->assertOk()->assertSee('Ebook szczegoly')->assertSee('19,99 PLN');
        $this->get('/pl/products/nie-istnieje')->assertNotFound();
    }

    public function test_product_page_description_and_specification_render_as_separate_tabs(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Ebook ze specyfikacja', 'short_description' => '', 'description_html' => '<p>Pelny opis produktu.</p>',
            'type' => 'digital', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => 'EBOOK-SPEC-001', 'gtin' => '5901234123457', 'mpn' => 'MPN-42', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $slug = (string) DB::table('product_translations')->where('product_id', DB::table('products')->latest('id')->value('id'))->value('slug');
        $this->app['auth']->forgetGuards();

        $response = $this->get('/pl/products/'.$slug)->assertOk();
        // Tab bar and both panels' generic engine wiring — a plain radio
        // group plus data-storefront-show-if, no product-tabs-specific JS.
        $response->assertSee('name="product_tab" value="opis"', false);
        $response->assertSee('name="product_tab" value="specyfikacja"', false);
        $response->assertSee('Pelny opis produktu.');
        $response->assertSee('EBOOK-SPEC-001');
        $response->assertSee('5901234123457');
        $response->assertSee('MPN-42');
        // The tabs region renders after (outside) .product-info, not squeezed into it.
        $this->assertGreaterThan(
            strpos((string) $response->getContent(), 'class="product-info"'),
            strpos((string) $response->getContent(), 'class="product-tabs"'),
        );
    }

    public function test_specification_tab_shows_one_offer_at_a_time_not_every_offer_at_once(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Kubek wariantowy', 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $slug = (string) DB::table('product_translations')->where('product_id', $productId)->value('slug');

        [$colorId, $colorValues] = $this->createAttributeWithValues($staff, 'kolor', 'Kolor', ['czerwony' => 'Czerwony', 'niebieski' => 'Niebieski'], 'color');
        $this->actingAs($staff)->post(route('panel.catalog.products.variants.generate', $productId), [
            'options' => [['attribute_definition_id' => $colorId, 'attribute_value_ids' => $colorValues]],
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $html = (string) $this->get('/pl/products/'.$slug)->assertOk()->getContent();

        // Each offer's own <table class="spec-table"> is wrapped in the same per-offer
        // data-storefront-show-if the add-to-cart/SKU-line partials already use — only the picked
        // variant's row is ever visible at once. Without this, every offer renders unconditionally.
        $this->assertSame(1, preg_match_all('#<table class="spec-table"\s+data-storefront-show-if="kolor:czerwony"#', $html));
        $this->assertSame(1, preg_match_all('#<table class="spec-table"\s+data-storefront-show-if="kolor:niebieski"#', $html));
        $this->assertSame(2, substr_count($html, '<table class="spec-table"'));
        $this->assertStringNotContainsString('<caption>', $html);
    }

    public function test_short_and_full_descriptions_follow_the_selected_variant_with_product_copy_as_fallback(): void
    {
        $staff = StaffAccount::factory()->create();
        $data = $this->createProductWithColorSizeVariants($staff);

        DB::table('product_translations')->where('product_id', $data['productId'])->where('locale', 'pl')->update([
            'short_description' => 'Domyslny krotki opis produktu.',
            'description_html' => '<p>Domyslny pelny opis produktu.</p>',
        ]);

        foreach ([
            'czerwony-s' => ['Koszulka czerwona S', 'Krotki opis czerwonego S.', '<h2>Czerwony wariant S</h2><p>Pelny opis czerwonego S.</p>'],
            'niebieski-m' => ['Koszulka niebieska M', 'Krotki opis niebieskiego M.', '<h2>Niebieski wariant M</h2><p>Pelny opis niebieskiego M.</p>'],
        ] as $combination => [$name, $shortDescription, $descriptionHtml]) {
            DB::table('variant_translations')->insert([
                'product_variant_id' => $data['variants'][$combination],
                'locale' => 'pl',
                'name' => $name,
                'short_description' => $shortDescription,
                'description_html' => $descriptionHtml,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $html = (string) $this->get('/pl/products/'.$data['slug'])->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<p[^>]+data-storefront-show-if="kolor:czerwony,rozmiar:s"[^>]*hidden[^>]*>Krotki opis czerwonego S\.</p>#', $html);
        $this->assertMatchesRegularExpression('#<p[^>]+data-storefront-show-if="kolor:niebieski,rozmiar:m"[^>]*hidden[^>]*>Krotki opis niebieskiego M\.</p>#', $html);
        $this->assertMatchesRegularExpression('#<div data-storefront-show-if="kolor:czerwony,rozmiar:s"[^>]*hidden[^>]*>\s*<h2>Czerwony wariant S</h2><p>Pelny opis czerwonego S\.</p>#', $html);
        $this->assertMatchesRegularExpression('#<div data-storefront-show-if="kolor:niebieski,rozmiar:m"[^>]*hidden[^>]*>\s*<h2>Niebieski wariant M</h2><p>Pelny opis niebieskiego M\.</p>#', $html);
        $this->assertMatchesRegularExpression('#data-storefront-show-if-fallback\s*>Domyslny krotki opis produktu\.#', $html);
        $this->assertMatchesRegularExpression('#data-storefront-show-if-fallback\s*>\s*<p>Domyslny pelny opis produktu\.</p>#', $html);
        $this->assertStringContainsString('Domyslny pelny opis produktu.', $html);

        $deepLinkHtml = (string) $this->get('/pl/products/'.$data['slug'].'/kolor/czerwony/rozmiar/s')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<p class="short-description" data-storefront-show-if="kolor:czerwony,rozmiar:s"\s*>Krotki opis czerwonego S\.</p>#',
            $deepLinkHtml,
        );
        $this->assertMatchesRegularExpression(
            '#<div data-storefront-show-if="kolor:czerwony,rozmiar:s"\s*>\s*<h2>Czerwony wariant S</h2><p>Pelny opis czerwonego S\.</p>#',
            $deepLinkHtml,
        );
        $this->assertStringNotContainsString('Domyslny krotki opis produktu.', $deepLinkHtml);
        $this->assertStringNotContainsString('Domyslny pelny opis produktu.', $deepLinkHtml);
    }

    public function test_a_plugin_registered_product_tab_renders_alongside_the_built_in_tabs(): void
    {
        $product = $this->createDigitalProduct('Ebook z zakladka pluginu', '9.99');

        app(ProductTabRegistry::class)->register(
            'demo-plugin-tab', 'demo-plugin', 'Demo pluginu',
            static fn (ProductTabContext $context): string => '<p>Tresc z demo pluginu, ofert: '.count($context->offers).'.</p>',
        );

        $response = $this->get('/pl/products/'.$product['slug'])->assertOk();
        $response->assertSee('name="product_tab" value="demo-plugin-tab"', false);
        $response->assertSee('Demo pluginu');
        $response->assertSee('data-storefront-show-if="product_tab:demo-plugin-tab"', false);
        // The render closure receives ProductTabContext (product/axes/offers), so a plugin tab can
        // build the same per-offer, variant-reactive markup the built-in Specyfikacja tab uses.
        $response->assertSee('Tresc z demo pluginu, ofert: 1.');
    }

    public function test_full_checkout_flow_renders_confirmation_page_and_is_ownership_scoped(): void
    {
        $product = $this->createDigitalProduct('Ebook checkout', '30.00');

        $cart = $this->postJson('/cart/items', ['variant_id' => $product['variantId'], 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);

        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com',
            'billing_address' => ['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            'ships_to_different_address' => false,
        ])->assertOk();

        $methods = $this->getJson("/checkout/{$draftId}/payment-methods")->assertOk()->json('methods');
        $this->assertContains('core.bank_transfer', array_column($methods, 'id'));

        $hash = (string) $this->getJson("/checkout/{$draftId}")->assertOk()->json('price.hash');
        $orderId = (string) $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])
            ->assertOk()->json('order.id');

        $confirmation = $this->get("/pl/checkout/{$draftId}/thank-you")->assertOk();
        $confirmation->assertSee($orderId);
        $confirmation->assertSee('PL00 1111 2222 3333');

        $email = EmailMessage::query()->where('definition_key', 'core.order_placed')->where('source_id', $orderId)->firstOrFail();
        $guestOrderUrl = (string) $email->variables['order_url'];
        $guestOrderPath = (string) parse_url($guestOrderUrl, PHP_URL_PATH);
        $guestToken = basename($guestOrderPath);
        $this->assertDatabaseHas('guest_order_access_tokens', ['order_id' => $orderId]);
        $this->assertStringNotContainsString($guestToken, (string) DB::table('guest_order_access_tokens')->where('order_id', $orderId)->value('token_hash'));

        // A different visitor (no cart/session cookie carried over) must
        // not be able to view this confirmation via a guessed/shared URL.
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->get("/pl/checkout/{$draftId}/thank-you")->assertNotFound();
        $this->get($guestOrderPath)->assertOk()->assertSee($orderId);
    }

    public function test_customer_sees_own_orders_but_not_another_customers(): void
    {
        $product = $this->createDigitalProduct('Ebook konto', '15.00');

        // Customer A places an order while authenticated.
        $registerA = $this->postJson('/customer/register', [
            'email' => 'a@example.com', 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated();
        $this->carryCookiesFrom($registerA);

        $this->postJson('/cart/items', ['variant_id' => $product['variantId'], 'quantity' => '1'])->assertOk();
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person',
            'billing_address' => ['recipient_name' => 'Anna A', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            'ships_to_different_address' => false,
        ])->assertOk();
        $hash = (string) $this->getJson("/checkout/{$draftId}")->assertOk()->json('price.hash');
        $orderId = (string) $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])
            ->assertOk()->json('order.id');

        $this->get('/pl/account/orders')->assertOk()->assertSee($orderId);
        $this->get('/pl/account/orders/'.$orderId)->assertOk()->assertSee('PL00 1111 2222 3333');

        // Customer B, a completely separate identity, must not reach it.
        $this->postJson('/customer/logout')->assertNoContent();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];

        $registerB = $this->postJson('/customer/register', [
            'email' => 'b@example.com', 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated();
        $this->carryCookiesFrom($registerB);

        $this->get('/pl/account/orders/'.$orderId)->assertNotFound();
        $this->get('/pl/account/orders')->assertOk()->assertDontSee($orderId);
    }

    public function test_guest_visiting_customer_orders_is_redirected_to_storefront_login(): void
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];

        $this->get('/pl/account/orders')->assertRedirect('/pl/account/login');
    }

    /**
     * A theme with only ONE top-level view (catalog/index.blade.php), no
     * layout and no partials of its own — proves the two halves of the
     * 2026-09-17 template-fallback decision (docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013)
     * in a single package: theme::layout and theme::partials.product-card,
     * both missing here, must fall back to the default theme's own copy.
     */
    private function stubThemeMissingPartialsZip(string $id): string
    {
        $dir = sys_get_temp_dir().'/lc-fallback-theme-'.uniqid();
        mkdir($dir.'/views/catalog', 0777, true);
        file_put_contents($dir.'/manifest.json', json_encode([
            'id' => $id, 'name' => 'Motyw awaryjny', 'version' => '1.0.0', 'type' => 'theme', 'engine' => 'blade',
        ], JSON_THROW_ON_ERROR));
        file_put_contents($dir.'/views/catalog/index.blade.php', <<<'BLADE'
            @extends('theme::layout')

            @section('content')
                <h1>Sklep na motywie awaryjnym</h1>
                @foreach ($page->items as $item)
                    @include('theme::partials.product-card')
                @endforeach
            @endsection
            BLADE);

        return $this->zipDirectory($dir);
    }

    public function test_a_theme_missing_layout_and_partials_falls_back_to_the_default_theme_copies(): void
    {
        $this->createDigitalProduct('Ebook z motywu awaryjnego', '12.00');

        app(ThemeInstaller::class)->installAndActivate($this->stubThemeMissingPartialsZip('fallback-stub'));
        $this->rebootThemes();

        $response = $this->get('/pl')->assertOk();
        // Own top-level view actually used (not silently the OLD theme's).
        $response->assertSee('Sklep na motywie awaryjnym');
        // theme::layout fell back — only the default theme's layout prints this.
        $response->assertSee('Litecommerce');
        // theme::partials.product-card fell back — only it prints the product.
        $response->assertSee('Ebook z motywu awaryjnego');
    }

    public function test_a_theme_missing_a_top_level_view_returns_503_not_a_silent_fallback(): void
    {
        app(ThemeInstaller::class)->installAndActivate($this->stubThemeMissingPartialsZip('incomplete-stub'));
        $this->rebootThemes();

        // This theme has no cart/show.blade.php at all — unlike partials,
        // top-level views never fall back to the default theme.
        $this->get('/pl/cart')->assertStatus(503);
    }

    public function test_dark_mode_toggle_is_shown_and_the_cookie_controls_the_rendered_theme(): void
    {
        // The menu is entirely merchant-configured — give the 'primary'
        // location the theme_mode_toggle widget the same way a real
        // merchant would (see StorefrontNavMenuTest for that mechanism).
        DB::table('menus')->insert([
            'name' => 'Menu główne', 'location_id' => 'primary',
            'content' => json_encode([
                ['id' => 'toggle', 'type' => 'widget', 'referenceKey' => 'theme_mode_toggle', 'referenceId' => null, 'url' => null, 'labelOverride' => null, 'targetBlank' => false, 'children' => []],
            ]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // default-storefront's manifest.json declares supports_dark_mode:
        // true, so the toggle partial renders and the light default (no
        // data-theme attribute) applies with no cookie set.
        $withoutCookie = $this->get('/pl')->assertOk();
        $withoutCookie->assertSee('data-storefront-theme-toggle', false);
        $withoutCookie->assertDontSee('data-theme="dark"', false);

        // The cookie is what data-storefront-theme-toggle (runtime.js)
        // writes client-side via document.cookie — plain, never encrypted
        // (withCookie() would auto-encrypt, simulating a cookie the
        // SERVER had set, which is not this cookie's origin) — the server
        // must already honor it on the very first render, with no
        // client-side bootstrap script.
        $withCookie = $this->withUnencryptedCookie('storefront_theme', 'dark')->get('/pl')->assertOk();
        $withCookie->assertSee('data-theme="dark"', false);
    }

    public function test_account_deletion_pages_are_gated_and_post_to_the_deletion_endpoints(): void
    {
        $customer = CustomerAccount::query()->create(['email' => 'buyer@example.com', 'password' => 'CorrectPassword123!']);

        $this->get('/pl/account/delete')->assertRedirect('/pl/account/login');
        $this->actingAs($customer, 'customer')->get('/pl/account/delete')->assertOk()->assertSee('/customer/account/deletion', false);
        $this->actingAs($customer, 'customer')->get('/pl/account/orders')->assertOk()->assertSee('/pl/account/delete', false);
        $this->get('/pl/account/delete/'.str_repeat('a', 64))->assertOk()->assertSee('/customer/account/deletion/confirm', false);
        $this->get('/pl/account/delete/short')->assertNotFound();
    }

    public function test_consent_pages_and_checkboxes_render_in_the_default_theme(): void
    {
        $customer = CustomerAccount::query()->create(['email' => 'buyer@example.com', 'password' => 'CorrectPassword123!']);

        $this->get('/pl/account/register')->assertOk()->assertSee('name="marketing_consent"', false);
        $this->get('/pl/checkout')->assertOk()->assertSee('name="marketing_consent"', false);
        $this->get('/pl/account/preferences')->assertRedirect('/pl/account/login');
        $this->actingAs($customer, 'customer')->get('/pl/account/preferences')->assertOk()->assertSee('/customer/account/marketing-consent', false)->assertDontSee('checked', false);
        $this->get('/pl/marketing/unsubscribe/'.str_repeat('a', 40))->assertOk()->assertSee('/marketing/unsubscribe/'.str_repeat('a', 40), false);
    }
}
