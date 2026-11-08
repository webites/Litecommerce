<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Infrastructure;

final class ProcOpenProcessRunner implements ProcessRunner
{
    public function available(): bool
    {
        return function_exists('proc_open');
    }

    public function firstExecutable(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function run(array $command, ?string $stdin = null, int $timeoutSeconds = 5): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            return null;
        }

        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + max(1, $timeoutSeconds);
        while (true) {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (! $status['running']) {
                break;
            }
            if (microtime(true) > $deadline) {
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return null;
            }
            usleep(20000);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closeCode = proc_close($process);

        return ['exit' => $status['exitcode'] >= 0 ? $status['exitcode'] : $closeCode, 'stdout' => $stdout, 'stderr' => $stderr];
    }
}
