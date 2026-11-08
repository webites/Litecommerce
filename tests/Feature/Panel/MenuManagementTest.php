<?php

declare(strict_types=1);

namespace Tests\Feature\Panel;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/** Panel screen for the WordPress-style menu builder (Cms\Presentation\Controllers\MenuController) — see StorefrontNavMenuTest for the storefront-facing rendering behaviour. */
final class MenuManagementTest extends TestCase
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

    public function test_visiting_the_index_never_auto_creates_a_menu(): void
    {
        // The menu is entirely merchant-configured in the panel — no code-level
        // default that guesses at "products, cart, a language switcher" etc.
        $staff = StaffAccount::factory()->create();

        $response = $this->actingAs($staff)->get(route('panel.cms.menus.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('menus', 0));
        $this->assertSame(0, DB::table('menus')->count());
    }

    public function test_creating_a_menu(): void
    {
        $staff = StaffAccount::factory()->create();

        $response = $this->actingAs($staff)->post(route('panel.cms.menus.store'), ['name' => 'Stopka']);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('menus', ['name' => 'Stopka', 'location_id' => null]);
    }

    public function test_assigning_a_location_already_held_by_another_menu_silently_takes_it_away(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.cms.menus.store'), ['name' => 'Pierwsze']);
        $first = (int) DB::table('menus')->where('name', 'Pierwsze')->value('id');
        $this->actingAs($staff)->put(route('panel.cms.menus.update', $first), ['name' => 'Pierwsze', 'locationId' => 'footer']);

        $this->actingAs($staff)->post(route('panel.cms.menus.store'), ['name' => 'Drugie']);
        $second = (int) DB::table('menus')->where('name', 'Drugie')->value('id');
        $this->actingAs($staff)->put(route('panel.cms.menus.update', $second), ['name' => 'Drugie', 'locationId' => 'footer'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('menus', ['id' => $first, 'location_id' => null]);
        $this->assertDatabaseHas('menus', ['id' => $second, 'location_id' => 'footer']);
    }

    public function test_saving_items_persists_a_nested_tree(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.cms.menus.store'), ['name' => 'Stopka']);
        $menuId = (int) DB::table('menus')->where('name', 'Stopka')->value('id');

        $tree = [
            ['id' => 'a', 'type' => 'custom', 'url' => 'https://example.com', 'labelOverride' => 'Zewnętrzny', 'referenceKey' => null, 'referenceId' => null, 'targetBlank' => true, 'children' => [
                ['id' => 'b', 'type' => 'widget', 'referenceKey' => 'cart', 'referenceId' => null, 'url' => null, 'labelOverride' => null, 'targetBlank' => false, 'children' => []],
            ]],
        ];

        $response = $this->actingAs($staff)->put(route('panel.cms.menus.items.update', $menuId), ['tree' => $tree]);

        $response->assertSessionHasNoErrors();
        $stored = json_decode((string) DB::table('menus')->where('id', $menuId)->value('content'), true);
        $this->assertSame('custom', $stored[0]['type']);
        $this->assertSame('Zewnętrzny', $stored[0]['labelOverride']);
        $this->assertCount(1, $stored[0]['children']);
        $this->assertSame('cart', $stored[0]['children'][0]['referenceKey']);
    }

    public function test_an_item_with_an_invalid_type_is_rejected_and_nothing_is_saved(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.cms.menus.store'), ['name' => 'Stopka']);
        $menuId = (int) DB::table('menus')->where('name', 'Stopka')->value('id');

        $response = $this->actingAs($staff)->put(route('panel.cms.menus.items.update', $menuId), [
            'tree' => [['id' => 'a', 'type' => 'not-a-real-type']],
        ]);

        $response->assertSessionHasErrors('tree.0.type');
        $this->assertSame('[]', DB::table('menus')->where('id', $menuId)->value('content'));
    }

    public function test_a_custom_link_without_a_url_is_rejected(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.cms.menus.store'), ['name' => 'Stopka']);
        $menuId = (int) DB::table('menus')->where('name', 'Stopka')->value('id');

        $this->actingAs($staff)->put(route('panel.cms.menus.items.update', $menuId), [
            'tree' => [['id' => 'a', 'type' => 'custom', 'url' => '']],
        ])->assertSessionHasErrors('tree.0.url');
    }

    public function test_deleting_a_menu(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.cms.menus.store'), ['name' => 'Stopka']);
        $menuId = (int) DB::table('menus')->where('name', 'Stopka')->value('id');

        $this->actingAs($staff)->delete(route('panel.cms.menus.destroy', $menuId))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('menus', ['id' => $menuId]);
    }

    public function test_the_editor_lists_pages_archetypes_and_widgets_for_the_picker(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'o-nas', 'title' => 'O nas',
            'seo_title' => '', 'seo_description' => '', 'status' => 'published',
        ])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.catalog.products.references.categories.store'), [
            'locale' => 'pl', 'name' => 'Buty', 'parent_id' => null, 'visible_in_tree' => true,
        ])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.cms.menus.store'), ['name' => 'Test']);
        $menuId = (int) DB::table('menus')->where('name', 'Test')->value('id');

        $response = $this->actingAs($staff)->get(route('panel.cms.menus.edit', $menuId));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('pages.0.label', 'O nas')
            ->has('archetypes', 5)
            ->where('archetypes.0.key', 'category')
            ->where('archetypes.0.instances.0.label', 'Buty')
            ->where('archetypes.2.key', 'blog')
            ->where('archetypes.2.instances.0.label', 'Blog')
            ->where('archetypes.3.key', 'blog_category')
            ->where('archetypes.4.key', 'blog_post')
            ->has('archetypes.4.instances', 0)
            ->has('widgets', 6));
    }

    public function test_the_editor_annotates_stored_items_with_their_resolved_display_name(): void
    {
        // A real gap the merchant found: the tree only showed the type
        // ("Widżet"/"Strona") with no way to tell WHICH widget/page it was.
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'o-nas', 'title' => 'O nas',
            'seo_title' => '', 'seo_description' => '', 'status' => 'published',
        ])->assertSessionHasNoErrors();
        $pageId = (int) DB::table('cms_pages')->latest('id')->value('id');
        $this->actingAs($staff)->post(route('panel.cms.menus.store'), ['name' => 'Test']);
        $menuId = (int) DB::table('menus')->where('name', 'Test')->value('id');

        $tree = [
            ['id' => 'a', 'type' => 'page', 'referenceId' => (string) $pageId, 'referenceKey' => null, 'url' => null, 'labelOverride' => null, 'targetBlank' => false, 'children' => []],
            ['id' => 'b', 'type' => 'widget', 'referenceKey' => 'cart', 'referenceId' => null, 'url' => null, 'labelOverride' => null, 'targetBlank' => false, 'children' => []],
            ['id' => 'c', 'type' => 'page', 'referenceId' => '999999', 'referenceKey' => null, 'url' => null, 'labelOverride' => null, 'targetBlank' => false, 'children' => []],
        ];
        DB::table('menus')->where('id', $menuId)->update(['content' => json_encode($tree)]);

        $response = $this->actingAs($staff)->get(route('panel.cms.menus.edit', $menuId));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('menu.content.0.autoLabel', 'O nas')
            // Widget labels are registered once at container boot (Storefront
            // ServiceProvider::boot()), the same "plain string, not lazily
            // re-evaluated per request" idiom already used by ProductTabRegistry
            // for hello-plugin's own tab label — so this only asserts it
            // resolved to SOMETHING, not a specific store-locale string.
            ->whereType('menu.content.1.autoLabel', 'string')
            ->where('menu.content.2.autoLabel', null));
    }

    public function test_editing_a_menu_that_no_longer_exists_redirects_to_the_index_with_a_message(): void
    {
        // Never a bare 404 — see litecommerce-panel-ui skill's "Zasada 7". The
        // message is a plain, generic fact — no speculation about why it's
        // missing (e.g. "deleted in another tab"), per the user's own correction.
        $staff = StaffAccount::factory()->create();

        $response = $this->actingAs($staff)->get(route('panel.cms.menus.edit', 999999));

        $response->assertRedirect(route('panel.cms.menus.index'));
        $response->assertSessionHas('error', 'Menu nie zostało znalezione.');
    }

    public function test_updating_settings_of_a_menu_that_no_longer_exists_redirects_to_the_index_with_a_message(): void
    {
        $staff = StaffAccount::factory()->create();

        $response = $this->actingAs($staff)->put(route('panel.cms.menus.update', 999999), ['name' => 'X']);

        $response->assertRedirect(route('panel.cms.menus.index'));
        $response->assertSessionHas('error');
    }

    public function test_saving_items_of_a_menu_that_no_longer_exists_redirects_to_the_index_with_a_message(): void
    {
        $staff = StaffAccount::factory()->create();

        $response = $this->actingAs($staff)->put(route('panel.cms.menus.items.update', 999999), ['tree' => []]);

        $response->assertRedirect(route('panel.cms.menus.index'));
        $response->assertSessionHas('error');
    }

    public function test_deleting_a_menu_that_no_longer_exists_redirects_to_the_index_with_a_message(): void
    {
        $staff = StaffAccount::factory()->create();

        $response = $this->actingAs($staff)->delete(route('panel.cms.menus.destroy', 999999));

        $response->assertRedirect(route('panel.cms.menus.index'));
        $response->assertSessionHas('error');
    }

    public function test_staff_without_manage_content_is_forbidden(): void
    {
        $editor = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();

        $this->actingAs($editor)->get(route('panel.cms.menus.index'))->assertForbidden();
    }
}
