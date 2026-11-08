<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\Digital\Infrastructure\Models\DownloadAttempt;
use Litecommerce\Modules\Digital\Infrastructure\Models\DownloadGrant;
use Litecommerce\Modules\Media\Contracts\PrivateFileLibrary;

/**
 * The public GET /downloads/{token} entry point resolves here.
 *
 * Resumed-download rule (docs/knowledge-base/06-fulfillment.md#dostęp-cyfrowy
 * left "sposób liczenia rozpoczętego/przerwanego pobrania i wznowień HTTP"
 * open; resolved here as a technical decision for MVP — see
 * docs/knowledge-base/12-decisions-sources.md): a request with no Range
 * header, or a Range starting at byte 0, is a fresh download and consumes
 * one unit of the grant's limit. A Range request starting past byte 0 is
 * treated as resuming an interrupted transfer of the same file and is
 * logged but does not consume another unit — a flaky connection on a
 * large file must not burn through a small download limit. This only
 * gates *which* attempts count; the actual partial-content response is
 * Symfony BinaryFileResponse's native Range handling
 * (Media\Contracts\PrivateFileLibrary::respond()).
 *
 * The row lock + increment here is a short transaction that commits
 * before the caller streams the file — never wrap the (potentially slow)
 * file transfer itself in this transaction.
 */
final class ConsumeDownload
{
    public function __construct(private readonly PrivateFileLibrary $files) {}

    public function execute(string $token, ?string $rangeHeader, ?string $ip, ?string $userAgent): ConsumeDownloadResult
    {
        return DB::transaction(function () use ($token, $rangeHeader, $ip, $userAgent): ConsumeDownloadResult {
            $grant = DownloadGrant::query()->where('access_token', $token)->lockForUpdate()->with('version')->first();

            if ($grant === null) {
                return new ConsumeDownloadResult(DownloadOutcome::NotFound);
            }

            [$rangeStart, $rangeEnd] = $this->parseRange($rangeHeader);
            $isResume = $rangeStart !== null && $rangeStart > 0;

            if ($grant->revoked_at !== null) {
                $this->log($grant, $ip, $userAgent, $rangeStart, $rangeEnd, 'revoked', false);

                return new ConsumeDownloadResult(DownloadOutcome::Revoked);
            }

            if ($grant->expires_at !== null && $grant->expires_at->isPast()) {
                $this->log($grant, $ip, $userAgent, $rangeStart, $rangeEnd, 'expired', false);

                return new ConsumeDownloadResult(DownloadOutcome::Expired);
            }

            if (! $this->files->isServable((int) $grant->version->private_file_id)) {
                $this->log($grant, $ip, $userAgent, $rangeStart, $rangeEnd, 'unavailable', false);

                return new ConsumeDownloadResult(DownloadOutcome::Unavailable);
            }

            if (! $isResume && $grant->max_downloads !== null && $grant->downloads_used >= $grant->max_downloads) {
                $this->log($grant, $ip, $userAgent, $rangeStart, $rangeEnd, 'limit_exceeded', false);

                return new ConsumeDownloadResult(DownloadOutcome::LimitExceeded);
            }

            if (! $isResume) {
                $grant->increment('downloads_used');
            }

            $this->log($grant, $ip, $userAgent, $rangeStart, $rangeEnd, 'served', ! $isResume);

            return new ConsumeDownloadResult(DownloadOutcome::Served, $grant->version->private_file_id, $grant->version->original_filename);
        });
    }

    /** @return array{0: ?int, 1: ?int} */
    private function parseRange(?string $header): array
    {
        if ($header === null || preg_match('/^bytes=(\d+)-(\d*)$/', trim($header), $matches) !== 1) {
            return [null, null];
        }

        return [(int) $matches[1], $matches[2] === '' ? null : (int) $matches[2]];
    }

    private function log(
        DownloadGrant $grant,
        ?string $ip,
        ?string $userAgent,
        ?int $rangeStart,
        ?int $rangeEnd,
        string $outcome,
        bool $counted,
    ): void {
        DownloadAttempt::query()->create([
            'download_grant_id' => $grant->getKey(),
            'ip_address' => $ip,
            'user_agent' => $userAgent === null ? null : Str::limit($userAgent, 250, ''),
            'range_start' => $rangeStart, 'range_end' => $rangeEnd,
            'counted' => $counted, 'outcome' => $outcome, 'occurred_at' => now(),
        ]);
    }
}
