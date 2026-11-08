<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/** Panel screen for Cms\Contracts\ArchetypeRegistry's registrants (Cms\Presentation\Controllers\ArchetypeTemplateController) — listing state and lazy row creation, see ArchetypePagesTest for the storefront-facing resolution behaviour. */
final class ArchetypeTemplateManagementTest extends TestCase
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

    public function test_the_index_lists_every_registered_archetype_as_using_the_theme_default(): void
    {
        $staff = StaffAccount::factory()->create();

        $response = $this->actingAs($staff)->get(route('panel.cms.archetypes.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('archetypes', 5)
            ->where('archetypes.0.key', 'category')
            ->where('archetypes.0.hasOverride', false)
            ->where('archetypes.1.key', 'brand')
            ->where('archetypes.1.hasOverride', false)
            ->where('archetypes.2.key', 'blog')
            ->where('archetypes.2.hasOverride', false)
            ->where('archetypes.3.key', 'blog_category')
            ->where('archetypes.3.hasOverride', false)
            ->where('archetypes.4.key', 'blog_post')
            ->where('archetypes.4.hasOverride', false));
    }

    public function test_opening_the_editor_lazily_creates_a_draft_row_that_does_not_count_as_an_override_yet(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->get(route('panel.cms.archetypes.edit', 'category'))->assertOk();

        $this->assertDatabaseHas('cms_pages', ['archetype' => 'category', 'status' => 'draft']);

        $response = $this->actingAs($staff)->get(route('panel.cms.archetypes.index'));
        $response->assertInertia(fn ($page) => $page->where('archetypes.0.hasOverride', false));
    }

    public function test_saving_publishes_immediately_and_marks_it_as_an_override_in_the_index(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->get(route('panel.cms.archetypes.edit', 'category'))->assertOk();

        $this->actingAs($staff)->put(route('panel.cms.archetypes.update', 'category'), [
            'root' => ['props' => ['title' => 'Szablon kategorii']],
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => 'Nadpisany', 'level' => '2']]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cms_pages', ['archetype' => 'category', 'status' => 'published']);
        $response = $this->actingAs($staff)->get(route('panel.cms.archetypes.index'));
        $response->assertInertia(fn ($page) => $page->where('archetypes.0.hasOverride', true));
    }

    public function test_restoring_default_deletes_the_row_not_just_unpublishes_it(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->get(route('panel.cms.archetypes.edit', 'category'))->assertOk();
        $this->actingAs($staff)->put(route('panel.cms.archetypes.update', 'category'), [
            'root' => ['props' => ['title' => 'Szablon kategorii']],
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => 'Nadpisany', 'level' => '2']]],
        ])->assertSessionHasNoErrors();
        $pageId = (int) DB::table('cms_pages')->where('archetype', 'category')->value('id');

        $this->actingAs($staff)->post(route('panel.cms.archetypes.restore', 'category'))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('cms_pages', ['id' => $pageId]);
        $this->assertDatabaseMissing('cms_page_translations', ['page_id' => $pageId]);
    }

    public function test_the_editor_exposes_context_and_general_dynamic_tags(): void
    {
        $staff = StaffAccount::factory()->create();

        $response = $this->actingAs($staff)->get(route('panel.cms.archetypes.edit', 'category'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('availableTags.category_name', 'Nazwa kategorii')
            ->has('generalTags'));
    }

    public function test_editing_an_unregistered_archetype_key_is_404(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->get(route('panel.cms.archetypes.edit', 'nie-istnieje'))->assertNotFound();
    }

    public function test_the_language_switcher_also_works_for_archetype_templates(): void
    {
        DB::table('languages')->insert(['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->get(route('panel.cms.archetypes.edit', 'category'))->assertOk();
        $this->actingAs($staff)->put(route('panel.cms.archetypes.update', 'category'), [
            'root' => ['props' => ['title' => 'Szablon kategorii']],
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => 'Polska wersja', 'level' => '2']]],
        ])->assertSessionHasNoErrors();

        $fallback = $this->actingAs($staff)->get(route('panel.cms.archetypes.edit', 'category').'?locale=en');
        $fallback->assertInertia(fn ($page) => $page->where('currentLocale', 'en')->where('isUntranslated', true));

        $this->actingAs($staff)->put(route('panel.cms.archetypes.update', 'category').'?locale=en', [
            'root' => ['props' => ['title' => 'Category template']],
            'content' => [['id' => 'a', 'type' => 'heading', 'props' => ['text' => 'English version', 'level' => '2']]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cms_page_translations', ['locale' => 'en', 'title' => 'Category template']);
        $pageId = (int) DB::table('cms_pages')->where('archetype', 'category')->value('id');
        $plTranslation = DB::table('cms_page_translations')->where('page_id', $pageId)->where('locale', 'pl')->first();
        $this->assertSame('Szablon kategorii', $plTranslation->title);
    }
}
