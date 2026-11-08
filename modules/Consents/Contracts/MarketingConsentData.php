<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Consents\Contracts;

final readonly class MarketingConsentData
{
    public function __construct(
        public int $id,
        public string $email,
        public string $locale,
        public \DateTimeImmutable $grantedAt,
    ) {}
}
