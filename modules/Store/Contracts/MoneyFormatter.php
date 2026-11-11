<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

/**
 * The single place that turns a stored amount into customer-facing text
 * (grouping, decimal separator, currency symbol and its position follow the locale and the currency, e.g. "100,00 zł" vs "$100.00"); storefront views,
 * JSON payloads and e-mails all call it instead of formatting on their own.
 */
interface MoneyFormatter
{
    /** Uses the decimal places configured for the currency (the amount's own scale for an unknown one). */
    public function format(string $amount, string $currencyCode, ?string $locale = null): string;

    public function formatWithPlaces(string $amount, string $currencyCode, int $decimalPlaces, ?string $locale = null): string;

    /**
     * Returns the payload with a `<key>_formatted` sibling next to every numeric `*_amount` value, at any depth.
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function annotate(array $payload, string $currencyCode, ?string $locale = null): array;
}
