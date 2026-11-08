<?php

declare(strict_types=1);

namespace Tests\Feature\Updates;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Contracts\EnvironmentRequirements;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceReason;
use Litecommerce\Modules\Updates\Application\BuildReleasePackage;
use Litecommerce\Modules\Updates\Application\EnvironmentProbe;
use Litecommerce\Modules\Updates\Application\UpdateOperationState;
use Litecommerce\Modules\Updates\Application\UpdateOperationStore;
use Litecommerce\Modules\Updates\Application\UpdatePaths;
use Litecommerce\Modules\Updates\Application\UpdateState;
use Litecommerce\Modules\Updates\Application\UpdateStateStore;
use Litecommerce\Modules\Updates\Contracts\ReleaseInfo;
use Litecommerce\Modules\Updates\Contracts\ReleaseSource;
use Litecommerce\Modules\Updates\Contracts\SemanticVersion;
use Litecommerce\Modules\Updates\Contracts\UpdateAlreadyRunningException;
use Litecommerce\Modules\Updates\Contracts\UpdateChecker;
use Litecommerce\Modules\Updates\Contracts\UpdateInstaller;
use Litecommerce\Modules\Updates\Contracts\UpdateOperationData;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;
use ZipArchive;

final class UpdateInstallationTest extends TestCase
{
    use UsesIsolatedStorage;

    private string $workspace;

    private string $root;

    private string $secretKey;

    private string $checksum = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->workspace = sys_get_temp_dir().'/lc-install-'.bin2hex(random_bytes(4));
        $this->root = $this->workspace.'/root';
        mkdir($this->root.'/app', 0775, true);
        mkdir($this->root.'/storage', 0775, true);
        file_put_contents($this->root.'/app/a.php', 'old a');
        file_put_contents($this->root.'/app/removed.php', 'to be removed');
        file_put_contents($this->root.'/local-note.txt', 'merchant file');
        file_put_contents($this->root.'/.env', 'APP_KEY=keep');
        file_put_contents($this->root.'/storage/data.txt', 'shop data');

        $pair = sodium_crypto_sign_keypair();
        $this->secretKey = base64_encode(sodium_crypto_sign_secretkey($pair));
        config(['updates.trusted_public_keys' => [base64_encode(sodium_crypto_sign_publickey($pair))], 'version.current' => '0.1.0', 'backup.directory' => $this->workspace.'/backups']);

        $this->buildInstalledVersion();
        $this->bindEnvironment();
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->workspace));
        parent::tearDown();
    }

    public function test_an_update_replaces_adds_and_removes_only_release_files(): void
    {
        $release = $this->publish('0.2.0', ['app/a.php' => 'new a', 'app/new.php' => 'brand new', 'artisan' => 'artisan']);

        $installer = app(UpdateInstaller::class);
        $installer->start($release, '7');
        $swapped = $installer->advance(60);
        $this->assertSame('running', $swapped?->status);
        $this->assertSame('done', $swapped?->steps['swap']['status']);
        $this->assertSame('pending', $swapped?->steps['migrate']['status']);
        $result = $installer->advance(60);

        $this->assertSame('completed', $result?->status, (string) $result?->errorMessage);
        $this->assertSame('new a', file_get_contents($this->root.'/app/a.php'));
        $this->assertSame('brand new', file_get_contents($this->root.'/app/new.php'));
        $this->assertFileDoesNotExist($this->root.'/app/removed.php');
        $this->assertSame('merchant file', file_get_contents($this->root.'/local-note.txt'));
        $this->assertSame('APP_KEY=keep', file_get_contents($this->root.'/.env'));
        $this->assertSame('shop data', file_get_contents($this->root.'/storage/data.txt'));
        $this->assertStringContainsString('"version": "0.2.0"', (string) file_get_contents($this->root.'/manifest.json'));
        $this->assertFalse(app(MaintenanceMode::class)->isActive());
        $this->assertDatabaseHas('audit_entries', ['action' => 'updates.completed', 'subject_id' => '0.2.0', 'actor_id' => '7']);
        $this->assertNotEmpty(glob($this->workspace.'/backups/*.zip'));
        $this->assertSame('old a', file_get_contents($this->workspace.'/backups/update-'.$result->id.'/files/app/a.php'));
    }

    public function test_repeating_the_swap_after_an_interruption_keeps_the_original_backup(): void
    {
        $release = $this->publish('0.2.0', ['app/a.php' => 'new a']);
        $installer = app(UpdateInstaller::class);
        $installer->start($release, null);
        $id = (string) $installer->advance(60)?->id;
        $installer->advance(60);

        $store = app(UpdateOperationStore::class);
        $state = $store->load();
        $state->status = UpdateOperationState::RUNNING;
        $state->mark('swap', 'running');
        $store->save($state);

        $installer->advance(60);
        $this->assertSame('completed', $installer->advance(60)?->status);
        $this->assertSame('old a', file_get_contents($this->workspace.'/backups/update-'.$id.'/files/app/a.php'));
        $this->assertSame('new a', file_get_contents($this->root.'/app/a.php'));
    }

    public function test_a_second_update_cannot_start_while_one_is_unfinished(): void
    {
        $release = $this->publish('0.2.0', ['app/a.php' => 'new a']);
        $installer = app(UpdateInstaller::class);
        $installer->start($release, null);

        $this->expectException(UpdateAlreadyRunningException::class);
        $installer->start($release, null);
    }

    public function test_an_archive_not_signed_by_a_trusted_key_changes_nothing_and_leaves_maintenance(): void
    {
        $release = $this->publish('0.2.0', ['app/a.php' => 'evil'], signingKey: base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair())));

        $result = $this->runUpdate($release);

        $this->assertSame('rolled_back', $result->status);
        $this->assertSame('signature_invalid', $result->errorCode);
        $this->assertSame('old a', file_get_contents($this->root.'/app/a.php'));
        $this->assertFalse(app(MaintenanceMode::class)->isActive());
    }

    public function test_a_wrong_published_checksum_stops_before_anything_is_unpacked(): void
    {
        $release = $this->publish('0.2.0', ['app/a.php' => 'new a']);
        $this->checksum = str_repeat('0', 64);

        $result = $this->runUpdate($release);

        $this->assertSame('rolled_back', $result->status);
        $this->assertSame('checksum_mismatch', $result->errorCode);
        $this->assertSame('old a', file_get_contents($this->root.'/app/a.php'));
    }

    public function test_an_unlisted_archive_entry_is_refused(): void
    {
        $release = $this->publish('0.2.0', ['app/a.php' => 'new a']);
        $zip = new ZipArchive;
        $zip->open($this->workspace.'/served/litecommerce-0.2.0.zip');
        $zip->addFromString('../evil.php', 'x');
        $zip->close();
        $this->checksum = hash_file('sha256', $this->workspace.'/served/litecommerce-0.2.0.zip');

        $result = $this->runUpdate($release);

        $this->assertSame('unexpected_entry', $result->errorCode);
        $this->assertFileDoesNotExist($this->workspace.'/evil.php');
    }

    public function test_a_failure_while_replacing_files_restores_everything_already_changed(): void
    {
        file_put_contents($this->root.'/blocked', 'a file where the release needs a directory');
        $release = $this->publish('0.2.0', ['app/a.php' => 'new a', 'app/new.php' => 'brand new', 'blocked/x.php' => 'x']);

        $result = $this->runUpdate($release);

        $this->assertSame('rolled_back', $result->status);
        $this->assertSame('swap_failed', $result->errorCode);
        $this->assertSame('old a', file_get_contents($this->root.'/app/a.php'));
        $this->assertFileDoesNotExist($this->root.'/app/new.php');
        $this->assertSame('to be removed', file_get_contents($this->root.'/app/removed.php'));
        $this->assertStringContainsString('"version": "0.1.0"', (string) file_get_contents($this->root.'/manifest.json'));
        $this->assertFalse(app(MaintenanceMode::class)->isActive());
    }

    public function test_files_that_differ_from_the_manifest_after_the_swap_keep_the_store_in_maintenance(): void
    {
        $release = $this->publish('0.2.0', ['app/a.php' => 'new a']);
        $installer = app(UpdateInstaller::class);
        $installer->start($release, null);
        $installer->advance(60);
        file_put_contents($this->root.'/app/a.php', 'tampered');

        $result = $installer->advance(60);

        $this->assertSame('failed', $result?->status);
        $this->assertSame('integrity_failed', $result?->errorCode);
        $this->assertSame(MaintenanceReason::Update, app(MaintenanceMode::class)->state()?->reason);
        $this->assertSame('tampered', file_get_contents($this->root.'/app/a.php'));
    }

    public function test_the_panel_starts_and_drives_an_update_for_a_staff_member_allowed_to_update(): void
    {
        $release = $this->publish('0.2.0', ['app/a.php' => 'new a']);
        app(UpdateStateStore::class)->save(new UpdateState(release: $release->toArray()));
        $admin = StaffAccount::factory()->create();

        $this->actingAs($admin)->post(route('panel.settings.updates.install'))->assertRedirect();
        $this->assertDatabaseHas('audit_entries', ['action' => 'updates.started', 'actor_id' => (string) $admin->getKey()]);

        $this->actingAs($admin)->postJson(route('panel.settings.updates.advance'))->assertOk()
            ->assertJsonPath('status', 'running')
            ->assertJsonPath('steps.swap', 'done');
        $this->actingAs($admin)->postJson(route('panel.settings.updates.advance'))->assertOk()->assertJsonPath('status', 'completed');
        $this->assertSame('new a', file_get_contents($this->root.'/app/a.php'));

        config(['version.current' => '0.2.0']);
        $this->assertNull(app(UpdateChecker::class)->status()->availableRelease);
        $this->assertSame('completed', app(UpdateOperationStore::class)->load()?->status);
    }

    public function test_only_staff_with_the_update_permission_can_start_or_drive_an_update(): void
    {
        $manager = StaffAccount::factory()->withRole(SystemStaffRole::StoreManager)->create();

        $this->actingAs($manager)->post(route('panel.settings.updates.install'))->assertForbidden();
        $this->actingAs($manager)->postJson(route('panel.settings.updates.advance'))->assertForbidden();
        $this->assertNull(app(UpdateOperationStore::class)->load());
    }

    public function test_an_incompatible_release_is_not_started_from_the_panel(): void
    {
        $release = $this->publish('0.2.0', ['app/a.php' => 'new a']);
        app(UpdateStateStore::class)->save(new UpdateState(release: $release->toArray(), problems: [['code' => 'php_too_old', 'params' => []]]));

        $this->actingAs(StaffAccount::factory()->create())->post(route('panel.settings.updates.install'))->assertSessionHas('error');
        $this->assertNull(app(UpdateOperationStore::class)->load());
    }

    public function test_manually_enabled_maintenance_blocks_the_update(): void
    {
        app(MaintenanceMode::class)->enable(MaintenanceReason::Manual);

        $result = $this->runUpdate($this->publish('0.2.0', ['app/a.php' => 'new a']));

        $this->assertSame('maintenance_already_active', $result->errorCode);
        $this->assertTrue(app(MaintenanceMode::class)->isActive());
    }

    private function runUpdate(ReleaseInfo $release): UpdateOperationData
    {
        $installer = app(UpdateInstaller::class);
        $installer->start($release, null);

        return $installer->advance(60);
    }

    /** @param array<string, string> $files */
    private function publish(string $version, array $files, ?string $signingKey = null): ReleaseInfo
    {
        $source = $this->workspace.'/src-'.$version;
        foreach ($files as $path => $content) {
            @mkdir(dirname($source.'/'.$path), 0775, true);
            file_put_contents($source.'/'.$path, $content);
        }

        $built = $this->builder()->build($source, $this->workspace.'/served', $version, $signingKey ?? $this->secretKey);
        $this->checksum = hash_file('sha256', $built['archive']);
        $served = $this->workspace.'/served';
        $this->bindSource($served, $version);

        return new ReleaseInfo(SemanticVersion::parse($version), "v{$version}", "https://github.com/acme/shop/{$version}.zip", "https://github.com/acme/shop/{$version}.sha256", "https://github.com/acme/shop/{$version}.manifest", "https://github.com/acme/shop/{$version}.sig", '', null);
    }

    private function bindSource(string $served, string $version): void
    {
        $test = $this;
        app()->instance(ReleaseSource::class, new class($served, $version, $test) implements ReleaseSource
        {
            public function __construct(private string $dir, private string $version, private object $test) {}

            public function stableReleases(): array
            {
                return [];
            }

            public function readAsset(string $url): string
            {
                return match (true) {
                    str_ends_with($url, '.sha256') => $this->test->currentChecksum(),
                    str_ends_with($url, '.manifest') => (string) file_get_contents($this->dir.'/manifest.json'),
                    str_ends_with($url, '.sig') => (string) file_get_contents($this->dir.'/manifest.json.sig'),
                    default => '',
                };
            }

            public function download(string $url, string $destination, int $maxBytes): void
            {
                copy($this->dir."/litecommerce-{$this->version}.zip", $destination);
            }
        });
    }

    public function currentChecksum(): string
    {
        return $this->checksum;
    }

    private function buildInstalledVersion(): void
    {
        $source = $this->workspace.'/src-0.1.0';
        mkdir($source.'/app', 0775, true);
        file_put_contents($source.'/app/a.php', 'old a');
        file_put_contents($source.'/app/removed.php', 'to be removed');
        $this->builder()->build($source, $this->workspace.'/installed', '0.1.0', $this->secretKey);
        copy($this->workspace.'/installed/manifest.json', $this->root.'/manifest.json');
    }

    private function builder(): BuildReleasePackage
    {
        return new BuildReleasePackage(app(EnvironmentRequirements::class), []);
    }

    private function bindEnvironment(): void
    {
        app()->bind(UpdatePaths::class, fn () => new UpdatePaths($this->root, $this->workspace.'/work', $this->workspace.'/backups', ['.env', 'storage']));
        app()->bind(EnvironmentProbe::class, fn () => new class implements EnvironmentProbe
        {
            public function phpVersion(): string
            {
                return PHP_VERSION;
            }

            public function hasExtension(string $extension): bool
            {
                return true;
            }

            public function isWritable(string $path): bool
            {
                return true;
            }

            public function freeBytes(): ?int
            {
                return null;
            }
        });
    }
}
