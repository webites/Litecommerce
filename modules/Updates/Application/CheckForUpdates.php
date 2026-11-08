<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

use Litecommerce\Modules\Updates\Contracts\CompatibilityReport;
use Litecommerce\Modules\Updates\Contracts\ReleaseInfo;
use Litecommerce\Modules\Updates\Contracts\ReleaseSource;
use Litecommerce\Modules\Updates\Contracts\ReleaseSourceException;
use Litecommerce\Modules\Updates\Contracts\SemanticVersion;
use Litecommerce\Modules\Updates\Contracts\UpdateChecker;
use Litecommerce\Modules\Updates\Contracts\UpdateStatus;

final class CheckForUpdates implements UpdateChecker
{
    public function __construct(
        private readonly ReleaseSource $releases,
        private readonly VerifiedReleaseReader $reader,
        private readonly CompatibilityChecker $compatibility,
        private readonly UpdateStateStore $state,
        private readonly string $currentVersion,
    ) {}

    public function status(): UpdateStatus
    {
        $state = $this->state->load();
        $current = SemanticVersion::parse($this->currentVersion);
        $release = $state->release === null ? null : ReleaseInfo::fromArray($state->release);
        $offered = $release !== null && $release->version->compareTo($current) > 0 ? $release : null;

        return new UpdateStatus(
            $current,
            $offered,
            new CompatibilityReport($offered === null ? [] : $state->problems),
            $state->checkedAt,
            $state->errorCode,
            $state->automaticChecks,
        );
    }

    public function checkNow(): UpdateStatus
    {
        $previous = $this->state->load();
        $current = SemanticVersion::parse($this->currentVersion);
        $checkedAt = gmdate('c');

        try {
            $latest = $this->latestNewerThan($current);

            if ($latest === null) {
                $this->state->save(new UpdateState($previous->automaticChecks, $checkedAt));
            } else {
                $problems = $this->verifiedProblems($latest, $current);
                $this->state->save(new UpdateState($previous->automaticChecks, $checkedAt, null, $latest->toArray(), $problems));
            }
        } catch (ReleaseSourceException) {
            $this->state->save(new UpdateState($previous->automaticChecks, $checkedAt, 'source_unreachable', $previous->release, $previous->problems));
        } catch (UntrustedReleaseException $exception) {
            $this->state->save(new UpdateState($previous->automaticChecks, $checkedAt, $exception->errorCode));
        }

        return $this->status();
    }

    public function setAutomaticChecks(bool $enabled): void
    {
        $state = $this->state->load();
        $this->state->save(new UpdateState($enabled, $state->checkedAt, $state->errorCode, $state->release, $state->problems));
    }

    private function latestNewerThan(SemanticVersion $current): ?ReleaseInfo
    {
        $newer = array_values(array_filter($this->releases->stableReleases(), static fn (ReleaseInfo $release): bool => $release->version->compareTo($current) > 0));
        usort($newer, static fn (ReleaseInfo $a, ReleaseInfo $b): int => $b->version->compareTo($a->version));

        return $newer[0] ?? null;
    }

    /**
     * @return list<array{code: string, params: array<string, string>}>
     *
     * @throws UntrustedReleaseException
     */
    private function verifiedProblems(ReleaseInfo $release, SemanticVersion $current): array
    {
        return $this->compatibility->check($this->reader->read($release), $current)->problems;
    }
}
