<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cms\Contracts\BlockRenderer;
use Litecommerce\Modules\Cms\Contracts\GlobalTagRegistry;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Litecommerce\Modules\Themes\Contracts\ThemeInstaller;
use Litecommerce\Modules\Themes\Providers\ThemesServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;
use ZipArchive;

/**
 * Cms\Infrastructure\ThemeBlockRenderer — resolves theme::blocks.{type},
 * skipping unknown types/render failures without breaking the rest of the
 * page (same resilience contract as ConfigDrivenThemeRegionComposer, see
 * docs/knowledge-base/09-presentation.md#cms-i-blog).
 */
final class BlockRendererTest extends TestCase
{
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

        $zipPath = $this->zipDirectory(base_path('resources/themes/default-storefront'));
        app(ThemeInstaller::class)->installAndActivate($zipPath);
        @unlink($zipPath);
        $this->rebootThemes();
    }

    public function test_it_renders_known_block_types_via_the_default_theme_partials(): void
    {
        $html = app(BlockRenderer::class)->render([
            'content' => [
                ['id' => 'a', 'type' => 'hero', 'props' => ['title' => 'Witaj w sklepie', 'subtitle' => 'Najlepsze produkty']],
                ['id' => 'b', 'type' => 'rich-text', 'props' => ['html' => '<p>Tresc CMS</p>']],
            ],
        ], 'pl');

        $this->assertStringContainsString('Witaj w sklepie', $html);
        $this->assertStringContainsString('Najlepsze produkty', $html);
        $this->assertStringContainsString('Tresc CMS', $html);
    }

    public function test_an_unknown_block_type_is_skipped_without_breaking_the_rest_of_the_page(): void
    {
        $html = app(BlockRenderer::class)->render([
            'content' => [
                ['id' => 'a', 'type' => 'does-not-exist', 'props' => []],
                ['id' => 'b', 'type' => 'rich-text', 'props' => ['html' => '<p>Nadal widoczne</p>']],
            ],
        ], 'pl');

        $this->assertStringContainsString('Nadal widoczne', $html);
    }

    public function test_a_theme_supplied_block_partial_overrides_the_default_theme_copy(): void
    {
        app(ThemeInstaller::class)->installAndActivate($this->stubThemeWithHeroOverrideZip('hero-override-stub'));
        $this->rebootThemes();

        $html = app(BlockRenderer::class)->render([
            'content' => [
                ['id' => 'a', 'type' => 'hero', 'props' => ['title' => 'Domyslny tytul']],
            ],
        ], 'pl');

        $this->assertStringContainsString('Hero z motywu niestandardowego', $html);
        $this->assertStringNotContainsString('Domyslny tytul', $html);
    }

    public function test_product_grid_manual_mode_resolves_published_products_via_catalog_and_pricing(): void
    {
        $product = $this->createDigitalProduct('Ebook w siatce', '39.00');

        $html = app(BlockRenderer::class)->render([
            'content' => [
                ['id' => 'a', 'type' => 'product-grid', 'props' => [
                    'mode' => 'manual',
                    'product_ids' => [['id' => $product['productId'], 'name' => 'Ebook w siatce']],
                    'limit' => 8,
                ]],
            ],
        ], 'pl');

        $this->assertStringContainsString('Ebook w siatce', $html);
        $this->assertStringContainsString('39.00', $html);
    }

    public function test_container_block_renders_a_nested_block_inside_each_column(): void
    {
        $html = app(BlockRenderer::class)->render([
            'content' => [
                ['id' => 'a', 'type' => 'container', 'props' => [
                    'layout' => 'grid', 'columns' => '2', 'gap' => 'md',
                    'column_1' => [['id' => 'c1', 'type' => 'rich-text', 'props' => ['html' => '<p>Kolumna pierwsza</p>']]],
                    'column_2' => [['id' => 'c2', 'type' => 'rich-text', 'props' => ['html' => '<p>Kolumna druga</p>']]],
                ]],
            ],
        ], 'pl');

        $this->assertStringContainsString('cms-container--grid', $html);
        $this->assertStringContainsString('Kolumna pierwsza', $html);
        $this->assertStringContainsString('Kolumna druga', $html);
    }

    public function test_a_container_can_be_nested_inside_another_container(): void
    {
        $html = app(BlockRenderer::class)->render([
            'content' => [
                ['id' => 'outer', 'type' => 'container', 'props' => [
                    'layout' => 'flex', 'columns' => '1', 'gap' => 'md',
                    'column_1' => [[
                        'id' => 'inner', 'type' => 'container', 'props' => [
                            'layout' => 'grid', 'columns' => '2', 'gap' => 'sm',
                            'column_1' => [['id' => 'nested', 'type' => 'rich-text', 'props' => ['html' => '<p>Zagniezdzona tresc</p>']]],
                        ],
                    ]],
                ]],
            ],
        ], 'pl');

        $this->assertStringContainsString('Zagniezdzona tresc', $html);
        $this->assertSame(2, substr_count($html, 'cms-container '), 'expected two nested cms-container wrappers');
    }

    public function test_a_block_can_be_set_to_full_width_or_contained(): void
    {
        $full = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'rich-text', 'props' => ['html' => '<p>x</p>', 'width' => 'full']]],
        ], 'pl');
        $contained = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'rich-text', 'props' => ['html' => '<p>x</p>']]],
        ], 'pl');

        $this->assertStringContainsString('cms-block--full', $full);
        $this->assertStringContainsString('cms-block--contained', $contained);
    }

    public function test_heading_block_renders_the_chosen_level(): void
    {
        $html = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => 'Sekcja', 'level' => '3']]],
        ], 'pl');

        $this->assertStringContainsString('<h3 class="cms-heading">Sekcja</h3>', $html);
    }

    public function test_heading_block_allows_h1(): void
    {
        $html = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => 'Kategoria', 'level' => '1']]],
        ], 'pl');

        $this->assertStringContainsString('<h1 class="cms-heading">Kategoria</h1>', $html);
    }

    public function test_dynamic_tags_are_interpolated_into_any_blocks_text_props(): void
    {
        $html = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => 'Kategoria: {{category_name}}', 'level' => '1']]],
        ], 'pl', ['tags' => ['category_name' => 'Buty']]);

        $this->assertStringContainsString('Kategoria: Buty', $html);
    }

    public function test_an_unrecognised_tag_resolves_to_empty_string_not_literal_braces(): void
    {
        $html = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => 'X{{nieznany_tag}}Y', 'level' => '2']]],
        ], 'pl', ['tags' => ['category_name' => 'Buty']]);

        $this->assertStringContainsString('XY', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_dynamic_tags_are_interpolated_inside_a_containers_nested_columns(): void
    {
        $html = app(BlockRenderer::class)->render([
            'content' => [['id' => 'outer', 'type' => 'container', 'props' => [
                'layout' => 'flex', 'columns' => '1', 'gap' => 'md',
                'column_1' => [['id' => 'inner', 'type' => 'rich-text', 'props' => ['html' => '<p>{{brand_name}}</p>']]],
            ]]],
        ], 'pl', ['tags' => ['brand_name' => 'Nordic Home']]);

        $this->assertStringContainsString('Nordic Home', $html);
    }

    public function test_unknown_archetype_tags_are_empty_on_plain_pages_once_global_tags_are_available(): void
    {
        $html = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => '{{category_name}}', 'level' => '2']]],
        ], 'pl');

        $this->assertStringNotContainsString('{{category_name}}', $html);
    }

    public function test_a_registered_global_tag_resolves_even_on_a_plain_page(): void
    {
        app(GlobalTagRegistry::class)->register('store_name', 'Nazwa sklepu', fn () => 'Litecommerce');

        $html = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => 'Witamy w {{store_name}}', 'level' => '2']]],
        ], 'pl');

        $this->assertStringContainsString('Witamy w Litecommerce', $html);
    }

    public function test_an_archetype_tag_wins_over_a_global_tag_with_the_same_name(): void
    {
        app(GlobalTagRegistry::class)->register('title', 'Global title', fn () => 'Wartość globalna');

        $html = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => '{{title}}', 'level' => '2']]],
        ], 'pl', ['tags' => ['title' => 'Wartość kontekstu']]);

        $this->assertStringContainsString('Wartość kontekstu', $html);
        $this->assertStringNotContainsString('Wartość globalna', $html);
    }

    public function test_list_block_renders_ordered_and_unordered_items(): void
    {
        $ordered = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'list', 'props' => ['style' => 'ordered', 'items' => [['text' => 'Raz'], ['text' => 'Dwa']]]]],
        ], 'pl');

        $this->assertStringContainsString('<ol class="cms-list">', $ordered);
        $this->assertStringContainsString('<li>Raz</li>', $ordered);
        $this->assertStringContainsString('<li>Dwa</li>', $ordered);
    }

    public function test_button_block_renders_a_link_with_the_chosen_style(): void
    {
        $html = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'button', 'props' => ['label' => 'Kup teraz', 'url' => '/products', 'style' => 'secondary', 'open_in_new_tab' => '_blank']]],
        ], 'pl');

        $this->assertStringContainsString('btn btn-secondary', $html);
        $this->assertStringContainsString('href="/products"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('Kup teraz', $html);
    }

    public function test_video_block_embeds_a_recognised_youtube_url_and_skips_an_unrecognised_one(): void
    {
        $recognised = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'video', 'props' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']]],
        ], 'pl');
        $unrecognised = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'video', 'props' => ['url' => 'https://example.com/not-youtube']]],
        ], 'pl');

        $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $recognised);
        $this->assertStringNotContainsString('<iframe', $unrecognised);
    }

    public function test_quote_block_renders_text_and_optional_citation(): void
    {
        $html = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'quote', 'props' => ['text' => 'Świetny sklep', 'citation' => 'Klient']]],
        ], 'pl');

        $this->assertStringContainsString('cms-quote', $html);
        $this->assertStringContainsString('Świetny sklep', $html);
        $this->assertStringContainsString('Klient', $html);
    }

    public function test_separator_and_spacer_blocks_render(): void
    {
        $html = app(BlockRenderer::class)->render([
            'content' => [
                ['id' => 'a', 'type' => 'separator', 'props' => []],
                ['id' => 'b', 'type' => 'spacer', 'props' => ['height' => 'lg']],
            ],
        ], 'pl');

        $this->assertStringContainsString('cms-separator', $html);
        $this->assertStringContainsString('cms-spacer cms-spacer--lg', $html);
    }

    public function test_image_banner_block_renders_an_optional_caption(): void
    {
        $media = app(MediaLibrary::class)->store(
            contents: 'fake-image-bytes',
            originalFilename: 'banner.png',
            mimeType: 'image/png',
        );

        $html = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'image-banner', 'props' => ['image' => ['media_id' => $media->id], 'caption' => 'Podpis zdjęcia']]],
        ], 'pl');

        $this->assertStringContainsString('Podpis zdjęcia', $html);
        $this->assertStringContainsString('<figcaption>', $html);

        // No image resolved (no media_id at all) — block renders nothing, caption never appears without an image.
        $withoutImage = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'image-banner', 'props' => ['caption' => 'Podpis zdjęcia']]],
        ], 'pl');
        $this->assertStringNotContainsString('Podpis zdjęcia', $withoutImage);
    }

    public function test_archive_heading_block_reads_title_and_description_from_render_context(): void
    {
        $withDescription = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'archive-heading', 'props' => ['show_description' => '1']]],
        ], 'pl', ['title' => 'Buty sportowe', 'description' => '<p>Opis kategorii</p>']);
        $withoutContext = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'archive-heading', 'props' => ['show_description' => '1']]],
        ], 'pl');

        $this->assertStringContainsString('Buty sportowe', $withDescription);
        $this->assertStringContainsString('Opis kategorii', $withDescription);
        $this->assertStringNotContainsString('cms-archive-heading', $withoutContext, 'no title in context renders nothing, never a broken heading');
    }

    public function test_product_grid_current_mode_resolves_from_context_and_is_empty_outside_one(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.references.categories.store'), [
            'locale' => 'pl', 'name' => 'Buty', 'parent_id' => null, 'visible_in_tree' => true,
        ])->assertSessionHasNoErrors();
        $categoryId = (int) DB::table('categories')->value('id');
        $this->app['auth']->forgetGuards();

        $product = $this->createDigitalProduct('Produkt w archiwum kategorii', '19.00', $categoryId);

        $inCategoryContext = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'product-grid', 'props' => ['mode' => 'current', 'limit' => 8]]],
        ], 'pl', ['category_id' => $categoryId]);
        $outsideAnyContext = app(BlockRenderer::class)->render([
            'content' => [['id' => 'a', 'type' => 'product-grid', 'props' => ['mode' => 'current', 'limit' => 8]]],
        ], 'pl');

        $this->assertStringContainsString('Produkt w archiwum kategorii', $inCategoryContext);
        $this->assertStringNotContainsString('product-card', $outsideAnyContext);
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

    private function stubThemeWithHeroOverrideZip(string $id): string
    {
        $dir = sys_get_temp_dir().'/lc-hero-override-theme-'.uniqid();
        mkdir($dir.'/views/blocks', 0777, true);
        file_put_contents($dir.'/manifest.json', json_encode([
            'id' => $id, 'name' => 'Motyw z niestandardowym hero', 'version' => '1.0.0', 'type' => 'theme', 'engine' => 'blade',
        ], JSON_THROW_ON_ERROR));
        file_put_contents($dir.'/views/blocks/hero.blade.php', <<<'BLADE'
            <section class="cms-hero-override">Hero z motywu niestandardowego</section>
            BLADE);

        return $this->zipDirectory($dir);
    }

    /** @return array{productId:int, variantId:int, slug:string} */
    private function createDigitalProduct(string $name, string $priceAmount, ?int $categoryId = null): array
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => $name, 'short_description' => 'Opis krotki', 'description_html' => '<p>Opis</p>',
            'type' => 'digital', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '',
            'category_ids' => $categoryId === null ? [] : [$categoryId],
            'primary_category_id' => $categoryId === null ? '' : $categoryId,
        ])->assertSessionHasNoErrors();

        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->where('product_id', $productId)->value('id');
        $slug = (string) DB::table('product_translations')->where('product_id', $productId)->value('slug');

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), [
            'regular_amount' => $priceAmount, 'regular_mode' => 'gross',
        ])->assertSessionHasNoErrors();

        $this->app['auth']->forgetGuards();

        return ['productId' => $productId, 'variantId' => $variantId, 'slug' => $slug];
    }
}
