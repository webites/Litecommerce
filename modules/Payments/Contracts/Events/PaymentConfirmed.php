<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts\Events;

/**
 * Fired synchronously, in the same request, right after a payment attempt
 * is confirmed (manual confirmation, operator webhook, or expiry
 * reconciliation all converge on the same commit point). A plugin
 * listens via Laravel's native dispatcher from its own boot():
 * `Event::listen(PaymentConfirmed::class, ...)`. Carries only ids and
 * primitives — a listener that needs more reads it back through
 * Payments' own public contracts (e.g. `PaymentAttemptCatalog`), never
 * through this module's Eloquent models.
 */
final readonly class PaymentConfirmed
{
    public function __construct(
        public string $paymentAttemptId,
        public string $orderId,
        public string $amount,
        public string $currencyCode,
    ) {}
}
