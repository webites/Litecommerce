<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Contracts;

final readonly class DigitalGrantData
{
    public function __construct(
        public int $id,
        public string $accessToken,
        public int $fileId,
        public string $fileLabel,
        public string $originalFilename,
        public ?string $orderId,
        public string $referenceType,
        public string $referenceId,
        public \DateTimeImmutable $grantedAt,
        public ?\DateTimeImmutable $expiresAt,
        public ?int $maxDownloads,
        public int $downloadsUsed,
        public ?\DateTimeImmutable $revokedAt,
        public ?string $revokedReason,
    ) {}

    public function isUsable(): bool
    {
        if ($this->revokedAt !== null) {
            return false;
        }

        if ($this->expiresAt !== null && $this->expiresAt < new \DateTimeImmutable) {
            return false;
        }

        return $this->maxDownloads === null || $this->downloadsUsed < $this->maxDownloads;
    }
}
