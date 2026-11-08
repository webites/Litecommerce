<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Litecommerce\Modules\Media\Contracts\PrivateFileLibrary;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class StorageMigrationTest extends TestCase
{
    use UsesIsolatedStorage;

    private string $archiveRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $path = $this->useIsolatedStorage();
        $this->archiveRoot = $path.'/app/archive';
        mkdir($this->archiveRoot, 0777, true);
        config(['filesystems.disks.archive' => ['driver' => 'local', 'root' => $this->archiveRoot, 'throw' => false]]);
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
    }

    public function test_private_files_are_copied_verified_and_switched_batch_by_batch_keeping_sources(): void
    {
        $library = app(PrivateFileLibrary::class);
        $first = $library->store('first file', 'a.txt', 'text/plain');
        $second = $library->store('second file', 'b.txt', 'text/plain');
        $firstPath = (string) DB::table('private_files')->where('id', $first->id)->value('path');

        $this->artisan('media:migrate-disk', ['type' => 'private', 'disk' => 'archive', '--batch' => 1])
            ->expectsOutputToContain('Migrated: 1, failed: 0, remaining: 1.')
            ->assertSuccessful();
        $this->assertDatabaseHas('private_files', ['id' => $first->id, 'disk' => 'archive']);
        $this->assertDatabaseHas('private_files', ['id' => $second->id, 'disk' => 'local']);
        $this->assertSame('first file', Storage::disk('archive')->get($firstPath));
        $this->assertTrue(Storage::disk('local')->exists($firstPath));

        $this->artisan('media:migrate-disk', ['type' => 'private', 'disk' => 'archive', '--batch' => 5, '--delete-source' => true])
            ->expectsOutputToContain('remaining: 0.')
            ->assertSuccessful();
        $this->assertDatabaseMissing('private_files', ['disk' => 'local']);
        $secondPath = (string) DB::table('private_files')->where('id', $second->id)->value('path');
        $this->assertFalse(Storage::disk('local')->exists($secondPath));
        $this->assertSame('second file', Storage::disk('archive')->get($secondPath));
        $this->assertSame('ok', $library->find($second->id)?->integrityStatus);
    }

    public function test_a_missing_source_file_fails_that_file_without_switching_it(): void
    {
        $library = app(PrivateFileLibrary::class);
        $file = $library->store('lost', 'a.txt', 'text/plain');
        Storage::disk('local')->delete((string) DB::table('private_files')->where('id', $file->id)->value('path'));

        $this->artisan('media:migrate-disk', ['type' => 'private', 'disk' => 'archive'])->assertFailed();

        $this->assertDatabaseHas('private_files', ['id' => $file->id, 'disk' => 'local']);
    }

    public function test_public_media_and_their_variants_move_together_and_dry_run_changes_nothing(): void
    {
        $media = app(MediaLibrary::class)->store('image-bytes', 'photo.jpg', 'image/jpeg');

        $this->artisan('media:migrate-disk', ['type' => 'public', 'disk' => 'archive', '--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseHas('media', ['id' => $media->id, 'disk' => 'public']);

        $this->artisan('media:migrate-disk', ['type' => 'public', 'disk' => 'archive'])->assertSuccessful();
        $this->assertDatabaseHas('media', ['id' => $media->id, 'disk' => 'archive']);
        $this->assertSame('image-bytes', Storage::disk('archive')->get((string) DB::table('media')->where('id', $media->id)->value('path')));
    }

    public function test_an_unwritable_target_disk_aborts_before_touching_anything(): void
    {
        $file = app(PrivateFileLibrary::class)->store('keep', 'a.txt', 'text/plain');
        config(['filesystems.disks.broken' => ['driver' => 'local', 'root' => '/proc/litecommerce-unwritable', 'throw' => false]]);

        $this->artisan('media:migrate-disk', ['type' => 'private', 'disk' => 'broken'])->assertFailed();

        $this->assertDatabaseHas('private_files', ['id' => $file->id, 'disk' => 'local']);
    }
}
