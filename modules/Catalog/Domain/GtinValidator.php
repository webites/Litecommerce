<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Domain;

final class GtinValidator
{
    public function isValid(string $value): bool
    {
        if (! preg_match('/^\d+$/', $value) || ! in_array(strlen($value), [8, 12, 13, 14], true)) {
            return false;
        }

        $checkDigit = (int) substr($value, -1);
        $payload = substr($value, 0, -1);
        $sum = 0;
        $weight = 3;

        for ($position = strlen($payload) - 1; $position >= 0; $position--) {
            $sum += ((int) $payload[$position]) * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - ($sum % 10)) % 10 === $checkDigit;
    }
}
