<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

interface HtmlContentFilter
{
    public function enabled(): bool;

    /** Applies the store policy: sanitizes when the option is on, returns the input untouched otherwise. */
    public function clean(?string $html): ?string;

    /** Sanitizes regardless of the store option. */
    public function sanitize(string $html): string;

    /**
     * Walks a structured document and sanitizes every string stored under an HTML-bearing key.
     * Without $force the store option is honoured and a disabled filter returns the document untouched.
     *
     * @param  array<array-key, mixed>  $document
     * @return array<array-key, mixed>
     */
    public function cleanDocument(array $document, bool $force = false): array;
}
