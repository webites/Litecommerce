<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts\Events;

/** The retention period of the order's personal data ended; extensions holding copies should erase them too. */
final readonly class OrderPersonalDataAnonymized
{
    public function __construct(public string $orderId, public \DateTimeImmutable $anonymizedAt) {}
}
