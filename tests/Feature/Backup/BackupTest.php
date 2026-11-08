<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use Illuminate\Support\Facades\File;
use Litecommerce\Modules\Backup\Contracts\BackupFailedException;
use Litecommerce\Modules\Backup\Contracts\BackupManager;
use Litecommerce\Modules\Backup\Contracts\BackupRequest;
use Litecommerce\Modules\Backup\Contracts\BackupTarget;
use Litecommerce\Modules\Backup\Contracts\BackupVerifier;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class BackupTest extends TestCase
{
    use UsesIsolatedStorage;

    private string $storage;

    private string $output;

    private string $inside;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = $this->useIsolatedStorage();
        $this->output = sys_get_temp_dir().'/litecommerce-backup-out-'.uniqid();
        $this->inside = base_path('storage/framework/testing-backup-'.uniqid());
        $this->artisan('migrate', ['--force' => true]);

        file_put_contents($this->storage.'/app/public/logo.png', 'public-image-bytes-'.str_repeat('x', 200));
        file_put_contents($this->storage.'/app/private/invoice.txt', 'private-invoice');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->output);
        File::deleteDirectory($this->inside);
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    /** @param list<BackupTarget> $targets */
    private function create(array $targets = [], array $extra = [], array $excludes = [], ?int $keep = null, ?string $label = null)
    {
        return app(BackupManager::class)->create(new BackupRequest($targets ?: BackupTarget::defaults(), $extra, $excludes, $this->output, $label, 'auto', $keep));
    }

    /** @return list<string> */
    private function entries(string $archive): array
    {
        $zip = new \ZipArchive;
        $zip->open($archive, \ZipArchive::RDONLY);
        $names = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[] = $zip->getNameIndex($index);
        }
        $zip->close();
        sort($names);

        return $names;
    }

    public function test_the_default_archive_holds_database_and_files_without_env(): void
    {
        $result = $this->create();

        $this->assertSame(['database.sqlite', 'files/private/invoice.txt', 'files/public/logo.png', 'manifest.json'], $this->entries($result->path));
        $this->assertSame('sqlite-vacuum', $result->databaseMethod);
        $this->assertSame(2, $result->fileCount);
        $this->assertSame(hash_file('sha256', $result->path), $result->sha256);
        $this->assertSame($result->sha256.'  '.basename($result->path)."\n", file_get_contents($result->path.'.sha256'));
        $this->assertTrue(app(BackupVerifier::class)->verify($result->path)->valid);
        $this->assertStringContainsString('APP_KEY', implode(' ', $result->warnings));
    }

    public function test_the_manifest_describes_what_was_archived(): void
    {
        $result = $this->create([BackupTarget::PublicFiles], [], ['*.nothing'], null, 'Before update!');

        $manifest = app(BackupVerifier::class)->verify($result->path)->manifest;
        $this->assertSame(1, $manifest['format_version']);
        $this->assertSame(['public'], $manifest['targets']);
        $this->assertNull($manifest['database']);
        $this->assertSame(1, $manifest['files']['count']);
        $this->assertSame(['*.nothing'], $manifest['excludes']);
        $this->assertStringEndsWith('-before-update.zip', $result->path);
    }

    public function test_targets_can_be_chosen_or_left_out(): void
    {
        $this->artisan('backup:create', ['--only' => 'database', '--output' => $this->output, '--json' => true])->assertSuccessful();
        $this->artisan('backup:create', ['--without' => 'database,private', '--output' => $this->output, '--name' => 'files', '--json' => true])->assertSuccessful();

        $archives = glob($this->output.'/*.zip');
        sort($archives);
        $names = array_map(fn (string $path): array => $this->entries($path), $archives);
        $this->assertContains(['database.sqlite', 'manifest.json'], $names);
        $this->assertContains(['files/public/logo.png', 'manifest.json'], $names);
    }

    public function test_only_and_without_cannot_be_combined_and_unknown_targets_are_rejected(): void
    {
        $this->artisan('backup:create', ['--only' => 'database', '--without' => 'private', '--output' => $this->output])->assertFailed();
        $this->artisan('backup:create', ['--only' => 'nonsense', '--output' => $this->output])->assertFailed();
        $this->artisan('backup:create', ['--without' => 'database,public,private', '--output' => $this->output])->assertFailed();
        $this->assertSame([], glob($this->output.'/*.zip') ?: []);
    }

    public function test_the_json_summary_is_machine_readable(): void
    {
        $this->artisan('backup:create', ['--only' => 'private', '--output' => $this->output, '--json' => true])->assertSuccessful();

        $archive = glob($this->output.'/*.zip')[0];
        $this->assertSame(['files/private/invoice.txt', 'manifest.json'], $this->entries($archive));
    }

    public function test_extra_paths_inside_the_application_are_archived_and_globs_exclude_files(): void
    {
        mkdir($this->inside.'/themes/shop', 0777, true);
        file_put_contents($this->inside.'/themes/shop/theme.json', '{}');
        file_put_contents($this->inside.'/themes/shop/debug.log', 'noise');

        $result = $this->create([], [$this->inside], ['*.log']);

        $relative = ltrim(substr($this->inside, strlen((string) realpath(base_path()))), '/');
        $this->assertContains("files/custom/{$relative}/themes/shop/theme.json", $this->entries($result->path));
        $this->assertNotContains("files/custom/{$relative}/themes/shop/debug.log", $this->entries($result->path));
    }

    public function test_extra_paths_outside_the_application_are_rejected(): void
    {
        $this->expectException(BackupFailedException::class);
        $this->expectExceptionMessageMatches('/outside the application directory/');

        $this->create([BackupTarget::PublicFiles], [sys_get_temp_dir()]);
    }

    public function test_symlinks_are_never_followed(): void
    {
        $secret = sys_get_temp_dir().'/litecommerce-secret-'.uniqid();
        file_put_contents($secret, 'do-not-archive');
        symlink($secret, $this->storage.'/app/public/leak.txt');

        try {
            $result = $this->create([BackupTarget::PublicFiles]);
        } finally {
            unlink($secret);
        }

        $this->assertSame(['files/public/logo.png', 'manifest.json'], $this->entries($result->path));
    }

    public function test_the_output_directory_cannot_be_web_accessible(): void
    {
        $this->expectException(BackupFailedException::class);
        $this->expectExceptionMessageMatches('/web-accessible/');

        app(BackupManager::class)->create(new BackupRequest(BackupTarget::defaults(), outputDirectory: $this->storage.'/app/public/backups'));
    }

    public function test_the_archive_directory_is_excluded_from_its_own_contents(): void
    {
        $this->output = $this->storage.'/app/private/backups';
        $first = $this->create([BackupTarget::PrivateFiles]);
        $second = $this->create([BackupTarget::PrivateFiles], [], [], null, 'again');

        $this->assertSame(['files/private/invoice.txt', 'manifest.json'], $this->entries($second->path));
        $this->assertTrue(is_file($first->path));
    }

    public function test_retention_keeps_only_the_newest_archives_and_their_checksums(): void
    {
        mkdir($this->output, 0700, true);
        foreach (['20200101-000000', '20200102-000000', '20200103-000000'] as $stamp) {
            touch("{$this->output}/litecommerce-backup-{$stamp}.zip");
            touch("{$this->output}/litecommerce-backup-{$stamp}.zip.sha256");
        }
        touch($this->output.'/unrelated.zip');

        $result = $this->create([BackupTarget::PrivateFiles], keep: 2);

        $this->assertSame(2, $result->removedOldBackups);
        $left = array_map('basename', glob($this->output.'/*') ?: []);
        $this->assertContains('litecommerce-backup-20200103-000000.zip', $left);
        $this->assertContains('litecommerce-backup-20200103-000000.zip.sha256', $left);
        $this->assertContains('unrelated.zip', $left);
        $this->assertNotContains('litecommerce-backup-20200101-000000.zip', $left);
        $this->assertNotContains('litecommerce-backup-20200101-000000.zip.sha256', $left);
        $this->assertContains(basename($result->path), $left);
    }

    public function test_keep_must_be_positive_and_something_must_be_selected(): void
    {
        $this->assertFailsWith('/--keep/', fn () => $this->create([BackupTarget::PrivateFiles], keep: 0));
        $this->assertFailsWith('/Nothing to back up/', fn () => app(BackupManager::class)->create(new BackupRequest([], outputDirectory: $this->output)));
    }

    public function test_a_second_backup_cannot_run_at_the_same_time(): void
    {
        mkdir($this->output, 0700, true);
        $held = fopen($this->output.'/.backup.lock', 'c');
        flock($held, LOCK_EX);

        try {
            $this->assertFailsWith('/already running/', fn () => $this->create([BackupTarget::PrivateFiles]));
        } finally {
            fclose($held);
        }

        $this->assertNotNull($this->create([BackupTarget::PrivateFiles])->path);
    }

    public function test_a_backup_that_cannot_fit_on_the_disk_is_refused_without_leftovers(): void
    {
        config(['backup.min_free_space_mb' => PHP_INT_MAX >> 24]);

        $this->assertFailsWith('/free disk space/', fn () => $this->create());
        $this->assertSame([], array_values(array_filter(glob($this->output.'/{,.}*', GLOB_BRACE) ?: [], static fn (string $path): bool => is_file($path) && ! str_ends_with($path, '.lock'))));
    }

    public function test_verify_accepts_an_intact_archive_and_rejects_corruption_or_a_wrong_checksum(): void
    {
        $result = $this->create([BackupTarget::PublicFiles]);
        $this->artisan('backup:verify', ['archive' => $result->path])->assertSuccessful();

        $bytes = (string) file_get_contents($result->path);
        $offset = strpos($bytes, 'public-image-bytes-');
        $this->assertNotFalse($offset);
        $corrupted = $this->output.'/corrupted.zip';
        file_put_contents($corrupted, substr_replace($bytes, 'Z', $offset, 1));
        $verdict = app(BackupVerifier::class)->verify($corrupted);
        $this->assertFalse($verdict->valid);
        $this->assertStringContainsString('files/public/logo.png', implode(' ', $verdict->problems));
        $this->artisan('backup:verify', ['archive' => $corrupted])->assertFailed();

        file_put_contents($result->path.'.sha256', str_repeat('0', 64)."  x.zip\n");
        $this->assertFalse(app(BackupVerifier::class)->verify($result->path)->valid);
    }

    public function test_verify_rejects_missing_files_and_archives_without_a_manifest(): void
    {
        $this->assertFalse(app(BackupVerifier::class)->verify($this->output.'/missing.zip')->valid);

        mkdir($this->output, 0700, true);
        $zip = new \ZipArchive;
        $zip->open($this->output.'/foreign.zip', \ZipArchive::CREATE);
        $zip->addFromString('hello.txt', 'hi');
        $zip->close();
        $this->assertFalse(app(BackupVerifier::class)->verify($this->output.'/foreign.zip')->valid);
    }

    private function assertFailsWith(string $pattern, callable $action): void
    {
        try {
            $action();
        } catch (BackupFailedException $exception) {
            $this->assertMatchesRegularExpression($pattern, $exception->getMessage());

            return;
        }

        $this->fail('Expected BackupFailedException was not thrown.');
    }
}
