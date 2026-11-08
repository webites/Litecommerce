<?php

declare(strict_types=1);

namespace Tests\Feature\Updates;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Litecommerce\Modules\Audit\Infrastructure\Models\AuditEntry;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Contracts\EnvironmentRequirements;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Scheduler\Application\RunDueScheduledTasks;
use Litecommerce\Modules\Updates\Application\BuildReleasePackage;
use Litecommerce\Modules\Updates\Contracts\ReleaseSource;
use Litecommerce\Modules\Updates\Contracts\ReleaseSourceException;
use Litecommerce\Modules\Updates\Contracts\UpdateChecker;
use Litecommerce\Modules\Updates\Infrastructure\GitHubReleaseSource;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class UpdateCheckTest extends TestCase
{
    use UsesIsolatedStorage;

    private string $secretKey;

    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(ReleaseSource::class, fn () => new GitHubReleaseSource((string) config('updates.repository')));
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);

        $pair = sodium_crypto_sign_keypair();
        $this->secretKey = base64_encode(sodium_crypto_sign_secretkey($pair));
        config(['updates.trusted_public_keys' => [base64_encode(sodium_crypto_sign_publickey($pair))], 'updates.repository' => 'acme/shop', 'version.current' => '0.1.0']);

        $this->workspace = sys_get_temp_dir().'/lc-updates-'.bin2hex(random_bytes(4));
        mkdir($this->workspace.'/src', 0775, true);
        file_put_contents($this->workspace.'/src/artisan', 'artisan');
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->workspace));
        parent::tearDown();
    }

    public function test_a_newer_signed_release_is_offered_with_a_compatibility_report(): void
    {
        $this->fakeGitHub([$this->release('0.2.0'), $this->release('0.1.5'), $this->release('0.3.0-rc.1', prerelease: true), $this->release('0.9.0', draft: true), $this->release('0.8.0', withManifest: false)], '0.2.0');

        $status = app(UpdateChecker::class)->checkNow();

        $this->assertNull($status->errorCode);
        $this->assertSame('0.2.0', $status->availableRelease?->version->toString());
        $this->assertSame('https://github.com/acme/shop/releases/download/v0.2.0/litecommerce-0.2.0.zip', $status->availableRelease?->archiveUrl);
        $this->assertNotNull($status->checkedAt);
        $this->assertSame(app(UpdateChecker::class)->status()->availableRelease?->tag, 'v0.2.0');
        foreach ($status->compatibility->problems as $problem) {
            $this->assertNotSame('not_newer', $problem['code']);
        }
    }

    public function test_the_current_version_up_to_date_offers_nothing(): void
    {
        $this->fakeGitHub([$this->release('0.1.0')], '0.1.0');

        $status = app(UpdateChecker::class)->checkNow();

        $this->assertNull($status->availableRelease);
        $this->assertNull($status->errorCode);
    }

    public function test_a_release_whose_manifest_is_not_signed_by_a_trusted_key_is_rejected(): void
    {
        $this->fakeGitHub([$this->release('0.2.0')], '0.2.0');
        config(['updates.trusted_public_keys' => [base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()))]]);

        $status = app(UpdateChecker::class)->checkNow();

        $this->assertSame('signature_invalid', $status->errorCode);
        $this->assertNull($status->availableRelease);
    }

    public function test_a_manifest_for_a_different_version_than_the_tag_is_rejected(): void
    {
        $this->fakeGitHub([$this->release('0.2.0')], '0.3.0');

        $this->assertSame('manifest_invalid', app(UpdateChecker::class)->checkNow()->errorCode);
    }

    public function test_an_unreachable_source_keeps_the_last_known_release(): void
    {
        $this->fakeGitHub([$this->release('0.2.0')], '0.2.0');
        app(UpdateChecker::class)->checkNow();

        Http::swap(new Factory);
        Http::fake(['api.github.com/*' => Http::response('', 503)]);
        $status = app(UpdateChecker::class)->checkNow();

        $this->assertSame('source_unreachable', $status->errorCode);
        $this->assertSame('0.2.0', $status->availableRelease?->version->toString());
    }

    public function test_assets_are_only_read_from_github_over_https(): void
    {
        Http::fake();
        $source = app(ReleaseSource::class);

        foreach (['http://github.com/a', 'https://evil.example/manifest.json', 'https://github.com.evil.example/x', 'file:///etc/passwd'] as $url) {
            try {
                $source->readAsset($url);
                $this->fail("{$url} must be refused.");
            } catch (ReleaseSourceException) {
                $this->addToAssertionCount(1);
            }
        }

        Http::assertNothingSent();
    }

    public function test_a_misconfigured_repository_is_reported_not_requested(): void
    {
        config(['updates.repository' => '../../etc']);
        Http::fake();

        $this->assertSame('source_unreachable', app(UpdateChecker::class)->checkNow()->errorCode);
        Http::assertNothingSent();
    }

    public function test_the_daily_task_only_contacts_github_while_automatic_checks_are_on(): void
    {
        $this->fakeGitHub([$this->release('0.2.0')], '0.2.0');
        app(UpdateChecker::class)->setAutomaticChecks(false);

        app(RunDueScheduledTasks::class)->run(50, 5);
        Http::assertNothingSent();
        $this->assertNull(app(UpdateChecker::class)->status()->checkedAt);

        app(UpdateChecker::class)->setAutomaticChecks(true);
        $this->travel(2)->days();
        app(RunDueScheduledTasks::class)->run(50, 5);

        $this->assertSame('0.2.0', app(UpdateChecker::class)->status()->availableRelease?->version->toString());
    }

    public function test_the_updates_tab_and_actions_need_the_settings_permission(): void
    {
        $this->fakeGitHub([$this->release('0.2.0')], '0.2.0');
        $admin = StaffAccount::factory()->create();
        $editor = StaffAccount::factory()->withRole(SystemStaffRole::ContentEditor)->create();

        $this->actingAs($admin)->post(route('panel.settings.updates.check'))->assertSessionHas('status');
        $this->actingAs($admin)->get(route('panel.settings.tabs.show', 'updates'))->assertOk()
            ->assertJsonPath('props.release.version', '0.2.0')
            ->assertJsonPath('props.current_version', '0.1.0');
        $this->actingAs($admin)->put(route('panel.settings.updates.automatic'), ['enabled' => false])->assertSessionHas('status');
        $this->assertFalse(app(UpdateChecker::class)->status()->automaticChecks);
        $this->assertTrue(AuditEntry::query()->where('action', 'updates.automatic_checks_changed')->exists());

        $this->actingAs($editor)->post(route('panel.settings.updates.check'))->assertForbidden();
        $this->actingAs($editor)->put(route('panel.settings.updates.automatic'), ['enabled' => true])->assertForbidden();
    }

    /** @param list<array<string, mixed>> $releases */
    private function fakeGitHub(array $releases, string $manifestVersion): void
    {
        $built = (new BuildReleasePackage(app(EnvironmentRequirements::class), []))
            ->build($this->workspace.'/src', $this->workspace.'/out-'.$manifestVersion, $manifestVersion, $this->secretKey);
        $dir = dirname($built['archive']);

        Http::fake([
            'api.github.com/repos/acme/shop/releases*' => Http::response($releases),
            'github.com/*/manifest.json' => Http::response((string) file_get_contents($dir.'/manifest.json')),
            'github.com/*/manifest.json.sig' => Http::response((string) file_get_contents($dir.'/manifest.json.sig')),
        ]);
    }

    /** @return array<string, mixed> */
    private function release(string $version, bool $prerelease = false, bool $draft = false, bool $withManifest = true): array
    {
        $base = "https://github.com/acme/shop/releases/download/v{$version}";
        $names = ["litecommerce-{$version}.zip", "litecommerce-{$version}.zip.sha256", ...($withManifest ? ['manifest.json', 'manifest.json.sig'] : [])];

        return [
            'tag_name' => "v{$version}", 'draft' => $draft, 'prerelease' => $prerelease, 'published_at' => '2026-11-08T10:00:00Z',
            'html_url' => "https://github.com/acme/shop/releases/tag/v{$version}",
            'assets' => array_map(static fn (string $name): array => ['name' => $name, 'browser_download_url' => "{$base}/{$name}"], $names),
        ];
    }
}
