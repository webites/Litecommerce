<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduler;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Scheduler\Application\RunDueScheduledTasks;
use Litecommerce\Modules\Scheduler\Infrastructure\InMemoryScheduledTaskRegistry;
use RuntimeException;
use Tests\TestCase;

final class ScheduledTasksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);
    }

    public function test_due_task_runs_once_and_waits_for_its_next_interval(): void
    {
        $runs = 0;
        $registry = new InMemoryScheduledTaskRegistry;
        $registry->register('plugin.example.refresh', 300, function () use (&$runs): void {
            $runs++;
        });
        $runner = new RunDueScheduledTasks($registry, app(MaintenanceMode::class));

        $first = $runner->run(5, 5);
        $second = $runner->run(5, 5);

        $this->assertSame(1, $runs);
        $this->assertSame(1, $first->processed);
        $this->assertSame(0, $first->failed);
        $this->assertSame(0, $second->processed);
        $this->assertDatabaseHas('scheduled_tasks', [
            'key' => 'plugin.example.refresh',
            'status' => 'idle',
            'run_count' => 1,
            'consecutive_failures' => 0,
        ]);
        $this->assertTrue(now()->lt(DB::table('scheduled_tasks')->where('key', 'plugin.example.refresh')->value('next_run_at')));
    }

    public function test_failed_task_is_released_for_a_bounded_retry_without_leaking_its_message(): void
    {
        $registry = new InMemoryScheduledTaskRegistry;
        $registry->register('plugin.example.failure', 300, static function (): void {
            throw new RuntimeException('secret provider response');
        });

        $result = (new RunDueScheduledTasks($registry, app(MaintenanceMode::class)))->run(5, 5);

        $this->assertSame(1, $result->processed);
        $this->assertSame(1, $result->failed);
        $this->assertDatabaseHas('scheduled_tasks', [
            'key' => 'plugin.example.failure',
            'status' => 'idle',
            'last_error_code' => RuntimeException::class,
            'run_count' => 1,
            'consecutive_failures' => 1,
        ]);
        $this->assertFalse(DB::table('scheduled_tasks')->where('key', 'plugin.example.failure')->where('last_error_code', 'like', '%secret%')->exists());
    }

    public function test_expired_lease_can_be_recovered_by_an_idempotent_task(): void
    {
        $runs = 0;
        $registry = new InMemoryScheduledTaskRegistry;
        $registry->register('plugin.example.recover', 300, function () use (&$runs): void {
            $runs++;
        });
        DB::table('scheduled_tasks')->insert([
            'key' => 'plugin.example.recover',
            'interval_seconds' => 300,
            'status' => 'running',
            'next_run_at' => now()->subMinute(),
            'lease_token' => '00000000-0000-4000-8000-000000000001',
            'leased_until' => now()->subMinute(),
            'run_count' => 0,
            'consecutive_failures' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = (new RunDueScheduledTasks($registry, app(MaintenanceMode::class)))->run(1, 5);

        $this->assertSame(1, $runs);
        $this->assertSame(1, $result->processed);
        $this->assertDatabaseHas('scheduled_tasks', ['key' => 'plugin.example.recover', 'status' => 'idle', 'lease_token' => null, 'run_count' => 1]);
    }

    public function test_system_command_obeys_mode_and_force_can_be_used_for_diagnostics(): void
    {
        config()->set('scheduler.mode', 'traffic');

        $this->artisan('scheduler:run')->assertSuccessful();
        $this->assertDatabaseCount('scheduled_tasks', 0);

        $this->artisan('scheduler:run', ['--force' => true, '--limit' => 1, '--time-limit' => 5])->assertSuccessful();
        $this->assertDatabaseHas('scheduled_tasks', ['key' => 'core.email.process']);
        $this->assertDatabaseHas('scheduled_tasks', ['key' => 'core.email.prune_attachments']);
        $this->assertDatabaseHas('scheduled_tasks', ['key' => 'core.email.prune_history']);
    }
}
