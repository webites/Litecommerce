<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

/** What a customer may see about the hand-over of a pickup order. */
final readonly class PickupFulfillmentData
{
    public function __construct(
        public DeliveryFulfillmentState $state,
        public ?\DateTimeImmutable $readyAt,
        public ?\DateTimeImmutable $deadlineAt,
        public ?\DateTimeImmutable $handedOverAt,
        public bool $lapsesWhenUncollected,
    ) {}
}
