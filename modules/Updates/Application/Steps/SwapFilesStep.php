<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application\Steps;

use Litecommerce\Modules\Updates\Application\FileSwapper;
use Litecommerce\Modules\Updates\Application\ReleaseManifestParser;
use Litecommerce\Modules\Updates\Application\UpdateOperationState;
use Litecommerce\Modules\Updates\Application\UpdatePaths;
use Litecommerce\Modules\Updates\Application\UpdateProgress;
use Litecommerce\Modules\Updates\Contracts\InvalidReleaseManifestException;
use Litecommerce\Modules\Updates\Contracts\ReleaseManifest;

final class SwapFilesStep extends BaseUpdateStep
{
    public function __construct(
        private readonly FileSwapper $swapper,
        private readonly ReleaseManifestParser $parser,
        private readonly UpdatePaths $paths,
    ) {}

    public function key(): string
    {
        return 'swap';
    }

    public function endsRequest(): bool
    {
        return true;
    }

    public function run(UpdateOperationState $state, UpdateProgress $progress): void
    {
        $new = $this->parser->parse((string) file_get_contents($this->paths->workDirectory($state->id).'/manifest.json'));

        $this->swapper->swap($this->paths, $state, $new, $this->installedManifest($state->id), $progress);
        $this->dropStaleCaches();
    }

    /** Cached config, routes and events describe the previous release and would break the next request. */
    private function dropStaleCaches(): void
    {
        foreach (glob($this->paths->root.'/bootstrap/cache/*.php') ?: [] as $cached) {
            @unlink($cached);
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /** The installed manifest is copied aside first, because the swap itself replaces it. */
    private function installedManifest(string $operationId): ?ReleaseManifest
    {
        $copy = $this->paths->workDirectory($operationId).'/installed-manifest.json';

        if (! is_file($copy) && is_file($this->paths->root.'/manifest.json')) {
            @copy($this->paths->root.'/manifest.json', $copy);
        }

        $raw = @file_get_contents($copy);

        try {
            return $raw === false ? null : $this->parser->parse($raw);
        } catch (InvalidReleaseManifestException) {
            return null;
        }
    }
}
