<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts\Events;

/**
 * Fired synchronously, in the same request, right after the order commits
 * — the plugin-facing counterpart to the durable `order_outbox_events`
 * row of the same name (see "Eventy i dostarczanie",
 * docs/knowledge-base/08-extensions.md#projekt-techniczny-p1-012). A
 * plugin listens via Laravel's native dispatcher from its own boot():
 * `Event::listen(OrderPlaced::class, ...)`. Carries only the order id —
 * a listener that needs more reads it back through Orders' own public
 * contracts, never through this module's Eloquent models.
 */
final readonly class OrderPlaced
{
    public function __construct(public string $orderId) {}
}
