<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class CatalogReferencesEditTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert([
            'code' => 'pl',
            'name' => 'Polski',
            'is_default' => true,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_staff_can_edit_a_brand_translation(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.references.brands.store'), [
            'locale' => 'pl', 'name' => 'Stara nazwa',
        ])->assertSessionHasNoErrors();
        $brandId = (int) DB::table('brands')->value('id');

        $this->actingAs($staff)->put(route('panel.catalog.products.references.brands.update', $brandId), [
            'locale' => 'pl', 'name' => 'Nowa nazwa', 'description_html' => '<p>Opis</p>',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('brand_translations', 1);
        $this->assertDatabaseHas('brand_translations', ['brand_id' => $brandId, 'locale' => 'pl', 'name' => 'Nowa nazwa', 'description_html' => '<p>Opis</p>']);
        $this->assertDatabaseHas('audit_entries', ['action' => 'catalog.brand.updated', 'subject_id' => (string) $brandId]);
    }

    public function test_editing_a_brand_adds_a_translation_for_a_new_locale_instead_of_overwriting(): void
    {
        DB::table('languages')->insert(['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.references.brands.store'), ['locale' => 'pl', 'name' => 'Marka PL'])->assertSessionHasNoErrors();
        $brandId = (int) DB::table('brands')->value('id');

        $this->actingAs($staff)->put(route('panel.catalog.products.references.brands.update', $brandId), [
            'locale' => 'en', 'name' => 'Brand EN',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('brand_translations', 2);
        $this->assertDatabaseHas('brand_translations', ['brand_id' => $brandId, 'locale' => 'pl', 'name' => 'Marka PL']);
        $this->assertDatabaseHas('brand_translations', ['brand_id' => $brandId, 'locale' => 'en', 'name' => 'Brand EN']);
    }

    public function test_staff_can_upload_and_remove_a_brand_logo(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.references.brands.store'), ['locale' => 'pl', 'name' => 'Marka'])->assertSessionHasNoErrors();
        $brandId = (int) DB::table('brands')->value('id');

        $this->actingAs($staff)->post(route('panel.catalog.products.references.brands.logo.store', $brandId), [
            'image' => UploadedFile::fake()->image('logo.png', 40, 40),
        ])->assertSessionHasNoErrors();

        $mediaId = DB::table('brands')->where('id', $brandId)->value('logo_media_id');
        $this->assertNotNull($mediaId);
        $this->assertDatabaseCount('media', 1);

        $this->actingAs($staff)->delete(route('panel.catalog.products.references.brands.logo.destroy', $brandId))->assertSessionHasNoErrors();
        $this->assertNull(DB::table('brands')->where('id', $brandId)->value('logo_media_id'));
    }

    public function test_staff_can_move_and_rename_a_category(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.references.categories.store'), [
            'locale' => 'pl', 'name' => 'Elektronika', 'parent_id' => null, 'visible_in_tree' => true,
        ])->assertSessionHasNoErrors();
        $rootId = (int) DB::table('categories')->value('id');
        $this->actingAs($staff)->post(route('panel.catalog.products.references.categories.store'), [
            'locale' => 'pl', 'name' => 'Telefony', 'parent_id' => null, 'visible_in_tree' => true,
        ])->assertSessionHasNoErrors();
        $categoryId = (int) DB::table('categories')->max('id');

        $this->actingAs($staff)->put(route('panel.catalog.products.references.categories.update', $categoryId), [
            'locale' => 'pl', 'name' => 'Smartfony', 'parent_id' => $rootId, 'position' => 2, 'visible_in_tree' => false,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', ['id' => $categoryId, 'parent_id' => $rootId, 'position' => 2, 'visible_in_tree' => false]);
        $this->assertDatabaseHas('category_translations', ['category_id' => $categoryId, 'locale' => 'pl', 'name' => 'Smartfony']);
    }

    public function test_a_category_cannot_become_its_own_descendant(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.references.categories.store'), [
            'locale' => 'pl', 'name' => 'Rodzic', 'parent_id' => null, 'visible_in_tree' => true,
        ])->assertSessionHasNoErrors();
        $parentId = (int) DB::table('categories')->value('id');
        $this->actingAs($staff)->post(route('panel.catalog.products.references.categories.store'), [
            'locale' => 'pl', 'name' => 'Dziecko', 'parent_id' => $parentId, 'visible_in_tree' => true,
        ])->assertSessionHasNoErrors();
        $childId = (int) DB::table('categories')->max('id');

        $this->actingAs($staff)->put(route('panel.catalog.products.references.categories.update', $parentId), [
            'locale' => 'pl', 'name' => 'Rodzic', 'parent_id' => $childId, 'position' => 0, 'visible_in_tree' => true,
        ])->assertSessionHasErrors('parent_id');

        $this->assertDatabaseHas('categories', ['id' => $parentId, 'parent_id' => null]);
    }

    public function test_a_category_cannot_directly_become_its_own_parent(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.references.categories.store'), [
            'locale' => 'pl', 'name' => 'Kategoria', 'parent_id' => null, 'visible_in_tree' => true,
        ])->assertSessionHasNoErrors();
        $categoryId = (int) DB::table('categories')->value('id');

        $this->actingAs($staff)->put(route('panel.catalog.products.references.categories.update', $categoryId), [
            'locale' => 'pl', 'name' => 'Kategoria', 'parent_id' => $categoryId, 'position' => 0, 'visible_in_tree' => true,
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_staff_can_upload_and_remove_a_category_image(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.references.categories.store'), [
            'locale' => 'pl', 'name' => 'Kategoria', 'parent_id' => null, 'visible_in_tree' => true,
        ])->assertSessionHasNoErrors();
        $categoryId = (int) DB::table('categories')->value('id');

        $this->actingAs($staff)->post(route('panel.catalog.products.references.categories.image.store', $categoryId), [
            'image' => UploadedFile::fake()->image('cover.jpg', 60, 60),
        ])->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('categories')->where('id', $categoryId)->value('image_media_id'));

        $this->actingAs($staff)->delete(route('panel.catalog.products.references.categories.image.destroy', $categoryId))->assertSessionHasNoErrors();
        $this->assertNull(DB::table('categories')->where('id', $categoryId)->value('image_media_id'));
    }
}
