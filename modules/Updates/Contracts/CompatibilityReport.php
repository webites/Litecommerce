<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Contracts;

final readonly class CompatibilityReport
{
    /** @param list<array{code: string, params: array<string, string>}> $problems */
    public function __construct(public array $problems = []) {}

    public function isCompatible(): bool
    {
        return $this->problems === [];
    }
}
