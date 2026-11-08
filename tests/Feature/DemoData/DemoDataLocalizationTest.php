<?php

declare(strict_types=1);

namespace Tests\Feature\DemoData;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Store\Application\SetBaseCurrency;
use Litecommerce\Modules\Store\Application\SetDefaultLanguage;
use Litecommerce\Modules\Warehouse\Contracts\WarehouseProvisioner;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class DemoDataLocalizationTest extends TestCase
{
    use UsesIsolatedStorage;

    private const GROUPS = ['catalog::demo', 'cms::demo', 'blog::demo', 'media::demo', 'warehouse::demo'];

    private string $previousMemoryLimit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->previousMemoryLimit = (string) ini_get('memory_limit');
        ini_set('memory_limit', '256M');
        $this->artisan('migrate', ['--force' => true]);
        app(WarehouseProvisioner::class)->ensureDefaultWarehouseExists();
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->previousMemoryLimit);
        parent::tearDown();
    }

    public function test_every_group_has_the_same_keys_in_both_core_languages(): void
    {
        foreach (self::GROUPS as $group) {
            $this->assertSame(
                $this->shape(trans($group, [], 'pl')),
                $this->shape(trans($group, [], 'en')),
                "The demo content group {$group} differs between pl and en.",
            );
        }
    }

    public function test_the_english_set_contains_no_polish_text(): void
    {
        foreach (self::GROUPS as $group) {
            $this->assertDoesNotMatchRegularExpression('/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/u', json_encode(trans($group, [], 'en'), JSON_UNESCAPED_UNICODE), $group);
        }
    }

    public function test_an_english_store_gets_english_demo_content_and_the_same_structure_as_a_polish_one(): void
    {
        app(SetDefaultLanguage::class)->handle('en', 'English');
        app(SetBaseCurrency::class)->handle('USD', 'US dollar', '$', 2);

        $this->artisan('demo-data:seed')->assertSuccessful();

        $this->assertSame(18, DB::table('products')->count());
        $this->assertSame(30, DB::table('product_variants')->count());
        $this->assertSame(9, DB::table('cms_pages')->count());
        $this->assertSame(12, DB::table('blog_posts')->count());
        $this->assertSame(6, DB::table('blog_categories')->count());
        $this->assertSame(['en'], DB::table('product_translations')->distinct()->pluck('locale')->all());
        $this->assertDatabaseHas('product_translations', ['name' => 'Fjord Ceramic Mug']);
        $this->assertDatabaseHas('variant_translations', ['name' => 'Fjord Ceramic Mug — 250 ml']);
        $this->assertDatabaseHas('cms_page_translations', ['slug' => 'about-us', 'locale' => 'en']);
        $this->assertDatabaseHas('blog_post_translations', ['slug' => 'how-to-care-for-ceramics', 'locale' => 'en']);
        $this->assertDatabaseHas('media', ['alt_text' => 'Ceramic mug in a natural glaze']);
        $this->assertDatabaseHas('menus', ['name' => 'Demo menu']);
        $this->assertSame(0, DB::table('variant_translations')->where('name', 'like', '%Wariant%')->count());
        $this->assertSame(0, DB::table('variant_translations')->where('description_html', 'like', '%Najlepsze zastosowanie%')->count());
        $this->assertSame(0, DB::table('blog_post_translations')->where('content_html', 'like', '%Na koniec%')->count());

        $this->artisan('demo-data:remove', ['--force' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('products')->count());
        $this->assertSame([], Storage::disk('public')->allFiles('catalog'));
    }

    public function test_a_polish_store_still_gets_polish_content(): void
    {
        app(SetDefaultLanguage::class)->handle('pl', 'Polski');
        app(SetBaseCurrency::class)->handle('PLN', 'Polski złoty', 'zł', 2);

        $this->artisan('demo-data:seed')->assertSuccessful();

        $this->assertDatabaseHas('product_translations', ['name' => 'Kubek ceramiczny Fjord']);
        $this->assertDatabaseHas('cms_page_translations', ['slug' => 'o-nas', 'locale' => 'pl']);
        $this->assertDatabaseHas('menus', ['name' => 'Menu demonstracyjne']);
    }

    public function test_a_language_without_its_own_files_falls_back_to_english_content(): void
    {
        app(SetDefaultLanguage::class)->handle('de', 'Deutsch');
        app(SetBaseCurrency::class)->handle('EUR', 'Euro', '€', 2);

        $this->artisan('demo-data:seed')->assertSuccessful();

        $this->assertDatabaseHas('product_translations', ['name' => 'Fjord Ceramic Mug', 'locale' => 'de']);
    }

    /** @return array<int|string, mixed> */
    private function shape(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $shape = [];
        foreach ($value as $key => $item) {
            $shape[is_int($key) ? $key : (string) $key] = is_array($item) ? $this->shape($item) : gettype($item);
        }

        return $shape;
    }
}
