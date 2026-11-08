<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Contracts;

interface DeliveryMethodCatalog
{
    /**
     * Every enabled, currently available method (configured or from a
     * plugin) quoted for this context.
     * An inactive method, or an active one with no matching region (or an
     * excluded country), is simply absent from the list — "Metody
     * niedostępne ukryte, bez powodów dla klienta" (06-fulfillment.md).
     *
     * @return list<DeliveryOptionQuote>
     */
    public function quoteAvailable(DeliveryQuoteContext $context, string $locale): array;

    /**
     * Re-quotes one specific, already-selected method against the current
     * context. Null when the method does not exist, is inactive, or is no
     * longer available for this context (e.g. the address changed to an
     * excluded country) — the caller must then ask the customer to pick
     * another method rather than silently keep the old quote.
     */
    public function quote(string $methodId, DeliveryQuoteContext $context, string $locale): ?DeliveryOptionQuote;

    /**
     * Whether the method declares COD capability and the merchant has linked
     * it to cash-on-delivery. False for an unknown method. Used by Payments to gate
     * `core.cash_on_delivery` without depending on Delivery's storage.
     */
    public function supportsCashOnDelivery(string $methodId): bool;

    /**
     * Whether this is a personal-pickup method that declares support for
     * payment on collection and that the merchant has linked to it. False
     * for an unknown method. Used by Payments to gate `core.pay_on_pickup`.
     */
    public function supportsPayOnPickup(string $methodId): bool;

    /**
     * Days an unpaid pay-on-pickup order may wait after "ready for pickup"
     * before it lapses; null when the method is not a pickup method, is
     * unknown, or has no deadline configured.
     */
    public function pickupDeadlineDays(string $methodId): ?int;

    /**
     * Every ISO 3166-1 alpha-2 code that at least one active, non-pickup
     * delivery method would actually quote today — mirrors `quoteAvailable()`'s
     * own region-rule matching (exclude beats a country rate beats a
     * continent rate), not just "not excluded", since a method with no
     * rate-granting rule for a country is not quotable there either.
     * Pickup methods are ignored: they never consult the country at all.
     * Falls back to every known code when nothing is quotable anywhere yet
     * (no active method, or none configured with region rules) — an
     * unconfigured store must not present an empty country list.
     *
     * @return list<string>
     */
    public function sellableCountryCodes(): array;
}
