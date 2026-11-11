<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Contracts;

interface ScheduledTaskRegistry
{
    /** The label is a translation key or plain text shown next to the key in the panel; empty falls back to the key. */
    public function register(string $key, int $intervalSeconds, callable $handler, string $label = ''): void;

    /** @return list<ScheduledTaskDefinition> */
    public function all(): array;
}
