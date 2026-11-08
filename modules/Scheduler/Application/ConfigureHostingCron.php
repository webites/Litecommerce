<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Application;

use Litecommerce\Modules\Scheduler\Contracts\CronConfigurator;
use Litecommerce\Modules\Scheduler\Contracts\CronSetupResult;
use Litecommerce\Modules\Scheduler\Infrastructure\ProcessRunner;

final class ConfigureHostingCron implements CronConfigurator
{
    private const CRONTAB_PATHS = ['/usr/bin/crontab', '/bin/crontab', '/usr/local/bin/crontab', '/usr/sbin/crontab'];

    public function __construct(private readonly ProcessRunner $processes) {}

    public function canAttempt(): bool
    {
        return $this->processes->available() && $this->crontabBinary() !== null;
    }

    public function install(): CronSetupResult
    {
        if (! $this->processes->available()) {
            return new CronSetupResult(CronSetupResult::UNSUPPORTED, 'process_functions_disabled');
        }
        $crontab = $this->crontabBinary();
        if ($crontab === null) {
            return new CronSetupResult(CronSetupResult::UNSUPPORTED, 'crontab_missing');
        }
        $php = $this->phpCliBinary();
        if ($php === null) {
            return new CronSetupResult(CronSetupResult::UNSUPPORTED, 'php_cli_not_found');
        }

        $marker = $this->marker();
        $line = '* * * * * cd '.escapeshellarg(base_path()).' && '.escapeshellarg($php).' artisan schedule:run >> /dev/null 2>&1 '.$marker;

        $current = $this->processes->run([$crontab, '-l']);
        if ($current === null) {
            return new CronSetupResult(CronSetupResult::FAILED, 'crontab_unreadable', $line);
        }
        if ($current['exit'] !== 0 && ! str_contains(strtolower($current['stderr'].$current['stdout']), 'no crontab')) {
            return new CronSetupResult(CronSetupResult::FAILED, 'crontab_unreadable', $line);
        }

        $existing = $current['exit'] === 0 ? preg_split('/\R/', rtrim($current['stdout'])) : [];
        $existing = array_values(array_filter($existing ?: [], static fn (string $entry): bool => $entry !== ''));
        if (in_array($line, $existing, true)) {
            return new CronSetupResult(CronSetupResult::ALREADY_INSTALLED, null, $line);
        }

        $kept = array_values(array_filter($existing, static fn (string $entry): bool => ! str_contains($entry, $marker)));
        $written = $this->processes->run([$crontab, '-'], implode("\n", [...$kept, $line])."\n");
        if ($written === null || $written['exit'] !== 0) {
            return new CronSetupResult(CronSetupResult::FAILED, 'crontab_write_failed', $line);
        }

        return new CronSetupResult(CronSetupResult::INSTALLED, null, $line);
    }

    private function marker(): string
    {
        return '# litecommerce-scheduler:'.substr(sha1(base_path()), 0, 10);
    }

    private function crontabBinary(): ?string
    {
        return $this->processes->firstExecutable(self::CRONTAB_PATHS);
    }

    private function phpCliBinary(): ?string
    {
        $candidates = [];
        if (preg_match('/^php(\d+(\.\d+)*)?$/', basename(PHP_BINARY)) === 1) {
            $candidates[] = PHP_BINARY;
        }
        array_push($candidates, PHP_BINDIR.'/php', '/usr/local/bin/php', '/usr/bin/php');

        foreach (array_unique($candidates) as $candidate) {
            if ($this->processes->firstExecutable([$candidate]) === null) {
                continue;
            }
            $probe = $this->processes->run([$candidate, '-r', 'echo PHP_SAPI,"|",PHP_VERSION_ID;']);
            if ($probe === null || $probe['exit'] !== 0) {
                continue;
            }
            [$sapi, $version] = array_pad(explode('|', trim($probe['stdout']), 2), 2, '0');
            if ($sapi === 'cli' && (int) $version >= 80300) {
                return $candidate;
            }
        }

        return null;
    }
}
