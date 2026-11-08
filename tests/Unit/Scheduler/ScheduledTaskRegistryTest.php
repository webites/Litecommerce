<?php

declare(strict_types=1);

namespace Tests\Unit\Scheduler;

use InvalidArgumentException;
use Litecommerce\Modules\Scheduler\Infrastructure\InMemoryScheduledTaskRegistry;
use PHPUnit\Framework\TestCase;

final class ScheduledTaskRegistryTest extends TestCase
{
    public function test_plugin_can_register_a_callable_or_class_method(): void
    {
        $registry = new InMemoryScheduledTaskRegistry;
        $handler = new class
        {
            public function run(): void {}
        };

        $registry->register('plugin.example.callable', 60, [$handler, 'run']);

        $this->assertCount(1, $registry->all());
        $this->assertSame('plugin.example.callable', $registry->all()[0]->key);
        $this->assertSame(60, $registry->all()[0]->intervalSeconds);
    }

    public function test_duplicate_task_is_rejected(): void
    {
        $registry = new InMemoryScheduledTaskRegistry;
        $registry->register('plugin.example.task', 60, static function (): void {});

        $this->expectException(InvalidArgumentException::class);
        $registry->register('plugin.example.task', 60, static function (): void {});
    }

    public function test_sub_minute_interval_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new InMemoryScheduledTaskRegistry)->register('plugin.example.task', 59, static function (): void {});
    }

    public function test_task_key_requires_a_namespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new InMemoryScheduledTaskRegistry)->register('unscoped', 60, static function (): void {});
    }
}
