<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Contracts;

final readonly class DigitalAssetVersionData
{
    public function __construct(
        public int $id,
        public int $versionNumber,
        public string $originalFilename,
        public int $sizeBytes,
        public \DateTimeImmutable $createdAt,
    ) {}
}
