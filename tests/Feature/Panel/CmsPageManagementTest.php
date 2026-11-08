<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/** Panel CRUD for Cms\Infrastructure\Models\CmsPage — content editing and homepage exclusivity, see docs/knowledge-base/09-presentation.md#cms-i-blog. */
final class CmsPageManagementTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();

        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_an_unknown_page_redirects_to_the_listing_with_an_error(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->get(route('panel.cms.pages.edit', 999999))
            ->assertRedirect(route('panel.cms.pages.index'))
            ->assertSessionHas('error');
    }

    public function test_the_page_builder_save_action_persists_metadata_and_content_together(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'o-nas', 'title' => 'O nas',
            'seo_title' => 'O nas — sklep', 'seo_description' => 'Poznaj nasz sklep.', 'status' => 'draft',
        ])->assertSessionHasNoErrors();

        $pageId = (int) DB::table('cms_pages')->latest('id')->value('id');
        $this->assertSame('draft', DB::table('cms_pages')->where('id', $pageId)->value('status'));

        // Single request from the page builder's "Publish" button — root.props
        // (this project's title/slug/SEO/status fields) and content (Puck's
        // own block list) saved together, see PageController::save().
        $content = [
            ['id' => 'a', 'type' => 'rich-text', 'props' => ['html' => '<p>Tresc</p>']],
        ];
        $this->actingAs($staff)->put(route('panel.cms.pages.update', $pageId), [
            'root' => ['props' => [
                'title' => 'O nas — nowy tytuł',
                'slug' => 'o-nas-2',
                'seo_title' => 'Nowy tytuł SEO',
                'seo_description' => 'Nowy opis SEO.',
                'status' => 'published',
            ]],
            'content' => $content,
        ])->assertSessionHasNoErrors();

        $page = DB::table('cms_pages')->where('id', $pageId)->first();
        $this->assertSame('published', $page->status);

        $translation = DB::table('cms_page_translations')->where('page_id', $pageId)->first();
        $this->assertSame('o-nas-2', $translation->slug);
        $this->assertSame('O nas — nowy tytuł', $translation->title);
        $this->assertSame('Nowy tytuł SEO', $translation->seo_title);
        $this->assertSame('Nowy opis SEO.', $translation->seo_description);
        $this->assertSame(['content' => $content], json_decode((string) $translation->content, true));
    }

    public function test_changing_the_slug_of_an_already_published_page_flashes_a_redirect_suggestion(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'stara-nazwa', 'title' => 'Strona',
            'seo_title' => '', 'seo_description' => '', 'status' => 'published',
        ])->assertSessionHasNoErrors();
        $pageId = (int) DB::table('cms_pages')->latest('id')->value('id');

        $response = $this->actingAs($staff)->put(route('panel.cms.pages.update', $pageId), [
            'root' => ['props' => ['title' => 'Strona', 'slug' => 'nowa-nazwa', 'seo_title' => '', 'seo_description' => '', 'status' => 'published']],
            'content' => [],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('redirectSuggestion', ['locale' => 'pl', 'from_path' => '/stara-nazwa', 'to_path' => '/nowa-nazwa']);
    }

    public function test_changing_the_slug_of_a_draft_page_does_not_flash_a_redirect_suggestion(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'stara-nazwa', 'title' => 'Strona',
            'seo_title' => '', 'seo_description' => '', 'status' => 'draft',
        ])->assertSessionHasNoErrors();
        $pageId = (int) DB::table('cms_pages')->latest('id')->value('id');

        $response = $this->actingAs($staff)->put(route('panel.cms.pages.update', $pageId), [
            'root' => ['props' => ['title' => 'Strona', 'slug' => 'nowa-nazwa', 'seo_title' => '', 'seo_description' => '', 'status' => 'draft']],
            'content' => [],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('redirectSuggestion');
    }

    public function test_saving_a_published_page_without_changing_its_slug_does_not_flash_a_redirect_suggestion(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'stala-nazwa', 'title' => 'Strona',
            'seo_title' => '', 'seo_description' => '', 'status' => 'published',
        ])->assertSessionHasNoErrors();
        $pageId = (int) DB::table('cms_pages')->latest('id')->value('id');

        $response = $this->actingAs($staff)->put(route('panel.cms.pages.update', $pageId), [
            'root' => ['props' => ['title' => 'Strona 2', 'slug' => 'stala-nazwa', 'seo_title' => '', 'seo_description' => '', 'status' => 'published']],
            'content' => [],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('redirectSuggestion');
    }

    public function test_the_editor_exposes_general_dynamic_tags_but_no_context_ones(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'o-nas', 'title' => 'O nas',
            'seo_title' => '', 'seo_description' => '', 'status' => 'draft',
        ])->assertSessionHasNoErrors();
        $pageId = (int) DB::table('cms_pages')->latest('id')->value('id');

        $response = $this->actingAs($staff)->get(route('panel.cms.pages.edit', $pageId));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('generalTags')->missing('availableTags'));
    }

    public function test_setting_a_new_homepage_unsets_the_previous_one(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'strona-a', 'title' => 'Strona A',
            'seo_title' => '', 'seo_description' => '', 'status' => 'published',
        ])->assertSessionHasNoErrors();
        $pageA = (int) DB::table('cms_pages')->latest('id')->value('id');

        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'strona-b', 'title' => 'Strona B',
            'seo_title' => '', 'seo_description' => '', 'status' => 'published',
        ])->assertSessionHasNoErrors();
        $pageB = (int) DB::table('cms_pages')->latest('id')->value('id');

        $this->actingAs($staff)->put(route('panel.cms.pages.homepage.update', $pageA))->assertSessionHasNoErrors();
        $this->assertTrue((bool) DB::table('cms_pages')->where('id', $pageA)->value('is_homepage'));

        $this->actingAs($staff)->put(route('panel.cms.pages.homepage.update', $pageB))->assertSessionHasNoErrors();
        $this->assertFalse((bool) DB::table('cms_pages')->where('id', $pageA)->value('is_homepage'));
        $this->assertTrue((bool) DB::table('cms_pages')->where('id', $pageB)->value('is_homepage'));
    }

    public function test_a_slug_must_be_unique_per_locale(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'kontakt', 'title' => 'Kontakt',
            'seo_title' => '', 'seo_description' => '', 'status' => 'draft',
        ])->assertSessionHasNoErrors();

        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'kontakt', 'title' => 'Kontakt 2',
            'seo_title' => '', 'seo_description' => '', 'status' => 'draft',
        ])->assertSessionHasErrors('slug');
    }

    public function test_the_page_builder_save_action_rejects_a_slug_already_used_by_another_page(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'strona-a', 'title' => 'Strona A',
            'seo_title' => '', 'seo_description' => '', 'status' => 'draft',
        ])->assertSessionHasNoErrors();

        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'strona-b', 'title' => 'Strona B',
            'seo_title' => '', 'seo_description' => '', 'status' => 'draft',
        ])->assertSessionHasNoErrors();
        $pageB = (int) DB::table('cms_pages')->latest('id')->value('id');

        $this->actingAs($staff)->put(route('panel.cms.pages.update', $pageB), [
            'root' => ['props' => ['title' => 'Strona B', 'slug' => 'strona-a', 'seo_title' => '', 'seo_description' => '', 'status' => 'draft']],
            'content' => [],
        ])->assertSessionHasErrors('root.props.slug');

        // Saving page B with its OWN unchanged slug must still work (ignore-self, not a false collision).
        $this->actingAs($staff)->put(route('panel.cms.pages.update', $pageB), [
            'root' => ['props' => ['title' => 'Strona B', 'slug' => 'strona-b', 'seo_title' => '', 'seo_description' => '', 'status' => 'draft']],
            'content' => [],
        ])->assertSessionHasNoErrors();
    }

    public function test_archiving_a_page_unsets_it_as_homepage(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'strona-glowna', 'title' => 'Strona glowna',
            'seo_title' => '', 'seo_description' => '', 'status' => 'published',
        ])->assertSessionHasNoErrors();
        $pageId = (int) DB::table('cms_pages')->latest('id')->value('id');
        $this->actingAs($staff)->put(route('panel.cms.pages.homepage.update', $pageId))->assertSessionHasNoErrors();

        $this->actingAs($staff)->post(route('panel.cms.pages.archive', $pageId))->assertSessionHasNoErrors();

        $page = DB::table('cms_pages')->where('id', $pageId)->first();
        $this->assertSame('archived', $page->status);
        $this->assertFalse((bool) $page->is_homepage);
    }
}
