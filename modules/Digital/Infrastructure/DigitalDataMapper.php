<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Infrastructure;

use Litecommerce\Modules\Digital\Contracts\DigitalAssetVersionData;
use Litecommerce\Modules\Digital\Contracts\DigitalFileData;
use Litecommerce\Modules\Digital\Contracts\DigitalGrantData;
use Litecommerce\Modules\Digital\Infrastructure\Models\DigitalAssetVersion;
use Litecommerce\Modules\Digital\Infrastructure\Models\DownloadGrant;
use Litecommerce\Modules\Digital\Infrastructure\Models\VariantFileSet;

final class DigitalDataMapper
{
    public static function toVersionData(DigitalAssetVersion $version): DigitalAssetVersionData
    {
        return new DigitalAssetVersionData(
            id: (int) $version->getKey(),
            versionNumber: $version->version_number,
            originalFilename: $version->original_filename,
            sizeBytes: $version->size_bytes,
            createdAt: \DateTimeImmutable::createFromInterface($version->created_at),
        );
    }

    public static function toFileData(VariantFileSet $fileSet): DigitalFileData
    {
        return new DigitalFileData(
            id: (int) $fileSet->getKey(),
            variantId: $fileSet->variant_id,
            label: $fileSet->label,
            active: $fileSet->active,
            maxDownloads: $fileSet->max_downloads,
            validityDays: $fileSet->validity_days,
            position: $fileSet->position,
            currentVersion: $fileSet->currentVersion === null ? null : self::toVersionData($fileSet->currentVersion),
        );
    }

    public static function toGrantData(DownloadGrant $grant): DigitalGrantData
    {
        return new DigitalGrantData(
            id: (int) $grant->getKey(),
            accessToken: $grant->access_token,
            fileId: $grant->variant_file_set_id,
            fileLabel: $grant->fileSet->label,
            originalFilename: $grant->version->original_filename,
            orderId: $grant->order_id,
            referenceType: $grant->reference_type,
            referenceId: $grant->reference_id,
            grantedAt: \DateTimeImmutable::createFromInterface($grant->granted_at),
            expiresAt: $grant->expires_at === null ? null : \DateTimeImmutable::createFromInterface($grant->expires_at),
            maxDownloads: $grant->max_downloads,
            downloadsUsed: $grant->downloads_used,
            revokedAt: $grant->revoked_at === null ? null : \DateTimeImmutable::createFromInterface($grant->revoked_at),
            revokedReason: $grant->revoked_reason,
        );
    }
}
