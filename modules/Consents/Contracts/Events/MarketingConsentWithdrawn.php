<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Consents\Contracts\Events;

use Litecommerce\Modules\Consents\Contracts\ConsentSource;

final readonly class MarketingConsentWithdrawn
{
    public function __construct(public string $email, public ConsentSource $source, public \DateTimeImmutable $occurredAt) {}
}
