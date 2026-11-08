<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class BlogManagementTest extends TestCase
{
    use UsesIsolatedStorage;

    private StaffAccount $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->staff = StaffAccount::factory()->create();
    }

    public function test_staff_can_create_a_category_and_a_sanitized_scheduled_post(): void
    {
        $this->actingAs($this->staff)->post(route('panel.blog.categories.store'), [
            'locale' => 'pl', 'name' => 'Poradniki', 'slug' => 'poradniki', 'description' => 'Praktyczne poradniki',
            'seo_title' => '', 'seo_description' => '',
        ])->assertSessionHasNoErrors();
        $categoryId = (int) DB::table('blog_categories')->value('id');

        $this->actingAs($this->staff)->post(route('panel.blog.posts.store'), [
            'locale' => 'pl', 'title' => 'Pierwszy wpis', 'slug' => 'pierwszy-wpis', 'excerpt' => 'Zajawka',
            'content_html' => '<p onclick="bad()"><strong>Bezpieczna treść</strong><script>bad()</script></p>',
            'seo_title' => 'Pierwszy wpis SEO', 'seo_description' => 'Opis SEO',
            'status' => 'published', 'publish_at' => now()->addHour()->format('Y-m-d H:i:s'), 'category_ids' => [$categoryId],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('blog_posts', ['status' => 'published']);
        $this->assertDatabaseHas('blog_category_post', ['category_id' => $categoryId]);
        $html = (string) DB::table('blog_post_translations')->value('content_html');
        $this->assertStringContainsString('<strong>Bezpieczna treść</strong>', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('onclick', $html);

        $response = $this->actingAs($this->staff)->get(route('panel.blog.posts.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('posts.data.0.status', 'scheduled'));
    }

    public function test_slug_uniqueness_is_scoped_to_locale_and_ignores_the_edited_translation(): void
    {
        $payload = [
            'locale' => 'pl', 'title' => 'Wpis A', 'slug' => 'wpis-a', 'excerpt' => '', 'content_html' => '<p>Treść</p>',
            'seo_title' => '', 'seo_description' => '', 'status' => 'draft', 'publish_at' => '', 'category_ids' => [],
        ];
        $this->actingAs($this->staff)->post(route('panel.blog.posts.store'), $payload)->assertSessionHasNoErrors();
        $postId = (int) DB::table('blog_posts')->value('id');

        $this->actingAs($this->staff)->put(route('panel.blog.posts.update', $postId), $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->post(route('panel.blog.posts.store'), [...$payload, 'title' => 'Wpis B'])->assertSessionHasErrors('slug');
    }

    public function test_changing_the_slug_of_a_published_post_flashes_a_redirect_suggestion(): void
    {
        $payload = [
            'locale' => 'pl', 'title' => 'Wpis', 'slug' => 'stary-wpis', 'excerpt' => '', 'content_html' => '<p>Treść</p>',
            'seo_title' => '', 'seo_description' => '', 'status' => 'published', 'publish_at' => '', 'category_ids' => [],
        ];
        $this->actingAs($this->staff)->post(route('panel.blog.posts.store'), $payload)->assertSessionHasNoErrors();
        $postId = (int) DB::table('blog_posts')->value('id');

        $response = $this->actingAs($this->staff)->put(route('panel.blog.posts.update', $postId), [...$payload, 'slug' => 'nowy-wpis']);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('redirectSuggestion', ['locale' => 'pl', 'from_path' => '/blog/stary-wpis', 'to_path' => '/blog/nowy-wpis']);
    }

    public function test_changing_the_slug_of_a_draft_post_does_not_flash_a_redirect_suggestion(): void
    {
        $payload = [
            'locale' => 'pl', 'title' => 'Wpis', 'slug' => 'stary-wpis', 'excerpt' => '', 'content_html' => '<p>Treść</p>',
            'seo_title' => '', 'seo_description' => '', 'status' => 'draft', 'publish_at' => '', 'category_ids' => [],
        ];
        $this->actingAs($this->staff)->post(route('panel.blog.posts.store'), $payload)->assertSessionHasNoErrors();
        $postId = (int) DB::table('blog_posts')->value('id');

        $response = $this->actingAs($this->staff)->put(route('panel.blog.posts.update', $postId), [...$payload, 'slug' => 'nowy-wpis']);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('redirectSuggestion');
    }

    public function test_changing_the_slug_of_a_blog_category_flashes_a_redirect_suggestion(): void
    {
        $this->actingAs($this->staff)->post(route('panel.blog.categories.store'), [
            'locale' => 'pl', 'name' => 'Poradniki', 'slug' => 'stara-kategoria', 'description' => '', 'seo_title' => '', 'seo_description' => '',
        ])->assertSessionHasNoErrors();
        $categoryId = (int) DB::table('blog_categories')->value('id');

        $response = $this->actingAs($this->staff)->put(route('panel.blog.categories.update', $categoryId), [
            'locale' => 'pl', 'name' => 'Poradniki', 'slug' => 'nowa-kategoria', 'description' => '', 'seo_title' => '', 'seo_description' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('redirectSuggestion', ['locale' => 'pl', 'from_path' => '/blog/category/stara-kategoria', 'to_path' => '/blog/category/nowa-kategoria']);
    }

    public function test_staff_can_upload_and_remove_a_featured_image(): void
    {
        $this->actingAs($this->staff)->post(route('panel.blog.posts.store'), [
            'locale' => 'pl', 'title' => 'Wpis z obrazem', 'slug' => 'wpis-z-obrazem', 'excerpt' => '', 'content_html' => '<p>Treść</p>',
            'seo_title' => '', 'seo_description' => '', 'status' => 'draft', 'publish_at' => '', 'category_ids' => [],
        ])->assertSessionHasNoErrors();
        $postId = (int) DB::table('blog_posts')->value('id');

        $this->actingAs($this->staff)->post(route('panel.blog.posts.featured-image.store', $postId), [
            'image' => UploadedFile::fake()->image('featured.jpg', 1200, 630),
        ])->assertSessionHasNoErrors();

        $mediaId = DB::table('blog_posts')->where('id', $postId)->value('featured_media_id');
        $this->assertNotNull($mediaId);
        $this->assertDatabaseHas('media', ['id' => $mediaId, 'alt_text' => 'Wpis z obrazem']);
        $this->actingAs($this->staff)->get(route('panel.blog.posts.edit', $postId))
            ->assertInertia(fn ($page) => $page->where('post.featured_image_url', fn ($url): bool => is_string($url) && str_contains($url, '/storage/catalog/')));

        $this->actingAs($this->staff)->delete(route('panel.blog.posts.featured-image.destroy', $postId))->assertSessionHasNoErrors();
        $this->assertNull(DB::table('blog_posts')->where('id', $postId)->value('featured_media_id'));
    }

    public function test_missing_resources_redirect_to_their_listing_with_a_plain_message(): void
    {
        $this->actingAs($this->staff)->get(route('panel.blog.posts.edit', 999999))
            ->assertRedirect(route('panel.blog.posts.index'))
            ->assertSessionHas('error', trans('blog::flash.post_not_found'));

        $this->actingAs($this->staff)->put(route('panel.blog.categories.update', 999999), [
            'locale' => 'pl', 'name' => 'Brak', 'slug' => 'brak', 'description' => '', 'seo_title' => '', 'seo_description' => '',
        ])->assertRedirect(route('panel.blog.categories.index'))
            ->assertSessionHas('error', trans('blog::flash.category_not_found'));
    }
}
