<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Domain;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ValidGtin implements ValidationRule
{
    public function __construct(private GtinValidator $validator = new GtinValidator) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! $this->validator->isValid($value)) {
            $fail('Pole :attribute musi zawierać poprawny kod GTIN-8, UPC, EAN-13 lub GTIN-14.');
        }
    }
}
