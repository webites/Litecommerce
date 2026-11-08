<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts\Events;

/**
 * Fired synchronously, in the same request, right after a status change
 * commits — the plugin-facing counterpart to the durable
 * `order_outbox_events` row of type `OrderStatusChanged` (see "Eventy i
 * dostarczanie", docs/knowledge-base/08-extensions.md#projekt-techniczny-p1-012),
 * same convention as OrderPlaced. A plugin listens via Laravel's native
 * dispatcher from its own boot(): `Event::listen(OrderStatusChanged::class, ...)`.
 * Carries only the order id and the new status — a listener that needs
 * more reads it back through Orders' own public contracts, never through
 * this module's Eloquent models.
 */
final readonly class OrderStatusChanged
{
    public function __construct(
        public string $orderId,
        public string $status,
    ) {}
}
