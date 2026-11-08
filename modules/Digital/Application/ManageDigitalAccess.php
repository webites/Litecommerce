<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Application;

use Litecommerce\Modules\Digital\Contracts\DigitalAccessManager;
use Litecommerce\Modules\Digital\Infrastructure\Models\DownloadGrant;

final class ManageDigitalAccess implements DigitalAccessManager
{
    public function extend(int $grantId, ?\DateTimeImmutable $until): void
    {
        DownloadGrant::query()->whereKey($grantId)->whereNull('revoked_at')->update(['expires_at' => $until]);
    }

    public function setDownloadLimit(int $grantId, ?int $maxDownloads): void
    {
        DownloadGrant::query()->whereKey($grantId)->whereNull('revoked_at')->update(['max_downloads' => $maxDownloads]);
    }

    public function revoke(int $grantId, string $reason): void
    {
        DownloadGrant::query()->whereKey($grantId)->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoked_reason' => $reason]);
    }

    public function revokeForReference(string $referenceType, string $referenceId, string $reason): void
    {
        DownloadGrant::query()
            ->where('reference_type', $referenceType)->where('reference_id', $referenceId)->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoked_reason' => $reason]);
    }
}
