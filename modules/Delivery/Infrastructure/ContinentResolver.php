<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Infrastructure;

final class ContinentResolver
{
    public function continentFor(string $countryCode): ?string
    {
        /** @var array<string, string> $map */
        $map = config('delivery.continents', []);

        return $map[strtoupper($countryCode)] ?? null;
    }
}
