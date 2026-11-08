<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class MediaPickerTest extends TestCase
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

    private function seedImages(int $count): void
    {
        $library = app(MediaLibrary::class);
        for ($i = 1; $i <= $count; $i++) {
            $library->store('x', "photo-{$i}.jpg", 'image/jpeg');
        }
        $library->store('pdf', 'manual.pdf', 'application/pdf');
    }

    public function test_the_library_lists_images_newest_first_with_a_cursor_and_skips_other_files(): void
    {
        $this->seedImages(30);
        $staff = StaffAccount::factory()->create();

        $first = $this->actingAs($staff)->getJson(route('panel.media.index'))->assertOk()->json();
        $this->assertCount(24, $first['items']);
        $this->assertSame('photo-30.jpg', $first['items'][0]['filename']);
        $this->assertNotNull($first['next_before']);

        $second = $this->actingAs($staff)->getJson(route('panel.media.index', ['before' => $first['next_before']]))->assertOk()->json();
        $this->assertCount(6, $second['items']);
        $this->assertNull($second['next_before']);
        $this->assertNotContains('manual.pdf', array_column(array_merge($first['items'], $second['items']), 'filename'));
    }

    public function test_search_filters_by_filename_and_treats_wildcards_literally(): void
    {
        $this->seedImages(3);
        $staff = StaffAccount::factory()->create();

        $found = $this->actingAs($staff)->getJson(route('panel.media.index', ['search' => 'photo-2']))->json('items');
        $this->assertSame(['photo-2.jpg'], array_column($found, 'filename'));
        $this->assertSame([], $this->actingAs($staff)->getJson(route('panel.media.index', ['search' => '%']))->json('items'));
    }

    public function test_an_image_can_be_uploaded_from_the_picker(): void
    {
        $staff = StaffAccount::factory()->create();

        $response = $this->actingAs($staff)->postJson(route('panel.media.store'), ['image' => UploadedFile::fake()->image('new.png', 64, 64)])->assertCreated();

        $this->assertSame('new.png', $response->json('filename'));
        $this->assertNotEmpty($response->json('url'));
        $this->assertSame(1, DB::table('media')->count());
    }

    public function test_non_images_are_rejected_on_upload(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->postJson(route('panel.media.store'), ['image' => UploadedFile::fake()->create('evil.php', 1, 'application/x-php')])->assertUnprocessable();
        $this->assertSame(0, DB::table('media')->count());
    }

    public function test_the_picker_needs_content_or_catalog_permission_and_either_one_is_enough(): void
    {
        $none = StaffAccount::factory()->create(['permission_overrides' => ['core.content.manage' => false, 'core.catalog.manage' => false]]);
        $this->actingAs($none)->getJson(route('panel.media.index'))->assertForbidden();
        $this->actingAs($none)->postJson(route('panel.media.store'), ['image' => UploadedFile::fake()->image('a.png')])->assertForbidden();

        $catalogOnly = StaffAccount::factory()->create(['permission_overrides' => ['core.content.manage' => false, 'core.catalog.manage' => true]]);
        $this->actingAs($catalogOnly)->getJson(route('panel.media.index'))->assertOk();
    }
}
