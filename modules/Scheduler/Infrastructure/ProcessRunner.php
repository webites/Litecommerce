<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Infrastructure;

interface ProcessRunner
{
    public function available(): bool;

    /** @param list<string> $candidates */
    public function firstExecutable(array $candidates): ?string;

    /** @param list<string> $command
     * @return array{exit: int, stdout: string, stderr: string}|null Null when the process could not be started.
     */
    public function run(array $command, ?string $stdin = null, int $timeoutSeconds = 5): ?array;
}
