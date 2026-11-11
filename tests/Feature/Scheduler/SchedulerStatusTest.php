<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduler;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Scheduler\Application\RunDueScheduledTasks;
use Litecommerce\Modules\Scheduler\Contracts\SchedulerStatus;
use Litecommerce\Modules\Scheduler\Contracts\SchedulerStatusData;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class SchedulerStatusTest extends TestCase
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

    public function test_status_is_pending_until_a_task_has_run_then_ok(): void
    {
        $this->assertSame(SchedulerStatusData::HEALTH_PENDING, app(SchedulerStatus::class)->current()->health);

        app(RunDueScheduledTasks::class)->run(20, 10);

        $status = app(SchedulerStatus::class)->current();
        $this->assertSame(SchedulerStatusData::HEALTH_OK, $status->health);
        $this->assertNotNull($status->lastRunAt);
        $keys = array_map(static fn ($task): string => $task->key, $status->tasks);
        $this->assertContains('core.payments.reconcile_expired', $keys);
        $this->assertContains('core.warehouse.reconcile_expired_reservations', $keys);
        $this->assertContains('core.coupons.reconcile_expired_reservations', $keys);
        $this->assertContains('core.email.process', $keys);
    }

    public function test_tasks_expose_a_readable_name_with_the_key_as_fallback(): void
    {
        app(RunDueScheduledTasks::class)->run(20, 10);

        $names = [];
        foreach (app(SchedulerStatus::class)->current()->tasks as $task) {
            $names[$task->key] = $task->name;
        }

        $this->assertSame(trans('payments::tasks.reconcile_expired'), $names['core.payments.reconcile_expired']);
        foreach ($names as $key => $name) {
            $this->assertNotSame('', $name);
            $this->assertStringNotContainsString('::tasks.', $name, "Task [{$key}] shows an untranslated label.");
            $this->assertNotSame($key, $name, "Core task [{$key}] has no readable name.");
        }
    }

    public function test_status_reports_delay_thresholds_per_mode(): void
    {
        app(RunDueScheduledTasks::class)->run(20, 10);
        DB::table('scheduled_tasks')->update(['last_finished_at' => now()->subMinutes(5), 'next_run_at' => now()->addMinute()]);

        config(['scheduler.mode' => 'system']);
        $this->assertSame(SchedulerStatusData::HEALTH_DELAYED, app(SchedulerStatus::class)->current()->health);

        config(['scheduler.mode' => 'traffic']);
        $this->assertSame(SchedulerStatusData::HEALTH_OK, app(SchedulerStatus::class)->current()->health);

        DB::table('scheduled_tasks')->update(['last_finished_at' => now()->subHour()]);
        $this->assertSame(SchedulerStatusData::HEALTH_DELAYED, app(SchedulerStatus::class)->current()->health);

        config(['scheduler.mode' => 'off']);
        $this->assertSame(SchedulerStatusData::HEALTH_DISABLED, app(SchedulerStatus::class)->current()->health);
    }

    public function test_failing_and_overdue_tasks_are_reported(): void
    {
        app(RunDueScheduledTasks::class)->run(20, 10);
        DB::table('scheduled_tasks')->where('key', 'core.payments.reconcile_expired')->update([
            'consecutive_failures' => 2, 'last_error_code' => 'RuntimeException', 'next_run_at' => now()->subHour(),
        ]);

        $status = app(SchedulerStatus::class)->current();

        $this->assertSame(SchedulerStatusData::HEALTH_FAILING, $status->health);
        $task = collect($status->tasks)->firstWhere('key', 'core.payments.reconcile_expired');
        $this->assertSame(2, $task->consecutiveFailures);
        $this->assertTrue($task->overdue);
    }

    public function test_settings_tab_exposes_status_to_authorized_staff_only(): void
    {
        app(RunDueScheduledTasks::class)->run(20, 10);

        $admin = StaffAccount::factory()->create();
        $response = $this->actingAs($admin)->getJson(route('panel.settings.tabs.show', 'scheduler'))->assertOk();
        $response->assertJsonPath('component', 'scheduler');
        $response->assertJsonPath('props.mode', 'traffic');
        $response->assertJsonPath('props.health', 'ok');
        $this->assertStringContainsString('artisan schedule:run', (string) $response->json('props.cron_command'));

        $worker = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();
        $this->actingAs($worker)->getJson(route('panel.settings.tabs.show', 'scheduler'))->assertNotFound();
    }

    public function test_status_command_prints_mode_and_tasks(): void
    {
        app(RunDueScheduledTasks::class)->run(20, 10);

        $this->artisan('scheduler:status')
            ->expectsOutputToContain('Mode: traffic; health: ok')
            ->expectsOutputToContain('core.email.process')
            ->assertSuccessful();
    }
}
