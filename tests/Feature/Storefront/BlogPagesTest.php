<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use FilesystemIterator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Blog\Contracts\BlogCatalog;
use Litecommerce\Modules\Cms\Contracts\ArchetypeRegistry;
use Litecommerce\Modules\Cms\Contracts\BlockRenderer;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Themes\Contracts\ThemeInstaller;
use Litecommerce\Modules\Themes\Providers\ThemesServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;
use ZipArchive;

final class BlogPagesTest extends TestCase
{
    use UsesIsolatedStorage;

    private StaffAccount $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->withCredentials();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);

        $zipPath = $this->zipDirectory(base_path('resources/themes/default-storefront'));
        app(ThemeInstaller::class)->installAndActivate($zipPath);
        @unlink($zipPath);
        (new ThemesServiceProvider($this->app))->boot();

        $this->staff = StaffAccount::factory()->create();
    }

    public function test_published_posts_render_under_the_blog_prefix_and_future_posts_stay_hidden(): void
    {
        $this->createPost('Widoczny wpis', 'widoczny-wpis', now()->subMinute()->format('Y-m-d H:i:s'));
        $this->createPost('Zaplanowany wpis', 'zaplanowany-wpis', now()->addHour()->format('Y-m-d H:i:s'));

        $this->get('/pl/blog')->assertOk()->assertSee('Widoczny wpis')->assertDontSee('Zaplanowany wpis');
        $this->get('/pl/blog/widoczny-wpis')->assertOk()->assertSee('Bezpieczna treść', false);
        $this->get('/pl/blog/zaplanowany-wpis')->assertNotFound();
    }

    public function test_featured_image_is_rendered_on_the_archive_and_post_page(): void
    {
        $postId = $this->createPost('Wpis z obrazem', 'wpis-z-obrazem');
        $this->actingAs($this->staff)->post(route('panel.blog.posts.featured-image.store', $postId), [
            'image' => UploadedFile::fake()->image('featured.jpg', 1200, 630),
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $this->get('/pl/blog')->assertOk()
            ->assertSee('class="blog-card__image"', false)
            ->assertSee('alt=""', false);
        $this->get('/pl/blog/wpis-z-obrazem')->assertOk()
            ->assertSee('class="blog-post__featured-image"', false)
            ->assertSee('alt="Wpis z obrazem"', false);
    }

    public function test_category_archive_uses_its_keyed_archetype_and_filters_posts(): void
    {
        $this->actingAs($this->staff)->post(route('panel.blog.categories.store'), [
            'locale' => 'pl', 'name' => 'Poradniki', 'slug' => 'poradniki', 'description' => 'Opis kategorii', 'seo_title' => 'Poradniki SEO', 'seo_description' => 'Opis kategorii SEO',
        ])->assertSessionHasNoErrors();
        $categoryId = (int) DB::table('blog_categories')->value('id');

        $this->createPost('W kategorii', 'w-kategorii', null, [$categoryId]);
        $this->createPost('Poza kategorią', 'poza-kategoria');

        $this->get('/pl/blog/category/poradniki')->assertOk()
            ->assertSee('<title>Poradniki SEO</title>', false)
            ->assertSee('<meta name="description" content="Opis kategorii SEO">', false)
            ->assertSee('Poradniki')
            ->assertSee('W kategorii')
            ->assertDontSee('Poza kategorią');
    }

    public function test_single_post_uses_the_blog_post_archetype_with_related_posts_and_categories_in_the_sidebar(): void
    {
        $this->actingAs($this->staff)->post(route('panel.blog.categories.store'), [
            'locale' => 'pl', 'name' => 'Poradniki', 'slug' => 'poradniki', 'description' => '', 'seo_title' => '', 'seo_description' => '',
        ])->assertSessionHasNoErrors();
        $categoryId = (int) DB::table('blog_categories')->value('id');

        $this->createPost('Bieżący wpis', 'biezacy-wpis', null, [$categoryId]);
        $this->createPost('Podobny wpis', 'podobny-wpis', null, [$categoryId]);
        $this->createPost('Niepowiązany wpis', 'niepowiazany-wpis');

        $this->get('/pl/blog/biezacy-wpis')->assertOk()
            ->assertSee('cms-container--ratio-2-1', false)
            ->assertSee('class="blog-post"', false)
            ->assertSee('Podobne posty')
            ->assertSee('Podobny wpis')
            ->assertDontSee('Niepowiązany wpis')
            ->assertSee('Kategorie bloga')
            ->assertSee('Poradniki');
    }

    public function test_blog_post_block_supports_carousel_layout_and_honours_the_limit(): void
    {
        $this->actingAs($this->staff)->post(route('panel.blog.categories.store'), [
            'locale' => 'pl', 'name' => 'Nowości', 'slug' => 'nowosci', 'description' => '', 'seo_title' => '', 'seo_description' => '',
        ])->assertSessionHasNoErrors();
        $categoryId = (int) DB::table('blog_categories')->value('id');
        $this->createPost('Baza', 'baza', null, [$categoryId]);
        $this->createPost('Starszy podobny', 'starszy-podobny', null, [$categoryId]);
        $this->createPost('Nowszy podobny', 'nowszy-podobny', null, [$categoryId]);

        $post = app(BlogCatalog::class)->findPublishedPostBySlug('baza', 'pl');
        $this->assertNotNull($post);
        $html = app(BlockRenderer::class)->render([
            'content' => [['type' => 'blog-post-list', 'props' => ['mode' => 'current', 'layout' => 'carousel', 'limit' => 1]]],
        ], 'pl', ['blog_post' => $post]);

        $this->assertStringContainsString('data-storefront-carousel', $html);
        $this->assertStringContainsString('Nowszy podobny', $html);
        $this->assertStringNotContainsString('Starszy podobny', $html);
    }

    public function test_blog_and_blog_categories_are_available_to_archetype_templates_and_menu_pickers(): void
    {
        $this->actingAs($this->staff)->post(route('panel.blog.categories.store'), [
            'locale' => 'pl', 'name' => 'Nowości', 'slug' => 'nowosci', 'description' => '', 'seo_title' => '', 'seo_description' => '',
        ])->assertSessionHasNoErrors();

        $registry = app(ArchetypeRegistry::class);
        $this->assertContains('blog', array_column($registry->all(), 'key'));
        $this->assertContains('blog_category', array_column($registry->all(), 'key'));
        $this->assertContains('blog_post', array_column($registry->all(), 'key'));
        $this->assertSame([['id' => 'index', 'label' => 'Blog']], $registry->listInstances('blog', 'pl'));
        $category = $registry->listInstances('blog_category', 'pl')[0];
        $this->assertSame('blog/category/nowosci', $registry->resolveInstance('blog_category', $category['id'], 'pl')['slug']);
    }

    public function test_an_older_active_theme_without_blog_show_inherits_the_default_view(): void
    {
        $directory = sys_get_temp_dir().'/lc-theme-before-blog-'.uniqid();
        mkdir($directory.'/views', 0777, true);
        file_put_contents($directory.'/manifest.json', json_encode([
            'id' => 'theme-before-blog',
            'name' => 'Theme before blog',
            'version' => '1.0.0',
            'type' => 'theme',
            'engine' => 'blade',
        ], JSON_THROW_ON_ERROR));
        file_put_contents($directory.'/views/layout.blade.php', '<html><body>Old theme @yield(\'content\')</body></html>');

        $zipPath = $this->zipDirectory($directory);
        app(ThemeInstaller::class)->installAndActivate($zipPath);
        @unlink($zipPath);
        (new ThemesServiceProvider($this->app))->boot();

        $this->createPost('Wpis w starszym motywie', 'wpis-w-starszym-motywie');

        $this->get('/pl/blog/wpis-w-starszym-motywie')->assertOk()
            ->assertSee('Old theme')
            ->assertSee('Wpis w starszym motywie');
    }

    /** @param list<int> $categoryIds */
    private function createPost(string $title, string $slug, ?string $publishAt = null, array $categoryIds = []): int
    {
        $this->actingAs($this->staff)->post(route('panel.blog.posts.store'), [
            'locale' => 'pl', 'title' => $title, 'slug' => $slug, 'excerpt' => 'Zajawka', 'content_html' => '<p>Bezpieczna treść</p>',
            'seo_title' => '', 'seo_description' => '', 'status' => 'published', 'publish_at' => $publishAt, 'category_ids' => $categoryIds,
        ])->assertSessionHasNoErrors();
        $postId = (int) DB::table('blog_posts')->max('id');
        $this->app['auth']->forgetGuards();

        return $postId;
    }

    private function zipDirectory(string $sourceDir): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'lc-theme-').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($sourceDir) + 1));
        }
        $zip->close();

        return $zipPath;
    }
}
