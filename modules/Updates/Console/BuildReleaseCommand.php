<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Litecommerce\Modules\Updates\Application\BuildReleasePackage;

final class BuildReleaseCommand extends Command
{
    protected $signature = 'updates:build-release
        {version : Stable semantic version, e.g. 0.1.0}
        {--source= : Directory with the built application (default: this installation)}
        {--output= : Directory for the archive and checksum}
        {--upgrade-from= : Oldest version this release can be installed over}';

    protected $description = 'Build and sign a release archive; the secret key is read from RELEASE_SIGNING_KEY';

    public function handle(BuildReleasePackage $builder): int
    {
        $secretKey = (string) getenv('RELEASE_SIGNING_KEY');

        try {
            $result = $builder->build(
                (string) ($this->option('source') ?: base_path()),
                (string) ($this->option('output') ?: base_path('release')),
                (string) $this->argument('version'),
                $secretKey,
                $this->option('upgrade-from') ?: null,
            );
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Built {$result['archive']}");

        return self::SUCCESS;
    }
}
