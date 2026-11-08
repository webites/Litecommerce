<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduler;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Scheduler\Application\ConfigureHostingCron;
use Litecommerce\Modules\Scheduler\Contracts\CronConfigurator;
use Litecommerce\Modules\Scheduler\Contracts\CronSetupResult;
use Litecommerce\Modules\Scheduler\Contracts\SchedulerStatus;
use Litecommerce\Modules\Scheduler\Infrastructure\ProcessRunner;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class CronConfigurationTest extends TestCase
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

    public function test_install_adds_one_marked_entry_and_preserves_foreign_entries(): void
    {
        $runner = new FakeCrontab("0 3 * * * /usr/bin/backup\n");
        $configurator = new ConfigureHostingCron($runner);

        $result = $configurator->install();

        $this->assertSame(CronSetupResult::INSTALLED, $result->status);
        $this->assertStringContainsString('0 3 * * * /usr/bin/backup', $runner->crontab);
        $this->assertSame(1, substr_count($runner->crontab, 'artisan schedule:run'));
        $this->assertStringContainsString(escapeshellarg(base_path()), $runner->crontab);
        $this->assertStringContainsString('# litecommerce-scheduler:', $runner->crontab);
    }

    public function test_install_is_idempotent_and_replaces_a_stale_entry_for_the_same_installation(): void
    {
        $runner = new FakeCrontab('');
        $configurator = new ConfigureHostingCron($runner);

        $this->assertSame(CronSetupResult::INSTALLED, $configurator->install()->status);
        $this->assertSame(CronSetupResult::ALREADY_INSTALLED, $configurator->install()->status);
        $this->assertSame(1, substr_count($runner->crontab, 'artisan schedule:run'));

        $runner->crontab = str_replace('/php', '/php-old', $runner->crontab);
        $this->assertSame(CronSetupResult::INSTALLED, $configurator->install()->status);
        $this->assertSame(1, substr_count($runner->crontab, 'artisan schedule:run'));
    }

    public function test_install_does_not_touch_an_unreadable_crontab(): void
    {
        $runner = new FakeCrontab("0 3 * * * /usr/bin/backup\n");
        $runner->readFails = true;
        $configurator = new ConfigureHostingCron($runner);

        $result = $configurator->install();

        $this->assertSame(CronSetupResult::FAILED, $result->status);
        $this->assertSame('crontab_unreadable', $result->reason);
        $this->assertSame("0 3 * * * /usr/bin/backup\n", $runner->crontab);
    }

    public function test_disabled_process_functions_are_reported_without_side_effects(): void
    {
        $runner = new FakeCrontab('');
        $runner->processesAvailable = false;

        $configurator = new ConfigureHostingCron($runner);

        $this->assertFalse($configurator->canAttempt());
        $result = $configurator->install();
        $this->assertSame(CronSetupResult::UNSUPPORTED, $result->status);
        $this->assertSame('process_functions_disabled', $result->reason);
    }

    public function test_panel_action_requires_permission_and_audits_the_attempt(): void
    {
        $runner = new FakeCrontab('');
        $runner->processesAvailable = false;
        $this->app->instance(ProcessRunner::class, $runner);

        $worker = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();
        $this->actingAs($worker)->post(route('panel.settings.scheduler.cron'))->assertForbidden();

        $admin = StaffAccount::factory()->create();
        $this->actingAs($admin)->post(route('panel.settings.scheduler.cron'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('audit_entries', ['action' => 'scheduler.cron.configure_attempted']);
        $this->assertSame('', $runner->crontab);
    }

    public function test_cron_invocation_records_a_signal_visible_in_status(): void
    {
        $this->assertNull(app(SchedulerStatus::class)->current()->systemCronSeenAt);

        $this->artisan('scheduler:run')->assertSuccessful();

        $this->assertNotNull(app(SchedulerStatus::class)->current()->systemCronSeenAt);
        $this->assertInstanceOf(CronConfigurator::class, app(CronConfigurator::class));
    }
}

final class FakeCrontab implements ProcessRunner
{
    public bool $processesAvailable = true;

    public bool $readFails = false;

    public function __construct(public string $crontab) {}

    public function available(): bool
    {
        return $this->processesAvailable;
    }

    public function firstExecutable(array $candidates): ?string
    {
        return $candidates[0] ?? null;
    }

    public function run(array $command, ?string $stdin = null, int $timeoutSeconds = 5): ?array
    {
        $binary = basename($command[0]);
        if ($binary === 'crontab' && ($command[1] ?? '') === '-l') {
            if ($this->readFails) {
                return ['exit' => 2, 'stdout' => '', 'stderr' => 'permission denied'];
            }

            return $this->crontab === ''
                ? ['exit' => 1, 'stdout' => '', 'stderr' => 'no crontab for user']
                : ['exit' => 0, 'stdout' => $this->crontab, 'stderr' => ''];
        }
        if ($binary === 'crontab' && ($command[1] ?? '') === '-') {
            $this->crontab = (string) $stdin;

            return ['exit' => 0, 'stdout' => '', 'stderr' => ''];
        }

        return ['exit' => 0, 'stdout' => 'cli|80400', 'stderr' => ''];
    }
}
