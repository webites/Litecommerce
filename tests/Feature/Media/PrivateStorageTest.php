<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Media\Contracts\PrivateFileLibrary;
use Litecommerce\Modules\Scheduler\Application\RunDueScheduledTasks;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class PrivateStorageTest extends TestCase
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

    public function test_private_files_live_outside_the_web_root_and_are_not_publicly_reachable(): void
    {
        $file = app(PrivateFileLibrary::class)->store('secret ebook bytes', 'ebook.pdf', 'application/pdf');

        $path = (string) DB::table('private_files')->where('id', $file->id)->value('path');
        $absolute = Storage::disk((string) config('media.private_disk'))->path($path);

        $this->assertFileExists($absolute);
        $this->assertStringStartsNotWith(realpath(public_path()) ?: public_path(), realpath($absolute) ?: $absolute);
        $this->assertNotSame('public', config('media.private_disk'));
        $this->assertSame($file->checksumSha256, hash('sha256', 'secret ebook bytes'));

        $response = $this->get('/storage/'.$path);
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('secret ebook bytes', (string) $response->getContent());
    }

    public function test_a_large_file_is_stored_from_its_path_with_a_matching_checksum(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'lc-big-');
        file_put_contents($source, str_repeat('0123456789abcdef', 65536));

        $file = app(PrivateFileLibrary::class)->storeFromPath($source, 'big.bin', 'application/octet-stream');

        $this->assertSame(1048576, $file->sizeBytes);
        $this->assertSame(hash_file('sha256', $source), $file->checksumSha256);
        $this->assertSame('ok', $file->integrityStatus);
        $this->assertSame(1048576, Storage::disk((string) config('media.private_disk'))->size((string) DB::table('private_files')->value('path')));
        @unlink($source);
    }

    public function test_the_scheduled_check_flags_altered_and_missing_files_and_blocks_serving_them(): void
    {
        $library = app(PrivateFileLibrary::class);
        $altered = $library->store('original', 'a.txt', 'text/plain');
        $missing = $library->store('gone', 'b.txt', 'text/plain');
        $intact = $library->store('fine', 'c.txt', 'text/plain');
        $disk = Storage::disk((string) config('media.private_disk'));
        $disk->put((string) DB::table('private_files')->where('id', $altered->id)->value('path'), 'changed');
        $disk->delete((string) DB::table('private_files')->where('id', $missing->id)->value('path'));

        app(RunDueScheduledTasks::class)->run(20, 10);

        $this->assertDatabaseHas('private_files', ['id' => $altered->id, 'integrity_status' => 'mismatch']);
        $this->assertDatabaseHas('private_files', ['id' => $missing->id, 'integrity_status' => 'missing']);
        $this->assertDatabaseHas('private_files', ['id' => $intact->id, 'integrity_status' => 'ok']);
        $this->assertFalse($library->isServable($altered->id));
        $this->assertFalse($library->isServable($missing->id));
        $this->assertTrue($library->isServable($intact->id));
        $this->assertDatabaseHas('scheduled_tasks', ['key' => 'core.media.verify_private_files', 'run_count' => 1]);
    }
}
